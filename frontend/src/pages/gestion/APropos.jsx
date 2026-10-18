import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { BadgeCheck, BookOpen, Globe, HardDrive, Mail, MessageCircle, Phone, ShieldCheck, Sparkles } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr } from "../../lib/format";
import { useSession } from "../../lib/session";
import { Badge, EntetePage, Panneau } from "../../components/ui";
import IllustrationMenu from "../../components/IllustrationsMenu";

const FONCTIONS = [
  ["/maisons", "Patrimoine sous mandat", "Bailleurs, mandats de gestion, maisons et locataires ; aucun bien ni bail sans mandat en vigueur."],
  ["/contrats", "Baux conformes", "Contrats selon la loi n° 2019-576, états des lieux sur tablette, préavis, révisions triennales."],
  ["/encaissements", "Loyers et quittances", "Encaissements, quittances avec QR code, répartition automatique bailleur / commission."],
  ["/recouvrement", "Recouvrement", "Relances graduées jusqu'à la mise en demeure et plans d'apurement suivis."],
  ["/rapprochement", "Mobile Money et banque", "Relevés Wave, Orange, MTN, Moov et bancaires rapprochés et encaissés en un clic."],
  ["/comptes-bailleurs", "Comptes et caisse", "Comptes courants des propriétaires, versements, caisse de l'agence, cautions."],
  ["/reservations", "Relations", "Site vitrine, agenda des visites, espaces en ligne des locataires et des propriétaires."],
  ["/rapports", "Pilotage", "Tableau de bord, rapports imprimables et Excel, activité de l'équipe, journal."],
];

const NOUVEAUTES = [
  "Page Menu : toutes les fonctions en tuiles, liste ou illustrations, par rubrique ou de A à Z.",
  "Guide d'utilisation illustré : 41 fiches avec captures annotées, recherche et impression.",
  "Rapprochement des relevés Mobile Money et bancaires, sans double encaissement.",
  "Compteurs partagés : factures SODECI / CIE réparties entre les logements d'une cour.",
  "Agenda des visites et état des lieux signé au doigt sur tablette.",
  "Révision triennale repérée automatiquement, avec la lettre au locataire.",
  "Activité de l'équipe, licence par agence, serveur local configuré en un script.",
];

const STATUTS = { essai: ["info", "Période d'essai"], valide: ["ok", "Valide"], alerte: ["attention", "Bientôt expirée"], grace: ["alerte", "Période de grâce"], expiree: ["alerte", "Expirée — lecture seule"] };

/** À propos : le logiciel, la licence de l'agence, les nouveautés, le support et les mentions. */
export default function APropos() {
  const { version, entreprise, estAdmin } = useSession();
  const { data: l } = useQuery({ queryKey: ["licence"], queryFn: () => api.get("/licence/") });
  return (
    <>
      <EntetePage titre="À propos" soustitre="Le logiciel, votre licence, les nouveautés et le support." />
      <div className="pile">
        <section className="hero-apropos">
          <div className="logo-apropos" aria-hidden="true">B</div>
          <div>
            <span className="sur-titre-section">Gestion locative, financière et comptable</span>
            <h1>Bail<span>Manager</span></h1>
            <p>Le registre de l'agence, toujours à jour : du mandat du propriétaire à la quittance du locataire, conçu pour les agences immobilières de Côte d'Ivoire.</p>
            <div className="pastilles-apropos">
              <span>Version {version}</span>
              <span>Installation locale</span>
              <span>Loi n° 2019-576</span>
            </div>
          </div>
        </section>

          <Panneau titre="Ce que fait BailManager" icone={Sparkles}>
            <div className="fonctions-apropos">
              {FONCTIONS.map(([lien, titre, texte]) => (
                <Link key={lien} to={lien} className="fonction-apropos">
                  <IllustrationMenu lien={lien} taille={52} />
                  <span><b>{titre}</b><span>{texte}</span></span>
                </Link>
              ))}
            </div>
          </Panneau>
        <div className="grille g3 bas-apropos">
          <Panneau titre="Votre licence" icone={BadgeCheck} actions={estAdmin && <Link to="/parametres?onglet=licence" className="petit-texte">Gérer</Link>}>
            {!l ? <p className="discret">Chargement…</p> : (
              <dl className="definitions">
                <dt>État</dt><dd><Badge ton={STATUTS[l.statut][0]}>{STATUTS[l.statut][1]}</Badge></dd>
                <dt>Titulaire</dt><dd>{l.essai ? entreprise?.nom || "—" : l.agence}</dd>
                <dt>{l.essai ? "Fin de l'essai" : "Valable jusqu'au"}</dt><dd>{dateFr(l.expire_le)}</dd>
                <dt>Utilisateurs</dt><dd>{l.utilises.utilisateurs}{l.utilisateurs != null ? ` sur ${l.utilisateurs}` : " (illimité)"}</dd>
                <dt>Biens gérés</dt><dd>{l.utilises.biens}{l.biens != null ? ` sur ${l.biens}` : " (illimité)"}</dd>
                <dt>Modules</dt><dd>{l.modules.length} sur {Object.keys(l.modules_libelles).length}</dd>
              </dl>
            )}
          </Panneau>
          <Panneau titre="Support" icone={MessageCircle}>
            <p className="discret" style={{ marginTop: 0 }}>Une difficulté, une suggestion, une licence à renouveler ? Contactez directement le développeur.</p>
            <div className="contacts-apropos">
              <a className="btn" href="tel:+2250749791287"><Phone size={16} /> 07 49 79 12 87</a>
              <a className="btn" href="tel:+2250100014330"><Phone size={16} /> 01 00 01 43 30</a>
              <a className="btn partage-whatsapp" href="https://wa.me/2250749791287"><MessageCircle size={16} /> WhatsApp</a>
              <a className="btn" href="mailto:kkjoss01@gmail.com"><Mail size={16} /> kkjoss01@gmail.com</a>
            </div>
            <Link to="/guide" className="btn primaire bloc" style={{ marginTop: 12 }}><BookOpen size={16} /> Ouvrir le guide d'utilisation</Link>
          </Panneau>
          <Panneau titre="Données et mentions" icone={ShieldCheck}>
            <ul className="mentions-apropos">
              <li><HardDrive size={15} /> <span>Les données de l'agence restent sur son propre serveur : rien n'est envoyé ailleurs, sauf la copie de sauvegarde chiffrée si vous l'activez.</span></li>
              <li><ShieldCheck size={15} /> <span>Mots de passe et clés des fournisseurs chiffrés, journal de toutes les opérations, droits par rôle.</span></li>
              <li><Globe size={15} /> <span>Photos du site vitrine : Unsplash (licence libre, usage commercial autorisé). Elles illustrent le site et ne représentent pas des biens gérés.</span></li>
              <li><Sparkles size={15} /> <span>Construit avec Django (Python) et React ; base SQLite ou MySQL / MariaDB.</span></li>
            </ul>
            <p className="petit-texte discret" style={{ marginBottom: 0 }}>© {new Date().getFullYear()} BailManager — tous droits réservés.</p>
          </Panneau>
        </div>
          <Panneau titre={`Nouveautés de la version ${version}`} icone={Sparkles}>
            <ul className="nouveautes-apropos colonnes">{NOUVEAUTES.map((n) => <li key={n}>{n}</li>)}</ul>
          </Panneau>
      </div>
    </>
  );
}
