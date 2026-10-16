import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, dateLongue, fcfa, montantEnLettres } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

/** Accord de plan d'apurement, à signer par le locataire et l'agence. */
export default function DocPlanApurement() {
  const { contrat, plan } = useParams();
  const e = useEntreprise();
  const { data: c } = useQuery({ queryKey: ["contrat-impression", contrat], queryFn: () => api.get(`/contrats/${contrat}/impression/`) });
  const { data: r, error } = useQuery({ queryKey: ["relances", contrat], queryFn: () => api.get(`/contrats/${contrat}/relances/`) });
  const p = r?.plans.find((x) => String(x.id) === plan);
  return (
    <Imprimable titre={p ? `Plan d'apurement ${p.reference}` : "Plan d'apurement"} chargement={!c || !r || !e} erreur={error} suite={{ libelle: "Terminer : retour au contrat", lien: `/contrats/${contrat}` }}>
      {c && p && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={p.reference} date={`Accord du ${dateFr(p.date_accord)}`} />
          <h1>Plan d'apurement de la dette locative</h1>
          <p className="centre">Contrat de bail n° {c.reference} — {c.maison_nom}</p>
          <div className="deux">
            <div className="cadre"><b>Le locataire</b><br /><strong>{c.locataire_detail.nom}</strong><br />{c.locataire_detail.telephone && <>Tél. : {c.locataire_detail.telephone}<br /></>}{c.maison_nom}</div>
            <div className="cadre"><b>L'agence, mandataire du bailleur</b><br /><strong>{e.nom_entreprise}</strong><br />pour le compte de {c.bailleur_detail.nom}</div>
          </div>
          <p style={{ marginTop: 12 }}>
            Le locataire reconnaît devoir la somme de <b>{fcfa(p.montant_total)}</b> ({montantEnLettres(p.montant_total)} francs CFA) au titre de loyers impayés à la date du {dateLongue(p.date_accord)}.
            Les parties conviennent de l'échéancier suivant, que le locataire s'engage à respecter <b>en plus du paiement du loyer courant</b> :
          </p>
          <table>
            <thead><tr><th>N°</th><th>Échéance</th><th className="droite">Montant</th><th className="droite">Cumul</th></tr></thead>
            <tbody>
              {p.echeances.map((x, i) => <tr key={i}><td>{i + 1}</td><td>{dateLongue(x.date)}</td><td className="droite">{fcfa(x.montant)}</td><td className="droite">{fcfa(x.cumul)}</td></tr>)}
              <tr><th>Total</th><th /><th className="droite">{fcfa(p.montant_total)}</th><th /></tr>
            </tbody>
          </table>
          {p.commentaire && <p>Observations : {p.commentaire}</p>}
          <p>
            Tout retard de plus de quinze jours sur une échéance rend immédiatement exigible la totalité du solde restant dû, sans préjudice des autres
            droits du bailleur prévus au contrat de bail. Le présent accord ne vaut pas renonciation aux sommes dues.
          </p>
          <p>Fait en deux exemplaires, {e.ville_signature ? `à ${e.ville_signature}, ` : ""}le {dateLongue(p.date_accord)}.</p>
          <div className="signatures">
            <div>Le locataire<br />{c.locataire_detail.nom}<br /><span style={{ fontSize: 10 }}>(« Lu et approuvé, bon pour accord »)</span></div>
            <div>Pour le bailleur, l'agence mandataire<br />{e.nom_entreprise}{e.representant_nom && <><br />{e.representant_nom}{e.representant_qualite && `, ${e.representant_qualite}`}</>}</div>
          </div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
