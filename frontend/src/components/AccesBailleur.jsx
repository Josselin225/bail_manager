import { useState } from "react";
import { KeyRound } from "lucide-react";
import { api } from "../lib/api";
import { useAction } from "../lib/hooks";
import { useSession } from "../lib/session";
import { Badge, Bouton, Fenetre, Panneau, useConfirmer } from "./ui";

/** Accès d'un bailleur à son espace en ligne : génération (code affiché une seule fois) et désactivation. */
export default function AccesBailleur({ b, onChange }) {
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const [code, setCode] = useState(null);
  const generer = useAction(() => api.post(`/bailleurs/${b.id}/generer-code/`), { apres: (r) => (setCode(r), onChange()) });
  const desactiver = useAction(() => api.post(`/bailleurs/${b.id}/desactiver-acces/`), { succes: "Accès désactivé.", apres: () => onChange() });
  return (
    <Panneau titre="Espace bailleur" icone={KeyRound}>
      <p className="discret" style={{ marginTop: 0 }}>Le propriétaire se connecte sur <b>/espace-bailleur</b> avec son téléphone ({b.telephone1 || "non renseigné"}) et un code d'accès : solde, relevés, versements, occupation de ses biens et quittances.</p>
      <div className="ligne-flex">
        {b.acces_portail ? <Badge ton="ok">Accès activé</Badge> : <Badge>Pas d'accès</Badge>}
        <span className="espace" />
        {estAdmin && (
          <>
            {b.acces_portail && <Bouton taille="petit" onClick={() => desactiver.mutate()}>Désactiver</Bouton>}
            <Bouton taille="petit" variante="primaire" chargement={generer.isPending} onClick={async () => (!b.acces_portail || (await confirmer("Générer un nouveau code ? L'ancien ne fonctionnera plus."))) && generer.mutate()}>
              {b.acces_portail ? "Nouveau code" : "Générer un code"}
            </Bouton>
          </>
        )}
      </div>
      <Fenetre titre="Code d'accès à l'espace bailleur" ouvert={!!code} onFermer={() => setCode(null)} taille="etroite" pied={<Bouton variante="primaire" onClick={() => setCode(null)}>J'ai noté le code</Bouton>}>
        <p>Communiquez ces informations à <b>{code?.nom}</b>. Le code n'est affiché qu'une seule fois.</p>
        <div className="panneau" style={{ padding: 16, textAlign: "center", boxShadow: "none" }}>
          <div className="discret">Téléphone</div>
          <div className="gras" style={{ fontSize: 18 }}>{code?.telephone}</div>
          <div className="discret" style={{ marginTop: 10 }}>Code d'accès</div>
          <div style={{ fontSize: 30, fontWeight: 800, letterSpacing: "0.2em" }}>{code?.code}</div>
        </div>
      </Fenetre>
    </Panneau>
  );
}
