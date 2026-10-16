"""Gestion locative : contrats de bail, encaissements, charges, révisions, cautions."""
from django.conf import settings
from django.db import models

from apps.core.utils import libelle_periode
from apps.patrimoine.models import Locataire, Maison


class Contrat(models.Model):
    class Statut(models.TextChoices):
        ACTIF = "actif", "Actif"
        TERMINE = "termine", "Terminé"

    maison = models.ForeignKey(Maison, on_delete=models.PROTECT, related_name="contrats")
    # Mandat de gestion en vertu duquel l'agence a signé ce bail pour le compte du bailleur
    mandat = models.ForeignKey(
        "patrimoine.MandatGestion", on_delete=models.PROTECT, related_name="contrats", null=True, blank=True,
    )
    locataire = models.ForeignKey(Locataire, on_delete=models.PROTECT, related_name="contrats")
    loyer_mensuel = models.DecimalField(max_digits=12, decimal_places=0)
    date_contrat = models.DateField("Date de signature")
    date_debut = models.DateField()
    date_fin = models.DateField("Échéance du bail", null=True, blank=True)
    statut_contrat = models.CharField(max_length=10, choices=Statut.choices, default=Statut.ACTIF)
    depot_garantie = models.DecimalField("Caution", max_digits=12, decimal_places=0, default=0)
    mois_avance = models.PositiveSmallIntegerField(default=2)
    avance_loyer = models.DecimalField(max_digits=12, decimal_places=0, default=0)
    droit_agence = models.DecimalField("Frais d'agence", max_digits=12, decimal_places=0, default=0)
    debut_facturation = models.DateField(
        null=True, blank=True, help_text="Premier mois suivi dans l'échéancier (= début du bail)."
    )
    date_prochain_loyer = models.DateField(null=True, blank=True)
    date_resiliation = models.DateField(null=True, blank=True)
    motif_resiliation = models.CharField(max_length=255, blank=True, default="")
    # Préavis de départ (3 mois, clause du bail) : donné par le locataire ou par le bailleur
    preavis_le = models.DateField("Préavis notifié le", null=True, blank=True)
    preavis_par = models.CharField(max_length=10, blank=True, default="", choices=[("locataire", "Le locataire"), ("bailleur", "Le bailleur")])
    preavis_motif = models.CharField(max_length=255, blank=True, default="")
    date_sortie_prevue = models.DateField(null=True, blank=True)
    cree_par = models.ForeignKey(settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="+")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "contrats"
        ordering = ["-date_debut", "-id"]

    def __str__(self):
        return f"{self.reference} — {self.locataire} / {self.maison}"

    @property
    def reference(self):
        return f"CT-{self.date_contrat:%Y}-{self.pk:04d}" if self.pk else ""

    @property
    def bailleur(self):
        return self.maison.bailleur


class Encaissement(models.Model):
    class Mode(models.TextChoices):
        ESPECES = "especes", "Espèces"
        MOBILE_MONEY = "mobile_money", "Mobile Money"
        VIREMENT = "virement", "Virement"
        CHEQUE = "cheque", "Chèque"

    class Origine(models.TextChoices):
        LOYER = "loyer", "Loyer"
        AVANCE = "avance", "Avance à la signature"
        CAUTION = "caution", "Retenue sur caution"

    contrat = models.ForeignKey(Contrat, on_delete=models.PROTECT, related_name="encaissements")
    montant_recu = models.DecimalField(max_digits=12, decimal_places=0)
    date_encaissement = models.DateTimeField(db_index=True)
    mode_paiement = models.CharField(max_length=15, choices=Mode.choices, default=Mode.ESPECES)
    reference_recu = models.CharField(max_length=50, unique=True)
    periode_mois = models.PositiveSmallIntegerField()
    periode_annee = models.PositiveSmallIntegerField()
    reliquat_apres_paiement = models.DecimalField(max_digits=12, decimal_places=0, default=0)
    taux_commission = models.DecimalField(max_digits=5, decimal_places=2, default=0)
    commission = models.DecimalField(max_digits=12, decimal_places=0, default=0)
    net_bailleur = models.DecimalField(max_digits=12, decimal_places=0, default=0)
    origine = models.CharField(max_length=10, choices=Origine.choices, default=Origine.LOYER)
    observation = models.CharField(max_length=255, blank=True, default="")
    enregistre_par = models.ForeignKey(settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="+")

    class Meta:
        db_table = "encaissements"
        ordering = ["-date_encaissement", "-id"]
        indexes = [models.Index(fields=["contrat", "periode_annee", "periode_mois"])]

    @property
    def periode_concernee(self):
        return libelle_periode(self.periode_mois, self.periode_annee)


class ChargeLocative(models.Model):
    TYPES = ["Eau", "Électricité", "Ordures", "Entretien", "Réparation", "Copropriété", "Autre"]

    contrat = models.ForeignKey(Contrat, on_delete=models.CASCADE, related_name="charges")
    type_charge = models.CharField(max_length=100)
    montant = models.DecimalField(max_digits=12, decimal_places=0)
    date_charge = models.DateField()
    description = models.TextField(blank=True, default="")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "charges_locatives"
        ordering = ["-date_charge", "-id"]


class RevisionLoyer(models.Model):
    class Origine(models.TextChoices):
        BAILLEUR = "bailleur", "À la demande du bailleur"
        TRIENNALE = "triennale", "Révision triennale"
        ACCORD = "accord", "Accord entre les parties"

    contrat = models.ForeignKey(Contrat, on_delete=models.CASCADE, related_name="revisions")
    origine = models.CharField(max_length=10, choices=Origine.choices, default=Origine.BAILLEUR)
    ancien_loyer = models.DecimalField(max_digits=12, decimal_places=0)
    nouveau_loyer = models.DecimalField(max_digits=12, decimal_places=0)
    motif = models.CharField(max_length=255, blank=True, default="")
    date_effet = models.DateField(help_text="Premier mois auquel s'applique le nouveau loyer.")
    effectue_par = models.ForeignKey(settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="+")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "revisions_loyer"
        ordering = ["-created_at", "-id"]


class MouvementCaution(models.Model):
    """Registre des cautions. Disponible = dépôt du contrat + somme des mouvements (négatifs)."""

    class Type(models.TextChoices):
        RETENUE = "retenue", "Retenue"
        RESTITUTION = "restitution", "Restitution au locataire"

    locataire = models.ForeignKey(Locataire, on_delete=models.PROTECT, related_name="mouvements_caution")
    contrat = models.ForeignKey(Contrat, null=True, blank=True, on_delete=models.PROTECT, related_name="mouvements_caution")
    type_mouvement = models.CharField(max_length=20, choices=Type.choices)
    montant = models.DecimalField(max_digits=12, decimal_places=0)
    motif = models.CharField(max_length=30, blank=True, default="")  # reparation | loyer | solde
    date_operation = models.DateTimeField(auto_now_add=True)
    commentaire = models.TextField(blank=True, default="")
    effectue_par = models.CharField(max_length=100, blank=True, default="")

    class Meta:
        db_table = "mouvements_caution"
        ordering = ["-date_operation", "-id"]


class EtatDesLieux(models.Model):
    """État des lieux contradictoire, annexé au bail : à l'entrée du locataire et à sa sortie."""

    class Type(models.TextChoices):
        ENTREE = "entree", "État des lieux d'entrée"
        SORTIE = "sortie", "État des lieux de sortie"

    contrat = models.ForeignKey(Contrat, on_delete=models.CASCADE, related_name="etats_des_lieux")
    type_etat = models.CharField(max_length=10, choices=Type.choices, default=Type.ENTREE)
    date_etat = models.DateField()
    index_eau = models.CharField("Index compteur d'eau", max_length=30, blank=True, default="")
    index_electricite = models.CharField("Index compteur d'électricité", max_length=30, blank=True, default="")
    nombre_cles = models.PositiveSmallIntegerField(default=0)
    pieces = models.JSONField(default=list)
    observations = models.TextField(blank=True, default="")
    etabli_par = models.ForeignKey(settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="+")
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = "etats_des_lieux"
        constraints = [models.UniqueConstraint(fields=["contrat", "type_etat"], name="un_etat_des_lieux_par_type")]


class PhotoEtatDesLieux(models.Model):
    """Photo prise lors d'un état des lieux, rattachée à une pièce (preuve en cas de litige)."""

    etat = models.ForeignKey(EtatDesLieux, on_delete=models.CASCADE, related_name="photos")
    piece = models.CharField(max_length=80)
    image = models.ImageField(upload_to="etats_des_lieux/")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "photos_etats_des_lieux"
        ordering = ["id"]


class Relance(models.Model):
    """Relance d'impayé graduée : rappel amiable, relance, puis mise en demeure."""

    class Niveau(models.IntegerChoices):
        RAPPEL = 1, "Rappel amiable"
        RELANCE = 2, "Relance"
        MISE_EN_DEMEURE = 3, "Mise en demeure"

    contrat = models.ForeignKey(Contrat, on_delete=models.CASCADE, related_name="relances")
    niveau = models.PositiveSmallIntegerField(choices=Niveau.choices)
    date_relance = models.DateField()
    montant_du = models.DecimalField(max_digits=12, decimal_places=0)
    periodes = models.TextField(blank=True, default="")
    delai_jours = models.PositiveSmallIntegerField(default=8)
    effectuee_par = models.ForeignKey(settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="+")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "relances"
        ordering = ["-date_relance", "-id"]

    @property
    def reference(self):
        return f"REL-{self.pk:04d}" if self.pk else ""


class PlanApurement(models.Model):
    """Échelonnement d'une dette locative : le suivi compare les échéances aux paiements reçus depuis l'accord."""

    contrat = models.ForeignKey(Contrat, on_delete=models.CASCADE, related_name="plans_apurement")
    montant_total = models.DecimalField(max_digits=12, decimal_places=0)
    date_accord = models.DateField()
    echeances = models.JSONField(default=list)  # [{"date": "AAAA-MM-JJ", "montant": 50000}, ...]
    actif = models.BooleanField(default=True)
    commentaire = models.CharField(max_length=255, blank=True, default="")
    cree_par = models.ForeignKey(settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="+")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "plans_apurement"
        ordering = ["-date_accord", "-id"]

    @property
    def reference(self):
        return f"PA-{self.pk:04d}" if self.pk else ""
