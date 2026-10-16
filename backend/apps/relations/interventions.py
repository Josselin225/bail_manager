"""Demandes d'intervention : création (agence ou locataire), photos, clôture avec imputation du coût."""
from django.db import transaction
from django.utils import timezone
from rest_framework import serializers
from rest_framework.exceptions import ValidationError

from apps.core.exceptions import RegleMetier
from apps.core.serializers import FichierImageField
from apps.core.utils import arrondir, auteur, aujourdhui, fcfa, journaliser

from .models import DemandeIntervention, PhotoIntervention


class InterventionSerializer(serializers.ModelSerializer):
    reference = serializers.CharField(read_only=True)
    maison_nom = serializers.CharField(source="maison.designation", read_only=True)
    bailleur_nom = serializers.CharField(source="maison.bailleur.nom", read_only=True)
    locataire_nom = serializers.CharField(source="locataire.nom", read_only=True, default="")
    locataire_telephone = serializers.CharField(source="locataire.telephone1", read_only=True, default="")
    categorie_libelle = serializers.CharField(source="get_categorie_display", read_only=True)
    statut_libelle = serializers.CharField(source="get_statut_display", read_only=True)
    imputation_libelle = serializers.CharField(source="get_imputation_display", read_only=True)
    photos = serializers.SerializerMethodField()

    class Meta:
        model = DemandeIntervention
        fields = [
            "id", "reference", "maison", "maison_nom", "bailleur_nom", "contrat", "locataire", "locataire_nom", "locataire_telephone",
            "origine", "categorie", "categorie_libelle", "urgente", "titre", "description", "statut", "statut_libelle",
            "artisan", "artisan_telephone", "date_intervention", "cout", "imputation", "imputation_libelle", "commentaire_agence",
            "cloturee_le", "created_at", "updated_at", "photos",
        ]
        read_only_fields = ["origine", "statut", "cout", "imputation", "cloturee_le", "contrat", "locataire"]

    def get_photos(self, obj):
        r = self.context.get("request")
        return [{"id": p.pk, "url": r.build_absolute_uri(p.image.url) if r else p.image.url} for p in obj.photos.all()]

    def validate_titre(self, v):
        if not v.strip():
            raise serializers.ValidationError("Décrivez le problème en quelques mots.")
        return v.strip()


def ajouter_photos(demande, fichiers):
    if demande.photos.count() + len(fichiers) > 10:
        raise RegleMetier("10 photos au maximum par demande.")
    champ = FichierImageField(prefixe="intervention")
    for f in fichiers:
        try:
            PhotoIntervention.objects.create(demande=demande, image=champ.run_validation(f))
        except ValidationError:
            raise RegleMetier(f"« {f.name} » n'est pas une image valide (JPG, PNG, GIF ou WebP).")


def rattacher_bail(demande):
    """La demande est rattachée au bail en cours sur la maison (et à son locataire)."""
    c = demande.maison.contrats.filter(statut_contrat="actif").select_related("locataire").first()
    if c:
        demande.contrat, demande.locataire = c, c.locataire


@transaction.atomic
def cloturer(demande, cout, imputation, request=None):
    """Clôt l'intervention et impute son coût : compte du bailleur, charge du locataire ou caisse de l'agence."""
    from apps.finances.models import CompteCourantBailleur
    from apps.finances.services import mouvement_caisse
    from apps.locatif.models import ChargeLocative

    if demande.statut in (DemandeIntervention.Statut.TERMINEE, DemandeIntervention.Statut.ANNULEE):
        raise RegleMetier("Cette demande est déjà clôturée.")
    cout = arrondir(cout or 0)
    if cout < 0:
        raise RegleMetier("Le coût ne peut pas être négatif.")
    if cout > 0 and imputation not in ("bailleur", "locataire", "agence"):
        raise RegleMetier("Indiquez à qui imputer le coût de l'intervention.")
    imputation = imputation if cout > 0 else "aucune"
    libelle = f"Intervention {demande.reference} — {demande.titre} ({demande.maison.designation})"
    if imputation == "bailleur":
        CompteCourantBailleur.objects.create(
            bailleur=demande.maison.bailleur, date_operation=timezone.now(), type_operation=CompteCourantBailleur.Type.AJUSTEMENT,
            montant=-cout, commentaire=libelle, effectue_par=auteur(request),
        )
    elif imputation == "locataire":
        if not demande.contrat_id:
            raise RegleMetier("Aucun bail n'est rattaché à cette demande : impossible d'imputer le coût au locataire.")
        ChargeLocative.objects.create(contrat=demande.contrat, type_charge="Réparation", montant=cout, date_charge=aujourdhui(), description=libelle)
    elif imputation == "agence":
        mouvement_caisse("Intervention", cout, libelle, request=request, sortie=True, reference=demande.reference)
    demande.cout, demande.imputation = cout, imputation
    demande.statut, demande.cloturee_le = DemandeIntervention.Statut.TERMINEE, timezone.now()
    if not demande.date_intervention:
        demande.date_intervention = aujourdhui()
    demande.save()
    journaliser(request, "Intervention", f"{libelle} clôturée — {fcfa(cout)} — {demande.get_imputation_display()}")
    return demande
