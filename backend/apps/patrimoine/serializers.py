from rest_framework import serializers

from apps.core.serializers import FichierImageField
from apps.core.utils import TYPES_DOCUMENTS, valider_fichier

from .models import Bailleur, Document, Locataire, MandatGestion, Maison


def resume_mandat(m):
    """Résumé d'un mandat pour les écrans de bail et l'espace locataire (None = aucun mandat en vigueur)."""
    if m is None:
        return None
    return {"id": m.pk, "reference": m.reference, "duree": m.duree, "tacite_reconduction": m.tacite_reconduction,
            "date_signature": m.date_signature, "date_debut": m.date_debut, "echeance": m.echeance()}


class MandatSerializer(serializers.ModelSerializer):
    reference = serializers.CharField(read_only=True)
    en_vigueur = serializers.BooleanField(read_only=True)
    nb_baux = serializers.SerializerMethodField()
    echeance = serializers.SerializerMethodField()
    duree_libelle = serializers.CharField(source="get_duree_display", read_only=True)
    statut_libelle = serializers.CharField(source="get_statut_display", read_only=True)
    bailleur_nom = serializers.CharField(source="bailleur.nom", read_only=True)
    document = serializers.FileField(write_only=True, required=False, allow_null=True)

    class Meta:
        model = MandatGestion
        fields = [
            "id", "reference", "nb_baux", "bailleur", "bailleur_nom", "duree", "duree_libelle", "tacite_reconduction", "echeance", "date_signature", "date_debut", "date_fin", "taux_commission",
            "statut", "statut_libelle", "en_vigueur", "document_signe", "document", "created_at",
        ]
        read_only_fields = ["document_signe", "created_at", "statut"]

    def get_nb_baux(self, obj):
        return obj.contrats.count()

    def get_echeance(self, obj):
        return obj.echeance()

    def validate_document(self, f):
        return valider_fichier(f, TYPES_DOCUMENTS, "mandat") if f else None

    def validate_taux_commission(self, v):
        if v < 0 or v > 100:
            raise serializers.ValidationError("Le taux doit être compris entre 0 et 100.")
        return v

    def validate(self, attrs):
        debut = attrs.get("date_debut") or getattr(self.instance, "date_debut", None)
        fin = attrs.get("date_fin", getattr(self.instance, "date_fin", None))
        duree = attrs.get("duree", getattr(self.instance, "duree", MandatGestion.Duree.INDETERMINEE))
        if duree == MandatGestion.Duree.INDETERMINEE:
            attrs["date_fin"], attrs["tacite_reconduction"] = None, False
            fin = None
        elif not fin:
            raise serializers.ValidationError({"date_fin": "Indiquez la date de fin d'un mandat à durée déterminée."})
        if debut and fin and fin <= debut:
            raise serializers.ValidationError({"date_fin": "La date de fin doit être postérieure au début."})
        return attrs

    def create(self, data):
        doc = data.pop("document", None)
        if doc:
            data["document_signe"] = doc
        return super().create(data)

    def update(self, instance, data):
        doc = data.pop("document", None)
        if doc:
            instance.document_signe = doc
        return super().update(instance, data)


class BailleurSerializer(serializers.ModelSerializer):
    photo = FichierImageField(prefixe="bailleur")
    solde = serializers.DecimalField(max_digits=14, decimal_places=0, read_only=True, default=0)
    commissions = serializers.DecimalField(max_digits=14, decimal_places=0, read_only=True, default=0)
    nb_maisons = serializers.IntegerField(read_only=True, default=0)
    nb_occupees = serializers.IntegerField(read_only=True, default=0)
    mandat = serializers.SerializerMethodField()
    acces_portail = serializers.SerializerMethodField()

    class Meta:
        model = Bailleur
        fields = [
            "id", "code_bailleur", "nom", "sexe", "numero_cni", "telephone1", "telephone2", "email", "adresse",
            "photo", "created_at", "solde", "commissions", "nb_maisons", "nb_occupees", "mandat", "acces_portail",
        ]
        read_only_fields = ["code_bailleur", "created_at"]

    def get_acces_portail(self, obj):
        acces = getattr(obj, "acces", None)
        return bool(acces and acces.actif)

    def get_mandat(self, obj):
        m = obj.mandat_actif()
        return MandatSerializer(m, context=self.context).data if m else None


class BailleurMiniSerializer(serializers.ModelSerializer):
    a_mandat = serializers.SerializerMethodField()

    class Meta:
        model = Bailleur
        fields = ["id", "nom", "code_bailleur", "telephone1", "a_mandat"]

    def get_a_mandat(self, obj):
        return obj.mandat_actif() is not None


class MaisonSerializer(serializers.ModelSerializer):
    photos = serializers.SerializerMethodField()
    photo = serializers.SerializerMethodField()
    mandat_bailleur = serializers.SerializerMethodField()
    bailleur_nom = serializers.CharField(source="bailleur.nom", read_only=True)
    statut_libelle = serializers.CharField(source="get_statut_display", read_only=True)
    locataire_actuel = serializers.SerializerMethodField()

    class Meta:
        model = Maison
        fields = [
            "id", "bailleur", "bailleur_nom", "designation", "type_maison", "adresse", "description",
            "statut", "statut_libelle", "condition", "loyer", "photos", "photo", "visible_site",
            "created_at", "locataire_actuel", "mandat_bailleur",
        ]
        read_only_fields = ["statut", "created_at"]

    def get_mandat_bailleur(self, obj):
        return resume_mandat(obj.bailleur.mandat_actif())

    def _url(self, f):
        r = self.context.get("request")
        return r.build_absolute_uri(f.url) if r else f.url

    def get_photos(self, obj):
        return [{"id": p.pk, "url": self._url(p.image)} for p in obj.photos.all()]

    def get_photo(self, obj):
        p = next(iter(obj.photos.all()), None)
        return self._url(p.image) if p else None

    def get_locataire_actuel(self, obj):
        c = next((c for c in obj.contrats.all() if c.statut_contrat == "actif"), None)
        if c is None:
            return None
        return {"contrat": c.pk, "id": c.locataire_id, "nom": c.locataire.nom, "date_debut": c.date_debut}

    def validate_loyer(self, v):
        if v <= 0:
            raise serializers.ValidationError("Le loyer doit être supérieur à zéro.")
        return v


class LocataireSerializer(serializers.ModelSerializer):
    photo_locataire = FichierImageField(prefixe="locataire")
    photo_cni_recto = FichierImageField(prefixe="cni")
    photo_cni_verso = FichierImageField(prefixe="cni")
    contrat_actif = serializers.SerializerMethodField()
    acces_portail = serializers.SerializerMethodField()

    class Meta:
        model = Locataire
        fields = [
            "id", "nom", "telephone1", "telephone2", "email", "piece_identite", "profession",
            "photo_locataire", "photo_cni_recto", "photo_cni_verso", "created_at", "contrat_actif", "acces_portail",
        ]
        read_only_fields = ["created_at"]

    def get_contrat_actif(self, obj):
        c = next((c for c in obj.contrats.all() if c.statut_contrat == "actif"), None)
        if c is None:
            return None
        from apps.locatif.services import statut_paiement

        return {
            "id": c.pk, "reference": c.reference, "maison": c.maison.designation, "loyer": c.loyer_mensuel,
            "date_prochain_loyer": c.date_prochain_loyer, "statut_paiement": statut_paiement(c),
        }

    def get_acces_portail(self, obj):
        acces = getattr(obj, "acces", None)
        return bool(acces and acces.actif)

    def validate(self, attrs):
        if not (attrs.get("telephone1") or getattr(self.instance, "telephone1", "")):
            raise serializers.ValidationError({"telephone1": "Le téléphone principal est obligatoire (il sert de connexion à l'espace locataire)."})
        return attrs


class DocumentSerializer(serializers.ModelSerializer):
    fichier = serializers.FileField()
    uploaded_by_nom = serializers.CharField(source="uploaded_by.nom_complet", read_only=True, default="")

    class Meta:
        model = Document
        fields = ["id", "entity_type", "entity_id", "fichier", "nom_original", "type_mime", "taille", "uploaded_by_nom", "created_at"]
        read_only_fields = ["nom_original", "type_mime", "taille", "created_at"]

    def validate_fichier(self, f):
        self._nom_original = f.name
        f = valider_fichier(f, TYPES_DOCUMENTS, "doc")
        return f

    def create(self, data):
        f = data["fichier"]
        data["nom_original"] = getattr(self, "_nom_original", f.name)[:255]
        data["type_mime"] = getattr(f, "content_type", "")
        data["taille"] = f.size
        return super().create(data)
