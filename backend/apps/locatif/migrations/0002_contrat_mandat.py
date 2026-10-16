"""Chaque bail garde le mandat de gestion sous lequel l'agence l'a signé.

Pour les baux existants : mandat du bailleur dont la période couvre la date de signature du bail,
à défaut le dernier mandat signé avant cette date, à défaut le plus ancien du bailleur.
"""
import django.db.models.deletion
from django.db import migrations, models
from django.db.models import Q


def rattacher_mandats(apps, schema_editor):
    Contrat = apps.get_model("locatif", "Contrat")
    MandatGestion = apps.get_model("patrimoine", "MandatGestion")
    for c in Contrat.objects.filter(mandat__isnull=True).select_related("maison"):
        mandats = MandatGestion.objects.filter(bailleur_id=c.maison.bailleur_id)
        d = c.date_contrat
        m = (
            mandats.filter(date_debut__lte=d).filter(Q(date_fin__isnull=True) | Q(date_fin__gte=d)).order_by("-date_debut", "-id").first()
            or mandats.filter(date_signature__lte=d).order_by("-date_signature", "-id").first()
            or mandats.order_by("date_debut", "id").first()
        )
        if m:
            c.mandat_id = m.pk
            c.save(update_fields=["mandat"])


class Migration(migrations.Migration):

    dependencies = [
        ("locatif", "0001_initial"),
        ("patrimoine", "0002_photos_maisons"),
    ]

    operations = [
        migrations.AddField(
            model_name="contrat",
            name="mandat",
            field=models.ForeignKey(blank=True, null=True, on_delete=django.db.models.deletion.PROTECT, related_name="contrats", to="patrimoine.mandatgestion"),
        ),
        migrations.RunPython(rattacher_mandats, migrations.RunPython.noop),
    ]
