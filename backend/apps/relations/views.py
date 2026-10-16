from django.db.models import Count, Max, Q
from django.shortcuts import get_object_or_404
from rest_framework import mixins, viewsets
from rest_framework.decorators import action
from rest_framework.response import Response

from apps.core.exceptions import RegleMetier
from apps.core.permissions import SuppressionAdmin
from apps.core.utils import journaliser
from apps.locatif.notifications import envoyer
from apps.patrimoine.models import Locataire

from .interventions import InterventionSerializer, ajouter_photos, cloturer, rattacher_bail
from .models import DemandeIntervention, MessageLocataire, MessageVisiteur, Reservation
from .serializers import MessageLocataireSerializer, MessageVisiteurSerializer, ReservationSerializer


class ReservationViewSet(mixins.ListModelMixin, mixins.UpdateModelMixin, mixins.DestroyModelMixin, viewsets.GenericViewSet):
    serializer_class = ReservationSerializer
    permission_classes = [SuppressionAdmin]
    filterset_fields = ["statut", "maison"]
    search_fields = ["nom_visiteur", "tel_visiteur", "maison__designation"]
    http_method_names = ["get", "patch", "delete"]

    def get_queryset(self):
        return Reservation.objects.select_related("maison")

    def perform_update(self, ser):
        r = ser.save()
        journaliser(self.request, "Réservation", f"Réservation #{r.pk} — {r.get_statut_display()}")

    def perform_destroy(self, r):
        journaliser(self.request, "Réservation", f"Réservation #{r.pk} supprimée")
        r.delete()


class MessageVisiteurViewSet(mixins.ListModelMixin, mixins.UpdateModelMixin, mixins.DestroyModelMixin, viewsets.GenericViewSet):
    serializer_class = MessageVisiteurSerializer
    permission_classes = [SuppressionAdmin]
    filterset_fields = ["lu"]
    search_fields = ["nom_visiteur", "email_visiteur", "sujet", "contenu"]
    http_method_names = ["get", "patch", "delete", "post"]

    def get_queryset(self):
        return MessageVisiteur.objects.all()

    @action(detail=True, methods=["post"])
    def repondre(self, request, pk=None):
        msg = self.get_object()
        texte = (request.data.get("contenu") or "").strip()
        if not texte:
            raise RegleMetier("Le message est vide.")
        from django.utils.html import escape, linebreaks

        ok = envoyer([msg.email_visiteur], f"Re : {msg.sujet or 'Votre message'}", "Réponse à votre message", linebreaks(escape(texte)))
        if not ok:
            raise RegleMetier("L'envoi a échoué : vérifiez la configuration e-mail (MAIL_HOST dans .env).")
        msg.lu = True
        msg.save(update_fields=["lu"])
        journaliser(request, "Message", f"Réponse envoyée à {msg.email_visiteur}")
        return Response({"detail": "Réponse envoyée."})

    def perform_destroy(self, m):
        journaliser(self.request, "Message", f"Message de {m.nom_visiteur} supprimé")
        m.delete()


class MessagerieLocataireViewSet(viewsets.ViewSet):
    """Messagerie agence ↔ locataires (messages_locataires.php)."""

    def list(self, request):
        q = request.query_params.get("search", "")
        convs = (
            Locataire.objects.filter(messages__isnull=False)
            .annotate(
                dernier=Max("messages__created_at"),
                non_lus=Count("messages", filter=Q(messages__lu=False, messages__expediteur="locataire")),
            )
            .order_by("-dernier")
        )
        if q:
            convs = convs.filter(nom__icontains=q)
        res = []
        for l in convs[:200]:
            dernier = l.messages.order_by("-created_at").first()
            res.append({
                "locataire": l.pk, "nom": l.nom, "telephone": l.telephone1, "non_lus": l.non_lus,
                "dernier_message": dernier.contenu[:120] if dernier else "", "date": l.dernier,
                "expediteur": dernier.expediteur if dernier else "",
            })
        return Response(res)

    def retrieve(self, request, pk=None):
        loc = get_object_or_404(Locataire, pk=pk)
        loc.messages.filter(expediteur="locataire", lu=False).update(lu=True)
        return Response({
            "locataire": {"id": loc.pk, "nom": loc.nom, "telephone": loc.telephone1},
            "messages": MessageLocataireSerializer(loc.messages.all(), many=True).data,
        })

    @action(detail=True, methods=["post"])
    def repondre(self, request, pk=None):
        loc = get_object_or_404(Locataire, pk=pk)
        texte = (request.data.get("contenu") or "").strip()
        if not texte:
            raise RegleMetier("Le message est vide.")
        m = MessageLocataire.objects.create(locataire=loc, expediteur="agence", contenu=texte[:4000])
        journaliser(request, "Message locataire", f"Réponse à {loc.nom}")
        return Response(MessageLocataireSerializer(m).data, status=201)

    @action(detail=False)
    def compteur(self, request):
        return Response({"non_lus": MessageLocataire.objects.filter(expediteur="locataire", lu=False).count()})


class InterventionViewSet(viewsets.ModelViewSet):
    """Demandes d'intervention vues par l'agence : suivi, photos, clôture avec imputation du coût."""

    serializer_class = InterventionSerializer
    permission_classes = [SuppressionAdmin]
    filterset_fields = ["statut", "maison", "categorie", "urgente"]
    search_fields = ["titre", "description", "maison__designation", "locataire__nom", "artisan"]

    def get_queryset(self):
        return DemandeIntervention.objects.select_related("maison__bailleur", "locataire").prefetch_related("photos")

    def perform_create(self, ser):
        d = ser.save(origine="agence")
        rattacher_bail(d)
        d.save(update_fields=["contrat", "locataire"])
        journaliser(self.request, "Intervention", f"{d.reference} créée — {d.titre} ({d.maison.designation})")

    def perform_update(self, ser):
        statut = self.request.data.get("statut")
        if ser.instance.statut == "terminee" and statut and statut != "terminee":
            raise RegleMetier("Une intervention terminée ne peut plus changer de statut.")
        d = ser.save()
        if statut in ("nouvelle", "en_cours", "annulee") and statut != d.statut:
            d.statut = statut
            d.save(update_fields=["statut"])
        journaliser(self.request, "Intervention", f"{d.reference} mise à jour — {d.get_statut_display()}")

    @action(detail=False)
    def compteur(self, request):
        return Response({"nouvelles": DemandeIntervention.objects.filter(statut="nouvelle").count()})

    @action(detail=True, methods=["post"])
    def photos(self, request, pk=None):
        d = self.get_object()
        ajouter_photos(d, request.FILES.getlist("photos"))
        return Response(InterventionSerializer(d, context={"request": request}).data)

    @action(detail=True, methods=["post"])
    def cloturer(self, request, pk=None):
        d = cloturer(self.get_object(), request.data.get("cout"), request.data.get("imputation"), request)
        return Response(InterventionSerializer(d, context={"request": request}).data)
