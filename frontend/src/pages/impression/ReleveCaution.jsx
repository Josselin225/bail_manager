import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, fcfa } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

export default function ReleveCaution() {
  const { id } = useParams();
  const e = useEntreprise();
  const { data: c, error } = useQuery({ queryKey: ["contrat", id], queryFn: () => api.get(`/contrats/${id}/`) });
  let solde = c ? Number(c.depot_garantie) : 0;
  const lignes = c ? [...c.mouvements_caution].reverse().map((m) => ((solde += Number(m.montant)), { ...m, solde })) : [];
  return (
    <Imprimable titre="Relevé de caution" chargement={!c || !e} erreur={error}>
      {c && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Contrat ${c.reference}`} date={`Édité le ${dateFr(new Date())}`} />
          <h1>Relevé du dépôt de garantie</h1>
          <div className="cadre" style={{ margin: "12px 0" }}><b>Locataire</b><br /><strong>{c.locataire_nom}</strong> — {c.maison_nom} — bail du {dateFr(c.date_debut)}</div>
          <table>
            <thead><tr><th>Date</th><th>Opération</th><th className="droite">Montant</th><th className="droite">Solde</th></tr></thead>
            <tbody>
              <tr><td>{dateFr(c.date_contrat)}</td><td>Dépôt de garantie à la signature</td><td className="droite">{fcfa(c.depot_garantie)}</td><td className="droite">{fcfa(c.depot_garantie)}</td></tr>
              {lignes.map((m) => <tr key={m.id}><td>{dateFr(m.date_operation)}</td><td>{m.type_libelle} — {m.commentaire}</td><td className="droite">{fcfa(m.montant)}</td><td className="droite">{fcfa(m.solde)}</td></tr>)}
            </tbody>
          </table>
          <p className="total">Caution disponible : {fcfa(c.caution_disponible)}</p>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
