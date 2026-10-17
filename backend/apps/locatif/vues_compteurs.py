"""API des compteurs partagés et de la répartition de leurs factures."""
from datetime import datetime

from rest_framework import serializers, viewsets
from rest_framework.decorators import action
from rest_framework.response import Response

from apps.core.exceptions import RegleMetier
from apps.core.permissions import SuppressionAdmin
from apps.core.utils import aujourdhui, journaliser

from . import compteurs
from .models import CompteurPartage, FacturePartagee


class CompteurSerializer(serializers.ModelSerializer):
    mode_libelle = serializers.CharField(source="get_mode_display", read_only=True)
    maisons_detail = serializers.SerializerMethodField()
    derniere_facture = serializers.SerializerMethodField()

    class Meta:
        model = CompteurPartage
        fields = ["id", "nom", "type_charge", "numero", "mode", "mode_libelle", "maisons", "maisons_detail", "actif", "derniere_facture"]

    def get_maisons_detail(self, obj):
        return [{"id": m.pk, "designation": m.designation, "statut": m.statut} for m in obj.maisons.all()]

    def get_derniere_facture(self, obj):
        f = obj.factures.first()
        return {"date": f.date_facture, "montant": f.montant, "periode": f.periode} if f else None

    def validate_maisons(self, v):
        if len(v) < 2:
            raise serializers.ValidationError("Un compteur partagé dessert au moins deux logements.")
        return v


def _facture(f):
    return {
        "id": f.pk, "numero": f.numero, "date_facture": f.date_facture, "periode": f.periode, "montant": f.montant,
        "lignes": [
            {"maison": l.maison.designation, "locataire": l.contrat.locataire.nom if l.contrat else None, "index_precedent": l.index_precedent,
             "index_actuel": l.index_actuel, "consommation": l.consommation, "montant": l.montant, "proprietaire": l.contrat_id is None and l.montant > 0}
            for l in f.lignes.select_related("maison", "contrat__locataire")
        ],
    }


class CompteurViewSet(viewsets.ModelViewSet):
    serializer_class = CompteurSerializer
    permission_classes = [SuppressionAdmin]
    pagination_class = None

    def get_queryset(self):
        return CompteurPartage.objects.prefetch_related("maisons")

    def perform_create(self, ser):
        c = ser.save()
        journaliser(self.request, "Compteur partagé", f"{c.nom} ({c.type_charge}, {c.maisons.count()} logements)")

    def perform_destroy(self, c):
        if c.factures.exists():
            raise RegleMetier("Ce compteur a des factures réparties : désactivez-le plutôt que de le supprimer.")
        c.delete()

    @action(detail=True)
    def preparation(self, request, pk=None):
        try:
            jour = datetime.strptime(request.query_params.get("date", ""), "%Y-%m-%d").date()
        except ValueError:
            jour = aujourdhui()
        return Response(compteurs.preparation(self.get_object(), jour))

    @action(detail=True, methods=["get", "post"])
    def factures(self, request, pk=None):
        c = self.get_object()
        if request.method == "POST":
            d = dict(request.data)
            try:
                d["date_facture"] = datetime.strptime(str(d.get("date_facture")), "%Y-%m-%d").date()
            except ValueError:
                raise RegleMetier("Date de facture invalide.")
            f = compteurs.repartir(c, d, request)
            return Response(_facture(f), status=201)
        return Response([_facture(f) for f in c.factures.all()[:24]])
