"""Jeu de données de démonstration (à n'utiliser que sur une base de test)."""
import random
from datetime import date

from django.core.management import call_command
from django.core.management.base import BaseCommand, CommandError
from django.utils import timezone

from django.db.models import Q

from apps.comptes.models import Utilisateur
from apps.finances.models import CompteCourantBailleur, MouvementCaisse
from apps.core.models import Parametres
from apps.core.utils import ajouter_mois, aujourdhui
from apps.locatif import services
from apps.locatif.models import ChargeLocative, Contrat
from apps.patrimoine.models import Bailleur, Locataire, MandatGestion, Maison
from apps.relations.models import MessageLocataire, MessageVisiteur, Reservation


class FauxRequete:
    def __init__(self, user):
        self.user = user
        self.META = {"REMOTE_ADDR": "127.0.0.1"}


class Command(BaseCommand):
    help = "Remplit une base VIDE avec des données fictives pour découvrir le logiciel."

    def handle(self, *args, **o):
        if Contrat.objects.exists():
            raise CommandError("La base contient déjà des contrats : données de démonstration non ajoutées.")
        random.seed(7)
        admin = Utilisateur.objects.filter(role="admin").first()
        if not admin:
            admin = Utilisateur.objects.create_user("admin@demo.ci", "demo12345", nom_complet="Awa Koné", role="admin", session_timeout_minutes=60)
        Utilisateur.objects.get_or_create(email="agent@demo.ci", defaults={"nom_complet": "Serge Yao", "role": "agent", "password": "!"})
        req = FauxRequete(admin)
        p = Parametres.charger()
        p.nom_entreprise = p.nom_entreprise if p.nom_entreprise != "BailManager" else "BailManager"
        p.contact_telephone = p.contact_telephone or "+225 07 49 79 12 87"
        p.adresse_siege = p.adresse_siege or "Yamoussoukro, quartier Habitat"
        p.save()

        j = aujourdhui()
        noms_b = ["Konan Kouassi Jean", "Traoré Mariam", "Bamba Souleymane", "N'Guessan Aya Christelle"]
        quartiers = ["Habitat", "Morofé", "Kokrenou", "Dioulakro", "Assabou", "N'Zuessy"]
        types = [("Studio", 45000), ("Chambre salon", 60000), ("2 pièces", 85000), ("3 pièces", 130000), ("Villa 4 pièces", 220000)]
        locs = ["Coulibaly Ibrahim", "Kouamé Akissi", "Diallo Fatoumata", "Ouattara Moussa", "Yao Brou Eric", "Kra Amenan", "Gbagbo Didier", "Touré Aminata", "Aké Patrice", "Soro Kady"]
        maisons = []
        for i, nom in enumerate(noms_b):
            b = Bailleur.objects.create(nom=nom, sexe="F" if i % 2 else "M", telephone1=f"07{random.randint(10000000, 99999999)}", adresse=f"Yamoussoukro, {quartiers[i]}")
            MandatGestion.objects.create(bailleur=b, date_signature=date(j.year - 1, 1, 10), date_debut=date(j.year - 1, 1, 10), taux_commission=[10, 8, 10, 12][i])
            for k in range(3):
                t, loyer = random.choice(types)
                maisons.append(Maison.objects.create(bailleur=b, designation=f"{t} {quartiers[(i + k) % 6]} n°{k + 1}", type_maison=t.split()[0] if t.startswith("Villa") else t, adresse=f"Yamoussoukro, {quartiers[(i + k) % 6]}", loyer=loyer, condition=4, description="Logement en bon état, eau et électricité (compteurs individuels), cour sécurisée."))
        for i, nom in enumerate(locs):
            l = Locataire.objects.create(nom=nom, telephone1=f"05{random.randint(10000000, 99999999)}", profession=random.choice(["Enseignant", "Commerçante", "Infirmier", "Comptable", "Étudiante", "Chauffeur"]))
            if i >= 9:
                continue
            m = maisons[i]
            debut = ajouter_mois(j.replace(day=random.choice([1, 5, 10])), -random.randint(3, 10))
            c = services.creer_contrat(maison=m, locataire=l, loyer_mensuel=m.loyer, date_debut=debut, date_contrat=debut, mois_avance=2, droit_agence=m.loyer, request=req)
            signature = timezone.make_aware(timezone.datetime(debut.year, debut.month, debut.day, 9))
            ids = list(c.encaissements.values_list("id", flat=True))
            c.encaissements.update(date_encaissement=signature)
            CompteCourantBailleur.objects.filter(encaissement_id__in=ids).update(date_operation=signature)
            MouvementCaisse.objects.filter(Q(encaissement_id__in=ids) | Q(reference_paiement=c.reference)).update(date_operation=signature)
            # paiements : la plupart à jour, quelques retards
            profil = ["a_jour", "a_jour", "a_jour", "retard", "partiel", "a_jour", "a_jour", "retard", "a_jour"][i]
            for per in services.echeancier(c):
                if per.reste <= 0:
                    continue
                courant = (per.annee, per.mois) == (j.year, j.month)
                if profil == "retard" and (per.annee * 12 + per.mois) >= (j.year * 12 + j.month - 1):
                    break
                if profil == "partiel" and courant:
                    services.enregistrer_encaissement(c, per.reste // 2, per.mois, per.annee, "mobile_money", req)
                    break
                if courant and i % 3 == 0:
                    break
                quand = timezone.make_aware(timezone.datetime(per.annee, per.mois, min(28, c.date_debut.day + random.randint(0, 4)), 10))
                if quand > timezone.now():
                    break
                services.enregistrer_encaissement(c, per.reste, per.mois, per.annee, random.choice(["especes", "mobile_money", "mobile_money", "virement"]), req, date_operation=quand)
            if i % 4 == 1:
                ChargeLocative.objects.create(contrat=c, type_charge="Eau", montant=random.choice([4500, 7200, 9800]), date_charge=j.replace(day=1), description="Facture SODECI")
        Reservation.objects.create(maison=maisons[-1], nom_visiteur="Kouadio Serge", tel_visiteur="0707070707", date_visite=j)
        MessageVisiteur.objects.create(nom_visiteur="Mme Yéo", email_visiteur="yeo@example.com", sujet="Confier une villa", contenu="Bonjour, je possède une villa à Assabou et souhaite vous en confier la gestion. Pouvez-vous me rappeler ?")
        MessageLocataire.objects.create(locataire=Locataire.objects.first(), expediteur="locataire", contenu="Bonjour, le robinet de la cuisine fuit. Pouvez-vous envoyer quelqu'un ?")
        call_command("photos_demo", stdout=self.stdout)
        self.stdout.write(self.style.SUCCESS(f"Données de démonstration créées. Connexion : {admin.email}"))
