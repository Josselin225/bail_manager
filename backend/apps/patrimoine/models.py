"""Patrimoine : bailleurs, mandats de gestion, biens (maisons), locataires, documents."""
from datetime import timedelta

from django.conf import settings
from django.db import models
from django.db.models import Q

from apps.core.utils import aujourdhui


class Bailleur(models.Model):
    class Sexe(models.TextChoices):
        M = "M", "Masculin"
        F = "F", "Féminin"

    code_bailleur = models.CharField(max_length=20, unique=True, blank=True)
    nom = models.CharField(max_length=100)
    sexe = models.CharField(max_length=1, choices=Sexe.choices, blank=True, default="")
    numero_cni = models.CharField("N° CNI", max_length=50, blank=True, default="")
    telephone1 = models.CharField(max_length=20, blank=True, default="")
    telephone2 = models.CharField(max_length=20, blank=True, default="")
    email = models.EmailField(max_length=100, blank=True, default="")
    adresse = models.TextField(blank=True, default="")
    photo = models.ImageField(upload_to="bailleurs/", blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "bailleurs"
        ordering = ["nom"]

    def __str__(self):
        return f"{self.nom} ({self.code_bailleur})"

    def save(self, *args, **kwargs):
        if not self.code_bailleur:
            self.code_bailleur = self.generer_code()
        super().save(*args, **kwargs)

    def generer_code(self):
        """Format hérité de la version PHP : BA-<2 initiales><année>-<ordre sur 4 chiffres>."""
        annee = aujourdhui().year
        initiales = (self.nom or "XX")[:2].upper()
        ordre = Bailleur.objects.filter(created_at__year=annee).count() + 1
        code = f"BA-{initiales}{annee}-{ordre:04d}"
        while Bailleur.objects.filter(code_bailleur=code).exists():
            ordre += 1
            code = f"BA-{initiales}{annee}-{ordre:04d}"
        return code

    def mandat_actif(self):
        return MandatGestion.objects.en_vigueur().filter(bailleur=self).order_by("-date_debut").first()


class MandatQuerySet(models.QuerySet):
    def en_vigueur(self, jour=None):
        jour = jour or aujourdhui()
        return self.filter(statut=MandatGestion.Statut.ACTIF, date_debut__lte=jour).filter(
            Q(date_fin__isnull=True) | Q(date_fin__gte=jour) | Q(tacite_reconduction=True)
        )


class MandatGestion(models.Model):
    """Un bailleur ne peut se voir enregistrer ni bien ni bail sans mandat de gestion actif."""

    class Statut(models.TextChoices):
        ACTIF = "actif", "Actif"
        RESILIE = "resilie", "Résilié"
        EXPIRE = "expire", "Expiré"

    class Duree(models.TextChoices):
        INDETERMINEE = "indeterminee", "Durée indéterminée"
        DETERMINEE = "determinee", "Durée déterminée"

    bailleur = models.ForeignKey(Bailleur, on_delete=models.CASCADE, related_name="mandats")
    duree = models.CharField(max_length=12, choices=Duree.choices, default=Duree.INDETERMINEE)
    # Durée déterminée : renouvelé automatiquement pour la même durée, sauf résiliation
    tacite_reconduction = models.BooleanField(default=False)
    # Date à laquelle les locataires ont été prévenus de l'échéance (évite les envois répétés)
    locataires_informes_le = models.DateField(null=True, blank=True)
    date_signature = models.DateField()
    date_debut = models.DateField()
    date_fin = models.DateField(null=True, blank=True, help_text="Vide = durée indéterminée")
    taux_commission = models.DecimalField(max_digits=5, decimal_places=2, default=10)
    statut = models.CharField(max_length=10, choices=Statut.choices, default=Statut.ACTIF)
    document_signe = models.FileField(upload_to="mandats/", blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    objects = MandatQuerySet.as_manager()

    class Meta:
        db_table = "mandats_gestion"
        ordering = ["-date_debut", "-id"]

    @property
    def reference(self):
        return f"MG-{self.pk:04d}" if self.pk else ""

    def echeance(self, jour=None):
        """Date de fin réelle : None si durée indéterminée ; avec tacite reconduction, fin de la période en cours."""
        if self.duree == self.Duree.INDETERMINEE or self.date_fin is None:
            return None
        fin = self.date_fin
        if self.tacite_reconduction:
            jour = jour or aujourdhui()
            periode = fin - self.date_debut + timedelta(days=1)
            while fin < jour:
                fin += periode
        return fin

    @property
    def en_vigueur(self):
        j = aujourdhui()
        fin = self.echeance(j)
        return self.statut == "actif" and self.date_debut <= j and (fin is None or fin >= j)


class Maison(models.Model):
    class Statut(models.TextChoices):
        DISPONIBLE = "disponible", "Disponible"
        OCCUPE = "occupe", "Occupée"

    bailleur = models.ForeignKey(Bailleur, on_delete=models.PROTECT, related_name="maisons")
    designation = models.CharField(max_length=255)
    type_maison = models.CharField(max_length=100, blank=True, default="")
    adresse = models.TextField(blank=True, default="")
    description = models.TextField(blank=True, default="")
    statut = models.CharField(max_length=12, choices=Statut.choices, default=Statut.DISPONIBLE)
    condition = models.PositiveSmallIntegerField(
        default=4, help_text="Nombre de loyers à verser avant l'entrée (avance + caution)."
    )
    loyer = models.DecimalField(max_digits=12, decimal_places=0, default=0)
    visible_site = models.BooleanField("Afficher sur le site vitrine", default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "maisons"
        ordering = ["designation"]

    def __str__(self):
        return self.designation


class PhotoMaison(models.Model):
    """Photos publiées sur le site vitrine, sans limite de nombre ; la première (ordre le plus bas) est la principale."""

    maison = models.ForeignKey(Maison, on_delete=models.CASCADE, related_name="photos")
    image = models.ImageField(upload_to="public/maisons/")
    ordre = models.PositiveIntegerField(default=0)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "photos_maisons"
        ordering = ["ordre", "id"]

    def __str__(self):
        return f"Photo {self.pk} — {self.maison}"


class Locataire(models.Model):
    nom = models.CharField(max_length=100)
    telephone1 = models.CharField(max_length=20, blank=True, default="", db_index=True)
    telephone2 = models.CharField(max_length=20, blank=True, default="")
    email = models.EmailField(max_length=150, blank=True, default="")
    piece_identite = models.CharField("N° pièce d'identité", max_length=50, blank=True, default="")
    profession = models.CharField(max_length=100, blank=True, default="")
    photo_locataire = models.ImageField(upload_to="locataires/", blank=True, null=True)
    photo_cni_recto = models.ImageField(upload_to="locataires/", blank=True, null=True)
    photo_cni_verso = models.ImageField(upload_to="locataires/", blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "locataires"
        ordering = ["nom"]

    def __str__(self):
        return self.nom


class LocataireAcces(models.Model):
    """Code d'accès à l'espace locataire (stocké haché, affiché une seule fois)."""

    locataire = models.OneToOneField(Locataire, on_delete=models.CASCADE, related_name="acces")
    code_acces = models.CharField(max_length=255)
    actif = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "locataire_acces"


class Document(models.Model):
    """Dossiers scannés rattachés à un bailleur, un locataire, un contrat ou une maison."""

    class Entite(models.TextChoices):
        BAILLEUR = "bailleur", "Bailleur"
        LOCATAIRE = "locataire", "Locataire"
        CONTRAT = "contrat", "Contrat"
        MAISON = "maison", "Maison"

    entity_type = models.CharField(max_length=12, choices=Entite.choices)
    entity_id = models.PositiveIntegerField()
    fichier = models.FileField(upload_to="documents/")
    nom_original = models.CharField(max_length=255)
    type_mime = models.CharField(max_length=100, blank=True, default="")
    taille = models.PositiveIntegerField(default=0)
    uploaded_by = models.ForeignKey(
        settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="+"
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "documents"
        ordering = ["-created_at"]
        indexes = [models.Index(fields=["entity_type", "entity_id"])]
