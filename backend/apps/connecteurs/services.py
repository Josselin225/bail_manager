"""Envoi de SMS / WhatsApp, paiement Mobile Money et signature par code SMS.

Chaque fournisseur est « prêt à brancher » : il suffit de choisir le fournisseur et de saisir les identifiants
dans Paramètres > Connecteurs. En mode test, rien ne sort du logiciel : les messages sont enregistrés
dans le journal des envois et les paiements se valident sur une page de simulation.
"""
import base64
import json
import secrets
import urllib.error
import urllib.parse
import urllib.request
from datetime import timedelta

from django.contrib.auth.hashers import check_password, make_password
from django.db import transaction
from django.utils import timezone

from apps.core.exceptions import RegleMetier
from apps.core.utils import fcfa, ip_client, journaliser
from apps.locatif import services as locatif
from apps.locatif.models import Contrat, Encaissement

from .models import Connecteurs, MessageSortant, PaiementEnLigne, SignatureContrat

DELAI_HTTP = 15


def numero_international(tel: str) -> str:
    """« 07 07 07 07 07 » → « +2250707070707 » (numéros ivoiriens à 10 chiffres) ; les numéros en +xxx sont gardés."""
    t = "".join(c for c in (tel or "") if c.isdigit() or c == "+")
    if t.startswith("00"):
        t = "+" + t[2:]
    if not t.startswith("+") and len(t) == 10:
        t = "+225" + t
    return t


def _http(url, *, donnees=None, json_=None, entetes=None, methode=None):
    corps, h = None, dict(entetes or {})
    if json_ is not None:
        corps, h["Content-Type"] = json.dumps(json_).encode(), "application/json"
    elif donnees is not None:
        corps, h["Content-Type"] = urllib.parse.urlencode(donnees).encode(), "application/x-www-form-urlencoded"
    req = urllib.request.Request(url, data=corps, headers=h, method=methode or ("POST" if corps is not None else "GET"))
    try:
        with urllib.request.urlopen(req, timeout=DELAI_HTTP) as r:
            texte = r.read().decode() or "{}"
    except urllib.error.HTTPError as e:
        raise RegleMetier(f"Le fournisseur a refusé la requête ({e.code}) : {e.read().decode()[:300]}")
    except urllib.error.URLError as e:
        raise RegleMetier(f"Fournisseur injoignable : {e.reason}")
    return json.loads(texte)


def _basique(identifiant, secret):
    return "Basic " + base64.b64encode(f"{identifiant}:{secret}".encode()).decode()


# ─── Messages ─────────────────────────────────────────────────────────────────
def _orange(cfg, numero, texte):
    jeton = _http("https://api.orange.com/oauth/v3/token", donnees={"grant_type": "client_credentials"},
                  entetes={"Authorization": _basique(cfg.sms_identifiant, cfg.sms_secret)})["access_token"]
    exp = numero_international(cfg.sms_expediteur)
    _http(f"https://api.orange.com/smsmessaging/v1/outbound/{urllib.parse.quote('tel:' + exp, safe='')}/requests",
          json_={"outboundSMSMessageRequest": {"address": f"tel:{numero}", "senderAddress": f"tel:{exp}", "outboundSMSTextMessage": {"message": texte}}},
          entetes={"Authorization": f"Bearer {jeton}"})


def _twilio(cfg, numero, texte):
    _http(f"https://api.twilio.com/2010-04-01/Accounts/{cfg.sms_identifiant}/Messages.json",
          donnees={"To": numero, "From": cfg.sms_expediteur, "Body": texte}, entetes={"Authorization": _basique(cfg.sms_identifiant, cfg.sms_secret)})


def _whatsapp(cfg, numero, texte):
    _http(f"https://graph.facebook.com/v20.0/{cfg.whatsapp_numero_id}/messages",
          json_={"messaging_product": "whatsapp", "to": numero.lstrip("+"), "type": "text", "text": {"body": texte}},
          entetes={"Authorization": f"Bearer {cfg.whatsapp_jeton}"})


def canal_disponible(cfg=None):
    """Canal utilisé pour un envoi : le canal préféré s'il est branché, sinon l'autre ; None si aucun."""
    cfg = cfg or Connecteurs.charger()
    actifs = [c for c, f in (("sms", cfg.sms_fournisseur), ("whatsapp", cfg.whatsapp_fournisseur)) if f != "desactive"]
    if not actifs:
        return None
    return cfg.canal_prefere if cfg.canal_prefere in actifs else actifs[0]


def envoyer_message(telephone, texte, *, nom="", objet="", canal=None, request=None):
    """Envoie (ou simule) un message ; ne lève jamais d'exception : l'échec est consigné dans le journal des envois."""
    from apps.core.licence import module_actif

    if not module_actif("connecteurs"):
        return None
    cfg = Connecteurs.charger()
    canal = canal or canal_disponible(cfg)
    numero = numero_international(telephone)
    if canal is None or not numero:
        return None
    fournisseur = cfg.sms_fournisseur if canal == "sms" else cfg.whatsapp_fournisseur
    if fournisseur == "desactive":
        return None
    msg = MessageSortant(canal=canal, fournisseur=fournisseur, destinataire=numero, nom=nom, texte=texte, objet=objet)
    if fournisseur == "test":
        msg.statut = MessageSortant.Statut.SIMULE
    else:
        try:
            {"orange": _orange, "twilio": _twilio, "meta": _whatsapp}[fournisseur](cfg, numero, texte)
            msg.statut = MessageSortant.Statut.ENVOYE
        except Exception as e:  # le métier ne doit jamais échouer à cause d'un SMS
            msg.statut, msg.erreur = MessageSortant.Statut.ECHEC, str(e)[:1000]
    msg.save()
    libelle = {"simule": "simulé", "envoye": "envoyé", "echec": "en échec"}[msg.statut]
    journaliser(request, f"{'SMS' if canal == 'sms' else 'WhatsApp'} {libelle}", f"{objet or 'Message'} — {nom or numero} ({numero}) : {texte[:160]}")
    return msg


def confirmer_encaissement(enc_id):
    """Message de confirmation au locataire après chaque loyer encaissé (à l'agence ou en ligne)."""
    cfg = Connecteurs.charger()
    if not cfg.envoi_paiements:
        return
    enc = Encaissement.objects.select_related("contrat__locataire").filter(pk=enc_id).first()
    if enc is None:
        return
    loc = enc.contrat.locataire
    reste = f" Reste à payer sur ce mois : {fcfa(enc.reliquat_apres_paiement)}." if enc.reliquat_apres_paiement > 0 else ""
    envoyer_message(loc.telephone1, f"Paiement reçu : {fcfa(enc.montant_recu)} pour le loyer {enc.periode_concernee}. Quittance n° {enc.reference_recu}.{reste} Merci.",
                    nom=loc.nom, objet="Confirmation de paiement")


# ─── Paiement Mobile Money ────────────────────────────────────────────────────
def periode_a_payer(contrat):
    """Le plus ancien mois non soldé (sinon le prochain) : c'est lui que le paiement en ligne règle."""
    for p in locatif.echeancier(contrat, apres=24):
        if p.reste > 0:
            return p
    return None


def _nouvelle_reference():
    return "PAY-" + secrets.token_hex(5).upper()


def initier_paiement(contrat: Contrat, telephone, operateur, request, retour_url, notification_url):
    cfg = Connecteurs.charger()
    if not cfg.paiement_actif:
        raise RegleMetier("Le paiement en ligne n'est pas activé par l'agence.")
    if contrat.statut_contrat != Contrat.Statut.ACTIF:
        raise RegleMetier("Ce bail est terminé.")
    if operateur not in dict(PaiementEnLigne.OPERATEURS):
        raise RegleMetier("Choisissez votre opérateur Mobile Money.")
    p = periode_a_payer(contrat)
    if p is None:
        raise RegleMetier("Aucun loyer à régler pour le moment.")
    pay = PaiementEnLigne.objects.create(
        reference=_nouvelle_reference(), contrat=contrat, periode_mois=p.mois, periode_annee=p.annee, montant=p.reste,
        telephone=numero_international(telephone), operateur=operateur, fournisseur=cfg.paiement_fournisseur,
    )
    description = f"Loyer {p.libelle} - {contrat.maison.designation}"[:100]
    if cfg.paiement_fournisseur == "test":
        pay.url_paiement = ""
    elif cfg.paiement_fournisseur == "cinetpay":
        r = _http("https://api-checkout.cinetpay.com/v2/payment", json_={
            "apikey": cfg.paiement_cle, "site_id": cfg.paiement_site_id, "transaction_id": pay.reference,
            "amount": int(pay.montant), "currency": "XOF", "description": description, "channels": "MOBILE_MONEY",
            "notify_url": notification_url, "return_url": retour_url, "customer_phone_number": pay.telephone,
            "customer_name": contrat.locataire.nom, "lang": "fr",
        })
        if str(r.get("code")) != "201":
            raise RegleMetier(f"CinetPay : {r.get('message') or r.get('description') or 'refus'}")
        pay.url_paiement, pay.jeton = r["data"]["payment_url"], r["data"].get("payment_token", "")
    elif cfg.paiement_fournisseur == "paydunya":
        r = _http("https://app.paydunya.com/api/v1/checkout-invoice/create", json_={
            "invoice": {"total_amount": int(pay.montant), "description": description},
            "store": {"name": "Loyer"},
            "actions": {"callback_url": notification_url, "return_url": retour_url, "cancel_url": retour_url},
            "custom_data": {"reference": pay.reference},
        }, entetes={"PAYDUNYA-MASTER-KEY": cfg.paiement_cle, "PAYDUNYA-PRIVATE-KEY": cfg.paiement_secret, "PAYDUNYA-TOKEN": cfg.paiement_site_id})
        if str(r.get("response_code")) != "00":
            raise RegleMetier(f"PayDunya : {r.get('response_text') or 'refus'}")
        pay.url_paiement, pay.jeton = r["response_text"], r.get("token", "")
    pay.save()
    journaliser(request, "Paiement en ligne", f"{pay.reference} initié — {contrat.locataire.nom} — {p.libelle} — {fcfa(pay.montant)} ({pay.get_operateur_display()}, {pay.fournisseur})")
    return pay


def verifier_paiement(pay: PaiementEnLigne):
    """Interroge l'agrégateur (jamais la seule notification reçue, qui pourrait être falsifiée) puis confirme."""
    if pay.statut != PaiementEnLigne.Statut.EN_ATTENTE or pay.fournisseur == "test":
        return pay
    cfg = Connecteurs.charger()
    if pay.fournisseur == "cinetpay":
        r = _http("https://api-checkout.cinetpay.com/v2/payment/check", json_={"apikey": cfg.paiement_cle, "site_id": cfg.paiement_site_id, "transaction_id": pay.reference})
        etat = (r.get("data") or {}).get("status")
        statut = {"ACCEPTED": "reussi", "REFUSED": "echoue"}.get(etat)
    else:
        r = _http(f"https://app.paydunya.com/api/v1/checkout-invoice/confirm/{pay.jeton}",
                  entetes={"PAYDUNYA-MASTER-KEY": cfg.paiement_cle, "PAYDUNYA-PRIVATE-KEY": cfg.paiement_secret, "PAYDUNYA-TOKEN": cfg.paiement_site_id})
        statut = {"completed": "reussi", "cancelled": "echoue", "failed": "echoue"}.get(r.get("status"))
    if statut:
        conclure_paiement(pay, statut == "reussi", detail=json.dumps(r)[:2000])
    return pay


@transaction.atomic
def conclure_paiement(pay: PaiementEnLigne, reussi: bool, detail="", request=None):
    pay = PaiementEnLigne.objects.select_for_update().select_related("contrat__locataire").get(pk=pay.pk)
    if pay.statut != PaiementEnLigne.Statut.EN_ATTENTE:
        return pay
    pay.detail = detail
    if not reussi:
        pay.statut = PaiementEnLigne.Statut.ECHOUE
        pay.save()
        journaliser(request, "Paiement en ligne", f"{pay.reference} échoué ou annulé")
        return pay
    c = pay.contrat
    # Le loyer a pu être réglé entre-temps à l'agence : on impute alors sur la période suivante due.
    p = locatif.echeancier(c, apres=24)
    cible = next((x for x in p if x.mois == pay.periode_mois and x.annee == pay.periode_annee and x.reste >= pay.montant), None) \
        or next((x for x in p if x.reste >= pay.montant), None)
    if cible is None:
        pay.statut, pay.detail = PaiementEnLigne.Statut.REUSSI, (detail + "\nÀ imputer manuellement : aucune période ne correspond au montant.").strip()
        pay.save()
        journaliser(request, "Paiement en ligne", f"{pay.reference} réussi — imputation manuelle nécessaire ({fcfa(pay.montant)})")
        return pay
    enc = locatif.enregistrer_encaissement(
        c, pay.montant, cible.mois, cible.annee, Encaissement.Mode.MOBILE_MONEY, request=request,
        observation=f"Paiement en ligne {pay.reference} — {pay.get_operateur_display()} {pay.telephone}", controler_dette=False,
    )
    pay.statut, pay.encaissement = PaiementEnLigne.Statut.REUSSI, enc
    pay.save()
    return pay


# ─── Signature électronique du bail ───────────────────────────────────────────
VALIDITE_CODE = timedelta(minutes=15)
ESSAIS_MAX = 5


def demander_signature(contrat: Contrat, request):
    cfg = Connecteurs.charger()
    if not cfg.signature_sms:
        raise RegleMetier("La signature par code SMS est désactivée dans Paramètres > Connecteurs.")
    if canal_disponible(cfg) is None:
        raise RegleMetier("Aucun service de SMS ou WhatsApp n'est branché : activez au moins le mode test.")
    if contrat.signatures.filter(signe_le__isnull=False).exists():
        raise RegleMetier("Ce bail est déjà signé électroniquement.")
    tel = contrat.locataire.telephone1
    if not tel:
        raise RegleMetier("Renseignez d'abord le téléphone du locataire.")
    code = f"{secrets.randbelow(1_000_000):06d}"
    contrat.signatures.filter(signe_le__isnull=True).delete()
    SignatureContrat.objects.create(contrat=contrat, telephone=numero_international(tel), code=make_password(code), expire_le=timezone.now() + VALIDITE_CODE)
    msg = envoyer_message(tel, f"{code} est votre code de signature du bail {contrat.reference} ({contrat.maison.designation}), loyer {fcfa(contrat.loyer_mensuel)}/mois. "
                               "Ne le communiquez qu'à l'agence. Valable 15 minutes.", nom=contrat.locataire.nom, objet="Signature du bail", request=request)
    journaliser(request, "Signature électronique", f"Code envoyé à {contrat.locataire.nom} pour le bail {contrat.reference}")
    # En mode test, rien n'est envoyé : le code est rendu pour pouvoir dérouler la signature.
    return {"telephone": numero_international(tel), "expire_dans": int(VALIDITE_CODE.total_seconds() // 60),
            "code_test": code if msg and msg.statut == MessageSortant.Statut.SIMULE else None,
            "statut_envoi": msg.statut if msg else None}


def valider_signature(contrat: Contrat, code, request, par):
    s = contrat.signatures.filter(signe_le__isnull=True).first()
    if s is None:
        raise RegleMetier("Aucune demande de signature en cours : envoyez d'abord un code.")
    if s.expire_le < timezone.now():
        raise RegleMetier("Ce code a expiré : envoyez-en un nouveau.")
    if s.tentatives >= ESSAIS_MAX:
        raise RegleMetier("Trop d'essais : envoyez un nouveau code.")
    if not check_password((code or "").strip(), s.code):
        s.tentatives += 1
        s.save(update_fields=["tentatives"])
        raise RegleMetier(f"Code incorrect ({ESSAIS_MAX - s.tentatives} essai(s) restant(s)).")
    s.signe_le, s.ip, s.validee_par = timezone.now(), ip_client(request), par
    s.save()
    journaliser(request, "Signature électronique", f"Bail {contrat.reference} signé par {contrat.locataire.nom} (code SMS au {s.telephone})")
    envoyer_message(contrat.locataire.telephone1, f"Votre bail {contrat.reference} est signé. Merci de votre confiance.", nom=contrat.locataire.nom, objet="Bail signé", request=request)
    return s


def signature_du_contrat(contrat):
    s = contrat.signatures.filter(signe_le__isnull=False).first()
    if s:
        return {"signe_le": s.signe_le, "telephone": s.telephone, "par": s.validee_par}
    attente = contrat.signatures.filter(signe_le__isnull=True, expire_le__gt=timezone.now()).first()
    return {"en_attente": True, "expire_le": attente.expire_le} if attente else None
