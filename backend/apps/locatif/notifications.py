"""E-mails transactionnels (optionnels : sans SMTP configuré, rien ne bloque)."""
import logging

from django.conf import settings
from django.core.mail import EmailMultiAlternatives
from django.utils.html import escape

from apps.core.models import Parametres
from apps.core.utils import fcfa

logger = logging.getLogger(__name__)


def _gabarit(nom_entreprise, titre, corps_html):
    return f"""
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #dde3ec;border-radius:8px;overflow:hidden">
      <div style="background:#0E2A47;color:#fff;padding:20px 24px">
        <div style="font-size:18px;font-weight:bold">{escape(nom_entreprise)}</div>
        <div style="opacity:.8;font-size:13px;margin-top:4px">{escape(titre)}</div>
      </div>
      <div style="padding:24px;color:#1d2433;font-size:14px;line-height:1.5">{corps_html}</div>
    </div>"""


def envoyer(destinataires, sujet, titre, corps_html):
    destinataires = [d for d in destinataires if d]
    if not destinataires or settings.EMAIL_BACKEND.endswith("console.EmailBackend") and not settings.DEBUG:
        return False
    p = Parametres.charger()
    try:
        msg = EmailMultiAlternatives(sujet, "", None, destinataires)
        msg.attach_alternative(_gabarit(p.nom_entreprise, titre, corps_html), "text/html")
        msg.send(fail_silently=False)
        return True
    except Exception as exc:  # l'encaissement ne doit jamais échouer à cause du mail
        logger.warning("Envoi e-mail impossible : %s", exc)
        return False


def envoyer_quittance(enc):
    c = enc.contrat
    loc = c.locataire
    p = Parametres.charger()
    lignes = [
        ("Référence", enc.reference_recu), ("Bien loué", c.maison.designation),
        ("Période", enc.periode_concernee), ("Montant versé", fcfa(enc.montant_recu)),
        ("Mode de paiement", enc.get_mode_paiement_display()),
        ("Reste pour cette période", fcfa(enc.reliquat_apres_paiement)),
    ]
    tableau = "".join(
        f"<tr><td style='padding:8px 12px;color:#5b6577'>{escape(k)}</td><td style='padding:8px 12px;font-weight:bold'>{escape(str(v))}</td></tr>"
        for k, v in lignes
    )
    corps = (
        f"<p>Bonjour <strong>{escape(loc.nom)}</strong>,</p><p>Votre paiement de loyer a bien été enregistré.</p>"
        f"<table style='width:100%;border-collapse:collapse;margin:16px 0;background:#f6f8fb'>{tableau}</table>"
        f"<p style='font-size:12px;color:#5b6577'>Votre quittance est disponible dans votre espace locataire.</p>"
    )
    return envoyer([loc.email, p.contact_email], f"Quittance {enc.reference_recu} — {enc.periode_concernee}", "Quittance de loyer", corps)
