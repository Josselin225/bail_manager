import { useQuery } from "@tanstack/react-query";
import {
  AlarmClock, BarChart3, BookOpen, Building2, CalendarCheck, CalendarDays, CircleUserRound, ClipboardList, Droplets, FileSignature, Gauge,
  GitCompareArrows, HandCoins, Handshake, History, Home, Info, KeyRound, Landmark, LayoutDashboard, LayoutGrid, LifeBuoy, Mail, MessagesSquare,
  Receipt, Settings, ShieldCheck, TrendingUp, UserRound, Users, UsersRound, Wallet, Wrench, Zap,
} from "lucide-react";
import { api } from "./api";

/*
 * Navigation de l'interface de gestion : partagée par le menu latéral et la page « Menu ».
 * `admin` : réservé à l'administrateur ; `module` : masqué si la licence ne l'inclut pas ;
 * `compteur` : pastille alimentée par useCompteurs ; `menuSeul` : affiché seulement sur la page Menu.
 */
export const NAV = [
  { lien: "/tableau-de-bord", libelle: "Tableau de bord", icone: LayoutDashboard, description: "Chiffres clés, loyers du mois et alertes." },
  { lien: "/menu", libelle: "Menu", icone: LayoutGrid, description: "Toutes les fonctions du logiciel sur une page." },
  {
    groupe: "Patrimoine",
    icone: Building2,
    liens: [
      { lien: "/bailleurs", libelle: "Bailleurs", icone: UserRound, description: "Propriétaires, mandats de gestion, relevés et versements." },
      { lien: "/maisons", libelle: "Maisons", icone: Home, description: "Biens confiés, photos, loyer et publication sur le site." },
      { lien: "/locataires", libelle: "Locataires", icone: Users, description: "Dossiers, pièces d'identité et accès à l'espace locataire." },
    ],
  },
  {
    groupe: "Contrats & loyers",
    icone: ClipboardList,
    liens: [
      { lien: "/contrats", libelle: "Contrats de bail", icone: FileSignature, description: "Baux, échéanciers, états des lieux, préavis et résiliations." },
      { lien: "/encaissements", libelle: "Encaissements", icone: HandCoins, description: "Loyers reçus, quittances et registre des paiements." },
      { lien: "/recouvrement", libelle: "Recouvrement", icone: AlarmClock, description: "Impayés, relances graduées et plans d'apurement." },
      { lien: "/calendrier", libelle: "Calendrier", icone: CalendarDays, description: "Échéances, visites, fins de bail et de mandat." },
    ],
  },
  {
    groupe: "Gestion locative",
    icone: KeyRound,
    liens: [
      { lien: "/charges", libelle: "Charges locatives", icone: Zap, description: "Eau, électricité, entretien et réparations par bail." },
      { lien: "/compteurs", libelle: "Compteurs partagés", icone: Droplets, module: "compteurs", description: "Répartition des factures SODECI et CIE d'une cour." },
      { lien: "/revisions", libelle: "Révisions de loyer", icone: TrendingUp, description: "Baux à réviser, historique et lettres aux locataires." },
      { lien: "/interventions", libelle: "Interventions", icone: Wrench, compteur: "interventions", description: "Pannes signalées, artisans et coût des travaux." },
    ],
  },
  {
    groupe: "Finances",
    icone: Landmark,
    liens: [
      { lien: "/comptes-bailleurs", libelle: "Comptes bailleurs", icone: Wallet, description: "Sommes dues à chaque propriétaire et versements." },
      { lien: "/caisse", libelle: "Caisse agence", icone: Receipt, description: "Commissions, frais d'agence, entrées et sorties." },
      { lien: "/rapprochement", libelle: "Rapprochement", icone: GitCompareArrows, module: "rapprochement", description: "Relevés Mobile Money et bancaires encaissés en un clic." },
      { lien: "/cautions", libelle: "Cautions", icone: ShieldCheck, description: "Dépôts de garantie détenus, retenues et restitutions." },
    ],
  },
  {
    groupe: "Relations",
    icone: Handshake,
    liens: [
      { lien: "/reservations", libelle: "Visites", icone: CalendarCheck, compteur: "reservations", description: "Agenda des visites et demandes reçues du site." },
      { lien: "/messages", libelle: "Messages du site", icone: Mail, compteur: "messages", description: "Formulaire de contact du site vitrine." },
      { lien: "/messagerie", libelle: "Messagerie locataires", icone: MessagesSquare, compteur: "locataires", description: "Échanges avec les locataires depuis leur espace." },
    ],
  },
  {
    groupe: "Pilotage",
    icone: Gauge,
    liens: [
      { lien: "/rapports", libelle: "Rapports", icone: BarChart3, description: "Rapports imprimables et exports Excel." },
      { lien: "/equipe", libelle: "Activité de l'équipe", icone: UsersRound, description: "Encaissements, baux, relances et visites par agent." },
      { lien: "/journal", libelle: "Journal d'activité", icone: History, admin: true, description: "Toutes les opérations : qui, quand, quoi." },
    ],
  },
  {
    groupe: "Administration",
    icone: Settings,
    menuSeul: true,
    liens: [
      { lien: "/parametres", libelle: "Paramètres", icone: Settings, admin: true, description: "Agence, clauses, connecteurs, sauvegarde, serveur, licence." },
      { lien: "/utilisateurs", libelle: "Utilisateurs", icone: Users, admin: true, description: "Comptes du personnel et droits." },
      { lien: "/profil", libelle: "Mon profil", icone: CircleUserRound, description: "Vos informations, photo et mot de passe." },
    ],
  },
  {
    groupe: "Aide",
    icone: LifeBuoy,
    liens: [
      { lien: "/guide", libelle: "Guide d'utilisation", icone: BookOpen, description: "Chaque écran expliqué pas à pas, avec captures." },
      { lien: "/a-propos", libelle: "À propos", icone: Info, description: "Version, éditeur et support." },
    ],
  },
];

/** Lien visible pour cet utilisateur et cette licence. */
export const lienVisible = (l, estAdmin, licence) => (!l.admin || estAdmin) && (!l.module || !licence || licence.modules.includes(l.module));

/** Pastilles du menu : visites à confirmer, messages non lus, interventions nouvelles. */
export function useCompteurs(actif) {
  return useQuery({
    queryKey: ["compteurs"],
    enabled: actif,
    refetchInterval: 60000,
    queryFn: async () => {
      const [r, m, l, i] = await Promise.all([
        api.get("/reservations/", { statut: "en_attente", taille: 1 }),
        api.get("/messages/", { lu: false, taille: 1 }),
        api.get("/messagerie/compteur/"),
        api.get("/interventions/compteur/"),
      ]);
      return { reservations: r.count, messages: m.count, locataires: l.non_lus, interventions: i.nouvelles };
    },
  });
}

// ─── Pages récentes et favoris (propres à ce navigateur) ───
const lire = (cle) => { try { return JSON.parse(localStorage.getItem(cle) || "[]"); } catch { return []; } };
const ecrire = (cle, v) => { try { localStorage.setItem(cle, JSON.stringify(v)); } catch { /* stockage indisponible */ } };
const TOUS_LIENS = () => NAV.flatMap((x) => (x.liens ? x.liens : [x]));

export function noterVisite(chemin) {
  const lien = TOUS_LIENS().find((l) => chemin === l.lien || chemin.startsWith(`${l.lien}/`));
  if (!lien || lien.lien === "/menu") return;
  ecrire("bm-recents", [lien.lien, ...lire("bm-recents").filter((x) => x !== lien.lien)].slice(0, 6));
}
export const recents = () => lire("bm-recents");
export const favoris = () => lire("bm-favoris");
export const basculerFavori = (lien) => {
  const f = favoris();
  const nouveaux = f.includes(lien) ? f.filter((x) => x !== lien) : [...f, lien];
  ecrire("bm-favoris", nouveaux);
  return nouveaux;
};
export const trouverLien = (chemin) => TOUS_LIENS().find((l) => l.lien === chemin);
