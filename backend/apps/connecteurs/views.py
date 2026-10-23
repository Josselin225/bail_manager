"""API des connecteurs : réglages (administrateur), journal des envois, paiement en ligne et signature du bail."""
from django.shortcuts import get_object_or_404
from rest_framework import serializers
from rest_framework.decorators import api_view, authentication_classes, permission_classes
from rest_framework.permissions import AllowAny
from rest_framework.response import Response

from apps.core.auth import verifier_csrf
from apps.core.exceptions import RegleMetier
from apps.core.permissions import EstAdmin
from apps.core.utils import journaliser
from apps.locatif.models import Contrat
from apps.portail.permissions import EstLocataire

from . import services
from .models import Connecteurs, MessageSortant, PaiementEnLigne

SECRETS = ["sms_secret", "whatsapp_jeton", "paiement_cle", "paiement_secret", "sauvegarde_secret", "sauvegarde_phrase"]


class ConnecteursSerializer(serializers.ModelSerializer):
    """Les secrets ne sont jamais renvoyés : seulement « renseigné ou non ». Un champ laissé vide n'efface pas l'ancien."""

    secrets_renseignes = serializers.SerializerMethodField()

    class Meta:
        model = Connecteurs
        exclude = ["id"]
        read_only_fields = ["derniere_sauvegarde_le", "derniere_sauvegarde_statut"]
        extra_kwargs = {c: {"write_only": True} for c in SECRETS}

    def get_secrets_renseignes(self, obj):
        return {c: bool(getattr(obj, c)) for c in SECRETS}

    def update(self, instance, data):
        for c in SECRETS:
            if not data.get(c):
                data.pop(c, None)
        return super().update(instance, data)


@api_view(["GET", "PATCH"])
@permission_classes([EstAdmin])
def connecteurs(request):
    cfg = Connecteurs.charger()
    if request.method == "PATCH":
        ser = ConnecteursSerializer(cfg, data=request.data, partial=True)
        ser.is_valid(raise_exception=True)
        ser.save()
        journaliser(request, "Paramètres", f"Connecteurs : SMS {cfg.sms_fournisseur}, WhatsApp {cfg.whatsapp_fournisseur}, paiement {cfg.paiement_fournisseur}")
    data = ConnecteursSerializer(cfg).data
    data["choix"] = {
        "sms": Connecteurs.FournisseurSms.choices, "whatsapp": Connecteurs.FournisseurWhatsapp.choices,
        "paiement": Connecteurs.FournisseurPaiement.choices, "sauvegarde": Connecteurs.Destination.choices,
    }
    data["url_notification"] = request.build_absolute_uri("/api/public/paiements/notification/")
    return Response(data)


@api_view(["POST"])
@permission_classes([EstAdmin])
def tester(request):
    tel = request.data.get("telephone") or ""
    canal = request.data.get("canal") or None
    if not services.numero_international(tel):
        raise RegleMetier("Saisissez le numéro qui doit recevoir le message d'essai.")
    msg = services.envoyer_message(tel, "Message d'essai BailManager : la connexion fonctionne.", objet="Essai", canal=canal, request=request)
    if msg is None:
        raise RegleMetier("Ce canal est désactivé.")
    return Response({"statut": msg.statut, "statut_libelle": msg.get_statut_display(), "erreur": msg.erreur})


@api_view(["POST"])
@permission_classes([EstAdmin])
def sauvegarde_en_ligne(request):
    """Sauvegarde immédiate vers la destination réglée (chiffrée)."""
    from apps.core.sauvegarde import sauvegarder_en_ligne

    if Connecteurs.charger().sauvegarde_destination == "desactive":
        raise RegleMetier("Choisissez d'abord une destination de sauvegarde.")
    return Response({"statut": sauvegarder_en_ligne(request=request)})


@api_view(["GET"])
@permission_classes([EstAdmin])
def envois(request):
    return Response([
        {"id": m.pk, "date": m.created_at, "canal": m.canal, "fournisseur": m.fournisseur, "destinataire": m.destinataire, "nom": m.nom,
         "objet": m.objet, "texte": m.texte, "statut": m.statut, "statut_libelle": m.get_statut_display(), "erreur": m.erreur}
        for m in MessageSortant.objects.all()[:150]
    ])


def _paiement(p: PaiementEnLigne):
    return {
        "reference": p.reference, "statut": p.statut, "statut_libelle": p.get_statut_display(), "montant": p.montant,
        "periode": f"{p.periode_mois:02d}/{p.periode_annee}", "operateur": p.get_operateur_display(), "telephone": p.telephone,
        "fournisseur": p.fournisseur, "date": p.created_at, "locataire": p.contrat.locataire.nom, "contrat": p.contrat_id,
        "recu": p.encaissement.reference_recu if p.encaissement else None, "encaissement": p.encaissement_id, "detail": p.detail,
    }


@api_view(["GET"])
@permission_classes([EstAdmin])
def paiements(request):
    qs = PaiementEnLigne.objects.select_related("contrat__locataire", "encaissement")[:150]
    return Response([_paiement(p) for p in qs])


# ─── Signature électronique (agence) ──────────────────────────────────────────
@api_view(["POST"])
def signature_envoyer(request, pk):
    return Response(services.demander_signature(get_object_or_404(Contrat.objects.select_related("locataire", "maison"), pk=pk), request))


@api_view(["POST"])
def signature_valider(request, pk):
    c = get_object_or_404(Contrat.objects.select_related("locataire"), pk=pk)
    services.valider_signature(c, request.data.get("code"), request, par="agence")
    return Response(services.signature_du_contrat(c))


# ─── Espace locataire : paiement en ligne et signature ───────────────────────
def _contrat_locataire(request):
    c = Contrat.objects.filter(locataire_id=request.session["locataire_id"], statut_contrat="actif").select_related("locataire", "maison").first()
    if c is None:
        raise RegleMetier("Aucun bail en cours.")
    return c


@api_view(["POST"])
@authentication_classes([])
@permission_classes([EstLocataire])
def payer(request):
    verifier_csrf(request)
    c = _contrat_locataire(request)
    retour = request.build_absolute_uri("/espace-locataire")
    pay = services.initier_paiement(c, request.data.get("telephone") or c.locataire.telephone1, request.data.get("operateur"), request,
                                    retour_url=retour, notification_url=request.build_absolute_uri("/api/public/paiements/notification/"))
    return Response({**_paiement(pay), "url": pay.url_paiement or f"/paiement-test/{pay.reference}"}, status=201)


@api_view(["GET"])
@authentication_classes([])
@permission_classes([EstLocataire])
def paiement_locataire(request, reference):
    pay = get_object_or_404(PaiementEnLigne.objects.select_related("contrat__locataire", "encaissement"), reference=reference, contrat__locataire_id=request.session["locataire_id"])
    try:
        services.verifier_paiement(pay)
        pay.refresh_from_db()
    except RegleMetier:
        pass  # agrégateur momentanément injoignable : le statut reste « en attente »
    return Response(_paiement(pay))


@api_view(["POST"])
@authentication_classes([])
@permission_classes([EstLocataire])
def simuler_paiement(request, reference):
    """Page de paiement de test : uniquement quand le fournisseur est « test ». Aucun argent ne circule."""
    verifier_csrf(request)
    pay = get_object_or_404(PaiementEnLigne, reference=reference, contrat__locataire_id=request.session["locataire_id"])
    if pay.fournisseur != "test":
        raise RegleMetier("Simulation impossible : ce paiement passe par un vrai fournisseur.")
    # Une simulation ne doit jamais encaisser un vrai loyer : si l'agence oubliait le mode test en exploitation,
    # un locataire pourrait sinon « payer » son loyer d'un clic. Le paiement est seulement marqué simulé.
    if pay.statut == PaiementEnLigne.Statut.EN_ATTENTE:
        reussi = request.data.get("resultat") == "reussi"
        pay.statut = PaiementEnLigne.Statut.REUSSI if reussi else PaiementEnLigne.Statut.ECHOUE
        pay.detail = "Simulation (mode test) : aucun loyer n'a été encaissé."
        pay.save(update_fields=["statut", "detail"])
        journaliser(request, "Paiement en ligne", f"{pay.reference} — simulation {'réussie' if reussi else 'échouée'} (mode test, aucun encaissement)")
    return Response(_paiement(PaiementEnLigne.objects.select_related("contrat__locataire", "encaissement").get(pk=pay.pk)))


@api_view(["POST"])
@authentication_classes([])
@permission_classes([EstLocataire])
def signer_locataire(request):
    verifier_csrf(request)
    c = _contrat_locataire(request)
    services.valider_signature(c, request.data.get("code"), request, par="locataire")
    return Response(services.signature_du_contrat(c))


# ─── Notification de l'agrégateur (serveur à serveur) ────────────────────────
@api_view(["POST", "GET"])
@authentication_classes([])
@permission_classes([AllowAny])
def notification(request):
    d = request.data if hasattr(request.data, "get") else {}
    ref = d.get("cpm_trans_id") or d.get("transaction_id") or d.get("data[custom_data][reference]") or request.query_params.get("reference")
    jeton = d.get("data[invoice][token]") or d.get("token")
    pay = PaiementEnLigne.objects.filter(reference=ref).first() if ref else PaiementEnLigne.objects.filter(jeton=jeton).first() if jeton else None
    if pay:
        try:
            services.verifier_paiement(pay)  # statut relu chez l'agrégateur : la notification seule ne vaut pas preuve
        except RegleMetier:
            pass
    return Response({"ok": True})
