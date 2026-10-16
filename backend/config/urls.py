from django.conf import settings
from django.http import FileResponse, HttpResponse
from django.urls import include, path, re_path
from rest_framework.routers import DefaultRouter

from apps.comptes import views as comptes
from apps.core import views as core
from apps.finances import views as finances
from apps.locatif import views as locatif
from apps.patrimoine import views as patrimoine
from apps.portail import views as portail
from apps.rapports import views as rapports
from apps.relations import views as relations

router = DefaultRouter(trailing_slash=True)
router.register("utilisateurs", comptes.UtilisateurViewSet, basename="utilisateur")
router.register("bailleurs", patrimoine.BailleurViewSet, basename="bailleur")
router.register("mandats", patrimoine.MandatViewSet, basename="mandat")
router.register("maisons", patrimoine.MaisonViewSet, basename="maison")
router.register("locataires", patrimoine.LocataireViewSet, basename="locataire")
router.register("documents", patrimoine.DocumentViewSet, basename="document")
router.register("contrats", locatif.ContratViewSet, basename="contrat")
router.register("encaissements", locatif.EncaissementViewSet, basename="encaissement")
router.register("charges", locatif.ChargeViewSet, basename="charge")
router.register("revisions", locatif.RevisionViewSet, basename="revision")
router.register("cautions", locatif.CautionViewSet, basename="caution")
router.register("compte-bailleurs", finances.CompteBailleurViewSet, basename="compte-bailleur")
router.register("caisse", finances.CaisseViewSet, basename="caisse")
router.register("reservations", relations.ReservationViewSet, basename="reservation")
router.register("messages", relations.MessageVisiteurViewSet, basename="message")
router.register("messagerie", relations.MessagerieLocataireViewSet, basename="messagerie")
router.register("clauses-contrat", core.ClauseContratViewSet, basename="clause-contrat")
router.register("clauses-mandat", core.ClauseMandatViewSet, basename="clause-mandat")
router.register("journal", core.JournalViewSet, basename="journal")

api = [
    path("auth/session/", comptes.session),
    path("auth/connexion/", comptes.connexion),
    path("auth/deconnexion/", comptes.deconnexion),
    path("auth/profil/", comptes.profil),
    path("auth/mot-de-passe/", comptes.changer_mot_de_passe),
    path("auth/mot-de-passe-oublie/", comptes.mot_de_passe_oublie),
    path("auth/reinitialiser/", comptes.reinitialiser_mot_de_passe),
    path("parametres/", core.parametres),
    path("sauvegarde/", core.sauvegarde),
    path("tableau-de-bord/", rapports.tableau_de_bord),
    path("rapports/mois-paiements/", rapports.mois_paiements),
    path("rapports/journalier/", rapports.rapport_journalier),
    path("rapports/periode/", rapports.rapport_periode),
    path("rapports/mensuel/", rapports.rapport_mensuel),
    path("rapports/annuel/", rapports.rapport_annuel),
    path("rapports/occupation/", rapports.etat_occupation),
    path("rapports/impayes/", rapports.liste_impayes),
    path("rapports/releve-bailleur/<int:pk>/", rapports.releve_bailleur),
    path("calendrier/", rapports.calendrier),
    path("recherche/", rapports.recherche),
    path("export/excel/", rapports.export_excel),
    # Site public & espace locataire
    path("public/vitrine/", portail.vitrine),
    path("public/maisons/", portail.maisons_publiques),
    path("public/maisons/<int:pk>/", portail.maison_publique),
    path("public/reservations/", portail.reserver),
    path("public/contact/", portail.contact),
    path("portail/connexion/", portail.connexion_locataire),
    path("portail/deconnexion/", portail.deconnexion_locataire),
    path("portail/espace/", portail.espace),
    path("portail/messages/", portail.envoyer_message),
    path("portail/quittances/<int:pk>/", portail.quittance_locataire),
    path("", include(router.urls)),
]


def application(request, *args, **kwargs):
    """Sert l'application React (build) pour toute URL qui n'est pas l'API."""
    index = settings.FRONTEND_BUILD_DIR / "index.html"
    if index.exists():
        return FileResponse(open(index, "rb"), content_type="text/html")
    return HttpResponse(
        "<h2>BailManager — API en ligne</h2><p>L'interface n'est pas encore construite : "
        "lancez <code>npm run build</code> dans le dossier frontend.</p>",
    )


urlpatterns = [
    path("api/", include(api)),
    re_path(r"^media/(?P<chemin>.+)$", core.media),
    re_path(r"^(?!api/|media/|static/).*$", application),
]
