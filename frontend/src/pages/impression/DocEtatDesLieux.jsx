import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, dateLongue } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

const ORDRE_ETATS = ["neuf", "bon", "usage", "mauvais"];
const COURTS = { neuf: "Neuf", bon: "Bon", usage: "Usagé", mauvais: "Mauvais" };

/**
 * État des lieux imprimable, annexé au bail. S'il n'a pas été saisi, la grille s'imprime vierge
 * pour être remplie à la main avec le locataire.
 */
export default function DocEtatDesLieux() {
  const { id, type = "entree" } = useParams();
  const e = useEntreprise();
  const { data: d, isLoading, error } = useQuery({ queryKey: ["etat-des-lieux", id, type], queryFn: () => api.get(`/contrats/${id}/etat-des-lieux/${type}/`) });
  const vierge = d && !d.etabli;
  return (
    <Imprimable titre={d ? `${d.libelle} — ${d.contrat.reference}` : "État des lieux"} chargement={isLoading || !e} erreur={error}>
      {d && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Annexe au contrat ${d.contrat.reference}`} date={vierge ? "Date : ____ / ____ / ________" : `Établi le ${dateFr(d.date_etat)}`} />
          <h1>{d.libelle}</h1>
          <p className="centre">Établi contradictoirement entre les parties — annexé au contrat de bail n° {d.contrat.reference}</p>
          <div className="deux">
            <div className="cadre">
              <b>Le bien</b><br />
              <strong>{d.bien.designation}</strong>{d.bien.type && ` — ${d.bien.type}`}<br />
              {d.bien.adresse}<br />
              Propriétaire : {d.bailleur.nom}, représenté par {e.nom_entreprise}{d.contrat.mandat_reference && ` (mandat ${d.contrat.mandat_reference})`}
            </div>
            <div className="cadre">
              <b>Le locataire (preneur)</b><br />
              <strong>{d.locataire.nom}</strong><br />
              {d.locataire.telephone && <>Tél. : {d.locataire.telephone}<br /></>}
              {d.locataire.piece_identite && <>Pièce d'identité : {d.locataire.piece_identite}<br /></>}
              Entrée dans les lieux : {dateFr(d.contrat.date_debut)}
            </div>
          </div>

          <h2>Relevés à la remise des clés</h2>
          <table>
            <tbody>
              <tr>
                <th>Compteur d'eau</th><td>{d.index_eau || <span className="a-remplir" />}</td>
                <th>Compteur d'électricité</th><td>{d.index_electricite || <span className="a-remplir" />}</td>
                <th>Clés remises</th><td>{d.etabli ? d.nombre_cles : <span className="a-remplir court" />}</td>
              </tr>
            </tbody>
          </table>

          <h2>État des pièces</h2>
          <p className="legende-edl">Cochez l'état constaté pour chaque élément : Neuf · Bon · Usagé (usure normale) · Mauvais (dégradé, cassé ou manquant).</p>
          {d.pieces.map((p, i) => (
            <table key={i} className="table-edl">
              <thead>
                <tr>
                  <th className="piece">{p.nom}</th>
                  {ORDRE_ETATS.map((k) => <th key={k} className="case-etat">{COURTS[k]}</th>)}
                  <th>Observations</th>
                </tr>
              </thead>
              <tbody>
                {p.elements.map((el, j) => (
                  <tr key={j}>
                    <td className="element">{el.nom}</td>
                    {ORDRE_ETATS.map((k) => <td key={k} className="case-etat"><span className={`case ${el.etat === k ? "cochee" : ""}`}>{el.etat === k ? "✓" : ""}</span></td>)}
                    <td className="obs">{el.observation}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ))}

          <h2>Observations générales</h2>
          {d.observations ? <p style={{ whiteSpace: "pre-wrap" }}>{d.observations}</p> : <div className="lignes-vierges"><span /><span /><span /></div>}

          <p style={{ marginTop: 10 }}>
            Le présent état des lieux, dressé contradictoirement, est annexé au contrat de bail. Il servira de référence pour l'état des lieux de sortie
            et le décompte du dépôt de garantie. Fait en deux exemplaires, {e.ville_signature ? `à ${e.ville_signature}, ` : ""}
            {vierge ? "le ____ / ____ / ________" : `le ${dateLongue(d.date_etat)}`}.
          </p>
          <div className="signatures">
            <div>Le preneur<br />{d.locataire.nom}<br /><span style={{ fontSize: 10 }}>(« Certifié exact »)</span></div>
            <div>Pour le bailleur, l'agence mandataire<br />{e.nom_entreprise}{e.representant_nom && <><br />{e.representant_nom}{e.representant_qualite && `, ${e.representant_qualite}`}</>}</div>
          </div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
