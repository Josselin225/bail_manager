"""Première installation : paramètres, clauses types et compte administrateur."""
import getpass

from django.core.management.base import BaseCommand

from apps.comptes.models import Utilisateur
from apps.core.models import ClauseContrat, ClauseMandat, Parametres

CLAUSES_CONTRAT = [
    ("Cadre légal", "Le présent bail est régi par la loi n° 2019-576 du 26 juin 2019 instituant le Code de la Construction et de l'Habitat, sous réserve des dispositions du Code civil."),
    ("Préavis de résiliation", "Le préavis de résiliation est fixé à trois (03) mois, notifié par lettre recommandée, acte de Commissaire de justice ou remise contre décharge."),
    ("Paiement du loyer", "Le preneur s'engage à payer le loyer au plus tard le 05 de chaque mois."),
    ("Charges et abonnements", "Les charges d'abonnement et de consommation d'eau et d'électricité sont à la charge exclusive du preneur."),
]
CLAUSES_MANDAT = [
    ("Objet", "Le mandant confie au mandataire, qui l'accepte, la gestion locative des biens immobiliers qu'il possède ou viendrait à posséder, aux fins de location, d'encaissement des loyers et de représentation auprès des locataires."),
    ("Pouvoirs du mandataire", "Le mandataire est habilité, au nom et pour le compte du mandant, à rechercher des locataires, signer les contrats de bail, encaisser les loyers, charges et dépôts de garantie, délivrer quittance, et assurer le suivi de l'entretien courant des biens confiés."),
    ("Représentation exclusive", "Pendant toute la durée du présent mandat, le mandant s'interdit de traiter directement avec les locataires des biens confiés pour tout ce qui relève de la gestion locative ; toute correspondance, notification ou autorisation relative à ces biens transite par le mandataire."),
    ("Obligations du mandataire", "Le mandataire s'engage à agir avec diligence et loyauté, à rendre compte de sa gestion, et à reverser au mandant les sommes lui revenant, déduction faite de sa commission et des frais justifiés, selon la périodicité convenue entre les parties."),
    ("Obligations du mandant", "Le mandant s'engage à mettre les biens confiés à disposition en bon état d'usage, à fournir au mandataire les documents nécessaires à l'exercice de sa mission, et à s'acquitter de la commission convenue."),
    ("Résiliation", "Le présent mandat peut être résilié à tout moment par l'une ou l'autre des parties moyennant un préavis écrit de trois (03) mois, sans préjudice des engagements en cours (baux non échus, sommes dues)."),
    ("Droit applicable", "Le présent mandat est régi par les dispositions du Code civil relatives au contrat de mandat, sous réserve des dispositions impératives applicables aux baux à usage d'habitation en vigueur en Côte d'Ivoire."),
]


class Command(BaseCommand):
    help = "Initialise une installation vierge (paramètres, clauses, administrateur)."

    def add_arguments(self, parser):
        parser.add_argument("--email", default="")
        parser.add_argument("--mot-de-passe", dest="mdp", default="")
        parser.add_argument("--nom", default="Administrateur")
        parser.add_argument("--entreprise", default="")

    def handle(self, *args, **o):
        p = Parametres.charger()
        if o["entreprise"]:
            p.nom_entreprise = o["entreprise"]
            p.save()
        if not ClauseContrat.objects.exists():
            for i, (t, c) in enumerate(CLAUSES_CONTRAT, 1):
                ClauseContrat.objects.create(titre=t, contenu=c, ordre_affichage=i * 10)
        if not ClauseMandat.objects.exists():
            for i, (t, c) in enumerate(CLAUSES_MANDAT, 1):
                ClauseMandat.objects.create(titre=t, contenu=c, ordre_affichage=i * 10)
        self.stdout.write(self.style.SUCCESS("Paramètres et clauses types en place."))

        if Utilisateur.objects.filter(role="admin").exists():
            self.stdout.write("Un administrateur existe déjà : aucun compte créé.")
            return
        email = o["email"] or input("E-mail de l'administrateur (laisser vide pour passer, ex. avant un import de la v1) : ").strip()
        if not email:
            self.stdout.write("Aucun administrateur créé.")
            return
        mdp = o["mdp"] or getpass.getpass("Mot de passe (8 caractères minimum) : ")
        if len(mdp) < 8:
            self.stderr.write("Mot de passe trop court.")
            return
        Utilisateur.objects.create_user(email=email, password=mdp, nom_complet=o["nom"], role="admin", session_timeout_minutes=30)
        self.stdout.write(self.style.SUCCESS(f"Administrateur {email} créé. Vous pouvez vous connecter."))
