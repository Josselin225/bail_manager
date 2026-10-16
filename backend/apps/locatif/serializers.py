from rest_framework import serializers

from apps.core.utils import aujourdhui, duree_entre, libelle_duree

from .models import ChargeLocative, Contrat, Encaissement, MouvementCaution, RevisionLoyer
from .services import statut_paiement


class ContratSerializer(serializers.ModelSerializer):
    reference = serializers.CharField(read_only=True)
    maison_nom = serializers.CharField(source="maison.designation", read_only=True)
    maison_adresse = serializers.CharField(source="maison.adresse", read_only=True)
    maison_type = serializers.CharField(source="maison.type_maison", read_only=True)
    locataire_nom = serializers.CharField(source="locataire.nom", read_only=True)
    locataire_telephone = serializers.CharField(source="locataire.telephone1", read_only=True)
    bailleur_id = serializers.IntegerField(source="maison.bailleur_id", read_only=True)
    bailleur_nom = serializers.CharField(source="maison.bailleur.nom", read_only=True)
    statut_libelle = serializers.CharField(source="get_statut_contrat_display", read_only=True)
    mandat = serializers.PrimaryKeyRelatedField(read_only=True)
    mandat_reference = serializers.CharField(source="mandat.reference", read_only=True, default="")
    mandat_echu = serializers.SerializerMethodField()
    anciennete = serializers.SerializerMethodField()
    etats_des_lieux = serializers.SerializerMethodField()
    statut_paiement = serializers.SerializerMethodField()
    jours_retard = serializers.SerializerMethodField()
    caution_disponible = serializers.SerializerMethodField()

    class Meta:
        model = Contrat
        fields = [
            "id", "reference", "mandat", "mandat_reference", "mandat_echu", "anciennete", "etats_des_lieux", "maison", "maison_nom", "maison_adresse", "maison_type", "locataire", "locataire_nom",
            "locataire_telephone", "bailleur_id", "bailleur_nom", "loyer_mensuel", "date_contrat", "date_debut",
            "date_fin", "statut_contrat", "statut_libelle", "depot_garantie", "mois_avance", "avance_loyer",
            "droit_agence", "date_prochain_loyer", "date_resiliation", "motif_resiliation", "statut_paiement",
            "jours_retard", "caution_disponible", "created_at",
        ]

    def get_etats_des_lieux(self, obj):
        """Date de l'état des lieux d'entrée / de sortie, ou None s'il n'est pas encore établi."""
        dates = {e.type_etat: e.date_etat for e in obj.etats_des_lieux.all()}
        return {"entree": dates.get("entree"), "sortie": dates.get("sortie")}

    def get_anciennete(self, obj):
        """Durée du bail depuis sa conclusion (signature) : jusqu'à aujourd'hui, ou jusqu'à la résiliation s'il est terminé."""
        fin = obj.date_resiliation if obj.statut_contrat == "termine" and obj.date_resiliation else aujourdhui()
        a, m, j = duree_entre(obj.date_contrat, fin)
        return {"annees": a, "mois": m, "jours": j, "jours_total": max(0, (fin - obj.date_contrat).days),
                "libelle": libelle_duree(a, m, j), "en_cours": obj.statut_contrat == "actif"}

    def get_mandat_echu(self, obj):
        """Bail en cours alors que le propriétaire n'a plus de mandat de gestion en vigueur avec l'agence."""
        return obj.statut_contrat == "actif" and obj.maison.bailleur.mandat_actif() is None

    def get_statut_paiement(self, obj):
        return statut_paiement(obj)

    def get_caution_disponible(self, obj):
        mvts = getattr(obj, "total_mvts_caution", None)
        if mvts is None:
            from .services import caution_disponible

            return caution_disponible(obj)
        return obj.depot_garantie + mvts

    def get_jours_retard(self, obj):
        if obj.statut_contrat == "actif" and obj.date_prochain_loyer and obj.date_prochain_loyer < aujourdhui():
            return (aujourdhui() - obj.date_prochain_loyer).days
        return 0


class ContratCreationSerializer(serializers.Serializer):
    maison = serializers.IntegerField()
    locataire = serializers.IntegerField()
    # Facultatif et contrôlé : le loyer d'un bail est toujours celui fixé sur le bien
    loyer_mensuel = serializers.DecimalField(max_digits=12, decimal_places=0, min_value=1, required=False, allow_null=True)
    date_debut = serializers.DateField()
    date_fin = serializers.DateField(required=False, allow_null=True)
    date_contrat = serializers.DateField(required=False, allow_null=True)
    mois_avance = serializers.IntegerField(min_value=0, max_value=2, default=2)
    droit_agence = serializers.DecimalField(max_digits=12, decimal_places=0, min_value=0, default=0)
    mode_paiement = serializers.ChoiceField(choices=Encaissement.Mode.choices, default="especes")


class EncaissementSerializer(serializers.ModelSerializer):
    periode_concernee = serializers.CharField(read_only=True)
    mode_libelle = serializers.CharField(source="get_mode_paiement_display", read_only=True)
    origine_libelle = serializers.CharField(source="get_origine_display", read_only=True)
    contrat_reference = serializers.CharField(source="contrat.reference", read_only=True)
    locataire_id = serializers.IntegerField(source="contrat.locataire_id", read_only=True)
    locataire_nom = serializers.CharField(source="contrat.locataire.nom", read_only=True)
    maison_nom = serializers.CharField(source="contrat.maison.designation", read_only=True)
    bailleur_nom = serializers.CharField(source="contrat.maison.bailleur.nom", read_only=True)
    enregistre_par_nom = serializers.CharField(source="enregistre_par.nom_complet", read_only=True, default="")

    class Meta:
        model = Encaissement
        fields = [
            "id", "contrat", "contrat_reference", "locataire_id", "locataire_nom", "maison_nom", "bailleur_nom",
            "montant_recu", "date_encaissement", "mode_paiement", "mode_libelle", "reference_recu", "periode_mois",
            "periode_annee", "periode_concernee", "reliquat_apres_paiement", "taux_commission", "commission",
            "net_bailleur", "origine", "origine_libelle", "observation", "enregistre_par_nom",
        ]


class EncaissementCreationSerializer(serializers.Serializer):
    contrat = serializers.IntegerField()
    montant = serializers.DecimalField(max_digits=12, decimal_places=0, min_value=1)
    periode_mois = serializers.IntegerField(min_value=1, max_value=12)
    periode_annee = serializers.IntegerField(min_value=2000, max_value=2100)
    mode_paiement = serializers.ChoiceField(choices=Encaissement.Mode.choices, default="especes")
    observation = serializers.CharField(required=False, allow_blank=True, default="")


class ChargeSerializer(serializers.ModelSerializer):
    contrat_reference = serializers.CharField(source="contrat.reference", read_only=True)
    locataire_nom = serializers.CharField(source="contrat.locataire.nom", read_only=True)
    maison_nom = serializers.CharField(source="contrat.maison.designation", read_only=True)

    class Meta:
        model = ChargeLocative
        fields = ["id", "contrat", "contrat_reference", "locataire_nom", "maison_nom", "type_charge", "montant", "date_charge", "description", "created_at"]

    def validate_montant(self, v):
        if v <= 0:
            raise serializers.ValidationError("Montant invalide.")
        return v


class RevisionSerializer(serializers.ModelSerializer):
    contrat_reference = serializers.CharField(source="contrat.reference", read_only=True)
    locataire_nom = serializers.CharField(source="contrat.locataire.nom", read_only=True)
    maison_nom = serializers.CharField(source="contrat.maison.designation", read_only=True)
    effectue_par_nom = serializers.CharField(source="effectue_par.nom_complet", read_only=True, default="")
    origine_libelle = serializers.CharField(source="get_origine_display", read_only=True)
    variation = serializers.SerializerMethodField()

    class Meta:
        model = RevisionLoyer
        fields = [
            "id", "contrat", "contrat_reference", "locataire_nom", "maison_nom", "ancien_loyer", "nouveau_loyer",
            "variation", "origine", "origine_libelle", "motif", "date_effet", "effectue_par_nom", "created_at",
        ]

    def get_variation(self, obj):
        if not obj.ancien_loyer:
            return None
        return round(float((obj.nouveau_loyer - obj.ancien_loyer) / obj.ancien_loyer * 100), 1)


class MouvementCautionSerializer(serializers.ModelSerializer):
    locataire_nom = serializers.CharField(source="locataire.nom", read_only=True)
    type_libelle = serializers.CharField(source="get_type_mouvement_display", read_only=True)
    contrat_reference = serializers.CharField(source="contrat.reference", read_only=True, default="")

    class Meta:
        model = MouvementCaution
        fields = ["id", "locataire", "locataire_nom", "contrat", "contrat_reference", "type_mouvement", "type_libelle", "montant", "motif", "date_operation", "commentaire", "effectue_par"]
