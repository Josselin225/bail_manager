import { useParams, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api, exporterExcel } from "../../lib/api";
import { dateFr, fcfa } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

/** Relevé du compte courant : côté agence (fiche bailleur) ou depuis l'espace bailleur (`portail`). */
export default function ReleveBailleur({ portail }) {
  const { id } = useParams();
  const [p] = useSearchParams();
  const params = { du: p.get("du") || "", au: p.get("au") || "" };
  const url = portail ? "/portail-bailleur/releve/" : `/rapports/releve-bailleur/${id}/`;
  const { data: r, error } = useQuery({ queryKey: ["releve", portail ? "portail" : id, params], queryFn: () => api.get(url, params) });
  const e = useEntreprise(r?.entreprise, !portail);
  const colonnes = [
    { cle: "date", libelle: "Date", exporter: (l) => dateFr(l.date) },
    { cle: "libelle", libelle: "Libellé" },
    { cle: "credit", libelle: "Crédit", type: "montant" },
    { cle: "debit", libelle: "Débit", type: "montant" },
    { cle: "solde", libelle: "Solde", type: "montant" },
  ];
  return (
    <Imprimable titre={r ? `Relevé ${r.bailleur.nom}` : "Relevé"} chargement={!r || !e} erreur={error} surExcel={r && (() => exporterExcel(`Relevé ${r.bailleur.nom}`, colonnes, r.lignes))}>
      {r && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Relevé de compte — ${r.bailleur.code}`} date={`Du ${dateFr(r.du)} au ${dateFr(r.au)}`} />
          <h1>Relevé de compte bailleur</h1>
          <div className="cadre" style={{ margin: "12px 0" }}>
            <b>Bailleur</b><br /><strong>{r.bailleur.nom}</strong>{r.bailleur.telephone && ` — ${r.bailleur.telephone}`}{r.bailleur.taux != null && ` — commission ${r.bailleur.taux} %`}
          </div>
          <table>
            <thead><tr><th className="nowrap">Date</th><th style={{ width: "46%" }}>Libellé</th><th className="droite">Crédit</th><th className="droite">Débit</th><th className="droite">Solde</th></tr></thead>
            <tbody>
              <tr><td colSpan={4}><i>Solde au {dateFr(r.du)}</i></td><td className="droite">{fcfa(r.solde_initial)}</td></tr>
              {r.lignes.map((l, i) => (
                <tr key={i}><td className="nowrap">{dateFr(l.date)}</td><td>{l.libelle}</td><td className="droite">{l.credit ? fcfa(l.credit) : ""}</td><td className="droite">{l.debit ? fcfa(l.debit) : ""}</td><td className="droite">{fcfa(l.solde)}</td></tr>
              ))}
              <tr><th colSpan={2}>Totaux de la période</th><th className="droite">{fcfa(r.credits)}</th><th className="droite">{fcfa(r.debits)}</th><th className="droite">{fcfa(r.solde_final)}</th></tr>
            </tbody>
          </table>
          <p className="total">Solde dû au bailleur au {dateFr(r.au)} : {fcfa(r.solde_final)}</p>
          <div className="signatures"><div>Le bailleur</div><div>Pour l'agence</div></div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
