import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { QRCodeSVG } from "qrcode.react";
import { api } from "../../lib/api";
import { dateFr, dateHeure, fcfa, montantEnLettres } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

export function CorpsQuittance({ q, e, souche = true }) {
  const qr = [
    e?.nom_entreprise || "BailManager",
    `Quittance ${q.reference_recu}`,
    `Locataire : ${q.locataire_nom}`,
    `Période : ${q.periode_concernee}`,
    `Montant : ${Math.round(q.montant_recu)} FCFA`,
    `Reste : ${Math.round(q.reste_periode)} FCFA`,
    `Date : ${dateHeure(q.date_encaissement)}`,
  ].join("\n");
  const bloc = (copie) => (
    <>
      <EnteteDoc e={e} reference={`N° ${q.reference_recu}`} date={`Le ${dateFr(q.date_encaissement)}`} />
      <h1>Quittance de loyer</h1>
      <div className="centre" style={{ fontSize: 11, color: "#555", marginBottom: 12 }}>{copie}</div>
      <div style={{ display: "grid", gridTemplateColumns: "1fr auto", gap: 16, alignItems: "start" }}>
        <div>
          <p>
            Reçu de <b>{q.locataire_nom}</b>{q.locataire_telephone && ` (${q.locataire_telephone})`} la somme de{" "}
            <b>{montantEnLettres(q.montant_recu)} francs CFA</b> ({fcfa(q.montant_recu)}), au titre du loyer de <b>{q.periode_concernee}</b>
            {q.origine === "avance" && " (avance versée à la signature)"}
            {q.origine === "caution" && " (imputé sur le dépôt de garantie)"}, pour le bien <b>{q.maison_nom}</b>
            {q.maison_adresse && ` situé ${q.maison_adresse}`}, propriété de {q.bailleur_nom}.
          </p>
          <table>
            <tbody>
              <tr><th>Loyer mensuel</th><td className="droite">{fcfa(q.loyer_periode)}</td><th>Mode de paiement</th><td>{q.mode_libelle}</td></tr>
              <tr><th>Total versé pour la période</th><td className="droite">{fcfa(q.total_paye_periode)}</td><th>Contrat</th><td>{q.contrat_reference}</td></tr>
              <tr><th>Reste à payer</th><td className="droite"><b>{fcfa(q.reste_periode)}</b></td><th>Prochaine échéance</th><td>{dateFr(q.prochaine_echeance)}</td></tr>
            </tbody>
          </table>
          {q.impayes?.length > 0 && (
            <p style={{ color: "#c23b30", fontWeight: 700, marginTop: 6 }}>Rappel : mois restant à régler — {q.impayes.join(", ")}.</p>
          )}
          {q.observation && <p className="petit-texte">Observation : {q.observation}</p>}
        </div>
        <div className="centre">
          <QRCodeSVG value={qr} size={96} level="M" />
          <div style={{ fontSize: 9, color: "#666" }}>Vérification</div>
        </div>
      </div>
      <div className="signatures" style={{ marginTop: 18 }}>
        <div>Le locataire</div>
        <div>Pour l'agence{q.enregistre_par_nom ? ` — ${q.enregistre_par_nom}` : ""}</div>
      </div>
    </>
  );
  return (
    <div className="doc">
      {bloc("Exemplaire locataire")}
      {souche && (
        <>
          <div className="separateur-coupe" />
          {bloc("Souche agence")}
        </>
      )}
      <p className="petit-texte" style={{ color: "#555", marginTop: 10 }}>Art. 425 : la présente quittance vaut preuve du paiement de la somme indiquée. Elle ne libère pas le locataire des sommes restant dues.</p>
      <PiedDoc e={e} />
    </div>
  );
}

export default function Quittance() {
  const { id } = useParams();
  const e = useEntreprise();
  const { data: q, isLoading, error } = useQuery({ queryKey: ["quittance", id], queryFn: () => api.get(`/encaissements/${id}/quittance/`) });
  return (
    <Imprimable titre={q ? `Quittance ${q.reference_recu}` : "Quittance"} chargement={isLoading || !e} erreur={error}>
      {q && e && <CorpsQuittance q={q} e={e} />}
    </Imprimable>
  );
}
