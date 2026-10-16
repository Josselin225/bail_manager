import io
import mimetypes
import zipfile
from pathlib import Path

from django.conf import settings
from django.core import management
from django.http import FileResponse, Http404, HttpResponse
from django.utils import timezone
from django.utils._os import safe_join
from django.views.decorators.http import require_GET
from rest_framework import viewsets
from rest_framework.decorators import action, api_view, permission_classes
from rest_framework.permissions import IsAuthenticated
from rest_framework.response import Response

from .articles_bail import ARTICLES_BAIL_CI
from .models import ClauseContrat, ClauseMandat, Journal, Parametres
from .permissions import EstAdmin, LectureOuAdmin
from .serializers import ClauseContratSerializer, ClauseMandatSerializer, JournalSerializer, ParametresSerializer
from .utils import journaliser


@api_view(["GET", "PATCH"])
@permission_classes([LectureOuAdmin])
def parametres(request):
    p = Parametres.charger()
    if request.method == "PATCH":
        ser = ParametresSerializer(p, data=request.data, partial=True, context={"request": request})
        ser.is_valid(raise_exception=True)
        ser.save()
        journaliser(request, "Paramètres", "Mise à jour des paramètres de l'entreprise")
        return Response(ser.data)
    return Response(ParametresSerializer(p, context={"request": request}).data)


class _ClauseViewSet(viewsets.ModelViewSet):
    permission_classes = [LectureOuAdmin]
    pagination_class = None
    libelle = ""

    def perform_create(self, ser):
        c = ser.save()
        journaliser(self.request, self.libelle, f"Ajout : {c.titre}")

    def perform_update(self, ser):
        c = ser.save()
        journaliser(self.request, self.libelle, f"Modification : {c.titre}")

    def perform_destroy(self, c):
        journaliser(self.request, self.libelle, f"Suppression : {c.titre}")
        c.delete()

    @action(detail=False, methods=["post"])
    def ordonner(self, request):
        for i, pk in enumerate(request.data.get("ordre", [])):
            self.get_queryset().filter(pk=pk).update(ordre_affichage=(i + 1) * 10)
        return Response({"ok": True})


class ClauseContratViewSet(_ClauseViewSet):
    queryset = ClauseContrat.objects.all()
    serializer_class = ClauseContratSerializer
    libelle = "Clause contrat"

    @action(detail=False)
    def bibliotheque(self, request):
        return Response(ARTICLES_BAIL_CI)


class ClauseMandatViewSet(_ClauseViewSet):
    queryset = ClauseMandat.objects.all()
    serializer_class = ClauseMandatSerializer
    libelle = "Clause mandat"


class JournalViewSet(viewsets.ReadOnlyModelViewSet):
    queryset = Journal.objects.select_related("utilisateur")
    serializer_class = JournalSerializer
    permission_classes = [EstAdmin]
    filterset_fields = ["utilisateur", "action"]
    search_fields = ["action", "details", "ip_adresse"]

    def get_queryset(self):
        qs = super().get_queryset()
        p = self.request.query_params
        if p.get("du"):
            qs = qs.filter(date_action__date__gte=p["du"])
        if p.get("au"):
            qs = qs.filter(date_action__date__lte=p["au"])
        return qs

    @action(detail=False)
    def actions(self, request):
        return Response(sorted(set(Journal.objects.values_list("action", flat=True))))

    @action(detail=False, methods=["post"])
    def vider(self, request):
        """Purge les entrées plus anciennes que N jours (90 par défaut) — jamais tout l'historique récent."""
        jours = max(30, int(request.data.get("jours", 90)))
        limite = timezone.now() - timezone.timedelta(days=jours)
        n, _ = Journal.objects.filter(date_action__lt=limite).delete()
        journaliser(request, "Journal", f"Purge de {n} entrée(s) de plus de {jours} jours")
        return Response({"supprimees": n})


@api_view(["POST"])
@permission_classes([EstAdmin])
def sauvegarde(request):
    """Sauvegarde complète téléchargeable : données (JSON) + fichiers envoyés."""
    buf = io.BytesIO()
    donnees = io.StringIO()
    management.call_command(
        "dumpdata", "--natural-foreign", "--exclude=contenttypes", "--exclude=auth.permission",
        "--exclude=sessions", "--indent=1", stdout=donnees,
    )
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as z:
        z.writestr("donnees.json", donnees.getvalue())
        racine = Path(settings.MEDIA_ROOT)
        if racine.exists():
            for f in racine.rglob("*"):
                if f.is_file():
                    z.write(f, f"uploads/{f.relative_to(racine)}")
    journaliser(request, "Sauvegarde", "Sauvegarde manuelle téléchargée")
    nom = f"bailmanager_sauvegarde_{timezone.localtime():%Y%m%d_%H%M}.zip"
    resp = HttpResponse(buf.getvalue(), content_type="application/zip")
    resp["Content-Disposition"] = f'attachment; filename="{nom}"'
    return resp


@require_GET
def media(request, chemin):
    """
    Sert les fichiers envoyés. Les images du site vitrine (dossier public/) sont
    accessibles à tous ; tout le reste (CNI, documents, mandats...) exige une
    session du personnel.
    """
    if not chemin.startswith("public/") and not (request.user.is_authenticated and request.user.is_active):
        raise Http404()
    try:
        complet = safe_join(settings.MEDIA_ROOT, chemin)
    except Exception:
        raise Http404()
    p = Path(complet)
    if not p.is_file():
        raise Http404()
    type_mime = mimetypes.guess_type(p.name)[0] or "application/octet-stream"
    resp = FileResponse(open(p, "rb"), content_type=type_mime)
    resp["X-Content-Type-Options"] = "nosniff"
    if not chemin.startswith("public/"):
        resp["Cache-Control"] = "private, no-store"
    return resp
