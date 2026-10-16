"""
Importe les données de la version PHP (MySQL) dans BailManager 2.

    1. Dans .env : LEGACY_DB_NAME=gestion_bail (+ LEGACY_DB_USER, LEGACY_DB_PASS...)
    2. python manage.py migrate
    3. python manage.py importer_ancienne_base --uploads "C:\\wamp64\\www\\bail_manager\\uploads"

Les identifiants (id) sont conservés. Les mots de passe des utilisateurs et les codes
d'accès des locataires restent valables (empreintes bcrypt PHP reconnues).
La base cible doit être vide : la commande refuse sinon (option --forcer pour passer outre).
"""
import shutil
from datetime import date, datetime, time
from decimal import Decimal
from pathlib import Path

from django.conf import settings
from django.core.management.base import BaseCommand, CommandError
from django.db import connection, connections, transaction
from django.utils import timezone

from apps.comptes.models import Utilisateur
from apps.core.models import ClauseContrat, ClauseMandat, Journal, Parametres
from apps.core.utils import arrondir, parser_periode
from apps.finances.models import CompteCourantBailleur, MouvementCaisse
from apps.locatif.models import ChargeLocative, Contrat, Encaissement, MouvementCaution, RevisionLoyer
from apps.locatif.services import recalculer_echeance
from apps.patrimoine.models import Bailleur, Document, Locataire, LocataireAcces, MandatGestion, Maison, PhotoMaison
from apps.relations.models import MessageLocataire, MessageVisiteur, Reservation

MODES = {"especes": "especes", "espèces": "especes", "virement": "virement", "mobile_money": "mobile_money",
         "mobile money": "mobile_money", "cheque": "cheque", "chèque": "cheque"}
STATUTS_RESA = {"en_attente": "en_attente", "confirme": "confirme", "confirmee": "confirme", "annule": "annule", "termine": "termine"}


def dt(v):
    """Datetime aware à partir d'une valeur MySQL (datetime, date ou texte)."""
    if v in (None, "", "0000-00-00 00:00:00"):
        return timezone.now()
    if isinstance(v, str):
        v = datetime.fromisoformat(v.replace(" ", "T")[:19])
    if isinstance(v, date) and not isinstance(v, datetime):
        v = datetime.combine(v, time(12, 0))
    return timezone.make_aware(v) if timezone.is_naive(v) else v


def d(v):
    if v in (None, "", "0000-00-00"):
        return None
    if isinstance(v, datetime):
        return v.date()
    if isinstance(v, str):
        return date.fromisoformat(v[:10])
    return v


def dec(v):
    return arrondir(Decimal(str(v or 0)))


def txt(v):
    return "" if v is None else str(v)


class Command(BaseCommand):
    help = "Importe la base MySQL de la version PHP (alias de base « legacy »)."

    def add_arguments(self, parser):
        parser.add_argument("--uploads", help="Dossier uploads/ de l'ancienne installation (copie des photos et scans)")
        parser.add_argument("--alias", default="legacy")
        parser.add_argument("--forcer", action="store_true")

    def lignes(self, table):
        cur = connections[self.alias].cursor()
        try:
            cur.execute(f"SELECT * FROM {table}")
        except Exception:
            self.stdout.write(self.style.WARNING(f"  table {table} absente — ignorée"))
            return []
        cols = [c[0] for c in cur.description]
        return [dict(zip(cols, r)) for r in cur.fetchall()]

    def fichier(self, sous_dossier_ancien, nom, sous_dossier_nouveau):
        """Copie un fichier de l'ancien uploads/ et renvoie son chemin relatif au nouveau MEDIA_ROOT."""
        if not nom or nom in ("default.png", "default_user.png"):
            return None
        rel = f"{sous_dossier_nouveau}/{nom}"
        if self.uploads:
            src = self.uploads / sous_dossier_ancien / nom
            if src.is_file():
                dst = Path(settings.MEDIA_ROOT) / rel
                dst.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(src, dst)
                self.nb_fichiers += 1
            else:
                self.manquants += 1
        return rel

    def handle(self, *args, **o):
        self.alias = o["alias"]
        if self.alias not in connections.databases:
            raise CommandError("Base « legacy » non configurée : renseignez LEGACY_DB_NAME dans le fichier .env.")
        self.uploads = Path(o["uploads"]) if o.get("uploads") else None
        self.nb_fichiers = self.manquants = 0
        if Contrat.objects.exists() and not o["forcer"]:
            raise CommandError("La base cible contient déjà des contrats. Utilisez une base vide (ou --forcer).")
        with transaction.atomic():
            self.importer()
        self.reinitialiser_sequences()
        self.stdout.write(self.style.SUCCESS(
            f"Import terminé. Fichiers copiés : {self.nb_fichiers}" + (f" — introuvables : {self.manquants}" if self.manquants else "")
        ))

    def importer(self):
        w = self.stdout.write
        # Paramètres
        for s in self.lignes("settings")[:1]:
            p = Parametres.charger()
            for champ in ["nom_entreprise", "contact_email", "contact_telephone", "activites", "cc_numero", "regime_imposition",
                          "rccm_numero", "compte_bancaire", "iban", "swift", "site_web", "adresse_siege"]:
                if s.get(champ) is not None:
                    setattr(p, champ, txt(s[champ]))
            p.taux_commission = Decimal(str(s.get("taux_commission") or 10))
            p.rappels_actifs = bool(s.get("rappels_actifs", 1))
            p.save()
        taux_defaut = Parametres.charger().taux_commission

        for t, Mod in (("clauses_contrat", ClauseContrat), ("clauses_mandat", ClauseMandat)):
            rows = self.lignes(t)
            if rows:
                Mod.objects.all().delete()
                Mod.objects.bulk_create([Mod(id=r["id"], titre=txt(r["titre"]), contenu=txt(r["contenu"]),
                                             ordre_affichage=r.get("ordre_affichage") or 0, actif=bool(r.get("actif", 1))) for r in rows])

        users = self.lignes("users")
        for r in users:
            u = Utilisateur(id=r["id"], nom_complet=txt(r["nom_complet"]), email=txt(r["email"]).lower(), role=r.get("role") or "agent",
                            session_timeout_minutes=r.get("session_timeout_minutes") or 10,
                            dernier_acces=dt(r["dernier_acces"]) if r.get("dernier_acces") else None)
            u.password = f"phpbcrypt${r['mot_de_passe']}"
            if r.get("photo_profil"):
                u.photo_profil = self.fichier("users", r["photo_profil"], "users")
            u.save()
        w(f"  {len(users)} utilisateur(s)")

        rows = self.lignes("bailleurs")
        for r in rows:
            b = Bailleur(id=r["id"], code_bailleur=txt(r["code_bailleur"]) or f"BA-IMP-{r['id']:04d}", nom=txt(r["nom"]),
                         sexe=txt(r.get("sexe"))[:1], numero_cni=txt(r.get("numero_cni")), telephone1=txt(r.get("telephone1")),
                         telephone2=txt(r.get("telephone2")), email=txt(r.get("email")), adresse=txt(r.get("adresse")))
            b.photo = self.fichier("bailleurs", r.get("photo"), "bailleurs")
            b.save()
            Bailleur.objects.filter(pk=b.pk).update(created_at=dt(r.get("created_at")))
        w(f"  {len(rows)} bailleur(s)")

        rows = self.lignes("mandats_gestion")
        MandatGestion.objects.bulk_create([
            MandatGestion(id=r["id"], bailleur_id=r["bailleur_id"], date_signature=d(r["date_signature"]), date_debut=d(r["date_debut"]),
                          date_fin=d(r.get("date_fin")), taux_commission=Decimal(str(r.get("taux_commission") or 10)),
                          statut=r.get("statut") or "actif", document_signe=self.fichier("mandats", r.get("document_signe"), "mandats") or "")
            for r in rows
        ])
        w(f"  {len(rows)} mandat(s)")

        rows = self.lignes("maisons")
        for r in rows:
            m = Maison(id=r["id"], bailleur_id=r["bailleur_id"], designation=txt(r["designation"]), type_maison=txt(r.get("type_maison")),
                       adresse=txt(r.get("adresse")), description=txt(r.get("description")), statut=r.get("statut") or "disponible",
                       condition=int(float(r.get("condition") or 0)), loyer=dec(r.get("loyer")))
            m.save()
            for i in (1, 2, 3):
                if f := self.fichier("maisons", r.get(f"image{i}"), "public/maisons"):
                    PhotoMaison.objects.create(maison=m, image=f, ordre=i)
        w(f"  {len(rows)} maison(s)")
        # Règle : pas de bien sans mandat de gestion. L'historique est repris tel quel, mais signalé.
        sans_mandat = [b for b in Bailleur.objects.filter(maisons__isnull=False).distinct() if b.mandat_actif() is None]
        if sans_mandat:
            self.stdout.write(self.style.WARNING(
                f"  ⚠ {len(sans_mandat)} bailleur(s) ont des maisons sans mandat de gestion en vigueur : "
                + ", ".join(b.nom for b in sans_mandat)
                + ". Enregistrez leur mandat avant tout nouveau bail."
            ))

        rows = self.lignes("locataires")
        for r in rows:
            l = Locataire(id=r["id"], nom=txt(r["nom"]), telephone1=txt(r.get("telephone1")), telephone2=txt(r.get("telephone2")),
                          email=txt(r.get("email")), piece_identite=txt(r.get("piece_identite")))
            for champ in ("photo_locataire", "photo_cni_recto", "photo_cni_verso"):
                setattr(l, champ, self.fichier("locataires", r.get(champ), "locataires"))
            l.save()
        w(f"  {len(rows)} locataire(s)")
        for r in self.lignes("locataire_acces"):
            LocataireAcces.objects.create(locataire_id=r["locataire_id"], code_acces=f"phpbcrypt${r['code_acces']}", actif=bool(r.get("actif", 1)))

        for r in self.lignes("documents"):
            Document.objects.create(id=r["id"], entity_type=r["entity_type"], entity_id=r["entity_id"],
                                    fichier=self.fichier("documents", r["nom_fichier"], "documents"), nom_original=txt(r["nom_original"]),
                                    type_mime=txt(r.get("type_mime")), taille=r.get("taille") or 0, uploaded_by_id=r.get("uploaded_by"))

        encs = self.lignes("encaissements")
        premiers = {}
        for e in encs:
            p = parser_periode(txt(e.get("periode_concernee"))) or (dt(e["date_encaissement"]).month, dt(e["date_encaissement"]).year)
            e["_p"] = p
            k = p[1] * 12 + p[0] - 1
            premiers[e["contrat_id"]] = min(premiers.get(e["contrat_id"], k), k)

        rows = self.lignes("contrats")
        for r in rows:
            loyer = dec(r["loyer_mensuel"])
            debut = d(r["date_debut"])
            if r["id"] in premiers:
                k = premiers[r["id"]]
                fact = date(k // 12, k % 12 + 1, 1)
            else:
                fact = (d(r.get("date_prochain_loyer")) or debut).replace(day=1)
            avance = dec(r.get("avance_loyer"))
            Contrat.objects.create(
                id=r["id"], maison_id=r["maison_id"], locataire_id=r["locataire_id"], loyer_mensuel=loyer,
                date_contrat=d(r.get("date_contrat")) or debut, date_debut=debut, date_fin=d(r.get("date_fin")),
                statut_contrat="termine" if r.get("statut_contrat") == "termine" else "actif",
                depot_garantie=dec(r.get("depot_garantie")), avance_loyer=avance,
                mois_avance=min(2, int(avance / loyer)) if loyer else 0, droit_agence=dec(r.get("droit_agence")),
                debut_facturation=fact, date_prochain_loyer=d(r.get("date_prochain_loyer")),
                date_resiliation=d(r.get("date_fin")) if r.get("statut_contrat") == "termine" else None,
            )
        w(f"  {len(rows)} contrat(s)")

        refs = set()
        objets = []
        for e in encs:
            ref = txt(e.get("reference_recu")) or f"REC-IMP{e['id']}"
            if ref in refs:
                ref = f"{ref}-{e['id']}"
            refs.add(ref)
            montant = dec(e["montant_recu"])
            commission = arrondir(montant * taux_defaut / 100)
            objets.append(Encaissement(
                id=e["id"], contrat_id=e["contrat_id"], montant_recu=montant, date_encaissement=dt(e["date_encaissement"]),
                mode_paiement=MODES.get(txt(e.get("mode_paiement")).lower(), "especes"), reference_recu=ref,
                periode_mois=e["_p"][0], periode_annee=e["_p"][1], reliquat_apres_paiement=dec(e.get("reliquat_apres_paiement")),
                taux_commission=taux_defaut, commission=commission, net_bailleur=montant - commission,
                observation="Importé de la version 1",
            ))
        Encaissement.objects.bulk_create(objets)
        w(f"  {len(objets)} encaissement(s)")

        rows = self.lignes("compte_courant_bailleur")
        CompteCourantBailleur.objects.bulk_create([
            CompteCourantBailleur(id=r["id"], bailleur_id=r["bailleur_id"], date_operation=dt(r["date_operation"]),
                                  type_operation=r.get("type_operation") if r.get("type_operation") in ("loyer_encaisse", "versement_bailleur") else "ajustement",
                                  montant=dec(r["montant"]), commentaire=txt(r.get("commentaire")))
            for r in rows if r.get("bailleur_id")
        ])
        rows = self.lignes("mouvements_caisse_entreprise")
        MouvementCaisse.objects.bulk_create([
            MouvementCaisse(id=r["id"], bailleur_id=r.get("bailleur_id"), type_mouvement=txt(r.get("type_mouvement")) or "Autre entrée",
                            montant=dec(r["montant"]), date_operation=dt(r["date_operation"]), reference_paiement=txt(r.get("reference_paiement")),
                            commentaire=txt(r.get("commentaire")), effectue_par=txt(r.get("effectue_par")))
            for r in rows
        ])
        w("  comptes bailleurs et caisse importés")

        for r in self.lignes("mouvements_caution"):
            quand = dt(r["date_operation"])
            c = (Contrat.objects.filter(locataire_id=r["locataire_id"], date_debut__lte=quand.date()).order_by("-date_debut").first()
                 or Contrat.objects.filter(locataire_id=r["locataire_id"]).first())
            solde = "solde de tout compte" in txt(r.get("commentaire")).lower()
            m = MouvementCaution.objects.create(id=r["id"], locataire_id=r["locataire_id"], contrat=c,
                                                type_mouvement="restitution" if solde else "retenue", montant=dec(r["montant"]),
                                                motif="solde" if solde else "", commentaire=txt(r.get("commentaire")), effectue_par=txt(r.get("effectue_par")))
            MouvementCaution.objects.filter(pk=m.pk).update(date_operation=quand)

        for r in self.lignes("charges_locatives"):
            ChargeLocative.objects.create(id=r["id"], contrat_id=r["contrat_id"], type_charge=txt(r["type_charge"]), montant=dec(r["montant"]),
                                          date_charge=d(r["date_charge"]), description=txt(r.get("description")))
        for r in self.lignes("revisions_loyer"):
            quand = dt(r.get("created_at"))
            rv = RevisionLoyer.objects.create(id=r["id"], contrat_id=r["contrat_id"], ancien_loyer=dec(r["ancien_loyer"]),
                                              nouveau_loyer=dec(r["nouveau_loyer"]), motif=txt(r.get("motif")),
                                              date_effet=quand.date().replace(day=1), effectue_par_id=r.get("effectue_par"))
            RevisionLoyer.objects.filter(pk=rv.pk).update(created_at=quand)

        for r in self.lignes("reservations"):
            x = Reservation.objects.create(id=r["id"], maison_id=r["maison_id"], nom_visiteur=txt(r["nom_visiteur"]), tel_visiteur=txt(r["tel_visiteur"]),
                                           date_visite=d(r["date_visite"]), statut=STATUTS_RESA.get(txt(r.get("statut")), "en_attente"))
            Reservation.objects.filter(pk=x.pk).update(created_at=dt(r.get("created_at")))
        for r in self.lignes("messages"):
            x = MessageVisiteur.objects.create(id=r["id"], nom_visiteur=txt(r["nom_visiteur"]), email_visiteur=txt(r["email_visiteur"]),
                                               sujet=txt(r.get("sujet")), contenu=txt(r["contenu"]), lu=r.get("statut") == "lu")
            MessageVisiteur.objects.filter(pk=x.pk).update(date_envoi=dt(r.get("date_envoi")))
        for r in self.lignes("messages_locataires"):
            x = MessageLocataire.objects.create(id=r["id"], locataire_id=r["locataire_id"], expediteur=r["expediteur"], contenu=txt(r["contenu"]), lu=bool(r.get("lu")))
            MessageLocataire.objects.filter(pk=x.pk).update(created_at=dt(r.get("created_at")))

        ids_users = set(Utilisateur.objects.values_list("id", flat=True))
        logs = self.lignes("logs")
        for r in logs:
            j = Journal.objects.create(utilisateur_id=r["utilisateur_id"] if r.get("utilisateur_id") in ids_users else None,
                                       action=txt(r.get("action")), details=txt(r.get("details")), ip_adresse=txt(r.get("ip_adresse")))
            Journal.objects.filter(pk=j.pk).update(date_action=dt(r.get("date_action")))
        w(f"  {len(logs)} entrée(s) de journal")

        ecarts = 0
        for c in Contrat.objects.filter(statut_contrat="actif"):
            avant = c.date_prochain_loyer
            if recalculer_echeance(c) != avant:
                ecarts += 1
        if ecarts:
            w(self.style.WARNING(f"  {ecarts} contrat(s) : prochaine échéance recalculée d'après les paiements réels"))
        Journal.objects.create(action="Import version 1", details="Données importées depuis la version PHP", ip_adresse="CLI")

    def reinitialiser_sequences(self):
        from django.apps import apps
        from django.core.management.color import no_style

        modeles = [m for m in apps.get_models() if m._meta.app_label in ("comptes", "core", "patrimoine", "locatif", "finances", "relations")]
        sql = connection.ops.sequence_reset_sql(no_style(), modeles)
        with connection.cursor() as cur:
            for s in sql:
                cur.execute(s)
