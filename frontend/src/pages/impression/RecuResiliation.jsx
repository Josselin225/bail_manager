import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, dateLongue, fcfa, montantEnLettres } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

export default function RecuResiliation() {
  const { id } = useParams();
  const e = useEntreprise();
  const { data: c, isLoading, error } = useQuery({ queryKey: ["contrat-impression", id], queryFn: () => api.get(`/contrats/${id}/impression/`) });
  const restitue = c ? c.restitution.reduce((s, m) => s - Number(m.montant), 0) : 0;
  const retenues = c ? c.retenues.reduce((s, m) => s - Number(m.montant), 0) : 0;
  return (
    <Imprimable titre={c ? `Résiliation ${c.reference}` : "Résiliation"} chargement={isLoading || !e} erreur={error}>
      {c && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Contrat ${c.reference}`} date={c.date_resiliation ? `Le ${dateFr(c.date_resiliation)}` : ""} />
          <h1>Résiliation de bail et solde de tout compte</h1>
          {c.statut_contrat !== "termine" && <p style={{ color: "#c23b30", fontWeight: 700 }}>Attention : ce contrat est toujours actif.</p>}
          <p style={{ marginTop: 14 }}>
            Le bail du bien <b>{c.maison_nom}</b> consenti à <b>{c.locataire_detail.nom}</b> depuis le {dateLongue(c.date_debut)} a pris fin le <b>{dateLongue(c.date_resiliation)}</b>
            {c.motif_resiliation && ` (motif : ${c.motif_resiliation})`}. Le bien a été restitué à l'agence.
          </p>
          <h2>Décompte du dépôt de garantie</h2>
          <table>
            <tbody>
              <tr><td>Dépôt de garantie versé</td><td className="droite">{fcfa(c.depot_garantie)}</td></tr>
              {c.retenues.map((m) => <tr key={m.id}><td>{dateFr(m.date_operation)} — {m.commentaire}</td><td className="droite">− {fcfa(-m.montant)}</td></tr>)}
              <tr><th>Total des retenues</th><th className="droite">− {fcfa(retenues)}</th></tr>
              <tr><th>Montant restitué au locataire</th><th className="droite">{fcfa(restitue)}</th></tr>
            </tbody>
          </table>
          {restitue > 0 && <p>Le locataire reconnaît avoir reçu la somme de <b>{montantEnLettres(restitue)} francs CFA</b> ({fcfa(restitue)}) pour solde de tout compte.</p>}
          <div className="signatures">
            <div>Le locataire<br /><span style={{ fontSize: 10 }}>(« Pour solde de tout compte »)</span></div>
            <div>Pour l'agence</div>
          </div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
