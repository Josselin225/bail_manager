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
        import shutil
        import tempfile

        from django.test import override_settings

        dossier = tempfile.mkdtemp()  # fichiers envoyés pendant les tests (signatures, photos) : jamais dans uploads/
        self.addCleanup(shutil.rmtree, dossier, True)
        reglage = override_settings(MEDIA_ROOT=dossier)
        reglage.enable()
        self.addCleanup(reglage.disable)
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

    def test_relances_et_plan_apurement(self):
        self.mandat()
        cid = self.contrat().data["id"]                    # début 05/03/2026, avance mars-avril ; AUJ = 20/06/2026
        url = f"/api/contrats/{cid}/relances/"
        self.assertTrue(self.api.get(url).data["situation"]["montant"] > 0)
        niveaux = [self.api.post(url).data["relances"][0]["niveau"] for _ in range(4)]
        self.assertEqual(niveaux, [1, 2, 3, 3])            # rappel, relance, mise en demeure (plafonnée)
        self.assertEqual(len(self.api.get("/api/contrats/recouvrement/").data), 1)
        # Plan d'apurement en 3 échéances, puis un paiement couvre la première
        dette = self.api.get(url).data["situation"]["montant"]
        r = self.api.post(f"/api/contrats/{cid}/plan-apurement/", {"nb_echeances": 3, "premiere_echeance": "2026-07-01"}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        plan = r.data["plans"][0]
        self.assertEqual(sum(e["montant"] for e in plan["echeances"]), dette)
        self.assertEqual(self.api.post(f"/api/contrats/{cid}/plan-apurement/", {"nb_echeances": 1, "premiere_echeance": "2026-07-01"}, format="json").status_code, 400)
        self.payer(cid, plan["echeances"][0]["montant"], 5)
        statuts = [e["statut"] for e in self.api.get(url).data["plans"][0]["echeances"]]
        self.assertEqual(statuts[0], "honoree")

    def test_interventions(self):
        from apps.finances.services import solde_bailleur

        self.mandat()
        cid = self.contrat().data["id"]
        r = self.api.post("/api/interventions/", {"maison": self.m.pk, "titre": "Fuite sous l'évier", "categorie": "plomberie", "urgente": True}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        self.assertEqual((r.data["contrat"], r.data["locataire"], r.data["statut"]), (cid, self.l.pk, "nouvelle"))   # rattachée au bail en cours
        iid = r.data["id"]
        self.assertEqual(self.api.patch(f"/api/interventions/{iid}/", {"statut": "en_cours", "artisan": "Koffi plombier"}, format="json").data["statut"], "en_cours")
        avant = solde_bailleur(self.b)
        self.assertEqual(self.api.post(f"/api/interventions/{iid}/cloturer/", {"cout": 15000}, format="json").status_code, 400)  # imputation exigée
        r = self.api.post(f"/api/interventions/{iid}/cloturer/", {"cout": 15000, "imputation": "bailleur"}, format="json")
        self.assertEqual(r.data["statut"], "terminee")
        self.assertEqual(solde_bailleur(self.b) - avant, -15000)                          # débitée du compte du bailleur
        self.assertEqual(self.api.patch(f"/api/interventions/{iid}/", {"statut": "en_cours"}, format="json").status_code, 400)
        # Imputation au locataire : ajoutée à ses charges
        iid2 = self.api.post("/api/interventions/", {"maison": self.m.pk, "titre": "Vitre cassée"}, format="json").data["id"]
        self.api.post(f"/api/interventions/{iid2}/cloturer/", {"cout": 8000, "imputation": "locataire"}, format="json")
        self.assertTrue(Contrat.objects.get(pk=cid).charges.filter(type_charge="Réparation", montant=8000).exists())

    def test_preavis_et_etat_des_lieux_de_sortie(self):
        self.mandat()
        cid = self.contrat().data["id"]
        r = self.api.post(f"/api/contrats/{cid}/preavis/", {"par": "locataire", "date_notification": "2026-06-20", "motif": "Mutation"}, format="json")
        self.assertEqual(str(r.data["date_sortie_prevue"]), "2026-09-20")             # trois mois après la notification
        self.assertEqual(self.api.post(f"/api/contrats/{cid}/preavis/", {"par": "locataire"}, format="json").status_code, 400)
        # Entrée : sol en bon état ; sortie : sol en mauvais état → dégradation détectée
        url = f"/api/contrats/{cid}/etat-des-lieux/"
        pieces = self.api.get(url + "entree/").data["pieces"]
        pieces[0]["elements"][0]["etat"] = "bon"
        self.api.post(url + "entree/", {"date_etat": "2026-03-05", "pieces": pieces}, format="json")
        sortie = self.api.get(url + "sortie/").data
        self.assertEqual(sortie["pieces"][0]["elements"][0]["etat_entree"], "bon")      # grille reprise de l'entrée
        # Photos : impossibles tant que la sortie n'est pas enregistrée
        self.assertEqual(self.api.post(url + "sortie/photos/", {"piece": "Salon"}, format="multipart").status_code, 400)
        sortie["pieces"][0]["elements"][0].update(etat="mauvais", observation="Carreaux cassés")
        r = self.api.post(url + "sortie/", {"date_etat": "2026-09-20", "pieces": sortie["pieces"]}, format="json")
        self.assertEqual([(d["element"], d["etat_entree"], d["etat_sortie"]) for d in r.data["degradations"]], [(pieces[0]["elements"][0]["nom"], "bon", "mauvais")])
        # Annulation du préavis (administrateur)
        self.assertIsNone(self.api.post(f"/api/contrats/{cid}/annuler-preavis/").data["date_sortie_prevue"])

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


    def test_connecteurs_mode_test(self):
        from apps.connecteurs.models import Connecteurs, MessageSortant, PaiementEnLigne

        self.mandat()
        self.contrat()
        c = Contrat.objects.get()
        # Réglages : les secrets ne ressortent jamais, un champ vide ne les efface pas
        self.api.patch("/api/connecteurs/", {"sms_secret": "TRES-SECRET", "envoi_relances": True}, format="json")
        r = self.api.patch("/api/connecteurs/", {"sms_secret": "", "sms_expediteur": "BAILMGR"}, format="json")
        self.assertNotIn("sms_secret", r.data)
        self.assertTrue(r.data["secrets_renseignes"]["sms_secret"])
        self.assertEqual(Connecteurs.charger().sms_secret, "TRES-SECRET")
        self.assertNotIn("TRES-SECRET", str(self.api.get("/api/parametres/").data))
        self.api.force_authenticate(self.agent)
        self.assertEqual(self.api.get("/api/connecteurs/").status_code, 403)
        self.api.force_authenticate(self.admin)
        # Relance : le SMS est simulé et journalisé, rien ne sort
        with self.captureOnCommitCallbacks(execute=True):
            self.api.post(f"/api/contrats/{c.pk}/relances/")
        m = MessageSortant.objects.get(objet="Rappel amiable")
        self.assertEqual(m.statut, "simule")
        self.assertIn("200 000", m.texte)
        # Signature par code : mauvais code refusé, bon code accepté
        r = self.api.post(f"/api/contrats/{c.pk}/signature/envoyer/")
        code = r.data["code_test"]
        self.assertEqual(len(code), 6)
        self.assertEqual(self.api.post(f"/api/contrats/{c.pk}/signature/valider/", {"code": "000000" if code != "000000" else "111111"}, format="json").status_code, 400)
        r = self.api.post(f"/api/contrats/{c.pk}/signature/valider/", {"code": code}, format="json")
        self.assertEqual(r.status_code, 200, r.data)
        self.assertIsNotNone(self.api.get(f"/api/contrats/{c.pk}/").data["signature_electronique"]["signe_le"])
        self.assertEqual(self.api.post(f"/api/contrats/{c.pk}/signature/envoyer/").status_code, 400)  # déjà signé
        # Paiement Mobile Money simulé depuis l'espace locataire : encaissement créé automatiquement
        self.l.telephone1 = "0505050505"
        self.l.save()
        code = self.api.post(f"/api/locataires/{self.l.pk}/generer-code/").data["code"]
        loc = APIClient()
        loc.post("/api/portail/connexion/", {"telephone": "0505050505", "code": code}, format="json")
        espace = loc.get("/api/portail/espace/").data
        self.assertEqual((espace["paiement_en_ligne"]["periode"], espace["paiement_en_ligne"]["montant"]), ("Mai 2026", 100000))
        r = loc.post("/api/portail/paiements/", {"operateur": "wave"}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        ref = r.data["reference"]
        self.assertEqual(r.data["url"], f"/paiement-test/{ref}")
        with self.captureOnCommitCallbacks(execute=True):
            r = loc.post(f"/api/portail/paiements/{ref}/simuler/", {"resultat": "reussi"}, format="json")
        self.assertEqual(r.data["statut"], "reussi")
        enc = PaiementEnLigne.objects.get(reference=ref).encaissement
        self.assertEqual((enc.periode_mois, enc.montant_recu, enc.mode_paiement), (5, 100000, "mobile_money"))
        self.assertTrue(MessageSortant.objects.filter(objet="Confirmation de paiement", texte__contains=enc.reference_recu).exists())
        # Un second « succès » ne crée pas de doublon ; un vrai fournisseur ne peut pas être simulé
        loc.post(f"/api/portail/paiements/{ref}/simuler/", {"resultat": "reussi"}, format="json")
        self.assertEqual(Encaissement.objects.filter(contrat=c, periode_mois=5).count(), 1)
        PaiementEnLigne.objects.filter(reference=ref).update(fournisseur="cinetpay", statut="en_attente")
        self.assertEqual(loc.post(f"/api/portail/paiements/{ref}/simuler/", {"resultat": "reussi"}, format="json").status_code, 400)


    def test_recapitulatif_fiscal_et_sauvegarde_chiffree(self):
        import tempfile
        import zipfile
        from pathlib import Path

        from apps.connecteurs.models import Connecteurs
        from apps.core.exceptions import RegleMetier
        from apps.core.sauvegarde import chiffrer, dechiffrer

        self.mandat(taux=10)
        self.contrat()
        r = self.api.get(f"/api/rapports/recap-fiscal/{self.b.pk}/", {"annee": 2026})
        self.assertEqual(r.status_code, 200)
        t = r.data["totaux"]
        self.assertEqual((t["brut"], t["commission"], t["net"]), (200000, 20000, 180000))   # avance de 2 mois
        self.assertEqual(r.data["biens"][0]["mois_loues"], 4)                               # mars → juin (aujourd'hui)
        self.assertEqual(self.api.get(f"/api/rapports/recap-fiscal/{self.b.pk}/", {"annee": 2025}).data["biens"], [])
        # Chiffrement : aller-retour, mauvaise phrase refusée, phrase trop courte refusée
        donnees = b"PK donnees sensibles"
        c = chiffrer(donnees, "une phrase assez longue")
        self.assertNotIn(b"sensibles", c)
        self.assertEqual(dechiffrer(c, "une phrase assez longue"), donnees)
        with self.assertRaises(RegleMetier):
            dechiffrer(c, "mauvaise phrase longue")
        with self.assertRaises(RegleMetier):
            chiffrer(donnees, "courte")
        # Copie chiffrée vers un dossier, puis restauration de l'archive
        with tempfile.TemporaryDirectory() as dossier:
            cfg = Connecteurs.charger()
            cfg.sauvegarde_destination, cfg.sauvegarde_dossier, cfg.sauvegarde_phrase, cfg.sauvegarde_garder = "dossier", dossier, "phrase de test bien longue", 1
            cfg.save()
            r = self.api.post("/api/connecteurs/sauvegarde/")
            self.assertEqual(r.status_code, 200, r.data)
            self.assertNotIn("phrase de test", str(self.api.get("/api/connecteurs/").data))
            fichiers = list(Path(dossier).glob("*.zip.chiffre"))
            self.assertEqual(len(fichiers), 1)
            archive = dechiffrer(fichiers[0].read_bytes(), "phrase de test bien longue")
            with zipfile.ZipFile(__import__("io").BytesIO(archive)) as z:
                self.assertIn("Koné Ibrahim", z.read("donnees.json").decode())
            cfg.sauvegarde_dossier = str(Path(dossier) / "absent")
            cfg.save()
            self.assertEqual(self.api.post("/api/connecteurs/sauvegarde/").status_code, 400)
            self.assertTrue(Connecteurs.charger().derniere_sauvegarde_statut.startswith("Échec"))
        # Aperçu des annonces partagées (balises Open Graph)
        from django.test import RequestFactory

        from config.urls import _apercu_annonce

        html = _apercu_annonce(RequestFactory().get(f"/nos-maisons/{self.m.pk}"), "<html><head><title>BailManager</title></head></html>")
        self.assertIn('property="og:title" content="Villa A1', html)


    def test_clauses_et_biens_figes_secrets_et_documents(self):
        from django.core.files.uploadedfile import SimpleUploadedFile
        from django.db import connection

        from apps.connecteurs.models import Connecteurs
        from apps.core.models import ClauseContrat, ClauseMandat

        ClauseMandat.objects.create(titre="Gestion", contenu="Version signée du mandat")
        ClauseContrat.objects.create(titre="Usage", contenu="Version signée du bail")
        r = self.api.post("/api/mandats/", {"bailleur": self.b.pk, "date_signature": "2026-01-01", "date_debut": "2026-01-01", "taux_commission": 10}, format="json")
        mandat = r.data["id"]
        self.contrat()
        c = Contrat.objects.get()
        # Les clauses types changent après la signature : bail et mandat gardent leur version
        ClauseMandat.objects.update(contenu="Nouvelle version")
        ClauseContrat.objects.update(contenu="Nouvelle version")
        m = self.api.get(f"/api/mandats/{mandat}/").data
        self.assertIn("Version signée du mandat", [x["contenu"] for x in m["clauses"]])
        self.assertIn("Version signée du bail", [x["contenu"] for x in self.api.get(f"/api/contrats/{c.pk}/impression/").data["clauses"]])
        # Biens confiés : le bien existant au mandat, puis un bien ajouté ensuite, daté
        self.assertEqual([b["designation"] for b in m["biens_confies"]], ["Villa A1"])
        self.api.post("/api/maisons/", {"bailleur": self.b.pk, "designation": "Studio B2", "loyer": 40000}, format="json")
        biens = self.api.get(f"/api/mandats/{mandat}/").data["biens_confies"]
        self.assertEqual([(b["designation"], str(b["confie_le"])) for b in biens], [("Villa A1", "2026-01-01"), ("Studio B2", "2026-06-20")])
        # Un renouvellement reprend les biens, l'ancien mandat garde sa liste
        r = self.api.post("/api/mandats/", {"bailleur": self.b.pk, "date_signature": "2026-06-20", "date_debut": "2026-06-20", "taux_commission": 9}, format="json")
        self.assertEqual(len(self.api.get(f"/api/mandats/{r.data['id']}/").data["biens_confies"]), 2)
        self.assertEqual(len(self.api.get(f"/api/mandats/{mandat}/").data["biens_confies"]), 2)
        # Secrets chiffrés en base, lisibles par le logiciel
        cfg = Connecteurs.charger()
        cfg.paiement_cle = "cle-api-tres-secrete"
        cfg.save()
        with connection.cursor() as cur:
            cur.execute("select paiement_cle from connecteurs")
            brut = cur.fetchone()[0]
        self.assertTrue(brut.startswith("enc1:"))
        self.assertNotIn("tres-secrete", brut)
        self.assertEqual(Connecteurs.charger().paiement_cle, "cle-api-tres-secrete")
        # Document joint : le contenu fait foi, pas l'extension
        faux = SimpleUploadedFile("contrat.pdf", b"MZ\x90\x00 programme", content_type="application/pdf")
        r = self.api.post("/api/documents/", {"entity_type": "bailleur", "entity_id": self.b.pk, "fichier": faux}, format="multipart")
        self.assertEqual(r.status_code, 400)
        vrai = SimpleUploadedFile("scan.pdf", b"%PDF-1.4\n%fin", content_type="application/pdf")
        r = self.api.post("/api/documents/", {"entity_type": "bailleur", "entity_id": self.b.pk, "fichier": vrai}, format="multipart")
        self.assertEqual(r.status_code, 201, r.data)


    def test_rapprochement_releve(self):
        from django.core.files.uploadedfile import SimpleUploadedFile

        self.mandat()
        self.l.telephone1 = "0505050505"
        self.l.save()
        self.contrat()  # avance mars-avril : mai et juin sont dus (100 000 chacun)
        releve = (
            "Relevé Wave - Agence\n"
            "Date;Expéditeur;Description;Montant;ID transaction\n"
            "05/06/2026 10:12;+225 05 05 05 05 05;Paiement loyer;150 000;T1\n"
            "06/06/2026;0707000000;Inconnu;25 000;T2\n"
            "07/06/2026;0707000001;Frais;-500;T3\n"
        ).encode("cp1252")
        envoi = lambda: SimpleUploadedFile("wave.csv", releve, content_type="text/csv")  # noqa: E731
        r = self.api.post("/api/rapprochement/analyser/", {"fichier": envoi()}, format="multipart")
        self.assertEqual(r.status_code, 200, r.data)
        self.assertEqual(r.data["colonnes"], {"date": 0, "montant": 3, "telephone": 1, "libelle": 2, "reference": 4})
        l1, l2, l3 = r.data["lignes"]
        self.assertEqual((l1["etat"], l1["contrat"], l1["montant"]), ("probable", Contrat.objects.get().pk, 150000))
        self.assertEqual((l2["etat"], l3["etat"]), ("a_affecter", "ignoree"))
        # 150 000 réglés : mai soldé (100 000) puis 50 000 sur juin
        ligne = {k: str(v) if k in ("date", "montant") else v for k, v in l1.items()}
        r = self.api.post("/api/rapprochement/encaisser/", {"lignes": [ligne], "mode": "mobile_money", "source": "Wave"}, format="json")
        res = r.data["resultats"][0]
        self.assertTrue(res["ok"], res)
        self.assertEqual([x["periode"] for x in res["recus"]], ["Mai 2026", "Juin 2026"])
        self.assertEqual(services.total_paye(Contrat.objects.get(), 6, 2026), 50000)
        # Le même relevé réimporté : la ligne est reconnue, rien n'est encaissé deux fois
        r = self.api.post("/api/rapprochement/analyser/", {"fichier": envoi()}, format="multipart")
        self.assertEqual(r.data["lignes"][0]["etat"], "deja")
        r = self.api.post("/api/rapprochement/encaisser/", {"lignes": [ligne], "mode": "mobile_money", "source": "Wave"}, format="json")
        self.assertFalse(r.data["resultats"][0]["ok"])
        self.assertEqual(Encaissement.objects.filter(contrat__locataire=self.l).count(), 4)


    def test_compteur_partage(self):
        from apps.locatif.models import ChargeLocative

        self.mandat()
        self.contrat()
        c = Contrat.objects.get()
        url = f"/api/contrats/{c.pk}/etat-des-lieux/entree/"
        r = self.api.post(url, {"date_etat": "2026-03-05", "index_eau": "100", "nombre_cles": 2, "pieces": self.api.get(url).data["pieces"]}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        vide = Maison.objects.create(bailleur=self.b, designation="Studio vide", loyer=40000)
        r = self.api.post("/api/compteurs/", {"nom": "Cour A1", "type_charge": "Eau", "mode": "index", "maisons": [self.m.pk, vide.pk]}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        cid = r.data["id"]
        prep = {x["designation"]: x for x in self.api.get(f"/api/compteurs/{cid}/preparation/", {"date": "2026-06-10"}).data}
        self.assertEqual(prep["Villa A1"]["index_precedent"], 100)       # repris de l'état des lieux d'entrée
        self.assertIsNone(prep["Studio vide"]["contrat"])
        solde = solde_bailleur(self.b)
        # 30 m³ chez le locataire, 10 m³ dans le logement vide : 30 001 FCFA répartis 3/4 - 1/4
        r = self.api.post(f"/api/compteurs/{cid}/factures/", {"date_facture": "2026-06-10", "montant": 30001, "numero": "F-1", "lignes": [
            {"maison": self.m.pk, "index_actuel": "130"}, {"maison": vide.pk, "index_precedent": "50", "index_actuel": "60"}]}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        parts = {l["maison"]: l["montant"] for l in r.data["lignes"]}
        self.assertEqual(parts, {"Villa A1": 22501, "Studio vide": 7500})
        self.assertEqual(sum(parts.values()), 30001)
        self.assertEqual(ChargeLocative.objects.get(contrat=c).montant, 22501)
        self.assertEqual(solde_bailleur(self.b), solde - 7500)            # part du logement vide au propriétaire
        # Facture suivante : l'index précédent est le dernier relevé
        prep = {x["designation"]: x for x in self.api.get(f"/api/compteurs/{cid}/preparation/").data}
        self.assertEqual(prep["Villa A1"]["index_precedent"], 130)
        r = self.api.post(f"/api/compteurs/{cid}/factures/", {"date_facture": "2026-06-15", "montant": 1000, "lignes": [{"maison": self.m.pk, "index_actuel": "120"}]}, format="json")
        self.assertEqual(r.status_code, 400)                              # index en baisse refusé
        # À parts égales : seul le logement occupé paie
        self.api.patch(f"/api/compteurs/{cid}/", {"mode": "egal"}, format="json")
        r = self.api.post(f"/api/compteurs/{cid}/factures/", {"date_facture": "2026-06-15", "montant": 9000}, format="json")
        self.assertEqual({l["maison"]: l["montant"] for l in r.data["lignes"]}, {"Villa A1": 9000, "Studio vide": 0})


    def test_agenda_des_visites_et_signature_etat_des_lieux(self):
        import base64
        import io

        from PIL import Image

        self.mandat()
        # Visite prise au téléphone, confirmée d'office, puis attribuée et faite
        r = self.api.post("/api/reservations/", {"maison": self.m.pk, "nom_visiteur": "Koffi", "tel_visiteur": "0101", "date_visite": "2026-06-22",
                                                 "heure_visite": "10:30", "origine": "telephone"}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        self.assertEqual((r.data["statut"], r.data["origine"]), ("confirme", "telephone"))
        v = r.data["id"]
        self.api.patch(f"/api/reservations/{v}/", {"agent": self.agent.pk}, format="json")
        agenda = self.api.get("/api/reservations/agenda/").data
        self.assertEqual([(x["nom_visiteur"], x["heure_visite"], x["agent_nom"]) for x in agenda], [("Koffi", "10:30:00", "Agent")])
        self.api.force_authenticate(self.agent)
        self.assertEqual(len(self.api.get("/api/reservations/agenda/", {"moi": 1}).data), 1)
        self.api.patch(f"/api/reservations/{v}/", {"statut": "termine", "compte_rendu": "Intéressé, veut voir le bail", "interesse": True}, format="json")
        self.api.force_authenticate(self.admin)
        self.assertEqual(len(self.api.get("/api/reservations/agenda/", {"du": "2026-06-23"}).data), 0)
        # Bien loué : plus de visite possible
        self.contrat()
        r = self.api.post("/api/reservations/", {"maison": self.m.pk, "nom_visiteur": "X", "tel_visiteur": "1", "date_visite": "2026-06-25"}, format="json")
        self.assertEqual(r.status_code, 400)
        # État des lieux signé au doigt sur la tablette
        c = Contrat.objects.get()
        url = f"/api/contrats/{c.pk}/etat-des-lieux/entree/"
        pieces = self.api.get(url).data["pieces"]
        self.assertEqual(self.api.post(url + "signer/", {"locataire": "x", "agence": "y"}, format="json").status_code, 400)  # pas encore établi
        self.api.post(url, {"date_etat": "2026-03-05", "nombre_cles": 2, "pieces": pieces}, format="json")
        tampon = io.BytesIO()
        Image.new("RGBA", (300, 120), (0, 0, 0, 0)).save(tampon, "PNG")
        png = "data:image/png;base64," + base64.b64encode(tampon.getvalue()).decode()
        self.assertEqual(self.api.post(url + "signer/", {"locataire": png}, format="json").status_code, 400)                  # les deux signatures
        r = self.api.post(url + "signer/", {"locataire": png, "agence": png}, format="json")
        self.assertEqual(r.status_code, 200, r.data)
        self.assertIsNotNone(self.api.get(url).data["signatures"])
        # Enregistrer sans changement garde les signatures ; une modification les annule
        self.api.post(url, {"date_etat": "2026-03-05", "nombre_cles": 2, "pieces": pieces}, format="json")
        self.assertIsNotNone(self.api.get(url).data["signatures"])
        self.api.post(url, {"date_etat": "2026-03-05", "nombre_cles": 3, "pieces": pieces}, format="json")
        self.assertIsNone(self.api.get(url).data["signatures"])


    def test_activite_des_agents(self):
        self.mandat()
        self.contrat()
        self.api.force_authenticate(self.agent)
        self.payer(Contrat.objects.get().pk, 100000, 5)
        r = self.api.get("/api/rapports/activite-agents/", {"du": "2026-01-01", "au": "2026-12-31"})
        self.assertEqual([l["nom"] for l in r.data["lignes"]], ["Agent"])            # un agent ne voit que lui
        self.assertEqual((r.data["lignes"][0]["encaissements"], r.data["lignes"][0]["montant"]), (1, 100000))
        self.api.force_authenticate(self.admin)
        r = self.api.get("/api/rapports/activite-agents/", {"du": "2026-01-01", "au": "2026-12-31"})
        par_nom = {l["nom"]: l for l in r.data["lignes"]}
        self.assertEqual(set(par_nom), {"Admin", "Agent"})
        self.assertEqual((par_nom["Admin"]["baux"], par_nom["Admin"]["encaissements"]), (1, 2))   # bail + avance de 2 mois


    def test_revision_triennale_a_echeance(self):
        self.mandat()
        self.contrat(date_debut="2023-05-01", mois_avance=0)       # trois ans révolus le 01/05/2026
        c = Contrat.objects.get()
        a = self.api.get("/api/revisions/a-echeance/").data
        self.assertEqual([(x["contrat"], str(x["echeance"])) for x in a], [(c.pk, "2026-05-01")])
        self.assertEqual(self.api.get("/api/tableau-de-bord/").data["alertes"]["revisions"], 1)
        r = self.api.post(f"/api/contrats/{c.pk}/reviser/", {"nouveau_loyer": 110000, "date_effet": "2026-09-01", "origine": "triennale", "motif": "Révision triennale"}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        lettre = self.api.get(f"/api/revisions/{r.data['id']}/").data
        self.assertEqual((lettre["locataire_nom"], lettre["bailleur_nom"], lettre["variation"]), ("Yao Aya", "Koné Ibrahim", 10.0))
        # Prochaine révision possible trois ans après la date d'effet
        self.assertEqual(self.api.get("/api/revisions/a-echeance/").data, [])
        self.assertEqual(services.revisions_a_echeance(0, date(2029, 9, 1))[0]["depuis"], date(2026, 9, 1))


    def test_licence(self):
        import base64

        from cryptography.hazmat.primitives import serialization
        from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey

        from apps.core import licence

        cle = Ed25519PrivateKey.generate()   # clé de test : la vraie clé privée n'est jamais dans le projet
        pem = cle.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption())
        p = mock.patch.object(licence, "CLE_PUBLIQUE", base64.b64encode(cle.public_key().public_bytes(serialization.Encoding.Raw, serialization.PublicFormat.Raw)).decode())
        p.start()
        self.addCleanup(p.stop)
        faire = lambda **d: licence.signer({"numero": "LIC-T", "agence": "Agence T", "expire_le": "2027-12-31", "utilisateurs": None, "biens": None, "modules": [], **d}, pem)  # noqa: E731
        # Sans licence : essai de 30 jours, tout est ouvert
        e = self.api.get("/api/licence/").data
        self.assertEqual((e["statut"], e["essai"], len(e["modules"])), ("essai", True, len(licence.MODULES)))
        self.assertEqual(self.api.get("/api/compteurs/").status_code, 200)
        # Licence modifiée à la main : refusée
        texte = faire(modules=["connecteurs"], utilisateurs=2, biens=1)
        corps, sig = texte.split(".")
        falsifiee = base64.urlsafe_b64encode(base64.urlsafe_b64decode(corps + "==").replace(b"2027", b"2099")).decode().rstrip("=") + "." + sig
        self.assertEqual(self.api.post("/api/licence/", {"texte": falsifiee}, format="json").status_code, 400)
        self.api.force_authenticate(self.agent)
        self.assertEqual(self.api.post("/api/licence/", {"texte": texte}, format="json").status_code, 400)   # administrateur seulement
        self.api.force_authenticate(self.admin)
        r = self.api.post("/api/licence/", {"texte": texte}, format="json")
        self.assertEqual((r.data["statut"], r.data["agence"], r.data["utilises"]), ("valide", "Agence T", {"utilisateurs": 2, "biens": 1}))
        # Modules non inclus et limites
        self.assertEqual(self.api.get("/api/compteurs/").status_code, 403)
        self.assertEqual(self.api.get("/api/rapprochement/historique/").status_code, 403)
        self.mandat()
        r = self.api.post("/api/maisons/", {"bailleur": self.b.pk, "designation": "Studio 2", "loyer": 30000}, format="json")
        self.assertEqual(r.status_code, 400)
        self.assertIn("limitée à 1 biens", r.data["detail"])
        r = self.api.post("/api/utilisateurs/", {"email": "trois@test.ci", "nom_complet": "Trois", "role": "agent", "password": "motdepasse12"}, format="json")
        self.assertEqual(r.status_code, 400)
        # Expirée depuis plus de 15 jours : lecture seule (consultation et sauvegarde permises)
        self.api.post("/api/licence/", {"texte": faire(expire_le="2026-05-01", modules=["compteurs"])}, format="json")
        e = self.api.get("/api/licence/").data
        self.assertEqual((e["statut"], e["lecture_seule"]), ("expiree", True))
        self.assertEqual(self.api.get("/api/maisons/").status_code, 200)
        r = self.api.patch(f"/api/maisons/{self.m.pk}/", {"designation": "X"}, format="json")
        self.assertEqual((r.status_code, r.json()["code"]), (403, "licence_expiree"))
        self.assertEqual(self.api.post("/api/auth/deconnexion/").status_code, 200)
        self.api.force_authenticate(self.admin)
        # En période de grâce : tout fonctionne encore
        self.api.post("/api/licence/", {"texte": faire(expire_le="2026-06-10", modules=["compteurs"])}, format="json")
        self.assertEqual(self.api.get("/api/licence/").data["statut"], "grace")
        self.assertEqual(self.api.patch(f"/api/maisons/{self.m.pk}/", {"designation": "Villa A1 bis"}, format="json").status_code, 200)


    def test_doublons_a_la_saisie(self):
        Locataire.objects.filter(pk=self.l.pk).update(telephone1="0505050505", piece_identite="CI 0012-3456", email="aya@test.ci")
        # Recherche pendant la saisie : téléphone écrit autrement, nom dans l'autre ordre, accents ignorés
        r = self.api.get("/api/locataires/doublons/", {"nom": "aya yao", "telephone1": "+225 05 05 05 05 05"})
        self.assertEqual(r.data[0]["id"], self.l.pk)
        self.assertTrue(r.data[0]["fort"])
        self.assertIn("même téléphone", r.data[0]["raisons"])
        self.assertIn("nom identique", r.data[0]["raisons"])
        r = self.api.get("/api/locataires/doublons/", {"nom": "Yao Ayaa"})            # petite faute : signalé, sans bloquer
        self.assertEqual((r.data[0]["raisons"], r.data[0]["fort"]), (["nom proche"], False))
        self.assertEqual(self.api.get("/api/locataires/doublons/", {"nom": "Traoré Mariam"}).data, [])
        # Enregistrement : confirmation exigée pour un indice fort (pièce d'identité), puis acceptée
        nouveau = {"nom": "Aya Y.", "telephone1": "0101010101", "piece_identite": "ci00123456"}
        r = self.api.post("/api/locataires/", nouveau, format="json")
        self.assertEqual((r.status_code, r.data["code"]), (400, "doublon_possible"))
        self.assertIn("même pièce d'identité", r.data["detail"])
        r = self.api.post("/api/locataires/", {**nouveau, "confirmer_doublon": True}, format="json")
        self.assertEqual(r.status_code, 201, r.data)
        # Un nom seulement proche n'empêche pas l'enregistrement
        self.assertEqual(self.api.post("/api/locataires/", {"nom": "Yao Ayaa", "telephone1": "0202020202"}, format="json").status_code, 201)
        # Modification sans toucher aux identifiants : pas de nouvelle vérification
        self.assertEqual(self.api.patch(f"/api/locataires/{r.data['id'] if 'id' in r.data else self.l.pk}/", {"profession": "Comptable"}, format="json").status_code, 200)
        # Bailleurs : même contrôle (CNI)
        Bailleur.objects.filter(pk=self.b.pk).update(numero_cni="C0099")
        self.mandat()
        r = self.api.post("/api/bailleurs/", {"nom": "Autre", "telephone1": "0909090909", "numero_cni": "c-0099"}, format="json")
        self.assertEqual(r.data["code"], "doublon_possible")
        self.assertEqual(self.api.get("/api/bailleurs/doublons/", {"nom": "Ibrahim Koné"}).data[0]["id"], self.b.pk)


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

    def test_espace_bailleur(self):
        b = Bailleur.objects.create(nom="Proprio", telephone1="0202020202")
        autre = Bailleur.objects.create(nom="Autre", telephone1="0303030303")
        for x in (b, autre):
            MandatGestion.objects.create(bailleur=x, date_signature=date(2026, 1, 1), date_debut=date(2026, 1, 1), taux_commission=10)
        m = Maison.objects.create(bailleur=b, designation="Villa P", loyer=100000)
        Maison.objects.create(bailleur=b, designation="Studio libre", loyer=40000)
        m2 = Maison.objects.create(bailleur=autre, designation="Chez l'autre", loyer=60000)
        l = Locataire.objects.create(nom="Loc", telephone1="0101010101")
        l2 = Locataire.objects.create(nom="Loc2", telephone1="0404040404")
        admin = Utilisateur.objects.create_user("a@a.ci", "motdepasse1", nom_complet="A", role="admin")
        api = APIClient()
        api.force_authenticate(admin)
        api.post("/api/contrats/", {"maison": m.pk, "locataire": l.pk, "date_debut": "2026-01-01"}, format="json")
        api.post("/api/contrats/", {"maison": m2.pk, "locataire": l2.pk, "date_debut": "2026-01-01"}, format="json")
        api.post("/api/compte-bailleurs/verser/", {"bailleur": b.pk, "montant": 50000}, format="json")
        code = api.post(f"/api/bailleurs/{b.pk}/generer-code/").data["code"]
        self.assertTrue(api.get(f"/api/bailleurs/{b.pk}/").data["acces_portail"])
        public = APIClient()
        self.assertEqual(public.get("/api/portail-bailleur/espace/").status_code, 403)
        self.assertEqual(public.post("/api/portail-bailleur/connexion/", {"telephone": "0202020202", "code": "FAUX"}, format="json").status_code, 400)
        self.assertEqual(public.post("/api/portail-bailleur/connexion/", {"telephone": "02 02 02 02 02", "code": code}, format="json").status_code, 200)
        r = public.get("/api/portail-bailleur/espace/")
        self.assertEqual(r.status_code, 200)
        ch = r.data["chiffres"]
        self.assertEqual((ch["nb_biens"], ch["nb_occupes"]), (2, 1))
        self.assertEqual(ch["solde"], 2 * 90000 - 50000)                  # avance de 2 mois nette de 10 %, moins le versement
        self.assertEqual(r.data["versements"][0]["montant"], 50000)
        self.assertEqual(len(r.data["mois"]), 12)
        self.assertEqual({q["maison"] for q in r.data["quittances"]}, {"Villa P"})   # jamais les biens d'un autre bailleur
        q = r.data["quittances"][0]["id"]
        self.assertEqual(public.get(f"/api/portail-bailleur/quittances/{q}/").status_code, 200)
        etrangere = Encaissement.objects.filter(contrat__maison=m2).first().pk
        self.assertEqual(public.get(f"/api/portail-bailleur/quittances/{etrangere}/").status_code, 404)
        self.assertEqual(public.get("/api/portail-bailleur/releve/", {"du": "2026-01-01"}).data["solde_final"], 130000)
        api.post(f"/api/bailleurs/{b.pk}/desactiver-acces/")
        public.post("/api/portail-bailleur/deconnexion/")
        self.assertEqual(public.post("/api/portail-bailleur/connexion/", {"telephone": "0202020202", "code": code}, format="json").status_code, 400)
