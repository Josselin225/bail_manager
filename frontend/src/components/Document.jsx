import { useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { Download, Printer, X } from "lucide-react";
import { api } from "../lib/api";
import { Bouton, Chargement, Encart } from "./ui";

/** Paramètres de l'entreprise (en-têtes et pieds de page des documents). */
export function useEntreprise(fournie) {
  const q = useQuery({ queryKey: ["parametres"], queryFn: () => api.get("/parametres/"), enabled: !fournie, staleTime: 300000 });
  return fournie || q.data;
}

export function EnteteDoc({ e, reference, date }) {
  if (!e) return null;
  return (
    <div className="doc-entete">
      {e.logo && <img src={e.logo} alt="" />}
      <div>
        <div className="nom">{e.nom_entreprise}</div>
        {e.activites && <div className="act">{e.activites}</div>}
        <div className="act">{[e.contact_telephone, e.contact_email].filter(Boolean).join(" — ")}</div>
      </div>
      {(reference || date) && (
        <div className="ref">
          {reference && <div><b>{reference}</b></div>}
          {date && <div>{date}</div>}
        </div>
      )}
    </div>
  );
}

export function PiedDoc({ e }) {
  if (!e?.pied_de_page?.length) return null;
  return <div className="pied">{e.pied_de_page.map((l) => <div key={l}>{l}</div>)}</div>;
}

/** Conteneur écran + barre d'outils (non imprimée) autour d'une ou plusieurs pages A4. */
export function Imprimable({ titre, chargement, erreur, children, surExcel }) {
  useEffect(() => {
    if (titre) document.title = titre;
  }, [titre]);
  return (
    <div className="doc-ecran">
      <div className="doc-barre non-imprime">
        <Bouton variante="primaire" icone={Printer} onClick={() => window.print()}>Imprimer / PDF</Bouton>
        {surExcel && <Bouton icone={Download} onClick={surExcel}>Excel</Bouton>}
        <span className="espace" />
        <Bouton icone={X} onClick={() => (window.history.length > 1 ? window.history.back() : window.close())}>Fermer</Bouton>
      </div>
      {erreur ? (
        <div style={{ maxWidth: "210mm", margin: "0 auto" }}><Encart ton="alerte">{erreur.message}</Encart></div>
      ) : chargement ? (
        <Chargement />
      ) : (
        children
      )}
    </div>
  );
}
