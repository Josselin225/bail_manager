import { useQuery } from "@tanstack/react-query";
import { QRCodeSVG } from "qrcode.react";
import { CalendarClock, Database, HardDrive, Network } from "lucide-react";
import { api } from "../lib/api";
import { dateHeure } from "../lib/format";
import { Badge, Chargement, Definitions, Encart, Panneau } from "./ui";

const octets = (n) => (n == null ? "—" : n > 1e9 ? `${(n / 1e9).toFixed(1)} Go` : n > 1e6 ? `${(n / 1e6).toFixed(1)} Mo` : `${Math.round(n / 1e3)} Ko`);
const HEURES = 26 * 3600 * 1000;

/** Âge d'une tâche quotidienne : alerte si elle n'a pas tourné depuis plus d'un jour. */
function Tache({ t, libelle }) {
  if (!t) return <Badge ton="attention">Jamais lancée</Badge>;
  const vieille = Date.now() - new Date(t.date).getTime() > HEURES;
  return <><Badge ton={vieille ? "attention" : "ok"}>{vieille ? "En retard" : "À jour"}</Badge> <span className="petit-texte discret">{libelle} le {dateHeure(t.date)}</span></>;
}

/** Paramètres > Serveur : comment joindre le logiciel depuis les autres postes, et santé de l'installation locale. */
export default function Serveur() {
  const { data: s } = useQuery({ queryKey: ["systeme"], queryFn: () => api.get("/systeme/") });
  if (!s) return <Chargement />;
  const libre = s.disque.libre / s.disque.total;
  return (
    <div className="pile">
      <Panneau titre="Accès depuis les autres postes" icone={Network}>
        {s.adresses.length === 0 ? (
          <Encart ton="attention">Ce poste n'est relié à aucun réseau : seul ce PC peut ouvrir BailManager.</Encart>
        ) : (
          <>
            <p className="discret" style={{ marginTop: 0 }}>Sur les ordinateurs, tablettes et téléphones connectés au même réseau (câble ou Wi-Fi de l'agence), ouvrez cette adresse puis ajoutez-la aux favoris ou installez l'application. Pour qu'elle ne change pas, donnez une adresse IP fixe à ce PC.</p>
            <div className="acces-reseau">
              <div className="adresse-reseau">
                <QRCodeSVG value={s.adresses[0]} size={112} marginSize={1} />
                <div><div className="petit-texte discret">Adresse réseau</div><b className="adresse">{s.adresses[0]}</b><div className="petit-texte discret">Scannez le code avec un téléphone de l'agence.</div></div>
              </div>
            </div>
            {s.adresses.length > 1 && <p className="petit-texte discret">Si elle ne répond pas depuis un poste, essayez : {s.adresses.slice(1).join(", ")} (autres cartes réseau de ce PC).</p>}
          </>
        )}
        <Encart ton="info">Le logiciel est installé localement : depuis l'extérieur de l'agence, ni les locataires ni les propriétaires ne peuvent joindre leur espace en ligne, et l'agrégateur de paiement ne peut pas confirmer un paiement Mobile Money tant que BailManager n'est pas mis en ligne.</Encart>
      </Panneau>
      <div className="grille g2">
        <Panneau titre="Installation" icone={Database}>
          <Definitions items={[
            ["Version", s.version],
            ["Poste serveur", s.poste],
            ["Base de données", `${s.base.moteur} — ${s.base.nom}`],
            ["Taille de la base", octets(s.base.taille)],
            ["Espace disque libre", <span>{octets(s.disque.libre)} sur {octets(s.disque.total)} {libre < 0.1 && <Badge ton="alerte">Presque plein</Badge>}</span>],
          ]} />
        </Panneau>
        <Panneau titre="Tâches et sauvegardes" icone={CalendarClock}>
          <Definitions items={[
            ["Rappels quotidiens", <Tache t={s.taches.rappels} libelle="dernier passage" />],
            ["Sauvegarde locale", <Tache t={s.taches.sauvegarde} libelle="dernière" />],
            ["Copies conservées", `${s.sauvegardes_locales.nombre} dans ${s.sauvegardes_locales.dossier}`],
            ["Copie hors du serveur", s.copie_hors_serveur.date ? <span>{s.copie_hors_serveur.statut.startsWith("Échec") ? <Badge ton="alerte">Échec</Badge> : <Badge ton="ok">Réussie</Badge>} <span className="petit-texte discret">{dateHeure(s.copie_hors_serveur.date)} — {s.copie_hors_serveur.destination}</span></span> : s.copie_hors_serveur.destination],
          ]} />
          {(!s.taches.rappels || !s.taches.sauvegarde) && (
            <p className="petit-texte discret" style={{ marginBottom: 0 }}><HardDrive size={13} /> Pour automatiser rappels et sauvegardes, lancez une fois <b>scripts-windows\3-configurer-serveur.bat</b> en administrateur sur ce PC.</p>
          )}
        </Panneau>
      </div>
    </div>
  );
}
