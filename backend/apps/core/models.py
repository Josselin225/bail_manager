"""Paramètres de l'agence, clauses types, journal d'audit et anti brute-force."""
from django.conf import settings
from django.db import models


class Parametres(models.Model):
    """Fiche entreprise (une seule ligne) — en-têtes et pieds de page des documents."""

    nom_entreprise = models.CharField(max_length=255, default="BailManager")
    contact_email = models.EmailField(blank=True, default="")
    contact_telephone = models.CharField(max_length=150, blank=True, default="")
    activites = models.TextField(blank=True, default="")
    adresse_siege = models.TextField(blank=True, default="")
    cc_numero = models.CharField("N° compte contribuable", max_length=50, blank=True, default="")
    regime_imposition = models.CharField(max_length=100, blank=True, default="")
    rccm_numero = models.CharField(max_length=100, blank=True, default="")
    compte_bancaire = models.CharField(max_length=150, blank=True, default="")
    iban = models.CharField(max_length=100, blank=True, default="")
    swift = models.CharField(max_length=30, blank=True, default="")
    site_web = models.CharField(max_length=150, blank=True, default="")
    # Signataire de l'agence sur les mandats de gestion et les contrats de bail
    representant_nom = models.CharField("Représentant de l'agence", max_length=150, blank=True, default="")
    representant_qualite = models.CharField("Qualité du représentant", max_length=100, blank=True, default="Gérant")
    ville_signature = models.CharField("Ville de signature", max_length=100, blank=True, default="")
    taux_commission = models.DecimalField(
        "Taux de commission par défaut (%)", max_digits=5, decimal_places=2, default=10,
        help_text="Utilisé si le mandat du bailleur ne précise pas son propre taux.",
    )
    rappels_actifs = models.BooleanField(default=True)
    logo = models.ImageField(upload_to="public/logo/", blank=True, null=True)
    couleur_principale = models.CharField(max_length=9, blank=True, default="")
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = "settings"
        verbose_name = "Paramètres"

    def __str__(self):
        return self.nom_entreprise

    @classmethod
    def charger(cls):
        obj = cls.objects.first()
        if obj is None:
            obj = cls.objects.create()
        return obj

    def lignes_pied_de_page(self):
        """Même construction que buildFooterLines() de la version PHP."""
        lignes = []
        siege = f"Siège social : {self.adresse_siege}" if self.adresse_siege else ""
        tel = f"Tél : {self.contact_telephone}" if self.contact_telephone else ""
        if siege or tel:
            lignes.append(" - ".join(x for x in (siege, tel) if x))
        cc = [
            f"CC N° : {self.cc_numero}" if self.cc_numero else "",
            f"Régime d'imposition : {self.regime_imposition}" if self.regime_imposition else "",
            f"N° RCCM : {self.rccm_numero}" if self.rccm_numero else "",
            f"E-mail : {self.contact_email}" if self.contact_email else "",
        ]
        if any(cc):
            lignes.append(" - ".join(x for x in cc if x))
        banque = [
            f"Compte bancaire : {self.compte_bancaire}" if self.compte_bancaire else "",
            f"IBAN {self.iban}" if self.iban else "",
            f"SWIFT : {self.swift}" if self.swift else "",
        ]
        if any(banque):
            lignes.append(" - ".join(x for x in banque if x))
        if self.site_web:
            lignes.append(self.site_web)
        return lignes


class ClauseBase(models.Model):
    titre = models.CharField(max_length=150)
    contenu = models.TextField()
    ordre_affichage = models.PositiveIntegerField(default=0)
    actif = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        abstract = True
        ordering = ["ordre_affichage", "id"]

    def __str__(self):
        return self.titre


def clauses_en_vigueur(modele):
    """Copie des clauses actives (titre, contenu), à figer dans un bail ou un mandat au moment de sa signature."""
    return list(modele.objects.filter(actif=True).values("titre", "contenu"))


class ClauseContrat(ClauseBase):
    class Meta(ClauseBase.Meta):
        db_table = "clauses_contrat"


class ClauseMandat(ClauseBase):
    class Meta(ClauseBase.Meta):
        db_table = "clauses_mandat"


class Journal(models.Model):
    """Journal d'audit : chaque opération sensible y est tracée."""

    utilisateur = models.ForeignKey(
        settings.AUTH_USER_MODEL, null=True, blank=True, on_delete=models.SET_NULL, related_name="+"
    )
    action = models.CharField(max_length=255)
    details = models.TextField(blank=True, default="")
    date_action = models.DateTimeField(auto_now_add=True, db_index=True)
    ip_adresse = models.CharField(max_length=50, blank=True, default="")

    class Meta:
        db_table = "logs"
        ordering = ["-date_action", "-id"]


class RateLimit(models.Model):
    """Compteur de tentatives persistant (clé = contexte:ip), indépendant de la session."""

    cle = models.CharField(max_length=191, unique=True)
    tentatives = models.PositiveIntegerField(default=1)
    derniere_tentative = models.DateTimeField()

    class Meta:
        db_table = "rate_limits"


class Licence(models.Model):
    """Licence installée (texte signé) et début de la période d'essai de cette installation."""

    texte = models.TextField(blank=True, default="")
    installee_le = models.DateTimeField(null=True, blank=True)
    debut_essai = models.DateField()

    class Meta:
        db_table = "licence"

    @classmethod
    def charger(cls):
        from .utils import aujourdhui

        return cls.objects.first() or cls.objects.create(debut_essai=aujourdhui())
