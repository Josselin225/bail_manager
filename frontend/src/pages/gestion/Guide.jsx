import { useState } from "react";
import { Link } from "react-router-dom";
import { EntetePage, Panneau } from "../../components/ui";

const SECTIONS = [
  { id: "demarrage", titre: "Mise en route", lignes: [
    ["Paramètres", "Commencez par Paramètres : nom de l'agence, logo, coordonnées, mentions légales (CC, RCCM, banque) imprimées en pied de chaque document, et taux de commission par défaut."],
    ["Utilisateurs", "Créez un compte par membre du personnel. Un agent encaisse, crée bailleurs, biens, locataires et contrats ; seul l'administrateur supprime, résilie, révise un loyer, gère les comptes et consulte le journal."],
  ] },
  { id: "patrimoine", titre: "Patrimoine", lignes: [
    ["Bailleur puis mandat", "Enregistrez le propriétaire : un code (BA-XX2026-0001) est généré. Saisissez ensuite son mandat de gestion (taux de commission, durée, scan signé). Sans mandat en vigueur, impossible d'ajouter un bien ou de signer un bail pour lui. Un nouveau mandat remplace le précédent (renouvellement)."],
    ["Maisons", "Chaque bien appartient à un bailleur sous mandat. Ajoutez jusqu'à trois photos : elles apparaissent sur le site public (« Nos maisons ») tant que le bien est libre et publié."],
    ["Locataires", "Le téléphone principal sert d'identifiant à l'espace locataire. Joignez photo et CNI recto/verso ; les autres pièces vont dans l'onglet Documents de la fiche."],
  ] },
  { id: "contrats", titre: "Contrats de bail", lignes: [
    ["Créer un bail", "Choisissez une maison libre et un locataire. La caution est fixée à 2 mois de loyer (Art. 416) ; l'avance est de 0 à 2 mois (Art. 415) ; les frais d'agence sont modifiables. Le total à verser se calcule en direct."],
    ["Ce qui se passe à l'enregistrement", "Le bien passe « occupé », l'avance génère les quittances des premiers mois (part bailleur créditée, commission en caisse), les frais d'agence entrent en caisse et le reçu de signature s'ouvre pour impression."],
    ["Échéance, révision, résiliation", "Depuis la fiche du contrat : fixer une date de fin (elle apparaît au calendrier et dans les rappels), réviser le loyer à partir d'un mois donné, faire une retenue sur caution, ou résilier (solde de tout compte)."],
  ] },
  { id: "encaissements", titre: "Encaissements et quittances", lignes: [
    ["Encaisser", "Menu Encaissements ou fiche du contrat. Le premier mois non soldé est proposé automatiquement avec le reste à payer. Le paiement partiel est accepté ; un montant supérieur au reste est refusé (le surplus se saisit sur le mois suivant)."],
    ["Règle des dettes antérieures", "On ne peut pas encaisser un mois tant qu'un mois précédent n'est pas soldé : le formulaire l'indique et propose de basculer sur le mois dû."],
    ["Répartition", "Chaque encaissement crédite le compte du bailleur du montant net et verse la commission (taux du mandat) dans la caisse de l'agence. Un reçu émis ne peut être ni modifié ni supprimé."],
    ["Quittance", "Montant en chiffres et en lettres, période, reste dû, rappel des impayés et QR code de vérification. Imprimez ou enregistrez en PDF (« Enregistrer au format PDF » dans la fenêtre d'impression)."],
  ] },
  { id: "finances", titre: "Finances", lignes: [
    ["Comptes bailleurs", "Historique des crédits (loyers nets) et versements de chaque bailleur. « Verser au bailleur » enregistre une remise d'argent, plafonnée au solde dû. Le relevé imprimable sert de justificatif."],
    ["Caisse agence", "Commissions, frais d'agence, retenues pour réparations ; les dépenses de l'agence se saisissent en « Retrait / dépense » (impossible au-delà du solde)."],
    ["Cautions", "Registre des dépôts. À la résiliation : la retenue « réparations » va en caisse, la retenue « loyers impayés » solde les mois dus les plus anciens (quittances émises), le reste est restitué au locataire."],
  ] },
  { id: "relations", titre: "Site public et relations", lignes: [
    ["Réservations", "Les demandes de visite du site arrivent ici : confirmez, marquez « visite faite » ou annulez."],
    ["Messages du site", "Messages du formulaire Contact ; répondez par e-mail directement si l'envoi d'e-mails est configuré."],
    ["Connecteurs (SMS, WhatsApp, Mobile Money)", "Paramètres > Connecteurs (administrateur) : choisissez le fournisseur SMS, WhatsApp et l'agrégateur de paiement, saisissez les identifiants puis « Essayer ». En mode test, rien n'est envoyé ni débité : consultez le « Journal des envois ». Les relances, confirmations de paiement et rappels partent automatiquement selon les cases cochées."],
    ["Signature électronique", "Fiche du contrat > Conditions > « Faire signer par SMS » : un code à 6 chiffres est envoyé au locataire ; il le saisit dans son espace ou vous le dicte. Le bail imprimé porte alors la mention de signature électronique."],
    ["Récapitulatif fiscal", "Fiche bailleur > « Récapitulatif annuel » (ou espace propriétaire) : loyers encaissés, commissions et net par bien sur l'année choisie, à imprimer ou exporter vers Excel pour la déclaration des revenus fonciers."],
    ["Partager une annonce", "Sur le site (fiche d'une maison) ou sur la fiche d'un bien disponible : boutons WhatsApp, Facebook et « Copier le lien ». L'aperçu affiche la photo, le titre et le loyer."],
    ["Installer l'application", "Dans Chrome ou Edge (ordinateur, Android), le bouton « Installer » en haut de l'écran ajoute BailManager comme une application. Sur iPhone : Partager > « Sur l'écran d'accueil »."],
    ["Sauvegarde hors du serveur", "Paramètres > Sauvegarde : choisissez un dossier (disque externe, Google Drive / OneDrive synchronisé), WebDAV ou S3, et une phrase de chiffrement. La tâche quotidienne y dépose une copie chiffrée ; « Sauvegarder maintenant » le fait à la demande."],
    ["Espace bailleur", "Sur la fiche bailleur, « Générer un code » (administrateur) : le propriétaire se connecte sur /espace-bailleur avec son téléphone et ce code pour suivre son solde, ses versements, l'occupation de ses biens, imprimer ses relevés et consulter les quittances de ses locataires."],
    ["Recouvrement", "Menu Contrats & loyers > Recouvrement : tous les locataires en retard. « Rappel amiable », puis « Relance », puis « Mise en demeure » créent la lettre du niveau suivant, prête à imprimer. « Plan » étale la dette en mensualités et imprime l'accord à faire signer ; le suivi des échéances apparaît sur la fiche du contrat."],
    ["Espace locataire", "Sur la fiche locataire, « Générer un code » (administrateur) : le locataire se connecte sur /espace-locataire avec son téléphone et ce code pour voir ses paiements, télécharger ses quittances, consulter ses charges et écrire à l'agence (menu Messagerie locataires)."],
  ] },
  { id: "pilotage", titre: "Pilotage et sécurité", lignes: [
    ["Tableau de bord", "L'échéancier du mois montre chaque loyer attendu : vert payé, orange partiel, rouge en retard, gris à venir. Les flèches changent de mois."],
    ["Recherche", "Ctrl + K (ou la barre en haut) cherche bailleurs, locataires, maisons, contrats et numéros de reçu."],
    ["Rapports", "Journalier, mensuel, annuel, période, occupation, impayés, relevé bailleur : impression, PDF et export Excel."],
    ["Journal et sauvegardes", "Toutes les opérations sont journalisées (utilisateur, date, IP). Paramètres > Sauvegarde télécharge une archive complète ; une sauvegarde quotidienne automatique se planifie à l'installation."],
  ] },
];

export default function Guide() {
  const [actif, setActif] = useState(SECTIONS[0].id);
  const s = SECTIONS.find((x) => x.id === actif);
  return (
    <>
      <EntetePage titre="Guide d'utilisation" soustitre="Les règles de gestion et les gestes du quotidien, module par module." />
      <div className="grille grille-guide">
        <Panneau serre>
          <nav style={{ padding: 6 }}>
            {SECTIONS.map((x) => (
              <button key={x.id} className={`btn bloc ${x.id === actif ? "primaire" : "fantome"}`} style={{ justifyContent: "flex-start", marginBottom: 2 }} onClick={() => setActif(x.id)}>{x.titre}</button>
            ))}
          </nav>
        </Panneau>
        <Panneau titre={s.titre}>
          {s.lignes.map(([t, txt]) => (
            <div key={t} style={{ marginBottom: 18, maxWidth: 760 }}>
              <h3 style={{ marginBottom: 4 }}>{t}</h3>
              <p className="discret" style={{ margin: 0, lineHeight: 1.6 }}>{txt}</p>
            </div>
          ))}
          <p className="petit-texte discret">Une question ? <Link to="/a-propos">Contactez le support</Link>.</p>
        </Panneau>
      </div>
    </>
  );
}
