"""Tests des règles de gestion (python manage.py test)."""
from datetime import date
from decimal import Decimal
from unittest import mock

from django.test import TestCase
from rest_framework.test import APIClient

from apps.comptes.models import Utilisateur
from apps.core.models import Parametres
from apps.finances.models import CompteCourantBailleur, MouvementCaisse
from apps.finances.services import solde_bailleur, solde_caisse
from apps.patrimoine.models import Bailleur, Locataire, MandatGestion, Maison

from . import services
from .models import Contrat, Encaissement

AUJ = date(2026, 6, 20)


def _auj():
    return AUJ


class ReglesMetierTests(TestCase):
    def setUp(self):
        for cible in ("apps.locatif.services.aujourdhui", "apps.patrimoine.models.aujourdhui", "apps.core.utils.aujourdhui",
                      "apps.locatif.serializers.aujourdhui", "apps.rapports.views.aujourdhui"):
            p = mock.patch(cible, _auj)
            p.start()
            self.addCleanup(p.stop)
        Parametres.charger()
        self.admin = Utilisateur.objects.create_user("admin@test.ci", "motdepasse1", nom_complet="Admin", role="admin")
        self.agent = Utilisateur.objects.create_user("agent@test.ci", "motdepasse1", nom_complet="Agent", role="agent")
        self.api = APIClient()
        self.api.force_authenticate(self.admin)
        self.b = Bailleur.objects.create(nom="Koné Ibrahim", telephone1="0707")
        self.m = Maison.objects.create(bailleur=self.b, designation="Villa A1", loyer=100000)
        self.l = Locataire.objects.create(nom="Yao Aya", telephone1="0505")

    def mandat(self, taux=10):
        return MandatGestion.objects.create(bailleur=self.b, date_signature=date(2026, 1, 1), date_debut=date(2026, 1, 1), taux_commission=taux)

    def contrat(self, **kw):
        r = self.api.post("/api/contrats/", {"maison": self.m.pk, "locataire": self.l.pk, "loyer_mensuel": 100000,
                                             "date_debut": "2026-03-05", "mois_avance": 2, "droit_agence": 100000, **kw}, format="json")
        return r

    def payer(self, c, montant, mois, annee=2026):
        return self.api.post("/api/encaissements/", {"contrat": c, "montant": montant, "periode_mois": mois, "periode_annee": annee}, format="json")

    def test_code_bailleur(self):
        self.assertRegex(self.b.code_bailleur, r"^BA-KO\d{4}-0001$")

    def test_mandat_obligatoire(self):
        r = self.contrat()
        self.assertEqual(r.status_code, 400)
        self.assertIn("mandat", r.data["detail"])
        r = self.api.post("/api/maisons/", {"bailleur": self.b.pk, "designation": "X", "loyer": 5000}, format="json")
        self.assertEqual(r.status_code, 400)
        # Pas non plus de rattachement d'un bien existant à un bailleur sans mandat
        self.mandat()
        autre = Bailleur.objects.create(nom="Sans mandat")
        r = self.api.patch(f"/api/maisons/{self.m.pk}/", {"bailleur": autre.pk}, format="json")
        self.assertEqual(r.status_code, 400)
        self.m.refresh_from_db()
        self.assertEqual(self.m.bailleur_id, self.b.pk)

    def test_creation_contrat_avance_frais_caution(self):
        self.mandat(taux=12)
        r = self.contrat()
        self.assertEqual(r.status_code, 201, r.data)
        c = Contrat.objects.get()
        self.assertEqual(c.depot_garantie, 200000)            # 2 mois de caution
        self.assertEqual(c.encaissements.count(), 2)          # avance = mars + avril
        self.assertEqual(c.date_prochain_loyer, date(2026, 5, 5))
        self.m.refresh_from_db()
        self.assertEqual(self.m.statut, "occupe")
        self.assertEqual(solde_bailleur(self.b), 2 * 88000)   # net du taux du mandat (12 %)
        self.assertEqual(solde_caisse(), 100000 + 2 * 12000)  # frais d'agence + commissions
        self.assertEqual(self.contrat().status_code, 400)     # maison déjà occupée

    def test_avance_limitee_a_deux_mois(self):
        self.mandat()
        self.assertEqual(self.contrat(mois_avance=3).status_code, 400)

    def test_dette_anterieure_et_paiement_partiel(self):
        self.mandat()
        cid = self.contrat().data["id"]
        r = self.payer(cid, 100000, 6)                         # mai non payé
        self.assertEqual(r.status_code, 400)
        self.assertIn("Mai 2026", r.data["detail"])
        self.assertEqual(self.payer(cid, 60000, 5).status_code, 201)
        c = Contrat.objects.get()
        self.assertEqual(c.date_prochain_loyer, date(2026, 5, 5))   # mai partiel : échéance inchangée
        self.assertEqual(self.payer(cid, 50000, 5).status_code, 400)  # surpaiement refusé
        self.assertEqual(self.payer(cid, 40000, 5).status_code, 201)
        c.refresh_from_db()
        self.assertEqual(c.date_prochain_loyer, date(2026, 6, 5))
        self.assertEqual(self.payer(cid, 1000, 1).status_code, 400)   # avant le début du bail

    def test_situation_et_impayes(self):
        self.mandat()
        cid = self.contrat().data["id"]
        r = self.api.get(f"/api/contrats/{cid}/situation/?mois=6&annee=2026")
        self.assertEqual(r.data["dette"]["libelle"], "Mai 2026")
        c = Contrat.objects.get()
        self.assertEqual([p.libelle for p in services.periodes_impayees(c)], ["Mai 2026", "Juin 2026"])
        r = self.api.get("/api/rapports/impayes/")
        self.assertEqual(r.data["totaux"]["montant"], 200000)

    def test_revision_ne_cree_pas_de_faux_impayes(self):
        self.mandat()
        cid = self.contrat().data["id"]
        self.payer(cid, 100000, 5)
        r = self.api.post(f"/api/contrats/{cid}/reviser/", {"nouveau_loyer": 120000, "motif": "Triennale", "date_effet": "2026-06-01"}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        c = Contrat.objects.get()
        self.assertEqual(services.loyer_pour_periode(c, 5, 2026), 100000)
        self.assertEqual(services.loyer_pour_periode(c, 6, 2026), 120000)
        self.assertEqual(c.date_prochain_loyer, date(2026, 6, 5))
        self.assertEqual(self.payer(cid, 120000, 6).status_code, 201)

    def test_etat_des_lieux_entree(self):
        self.mandat()
        cid = self.contrat().data["id"]
        url = f"/api/contrats/{cid}/etat-des-lieux/entree/"
        d = self.api.get(url).data
        self.assertFalse(d["etabli"])
        self.assertTrue(d["pieces"])                                            # pièces proposées selon le type
        pieces = d["pieces"]
        pieces[0]["elements"][0].update(etat="bon", observation="Carrelage propre")
        r = self.api.post(url, {"date_etat": "2026-03-05", "index_eau": "00412", "nombre_cles": 3, "pieces": pieces}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        self.assertTrue(r.data["etabli"])
        self.assertEqual(str(self.api.get(f"/api/contrats/{cid}/").data["etats_des_lieux"]["entree"]), "2026-03-05")
        # État inconnu refusé ; une seule fiche d'entrée par bail (mise à jour, pas de doublon)
        pieces[0]["elements"][0]["etat"] = "excellent"
        self.assertEqual(self.api.post(url, {"date_etat": "2026-03-05", "pieces": pieces}, format="json").status_code, 400)
        pieces[0]["elements"][0]["etat"] = "usage"
        self.api.post(url, {"date_etat": "2026-03-06", "pieces": pieces}, format="json")
        self.assertEqual(Contrat.objects.get(pk=cid).etats_des_lieux.count(), 1)

    def test_anciennete_du_bail(self):
        from apps.core.utils import duree_entre, libelle_duree

        self.assertEqual(duree_entre(date(2024, 1, 31), date(2024, 2, 29)), (0, 1, 0))       # fins de mois
        self.assertEqual(libelle_duree(*duree_entre(date(2023, 6, 15), date(2026, 10, 16))), "3 ans 4 mois")
        self.mandat()
        cid = self.contrat(date_contrat="2026-02-10").data["id"]                           # AUJ = 20/06/2026
        a = self.api.get(f"/api/contrats/{cid}/").data["anciennete"]
        self.assertEqual((a["annees"], a["mois"], a["jours"], a["libelle"], a["en_cours"]), (0, 4, 10, "4 mois 10 jours", True))
        # Bail terminé : l'ancienneté s'arrête à la résiliation
        self.api.post(f"/api/contrats/{cid}/resilier/", {"motif": "Départ"}, format="json")
        a = self.api.get(f"/api/contrats/{cid}/").data["anciennete"]
        self.assertEqual((a["libelle"], a["en_cours"]), ("4 mois 10 jours", False))

    def test_loyer_fixe_par_le_bien_et_revision(self):
        self.mandat()
        r = self.contrat(loyer_mensuel=120000)                                  # autre loyer que celui du bien : refusé
        self.assertEqual(r.status_code, 400)
        self.assertIn("révision", r.data["detail"])
        r = self.api.post("/api/contrats/", {"maison": self.m.pk, "locataire": self.l.pk, "date_debut": "2026-03-05", "mois_avance": 2}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        self.assertEqual(int(r.data["loyer_mensuel"]), 100000)                  # loyer repris du bien
        cid = r.data["id"]
        # Bien loué : le loyer ne se modifie plus depuis la fiche du bien
        self.assertEqual(self.api.patch(f"/api/maisons/{self.m.pk}/", {"loyer": 90000}, format="json").status_code, 400)
        # Révision à la demande du bailleur : contrat et bien passent au nouveau loyer
        r = self.api.post(f"/api/contrats/{cid}/reviser/", {"nouveau_loyer": 110000, "date_effet": "2026-07-01", "motif": "Travaux"}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        self.assertEqual(r.data["origine"], "bailleur")
        self.m.refresh_from_db()
        self.assertEqual(self.m.loyer, 110000)

    def test_duree_du_mandat(self):
        base = {"bailleur": self.b.pk, "date_signature": "2026-01-01", "date_debut": "2026-01-01", "taux_commission": 10}
        r = self.api.post("/api/mandats/", {**base, "duree": "determinee"}, format="json")
        self.assertEqual(r.status_code, 400)                                   # date de fin obligatoire
        r = self.api.post("/api/mandats/", {**base, "duree": "indeterminee", "date_fin": "2026-12-31", "tacite_reconduction": True}, format="json")
        self.assertEqual((r.data["date_fin"], r.data["tacite_reconduction"], r.data["echeance"]), (None, False, None))
        # Durée déterminée échue : plus en vigueur, sauf tacite reconduction (renouvelé pour la même durée)
        echu = MandatGestion.objects.create(bailleur=self.b, duree="determinee", date_signature=date(2025, 1, 1), date_debut=date(2025, 1, 1), date_fin=date(2025, 12, 31))
        self.assertFalse(echu.en_vigueur)
        echu.tacite_reconduction = True
        echu.save()
        self.assertTrue(echu.en_vigueur)
        self.assertEqual(echu.echeance(), date(2026, 12, 31))

    def test_information_des_locataires_et_mandat_echu(self):
        from apps.patrimoine.mandats import informer_locataires_echeance
        from apps.relations.models import MessageLocataire

        m = MandatGestion.objects.create(bailleur=self.b, duree="determinee", date_signature=date(2026, 1, 1), date_debut=date(2026, 1, 1), date_fin=date(2026, 7, 10))
        cid = self.contrat().data["id"]
        self.assertEqual(self.api.get(f"/api/contrats/{cid}/").data["mandat_echu"], False)
        self.assertEqual(informer_locataires_echeance(m), 1)
        self.assertEqual(informer_locataires_echeance(m), 0)                    # une seule fois par mandat
        self.assertIn("10/07/2026", MessageLocataire.objects.get(locataire=self.l).contenu)
        # Renouvellement : nouveau message de confirmation au locataire
        r = self.api.post("/api/mandats/", {"bailleur": self.b.pk, "duree": "indeterminee", "date_signature": "2026-06-20", "date_debut": "2026-06-20", "taux_commission": 10}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        self.assertEqual(MessageLocataire.objects.filter(locataire=self.l).count(), 2)
        # Mandat résilié : le bail en cours est signalé « mandat échu », l'encaissement reste possible
        self.api.post(f"/api/mandats/{r.data['id']}/resilier/", {"forcer": True}, format="json")
        self.assertEqual(self.api.get(f"/api/contrats/{cid}/").data["mandat_echu"], True)
        self.assertTrue(self.api.get(f"/api/contrats/{cid}/situation/").data["mandat_echu"])
        self.assertEqual(self.payer(cid, 1000, 5).status_code, 201)

    def test_bail_rattache_au_mandat(self):
        mandat = self.mandat()
        r = self.contrat()
        self.assertEqual(r.data["mandat"], mandat.pk)
        self.assertEqual(r.data["mandat_reference"], f"MG-{mandat.pk:04d}")
        imp = self.api.get(f"/api/contrats/{r.data['id']}/impression/").data
        self.assertEqual(imp["mandat_detail"]["reference"], mandat.reference)
        self.assertEqual(self.api.get(f"/api/mandats/{mandat.pk}/").data["nb_baux"], 1)

    def test_etiquette_site_selon_contrat(self):
        public = APIClient()
        etat = lambda: public.get(f"/api/public/maisons/{self.m.pk}/").data["occupee"]  # noqa: E731
        self.assertFalse(etat())
        self.mandat()
        cid = self.contrat().data["id"]
        self.assertTrue(etat())
        self.assertEqual([m["id"] for m in public.get("/api/public/maisons/", {"etat": "occupee"}).data], [self.m.pk])
        self.assertEqual(public.get("/api/public/vitrine/").data["nb_disponibles"], 0)
        self.api.post(f"/api/contrats/{cid}/resilier/", {"motif": "Départ"}, format="json")
        self.assertFalse(etat())

    def test_resiliation_avec_retenues(self):
        self.mandat()
        cid = self.contrat().data["id"]
        caisse_avant = solde_caisse()
        bailleur_avant = solde_bailleur(self.b)
        r = self.api.post(f"/api/contrats/{cid}/resilier/", {"retenue_reparation": 30000, "retenue_loyer": 150000, "motif": "Départ"}, format="json")
        self.assertEqual(r.status_code, 200, r.data)
        self.assertEqual(r.data["restitue"], 20000)
        self.assertEqual(len(r.data["quittances"]), 2)             # mai (100 000) + juin (50 000)
        c = Contrat.objects.get()
        self.assertEqual(c.statut_contrat, "termine")
        self.assertEqual(services.caution_disponible(c), 0)
        self.m.refresh_from_db()
        self.assertEqual(self.m.statut, "disponible")
        self.assertEqual(solde_caisse() - caisse_avant, 30000 + 15000)    # réparations + commissions
        self.assertEqual(solde_bailleur(self.b) - bailleur_avant, 135000)
        self.assertEqual(self.payer(cid, 1000, 7).status_code, 400)

    def test_retenue_superieure_a_la_caution(self):
        self.mandat()
        cid = self.contrat().data["id"]
        r = self.api.post(f"/api/contrats/{cid}/caution/", {"retenue_reparation": 250000}, format="json")
        self.assertEqual(r.status_code, 400)

    def test_versement_bailleur_plafonne(self):
        self.mandat()
        self.contrat()
        r = self.api.post("/api/compte-bailleurs/verser/", {"bailleur": self.b.pk, "montant": 999999}, format="json")
        self.assertEqual(r.status_code, 400)
        r = self.api.post("/api/compte-bailleurs/verser/", {"bailleur": self.b.pk, "montant": 50000}, format="json")
        self.assertEqual(r.status_code, 201)
        self.assertEqual(solde_bailleur(self.b), 180000 - 50000)

    def test_droits_agent(self):
        self.mandat()
        cid = self.contrat().data["id"]
        agent = APIClient()
        agent.force_authenticate(self.agent)
        self.assertEqual(agent.post(f"/api/contrats/{cid}/resilier/", {}, format="json").status_code, 403)
        self.assertEqual(agent.delete(f"/api/locataires/{self.l.pk}/").status_code, 403)
        self.assertEqual(agent.get("/api/journal/").status_code, 403)
        self.assertEqual(agent.get("/api/tableau-de-bord/").status_code, 200)

    def test_tableau_de_bord_et_rapports(self):
        self.mandat()
        self.contrat()
        r = self.api.get("/api/tableau-de-bord/")
        self.assertEqual(r.status_code, 200)
        self.assertEqual(r.data["kpi"]["contrats_en_retard"], 1)
        self.assertEqual(r.data["mois_courant"]["lignes"][0]["statut"], "en_retard")
        for url in ("/api/rapports/journalier/", "/api/rapports/mensuel/", "/api/rapports/annuel/", "/api/rapports/occupation/",
                    f"/api/rapports/releve-bailleur/{self.b.pk}/", "/api/calendrier/", "/api/recherche/?q=Yao", "/api/cautions/",
                    "/api/cautions/synthese/", "/api/caisse/synthese/", "/api/compte-bailleurs/synthese/", "/api/bailleurs/", "/api/locataires/",
                    "/api/maisons/", f"/api/contrats/{Contrat.objects.get().pk}/", f"/api/contrats/{Contrat.objects.get().pk}/impression/",
                    f"/api/encaissements/{Encaissement.objects.first().pk}/quittance/"):
            self.assertEqual(self.api.get(url).status_code, 200, url)
        r = self.api.post("/api/export/excel/", {"titre": "Test", "colonnes": [{"cle": "a", "libelle": "A", "type": "montant"}], "lignes": [{"a": 5}]}, format="json")
        self.assertEqual(r.status_code, 200)


def _png(nom="photo.png"):
    import io

    from django.core.files.uploadedfile import SimpleUploadedFile
    from PIL import Image

    tampon = io.BytesIO()
    Image.new("RGB", (8, 6), (30, 60, 90)).save(tampon, "PNG")
    return SimpleUploadedFile(nom, tampon.getvalue(), content_type="image/png")


class PhotosMaisonTests(TestCase):
    def setUp(self):
        import shutil
        import tempfile

        from django.test import override_settings

        dossier = tempfile.mkdtemp()
        self.addCleanup(shutil.rmtree, dossier, True)
        reglage = override_settings(MEDIA_ROOT=dossier)
        reglage.enable()
        self.addCleanup(reglage.disable)
        b = Bailleur.objects.create(nom="B")
        self.m = Maison.objects.create(bailleur=b, designation="Villa", loyer=90000)
        self.api = APIClient()
        self.api.force_authenticate(Utilisateur.objects.create_user("agent@t.ci", "motdepasse1", nom_complet="Agent", role="agent"))

    def test_ajout_principale_suppression(self):
        url = f"/api/maisons/{self.m.pk}/photos/"
        r = self.api.post(url, {"photos": [_png("a.png"), _png("b.png"), _png("c.png")]}, format="multipart")
        self.assertEqual(r.status_code, 200, r.data)
        ids = [p["id"] for p in r.data["photos"]]
        self.assertEqual(len(ids), 3)
        self.assertEqual(r.data["photo"], r.data["photos"][0]["url"])
        r = self.api.post(f"{url}{ids[2]}/principale/")
        self.assertEqual([p["id"] for p in r.data["photos"]], [ids[2], ids[0], ids[1]])
        r = self.api.post(f"{url}{ids[0]}/supprimer/")             # un agent peut retirer une photo
        self.assertEqual([p["id"] for p in r.data["photos"]], [ids[2], ids[1]])
        self.assertEqual(len(APIClient().get(f"/api/public/maisons/{self.m.pk}/").data["images"]), 2)

    def test_fichier_non_image_refuse(self):
        from django.core.files.uploadedfile import SimpleUploadedFile

        faux = SimpleUploadedFile("x.png", b"pas une image", content_type="image/png")
        r = self.api.post(f"/api/maisons/{self.m.pk}/photos/", {"photos": [faux]}, format="multipart")
        self.assertEqual(r.status_code, 400)
        self.assertFalse(self.m.photos.exists())


class PortailTests(TestCase):
    def test_connexion_locataire_et_espace(self):
        b = Bailleur.objects.create(nom="B")
        MandatGestion.objects.create(bailleur=b, date_signature=date(2026, 1, 1), date_debut=date(2026, 1, 1))
        m = Maison.objects.create(bailleur=b, designation="Studio", loyer=50000)
        l = Locataire.objects.create(nom="Loc", telephone1="0101010101")
        admin = Utilisateur.objects.create_user("a@a.ci", "motdepasse1", nom_complet="A", role="admin")
        api = APIClient()
        api.force_authenticate(admin)
        api.post("/api/contrats/", {"maison": m.pk, "locataire": l.pk, "loyer_mensuel": 50000, "date_debut": "2026-01-01"}, format="json")
        code = api.post(f"/api/locataires/{l.pk}/generer-code/").data["code"]
        public = APIClient()
        self.assertEqual(public.post("/api/portail/connexion/", {"telephone": "0101010101", "code": "FAUX"}, format="json").status_code, 400)
        self.assertEqual(public.post("/api/portail/connexion/", {"telephone": "01 01 01 01 01", "code": code}, format="json").status_code, 200)
        r = public.get("/api/portail/espace/")
        self.assertEqual(r.status_code, 200)
        self.assertEqual(len(r.data["paiements"]), 2)
        self.assertEqual(public.post("/api/portail/messages/", {"contenu": "Bonjour"}, format="json").status_code, 201)
        self.assertEqual(public.get("/api/public/maisons/").status_code, 200)
