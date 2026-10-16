"""Rappels quotidiens (équivalent de php/cron_rappels.php)."""
from datetime import timedelta

from django.core.management.base import BaseCommand
from django.utils.html import escape

from apps.core.models import Parametres
from apps.core.utils import aujourdhui, fcfa, journaliser
from apps.locatif.models import Contrat
from apps.locatif.notifications import envoyer
from apps.patrimoine.mandats import informer_locataires_echeance
from apps.patrimoine.models import MandatGestion


class Command(BaseCommand):
    help = "Envoie les rappels de loyer (retards, échéances à 3 jours) et la synthèse quotidienne à l'agence."

    def handle(self, *args, **o):
        p = Parametres.charger()
        if not p.rappels_actifs:
            self.stdout.write("Rappels automatiques désactivés dans les paramètres.")
            return
        j = aujourdhui()
        actifs = Contrat.objects.filter(statut_contrat="actif").select_related("locataire", "maison")
        retards = list(actifs.filter(date_prochain_loyer__lt=j).order_by("date_prochain_loyer"))
        proches = list(actifs.filter(date_prochain_loyer__range=(j, j + timedelta(days=3))))
        fins = list(actifs.filter(date_fin__range=(j, j + timedelta(days=30))))
        # Mandats à durée déterminée non reconduits : l'agence est alertée à 90 jours, les locataires à 30 jours
        a_echeance = MandatGestion.objects.en_vigueur().filter(duree="determinee", tacite_reconduction=False).select_related("bailleur")
        mandats_90 = list(a_echeance.filter(date_fin__range=(j, j + timedelta(days=90))).order_by("date_fin"))
        informes = sum(informer_locataires_echeance(m) for m in a_echeance.filter(date_fin__range=(j, j + timedelta(days=30)), locataires_informes_le__isnull=True))
        envoyes = 0
        for c in retards + proches:
            if not c.locataire.email:
                continue
            en_retard = c.date_prochain_loyer < j
            corps = (
                f"<p>Bonjour {escape(c.locataire.nom)},</p><p>Votre loyer de <strong>{fcfa(c.loyer_mensuel)}</strong> "
                f"pour <strong>{escape(c.maison.designation)}</strong> "
                + (f"est en retard de {(j - c.date_prochain_loyer).days} jour(s) (échéance du {c.date_prochain_loyer:%d/%m/%Y}).</p>"
                   "<p>Merci de régulariser votre situation dans les meilleurs délais.</p>" if en_retard else
                   f"arrive à échéance le {c.date_prochain_loyer:%d/%m/%Y}.</p><p>Merci de prévoir votre paiement.</p>")
            )
            sujet = "Rappel — loyer en retard" if en_retard else "Rappel — échéance proche"
            if envoyer([c.locataire.email], sujet, "Rappel de paiement", corps):
                envoyes += 1
        if p.contact_email and (retards or proches or fins or mandats_90):
            def bloc(titre, items, fmt):
                return f"<h3 style='margin:18px 0 6px'>{titre} ({len(items)})</h3><ul>" + "".join(f"<li>{fmt(c)}</li>" for c in items) + "</ul>" if items else ""
            html = (
                bloc("Loyers en retard", retards, lambda c: f"{escape(c.locataire.nom)} — {escape(c.maison.designation)} — {fcfa(c.loyer_mensuel)} — {(j - c.date_prochain_loyer).days} j de retard")
                + bloc("Échéances dans les 3 jours", proches, lambda c: f"{escape(c.locataire.nom)} — {escape(c.maison.designation)} — le {c.date_prochain_loyer:%d/%m/%Y}")
                + bloc("Baux arrivant à échéance (30 j)", fins, lambda c: f"{escape(c.locataire.nom)} — {escape(c.maison.designation)} — fin le {c.date_fin:%d/%m/%Y}")
                + bloc("Mandats de gestion arrivant à échéance (90 j)", mandats_90, lambda m: f"{escape(m.bailleur.nom)} — mandat {m.reference} — fin le {m.date_fin:%d/%m/%Y} ({(m.date_fin - j).days} j)")
            )
            envoyer([p.contact_email], f"Synthèse quotidienne — {j:%d/%m/%Y}", "Synthèse quotidienne", html)
        resume = (
            f"Retards : {len(retards)} — Échéances proches : {len(proches)} — Fins de bail : {len(fins)} — "
            f"Mandats à échéance (90 j) : {len(mandats_90)} — Locataires informés d'une fin de mandat : {informes} — E-mails envoyés : {envoyes}"
        )
        journaliser(None, "Rappels automatiques", resume)
        self.stdout.write(self.style.SUCCESS(resume))
