import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { BadgeCheck, KeyRound, Lock, ShieldAlert } from "lucide-react";
import { api } from "../lib/api";
import { dateFr, dateHeure } from "../lib/format";
import { useSession } from "../lib/session";
import { Badge, Bouton, Definitions, Encart, Panneau, Zone, useToast } from "./ui";

const STATUTS = {
  essai: ["info", "Période d'essai"], valide: ["ok", "Valide"], alerte: ["attention", "Bientôt expirée"],
  grace: ["alerte", "Expirée — période de grâce"], expiree: ["alerte", "Expirée — lecture seule"],
};

/** Bandeau en haut de l'interface : fin d'essai ou de licence proche, grâce, lecture seule. */
export function BandeauLicence() {
  const { licence: l, estAdmin } = useSession();
  if (!l || l.statut === "valide" || (l.statut === "essai" && l.jours > 7)) return null;
  const quoi = l.essai ? "La période d'essai" : "La licence";
  const lien = estAdmin ? <Link to="/parametres?onglet=licence">Installer une licence</Link> : "Prévenez l'administrateur.";
  if (l.lecture_seule) {
    return <div className="bandeau-licence expiree" role="alert"><Lock size={16} /> <span><b>BailManager est en lecture seule</b> : {quoi.toLowerCase()} a pris fin le {dateFr(l.expire_le)}. Tout reste consultable, imprimable et sauvegardable, mais plus rien ne s'enregistre. {lien}</span></div>;
  }
  if (l.statut === "grace") {
    return <div className="bandeau-licence grace" role="alert"><ShieldAlert size={16} /> <span>{quoi} a expiré le {dateFr(l.expire_le)}. Le logiciel passera en lecture seule le <b>{dateFr(l.fin_grace)}</b>. {lien}</span></div>;
  }
  return <div className="bandeau-licence alerte"><ShieldAlert size={16} /> <span>{quoi} se termine le <b>{dateFr(l.expire_le)}</b> (dans {l.jours} jour{l.jours > 1 ? "s" : ""}). {lien}</span></div>;
}

/** Paramètres > Licence : état, limites, modules, installation d'une nouvelle licence. */
export default function Licence() {
  const toast = useToast();
  const { estAdmin, rafraichir } = useSession();
  const { data: e, refetch } = useQuery({ queryKey: ["licence"], queryFn: () => api.get("/licence/") });
  const [texte, setTexte] = useState("");
  const [envoi, setEnvoi] = useState(false);
  if (!e) return null;
  const installer = async () => {
    setEnvoi(true);
    try {
      await api.post("/licence/", { texte });
      setTexte("");
      toast.succes("Licence installée.");
      refetch();
      rafraichir();
    } catch (err) {
      toast.erreur(err.message);
    } finally {
      setEnvoi(false);
    }
  };
  const limite = (maxi, utilise) => (maxi == null ? `${utilise} (illimité)` : <span>{utilise} sur {maxi} {utilise >= maxi && <Badge ton="attention">Limite atteinte</Badge>}</span>);
  return (
    <div className="pile">
      <Panneau titre="Licence de cette installation" icone={BadgeCheck}>
        {e.erreur && <Encart ton="alerte" titre="Licence enregistrée illisible">{e.erreur}</Encart>}
        <Definitions items={[
          ["État", <Badge ton={STATUTS[e.statut][0]}>{STATUTS[e.statut][1]}</Badge>],
          ["Titulaire", e.essai ? "— (essai de 30 jours)" : e.agence],
          ["N° de licence", e.numero || "—"],
          [e.essai ? "Fin de l'essai" : "Valable jusqu'au", <span>{dateFr(e.expire_le)} <span className="discret">({e.jours >= 0 ? `dans ${e.jours} j` : `depuis ${-e.jours} j`})</span></span>],
          ["Lecture seule à partir du", dateFr(e.fin_grace)],
          ["Utilisateurs actifs", limite(e.utilisateurs, e.utilises.utilisateurs)],
          ["Biens gérés", limite(e.biens, e.utilises.biens)],
          ["Installée le", e.installee_le ? dateHeure(e.installee_le) : "—"],
        ]} />
        <h3 className="sous-titre-panneau">Modules</h3>
        <div className="modules-licence">
          {Object.entries(e.modules_libelles).map(([cle, libelle]) => (
            <div key={cle} className={e.modules.includes(cle) ? "inclus" : ""}>
              <Badge ton={e.modules.includes(cle) ? "ok" : ""}>{e.modules.includes(cle) ? "Inclus" : "Non inclus"}</Badge> {libelle}
            </div>
          ))}
        </div>
        <p className="petit-texte discret" style={{ marginBottom: 0 }}>À l'échéance : alerte 30 jours avant, 15 jours de grâce, puis lecture seule. Les données ne sont jamais bloquées : consultation, impression, export et sauvegarde restent possibles.</p>
      </Panneau>
      {estAdmin && (
        <Panneau titre="Installer une licence" icone={KeyRound}>
          <p className="discret" style={{ marginTop: 0 }}>Collez ici le texte de licence remis par votre fournisseur (renouvellement, extension d'utilisateurs, de biens ou de modules). Il remplace la licence actuelle.</p>
          <div className="formulaire">
            <Zone libelle="Texte de la licence" plein valeur={texte} onChange={setTexte} rows={4} placeholder="eyJudW1lcm8iOi…" />
          </div>
          <div style={{ marginTop: 12 }}><Bouton variante="primaire" icone={KeyRound} chargement={envoi} disabled={!texte.trim()} onClick={installer}>Installer la licence</Bouton></div>
        </Panneau>
      )}
    </div>
  );
}
