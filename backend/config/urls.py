from django.conf import settings
from django.http import FileResponse, HttpResponse
from django.urls import include, path, re_path
from rest_framework.routers import DefaultRouter

from apps.comptes import views as comptes
from apps.core import views as core
from apps.finances import views as finances
from apps.locatif import views as locatif
from apps.patrimoine import views as patrimoine
from apps.connecteurs import views as connecteurs
from apps.portail import bailleur as portail_bailleur
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
router.register("interventions", relations.InterventionViewSet, basename="intervention")
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
    path("rapports/recap-fiscal/<int:pk>/", rapports.recap_fiscal),
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
    path("portail/preavis/", portail.donner_preavis),
    path("portail/interventions/", portail.interventions_locataire),
    path("portail/quittances/<int:pk>/", portail.quittance_locataire),
    path("connecteurs/", connecteurs.connecteurs),
    path("connecteurs/tester/", connecteurs.tester),
    path("connecteurs/envois/", connecteurs.envois),
    path("connecteurs/sauvegarde/", connecteurs.sauvegarde_en_ligne),
    path("connecteurs/paiements/", connecteurs.paiements),
    path("contrats/<int:pk>/signature/envoyer/", connecteurs.signature_envoyer),
    path("contrats/<int:pk>/signature/valider/", connecteurs.signature_valider),
    path("portail/paiements/", connecteurs.payer),
    path("portail/paiements/<str:reference>/", connecteurs.paiement_locataire),
    path("portail/paiements/<str:reference>/simuler/", connecteurs.simuler_paiement),
    path("portail/signature/", connecteurs.signer_locataire),
    path("public/paiements/notification/", connecteurs.notification),
    path("portail-bailleur/connexion/", portail_bailleur.connexion_bailleur),
    path("portail-bailleur/deconnexion/", portail_bailleur.deconnexion_bailleur),
    path("portail-bailleur/espace/", portail_bailleur.espace_bailleur),
    path("portail-bailleur/releve/", portail_bailleur.releve_bailleur),
    path("portail-bailleur/recap-fiscal/", portail_bailleur.recap_fiscal),
    path("portail-bailleur/quittances/<int:pk>/", portail_bailleur.quittance_bailleur),
    path("", include(router.urls)),
]


def _apercu_annonce(request, html):
    """Balises Open Graph d'une annonce (/nos-maisons/<id>) : titre, prix et photo dans l'aperçu WhatsApp / Facebook."""
    import re

    from django.utils.html import escape

    from apps.core.models import Parametres
    from apps.core.utils import fcfa
    from apps.patrimoine.models import Maison

    m = re.fullmatch(r"/nos-maisons/(\d+)/?", request.path)
    maison = Maison.objects.filter(pk=m.group(1), visible_site=True).prefetch_related("photos").first() if m else None
    if maison is None:
        return html
    photo = maison.photos.first()
    titre = f"{maison.designation} — {fcfa(maison.loyer)} / mois"
    texte = (maison.description or f"{maison.type_maison} à louer, {maison.adresse}").strip()[:200]
    balises = {
        "og:type": "website", "og:site_name": Parametres.charger().nom_entreprise, "og:title": titre, "og:description": texte,
        "og:url": request.build_absolute_uri(), "og:image": request.build_absolute_uri(photo.image.url) if photo else "",
    }
    tags = "".join(f'<meta property="{k}" content="{escape(v)}" />' for k, v in balises.items() if v)
    tags += f'<meta name="twitter:card" content="summary_large_image" /><meta name="description" content="{escape(texte)}" />'
    return html.replace("</head>", tags + "</head>", 1).replace("<title>BailManager</title>", f"<title>{escape(titre)}</title>", 1)


def application(request, *args, **kwargs):
    """Sert l'application React (build) pour toute URL qui n'est pas l'API."""
    index = settings.FRONTEND_BUILD_DIR / "index.html"
    if index.exists():
        if request.path.startswith("/nos-maisons/"):
            return HttpResponse(_apercu_annonce(request, index.read_text(encoding="utf-8")), content_type="text/html")
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
