"""Photos des maisons : passage de 3 champs fixes (image1..3) à une table de photos sans limite.

Les photos existantes sont recopiées dans l'ordre (image1 devient la photo principale)
avant la suppression des anciens champs ; les fichiers restent en place.
"""
from django.db import migrations, models
import django.db.models.deletion


def recopier_photos(apps, schema_editor):
    Maison = apps.get_model("patrimoine", "Maison")
    PhotoMaison = apps.get_model("patrimoine", "PhotoMaison")
    for m in Maison.objects.all():
        for ordre, champ in enumerate(("image1", "image2", "image3")):
            f = getattr(m, champ)
            if f:
                PhotoMaison.objects.create(maison=m, image=f.name, ordre=ordre)


def restaurer_photos(apps, schema_editor):
    Maison = apps.get_model("patrimoine", "Maison")
    for m in Maison.objects.all():
        for champ, photo in zip(("image1", "image2", "image3"), m.photos.order_by("ordre", "id")):
            setattr(m, champ, photo.image.name)
        m.save()


class Migration(migrations.Migration):
    dependencies = [("patrimoine", "0001_initial")]

    operations = [
        migrations.CreateModel(
            name="PhotoMaison",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("image", models.ImageField(upload_to="public/maisons/")),
                ("ordre", models.PositiveIntegerField(default=0)),
                ("created_at", models.DateTimeField(auto_now_add=True)),
                ("maison", models.ForeignKey(on_delete=django.db.models.deletion.CASCADE, related_name="photos", to="patrimoine.maison")),
            ],
            options={"db_table": "photos_maisons", "ordering": ["ordre", "id"]},
        ),
        migrations.RunPython(recopier_photos, restaurer_photos),
        migrations.RemoveField(model_name="maison", name="image1"),
        migrations.RemoveField(model_name="maison", name="image2"),
        migrations.RemoveField(model_name="maison", name="image3"),
    ]
