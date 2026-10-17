"""Reprise de l'existant : fige les clauses actuelles dans les baux et mandats déjà signés et date les biens confiés."""
from django.db import migrations


def reprendre(apps, schema_editor):
    ClauseContrat = apps.get_model("core", "ClauseContrat")
    ClauseMandat = apps.get_model("core", "ClauseMandat")
    Contrat = apps.get_model("locatif", "Contrat")
    MandatGestion = apps.get_model("patrimoine", "MandatGestion")
    BienConfie = apps.get_model("patrimoine", "BienConfie")

    # Faute d'historique, les clauses en vigueur aujourd'hui sont la meilleure approximation pour l'existant.
    clauses_bail = list(ClauseContrat.objects.filter(actif=True).order_by("ordre_affichage", "id").values("titre", "contenu"))
    clauses_mandat = list(ClauseMandat.objects.filter(actif=True).order_by("ordre_affichage", "id").values("titre", "contenu"))
    Contrat.objects.filter(clauses_signees__isnull=True).update(clauses_signees=clauses_bail)

    for m in MandatGestion.objects.filter(clauses_signees__isnull=True):
        m.clauses_signees = clauses_mandat
        m.save(update_fields=["clauses_signees"])
    # Un bien est rattaché à chaque mandat sous lequel il existait : de sa création jusqu'au mandat suivant.
    for m in MandatGestion.objects.select_related("bailleur"):
        suivant = MandatGestion.objects.filter(bailleur=m.bailleur, date_debut__gt=m.date_debut).order_by("date_debut").first()
        for maison in m.bailleur.maisons.all():
            cree = maison.created_at.date()
            if suivant is not None and cree >= suivant.date_debut:
                continue
            BienConfie.objects.get_or_create(mandat=m, maison=maison, defaults={"confie_le": max(m.date_debut, cree)})


class Migration(migrations.Migration):
    dependencies = [
        ("patrimoine", "0005_clauses_et_biens_figes"),
        ("locatif", "0007_clauses_et_biens_figes"),
        ("core", "0001_initial"),
    ]

    operations = [migrations.RunPython(reprendre, migrations.RunPython.noop)]
