from django.db.models import Count, Q, Sum
from django.db.models.functions import TruncMonth
from django.shortcuts import get_object_or_404
from django_filters import rest_framework as filters
from rest_framework import mixins, viewsets
from rest_framework.decorators import action, api_view, parser_classes
from rest_framework.parsers import MultiPartParser
from rest_framework.response import Response

from apps.core.exceptions import RegleMetier
from apps.patrimoine.models import Bailleur

from . import services
from .models import CompteCourantBailleur, MouvementCaisse
from .serializers import MouvementCaisseSerializer, OperationBailleurSerializer


def _par_mois(qs, champ_date="date_operation"):
    lignes = (
        qs.annotate(m=TruncMonth(champ_date)).values("m")
        .annotate(credits=Sum("montant", filter=Q(montant__gt=0)), debits=Sum("montant", filter=Q(montant__lt=0)))
        .order_by("m")
    )
    return [
        {"mois": l["m"].strftime("%Y-%m"), "entrees": l["credits"] or 0, "sorties": -(l["debits"] or 0)}
        for l in lignes if l["m"]
    ]


class OperationFiltre(filters.FilterSet):
    du = filters.DateFilter(field_name="date_operation", lookup_expr="date__gte")
    au = filters.DateFilter(field_name="date_operation", lookup_expr="date__lte")

    class Meta:
        model = CompteCourantBailleur
        fields = ["bailleur", "type_operation"]


class CompteBailleurViewSet(mixins.ListModelMixin, viewsets.GenericViewSet):
    serializer_class = OperationBailleurSerializer
    filterset_class = OperationFiltre
    search_fields = ["commentaire", "bailleur__nom"]

    def get_queryset(self):
        return CompteCourantBailleur.objects.select_related("bailleur", "encaissement")

    @action(detail=False)
    def synthese(self, request):
        qs = self.filter_queryset(self.get_queryset())
        agg = qs.aggregate(
            credits=Sum("montant", filter=Q(montant__gt=0)), retraits=Sum("montant", filter=Q(montant__lt=0)),
            solde=Sum("montant"), nb=Count("id"),
        )
        base = self.get_queryset()
        if request.query_params.get("bailleur"):
            base = base.filter(bailleur=request.query_params["bailleur"])
        return Response({
            "credits": agg["credits"] or 0, "retraits": -(agg["retraits"] or 0), "solde_periode": agg["solde"] or 0,
            "nb_operations": agg["nb"], "solde_global": base.aggregate(t=Sum("montant"))["t"] or 0,
            "par_mois": _par_mois(base)[-12:],
        })

    @action(detail=False, pagination_class=None)
    def soldes(self, request):
        lignes = (
            Bailleur.objects.annotate(solde=Sum("operations__montant"))
            .values("id", "nom", "code_bailleur", "solde").order_by("nom")
        )
        return Response([{**l, "solde": l["solde"] or 0} for l in lignes])

    @action(detail=False, methods=["post"])
    def verser(self, request):
        b = get_object_or_404(Bailleur, pk=request.data.get("bailleur"))
        op = services.verser_au_bailleur(
            b, request.data.get("montant"), request.data.get("mode", "especes"), request.data.get("commentaire", ""), request
        )
        return Response(OperationBailleurSerializer(op).data, status=201)


class CaisseFiltre(filters.FilterSet):
    du = filters.DateFilter(field_name="date_operation", lookup_expr="date__gte")
    au = filters.DateFilter(field_name="date_operation", lookup_expr="date__lte")
    sens = filters.CharFilter(method="filtrer_sens")

    class Meta:
        model = MouvementCaisse
        fields = ["type_mouvement", "effectue_par", "bailleur"]

    def filtrer_sens(self, qs, name, value):
        return qs.filter(montant__gt=0) if value == "entree" else qs.filter(montant__lt=0) if value == "sortie" else qs


class CaisseViewSet(mixins.ListModelMixin, viewsets.GenericViewSet):
    serializer_class = MouvementCaisseSerializer
    filterset_class = CaisseFiltre
    search_fields = ["commentaire", "type_mouvement", "reference_paiement"]

    def get_queryset(self):
        return MouvementCaisse.objects.select_related("bailleur")

    @action(detail=False)
    def synthese(self, request):
        qs = self.filter_queryset(self.get_queryset())
        agg = qs.aggregate(e=Sum("montant", filter=Q(montant__gt=0)), s=Sum("montant", filter=Q(montant__lt=0)))
        par_type = qs.values("type_mouvement").annotate(total=Sum("montant"), nb=Count("id")).order_by("-total")
        return Response({
            "entrees": agg["e"] or 0, "sorties": -(agg["s"] or 0), "solde": services.solde_caisse(),
            "par_type": list(par_type), "par_mois": _par_mois(self.get_queryset())[-12:],
            "auteurs": list(MouvementCaisse.objects.exclude(effectue_par="").values_list("effectue_par", flat=True).distinct()),
        })

    @action(detail=False, methods=["post"])
    def retrait(self, request):
        motif = (request.data.get("motif") or "").strip()
        if not motif:
            from apps.core.exceptions import RegleMetier

            raise RegleMetier("Le motif est obligatoire.")
        note = (request.data.get("commentaire") or "").strip()
        mvt = services.mouvement_caisse(
            "Retrait/Dépense", request.data.get("montant"), motif + (f" — {note}" if note else ""), request, sortie=True
        )
        return Response(MouvementCaisseSerializer(mvt).data, status=201)

    @action(detail=False, methods=["post"])
    def entree(self, request):
        mvt = services.mouvement_caisse(
            request.data.get("type_mouvement") or "Autre entrée", request.data.get("montant"),
            request.data.get("commentaire", ""), request,
        )
        return Response(MouvementCaisseSerializer(mvt).data, status=201)


# ─── Rapprochement de relevés (Mobile Money, banque) ──────────────────────────
@api_view(["POST"])
@parser_classes([MultiPartParser])
def rapprochement_analyser(request):
    """Lit le relevé, devine les colonnes (ou applique celles choisies) et propose un bail pour chaque crédit."""
    import json

    from . import rapprochement as r

    fichier = request.FILES.get("fichier")
    if fichier is None:
        raise RegleMetier("Choisissez le fichier du relevé (CSV ou Excel).")
    if fichier.size > 5 * 1024 * 1024:
        raise RegleMetier("Relevé trop volumineux (5 Mo au plus).")
    entetes, corps = r.lire_releve(fichier)
    colonnes = r.deviner_colonnes(entetes)
    if request.data.get("colonnes"):
        choix = json.loads(request.data["colonnes"])
        colonnes = {k: (int(v) if v not in (None, "") else None) for k, v in choix.items() if k in r.CHAMPS}
    lignes = r.suggerer(entetes, corps, colonnes)
    return Response({"entetes": entetes, "colonnes": colonnes, "lignes": lignes, "apercu": corps[:3]})


@api_view(["POST"])
def rapprochement_encaisser(request):
    from . import rapprochement as r

    lignes = request.data.get("lignes") or []
    if not lignes:
        raise RegleMetier("Aucune ligne sélectionnée.")
    return Response({"resultats": r.encaisser_lignes(lignes, request.data.get("mode", "mobile_money"), request.data.get("source") or "relevé", request)})


@api_view(["GET"])
def rapprochement_historique(request):
    from .models import LigneRapprochee

    qs = LigneRapprochee.objects.select_related("contrat__locataire", "encaissement")[:200]
    return Response([
        {"id": x.pk, "date": x.date_operation, "montant": x.montant, "source": x.source, "telephone": x.telephone, "libelle": x.libelle,
         "reference": x.reference, "locataire": x.contrat.locataire.nom, "contrat": x.contrat_id,
         "recu": x.encaissement.reference_recu if x.encaissement else None, "reste": x.reste_non_affecte, "importe_le": x.created_at}
        for x in qs
    ])
