import { useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, ArrowRight, Download, Printer } from "lucide-react";
import { Link } from "react-router-dom";
import { api } from "../lib/api";
import { Bouton, Chargement, Encart } from "./ui";
import { Marque } from "../pages/public/Site";

/** Paramètres de l'entreprise (en-têtes et pieds de page des documents). */
export function useEntreprise(fournie, charger = true) {
  const q = useQuery({ queryKey: ["parametres"], queryFn: () => api.get("/parametres/"), enabled: !fournie && charger, staleTime: 300000 });
  return fournie || q.data;
}

/** En-tête aux couleurs du logiciel : marque (ou logo) à gauche, référence et date dans un encadré à droite. */
export function EnteteDoc({ e, reference, date }) {
  if (!e) return null;
  return (
    <>
      <div className="doc-entete">
        <div className="doc-marque">
          {e.logo ? <img src={e.logo} alt="" /> : <span className="doc-logo">{(e.nom_entreprise || "B").trim()[0]}</span>}
          <div>
            <div className="nom"><Marque nom={e.nom_entreprise} /></div>
            {e.activites && <div className="act">{e.activites}</div>}
            <div className="act">{[e.contact_telephone, e.contact_email].filter(Boolean).join(" · ")}</div>
          </div>
        </div>
        {(reference || date) && (
          <div className="ref">
            {reference && <b>{reference}</b>}
            {date && <span>{date}</span>}
          </div>
        )}
      </div>
      <div className="doc-filet" aria-hidden="true" />
    </>
  );
}

export function PiedDoc({ e }) {
  if (!e) return null;
  return (
    <div className="pied">
      <span className="pied-marque"><Marque nom={e.nom_entreprise} /></span>
      {e.pied_de_page?.length > 0 && <span className="pied-lignes">{e.pied_de_page.join("  ·  ")}</span>}
    </div>
  );
}

/** Conteneur écran + barre d'outils (non imprimée) autour d'une ou plusieurs pages A4. */
export function Imprimable({ titre, chargement, erreur, children, surExcel, suite, outils }) {
  useEffect(() => {
    if (titre) document.title = titre;
  }, [titre]);
  return (
    <div className="doc-ecran">
      <div className="doc-barre non-imprime">
        <Bouton variante="primaire" icone={Printer} onClick={() => window.print()}>Imprimer / PDF</Bouton>
        {surExcel && <Bouton icone={Download} onClick={surExcel}>Excel</Bouton>}
        {suite && <Link className="btn" to={suite.lien}>{suite.libelle} <ArrowRight size={16} /></Link>}
        {outils}
        <span className="espace" />
        <Bouton icone={ArrowLeft} onClick={() => (window.history.length > 1 ? window.history.back() : (window.location.href = "/tableau-de-bord"))}>Retour</Bouton>
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
