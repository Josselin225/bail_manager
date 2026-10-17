"""Services externes prêts à brancher : SMS, WhatsApp, paiement Mobile Money et signature par code SMS.

Les identifiants sont rangés ici, et non dans Parametres, car la fiche entreprise est publique
(en-têtes des quittances consultables par les locataires et les bailleurs).
"""
from django.db import models

from apps.core.chiffrement import ChampSecret


class Connecteurs(models.Model):
    class FournisseurSms(models.TextChoices):
        DESACTIVE = "desactive", "Désactivé"
        TEST = "test", "Mode test (rien n'est envoyé)"
        ORANGE = "orange", "Orange SMS API"
        TWILIO = "twilio", "Twilio"

    class FournisseurWhatsapp(models.TextChoices):
        DESACTIVE = "desactive", "Désactivé"
        TEST = "test", "Mode test (rien n'est envoyé)"
        META = "meta", "WhatsApp Business (API Cloud de Meta)"

    class FournisseurPaiement(models.TextChoices):
        DESACTIVE = "desactive", "Désactivé"
        TEST = "test", "Mode test (aucun débit)"
        CINETPAY = "cinetpay", "CinetPay"
        PAYDUNYA = "paydunya", "PayDunya"

    sms_fournisseur = models.CharField(max_length=12, choices=FournisseurSms.choices, default=FournisseurSms.TEST)
    sms_identifiant = models.CharField("Identifiant (client ID / Account SID)", max_length=200, blank=True, default="")
    sms_secret = ChampSecret("Secret (client secret / Auth token)", blank=True, default="")
    sms_expediteur = models.CharField("Expéditeur", max_length=30, blank=True, default="", help_text="Numéro ou nom d'expéditeur validé chez le fournisseur.")

    whatsapp_fournisseur = models.CharField(max_length=12, choices=FournisseurWhatsapp.choices, default=FournisseurWhatsapp.DESACTIVE)
    whatsapp_jeton = ChampSecret("Jeton d'accès", blank=True, default="")
    whatsapp_numero_id = models.CharField("Identifiant du numéro", max_length=60, blank=True, default="")

    canal_prefere = models.CharField(max_length=10, choices=[("sms", "SMS"), ("whatsapp", "WhatsApp")], default="sms")
    envoi_relances = models.BooleanField("Envoyer les relances par message", default=True)
    envoi_paiements = models.BooleanField("Confirmer chaque paiement par message", default=True)
    envoi_rappels = models.BooleanField("Rappels d'échéance et de retard par message", default=False)

    paiement_fournisseur = models.CharField(max_length=12, choices=FournisseurPaiement.choices, default=FournisseurPaiement.TEST)
    paiement_site_id = models.CharField("Site ID / Token", max_length=200, blank=True, default="")
    paiement_cle = ChampSecret("Clé API / Master key", blank=True, default="")
    paiement_secret = ChampSecret("Clé secrète / Private key", blank=True, default="")

    signature_sms = models.BooleanField("Signature des baux par code SMS", default=True)

    class Destination(models.TextChoices):
        DESACTIVE = "desactive", "Désactivée"
        DOSSIER = "dossier", "Dossier (disque externe, dossier Google Drive / OneDrive synchronisé)"
        WEBDAV = "webdav", "WebDAV (Nextcloud, kDrive, Koofr…)"
        S3 = "s3", "Stockage S3 (AWS, Backblaze B2, Wasabi, Cloudflare R2…)"

    sauvegarde_destination = models.CharField(max_length=10, choices=Destination.choices, default=Destination.DESACTIVE)
    sauvegarde_dossier = models.CharField("Dossier de destination", max_length=500, blank=True, default="")
    sauvegarde_url = models.CharField("Adresse (WebDAV ou point d'accès S3)", max_length=500, blank=True, default="")
    sauvegarde_bucket = models.CharField("Compartiment (bucket)", max_length=120, blank=True, default="")
    sauvegarde_region = models.CharField("Région", max_length=40, blank=True, default="")
    sauvegarde_identifiant = models.CharField("Identifiant / Access key", max_length=200, blank=True, default="")
    sauvegarde_secret = ChampSecret("Mot de passe / Secret key", blank=True, default="")
    sauvegarde_phrase = ChampSecret("Phrase de chiffrement", blank=True, default="")
    sauvegarde_garder = models.PositiveSmallIntegerField("Copies conservées (dossier)", default=30)
    derniere_sauvegarde_le = models.DateTimeField(null=True, blank=True)
    derniere_sauvegarde_statut = models.TextField(blank=True, default="")
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = "connecteurs"

    @classmethod
    def charger(cls):
        return cls.objects.first() or cls.objects.create()

    @property
    def paiement_actif(self):
        return self.paiement_fournisseur != self.FournisseurPaiement.DESACTIVE


class MessageSortant(models.Model):
    """Journal de chaque SMS / WhatsApp : en mode test, le message est seulement enregistré ici."""

    class Statut(models.TextChoices):
        SIMULE = "simule", "Simulé (mode test)"
        ENVOYE = "envoye", "Envoyé"
        ECHEC = "echec", "Échec"

    canal = models.CharField(max_length=10)
    fournisseur = models.CharField(max_length=12)
    destinataire = models.CharField(max_length=30)
    nom = models.CharField(max_length=150, blank=True, default="")
    texte = models.TextField()
    objet = models.CharField(max_length=60, blank=True, default="")
    statut = models.CharField(max_length=8, choices=Statut.choices)
    erreur = models.TextField(blank=True, default="")
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "messages_sortants"
        ordering = ["-created_at", "-id"]


class PaiementEnLigne(models.Model):
    """Paiement Mobile Money d'un loyer : à la confirmation de l'agrégateur, l'encaissement est créé automatiquement."""

    class Statut(models.TextChoices):
        EN_ATTENTE = "en_attente", "En attente"
        REUSSI = "reussi", "Réussi"
        ECHOUE = "echoue", "Échoué"

    OPERATEURS = [("wave", "Wave"), ("orange", "Orange Money"), ("mtn", "MTN MoMo"), ("moov", "Moov Money")]

    reference = models.CharField(max_length=30, unique=True)
    contrat = models.ForeignKey("locatif.Contrat", on_delete=models.PROTECT, related_name="paiements_en_ligne")
    periode_mois = models.PositiveSmallIntegerField()
    periode_annee = models.PositiveSmallIntegerField()
    montant = models.DecimalField(max_digits=12, decimal_places=0)
    telephone = models.CharField(max_length=30)
    operateur = models.CharField(max_length=10, choices=OPERATEURS)
    fournisseur = models.CharField(max_length=12)
    statut = models.CharField(max_length=10, choices=Statut.choices, default=Statut.EN_ATTENTE)
    url_paiement = models.URLField(max_length=500, blank=True, default="")
    jeton = models.CharField("Référence chez l'agrégateur", max_length=120, blank=True, default="")
    detail = models.TextField(blank=True, default="")
    encaissement = models.OneToOneField("locatif.Encaissement", null=True, blank=True, on_delete=models.PROTECT, related_name="paiement_en_ligne")
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = "paiements_en_ligne"
        ordering = ["-created_at"]


class SignatureContrat(models.Model):
    """Signature électronique d'un bail par code à usage unique envoyé par SMS au locataire."""

    contrat = models.ForeignKey("locatif.Contrat", on_delete=models.CASCADE, related_name="signatures")
    telephone = models.CharField(max_length=30)
    code = models.CharField(max_length=255)  # haché
    expire_le = models.DateTimeField()
    tentatives = models.PositiveSmallIntegerField(default=0)
    signe_le = models.DateTimeField(null=True, blank=True)
    ip = models.GenericIPAddressField(null=True, blank=True)
    validee_par = models.CharField(max_length=20, blank=True, default="")  # agence | locataire
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        db_table = "signatures_contrats"
        ordering = ["-created_at"]
