import { useParams, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api, exporterExcel } from "../../lib/api";
import { dateFr, fcfa } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

/** Récapitulatif annuel des revenus locatifs d'un bailleur : côté agence ou depuis l'espace bailleur (`portail`). */
export default function RecapFiscal({ portail }) {
  const { id } = useParams();
  const [p, setP] = useSearchParams();
  const annee = p.get("annee") || String(new Date().getFullYear());
  const url = portail ? "/portail-bailleur/recap-fiscal/" : `/rapports/recap-fiscal/${id}/`;
  const { data: r, error } = useQuery({ queryKey: ["recap-fiscal", portail ? "portail" : id, annee], queryFn: () => api.get(url, { annee }) });
  const e = useEntreprise(r?.entreprise, !portail);
  const colonnes = [
    { cle: "designation", libelle: "Bien" },
    { cle: "locataires", libelle: "Locataire(s)" },
    { cle: "mois_loues", libelle: "Mois loués" },
    { cle: "brut", libelle: "Loyers encaissés", type: "montant" },
    { cle: "commission", libelle: "Commission de gestion", type: "montant" },
    { cle: "net", libelle: "Net revenant au bailleur", type: "montant" },
  ];
  return (
    <Imprimable
      titre={r ? `Récapitulatif ${r.annee} — ${r.bailleur.nom}` : "Récapitulatif annuel"}
      chargement={!r || !e}
      erreur={error}
      surExcel={!portail && r ? () => exporterExcel(`Récapitulatif ${r.annee} ${r.bailleur.nom}`, colonnes, r.biens) : undefined}
      outils={r && (
        <label className="choix-annee">Année
          <select className="saisie" value={annee} onChange={(ev) => setP({ annee: ev.target.value }, { replace: true })}>
            {[...new Set([...r.annees.map(String), annee])].sort().reverse().map((a) => <option key={a}>{a}</option>)}
          </select>
        </label>
      )}
    >
      {r && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Récapitulatif ${r.annee} — ${r.bailleur.code}`} date={`Du 01/01/${r.annee} au 31/12/${r.annee}`} />
          <h1>Récapitulatif annuel des revenus locatifs {r.annee}</h1>
          <div className="deux" style={{ margin: "12px 0" }}>
            <div className="cadre"><b>Propriétaire</b><br /><strong>{r.bailleur.nom}</strong>{r.bailleur.cni && <><br />CNI {r.bailleur.cni}</>}{r.bailleur.adresse && <><br />{r.bailleur.adresse}</>}</div>
            <div className="cadre"><b>Gestionnaire</b><br /><strong>{e.nom_entreprise}</strong>{r.bailleur.mandat && <><br />Mandat de gestion n° {r.bailleur.mandat}</>}{r.bailleur.taux != null && <> — commission {r.bailleur.taux} %</>}</div>
          </div>
          <div className="tuiles">
            <div className="tuile"><span>Loyers encaissés</span><b>{fcfa(r.totaux.brut)}</b></div>
            <div className="tuile"><span>Commissions de gestion</span><b>{fcfa(r.totaux.commission)}</b></div>
            <div className="tuile"><span>Travaux et ajustements</span><b>{fcfa(r.totaux.ajustements)}</b></div>
            <div className="tuile fort"><span>Revenu net de l'année</span><b>{fcfa(r.totaux.revenu_net)}</b></div>
          </div>
          <h2>Détail par bien</h2>
          <table>
            <thead><tr><th>Bien</th><th>Locataire(s)</th><th className="droite">Mois loués</th><th className="droite">Loyers encaissés</th><th className="droite">Commission</th><th className="droite">Net</th></tr></thead>
            <tbody>
              {r.biens.length === 0 && <tr><td colSpan={6} className="centre discret">Aucun loyer encaissé cette année.</td></tr>}
              {r.biens.map((b) => (
                <tr key={b.designation}><td><b>{b.designation}</b><br /><span className="petit">{[b.type, b.adresse].filter(Boolean).join(" — ")}</span></td><td>{b.locataires || "—"}</td><td className="droite">{b.mois_loues}</td><td className="droite">{fcfa(b.brut)}</td><td className="droite">{fcfa(b.commission)}</td><td className="droite">{fcfa(b.net)}</td></tr>
              ))}
              <tr><th colSpan={2}>Total</th><th className="droite">{r.totaux.mois_loues}</th><th className="droite">{fcfa(r.totaux.brut)}</th><th className="droite">{fcfa(r.totaux.commission)}</th><th className="droite">{fcfa(r.totaux.net)}</th></tr>
            </tbody>
          </table>
          {r.ajustements.length > 0 && (
            <>
              <h2>Travaux et ajustements imputés au propriétaire</h2>
              <table>
                <thead><tr><th className="nowrap">Date</th><th>Libellé</th><th className="droite">Montant</th></tr></thead>
                <tbody>
                  {r.ajustements.map((a, i) => <tr key={i}><td className="nowrap">{dateFr(a.date)}</td><td>{a.libelle}</td><td className="droite">{fcfa(a.montant)}</td></tr>)}
                  <tr><th colSpan={2}>Total</th><th className="droite">{fcfa(r.totaux.ajustements)}</th></tr>
                </tbody>
              </table>
            </>
          )}
          <p>Sommes versées au propriétaire au cours de l'année : <b>{fcfa(r.totaux.verse)}</b>.</p>
          <p className="note-doc">
            Document établi par l'agence pour aider le propriétaire à déclarer ses revenus fonciers. Les montants sont ceux effectivement encaissés
            entre le 1er janvier et le 31 décembre {r.annee} (loyers, avances et retenues sur caution affectées aux loyers). Il ne constitue pas un avis
            d'imposition : rapprochez-vous de votre centre des impôts ou de votre conseil pour le calcul de l'impôt dû.
          </p>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
