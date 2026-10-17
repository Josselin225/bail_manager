import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, dateLongue } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

export default function DocMandat() {
  const { id } = useParams();
  const e = useEntreprise();
  const { data: m, error } = useQuery({ queryKey: ["mandat", id], queryFn: () => api.get(`/mandats/${id}/`) });
  const { data: b } = useQuery({ queryKey: ["bailleur", m?.bailleur], queryFn: () => api.get(`/bailleurs/${m.bailleur}/`), enabled: !!m });
  return (
    <Imprimable titre="Mandat de gestion" chargement={!m || !b || !e} erreur={error}>
      {m && b && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Mandat N° ${m.reference}`} date={`Signé le ${dateFr(m.date_signature)}`} />
          <h1>Mandat de gestion locative</h1>
          <h2>Entre les soussignés</h2>
          <div className="deux">
            <div className="cadre"><b>Le mandant (propriétaire)</b><br />{b.nom} — {b.code_bailleur}<br />{b.numero_cni && <>CNI : {b.numero_cni}<br /></>}{b.telephone1 && <>Tél. : {b.telephone1}<br /></>}{b.adresse}</div>
            <div className="cadre"><b>Le mandataire (agence)</b><br /><b>{e.nom_entreprise}</b><br />{e.adresse_siege && <>{e.adresse_siege}<br /></>}{e.rccm_numero && <>RCCM : {e.rccm_numero}<br /></>}{e.representant_nom && <>Représentée par {e.representant_nom}{e.representant_qualite && `, ${e.representant_qualite}`}</>}</div>
          </div>
          <h2>Conditions</h2>
          <table>
            <tbody>
              <tr><th>Prise d'effet</th><td>{dateLongue(m.date_debut)}</td></tr>
              <tr><th>Durée</th><td>{m.duree === "determinee" ? <>Durée déterminée — jusqu'au {dateLongue(m.date_fin)}{m.tacite_reconduction && ", renouvelable par tacite reconduction pour la même durée sauf résiliation"}</> : "Durée indéterminée, jusqu'à résiliation par l'une des parties"}</td></tr>
              <tr><th>Commission de gestion</th><td><b>{m.taux_commission} %</b> de chaque loyer encaissé, retenue avant reversement</td></tr>
            </tbody>
          </table>
          {m.biens_confies.length > 0 && (
            <>
              <h2>Biens confiés</h2>
              <table>
                <thead><tr><th>Bien</th><th>Type</th><th>Adresse</th><th className="nowrap">Confié le</th></tr></thead>
                <tbody>{m.biens_confies.map((x) => <tr key={x.id}><td>{x.designation}</td><td>{x.type_maison}</td><td>{x.adresse}</td><td className="nowrap">{dateFr(x.confie_le)}</td></tr>)}</tbody>
              </table>
            </>
          )}
          <h2>Clauses</h2>
          {m.clauses.map((c, i) => <div key={i} className="clause"><b>Article {i + 1} — {c.titre}.</b> {c.contenu}</div>)}
          <p>Le mandataire signe, au nom et pour le compte du mandant, les contrats de bail des biens confiés ; chaque bail fait référence au présent mandat ({m.reference}).</p>
          <p style={{ marginTop: 12 }}>Fait en deux exemplaires, {e.ville_signature ? `à ${e.ville_signature}, ` : ""}le {dateLongue(m.date_signature)}.</p>
          <div className="signatures">
            <div>Le mandant<br />{b.nom}<br /><span style={{ fontSize: 10 }}>(« Bon pour mandat »)</span></div>
            <div>Le mandataire<br />{e.nom_entreprise}{e.representant_nom && <><br />{e.representant_nom}{e.representant_qualite && `, ${e.representant_qualite}`}</>}<br /><span style={{ fontSize: 10 }}>(« Bon pour acceptation »)</span></div>
          </div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
