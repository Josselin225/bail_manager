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
