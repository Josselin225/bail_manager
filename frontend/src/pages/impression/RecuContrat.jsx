import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, dateLongue, fcfa, montantEnLettres } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

export default function RecuContrat() {
  const { id } = useParams();
  const e = useEntreprise();
  const { data: c, isLoading, error } = useQuery({ queryKey: ["contrat-impression", id], queryFn: () => api.get(`/contrats/${id}/impression/`) });
  const total = c ? Number(c.depot_garantie) + Number(c.avance_loyer) + Number(c.droit_agence) : 0;
  return (
    <Imprimable suite={c && { libelle: "Suivant : contrat de bail", lien: `/impression/contrat/${c.id}` }} titre={c ? `Reçu de signature ${c.reference}` : "Reçu"} chargement={isLoading || !e} erreur={error}>
      {c && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Contrat ${c.reference}`} date={`Le ${dateFr(c.date_contrat)}`} />
          <h1>Reçu de paiement à la signature du bail</h1>
          <p style={{ marginTop: 16 }}>
            Reçu de <b>{c.locataire_detail.nom}</b> la somme de <b>{montantEnLettres(total)} francs CFA</b> ({fcfa(total)}) à la signature du bail du bien <b>{c.maison_nom}</b>
            {c.maison_adresse && `, ${c.maison_adresse}`}, propriété de {c.bailleur_detail.nom}, avec prise d'effet le {dateLongue(c.date_debut)}.
          </p>
          <table>
            <thead><tr><th>Désignation</th><th className="droite">Montant</th></tr></thead>
            <tbody>
              <tr><td>Dépôt de garantie (caution — 2 mois de loyer)</td><td className="droite">{fcfa(c.depot_garantie)}</td></tr>
              <tr><td>Avance de loyer ({c.mois_avance} mois)</td><td className="droite">{fcfa(c.avance_loyer)}</td></tr>
              <tr><td>Frais d'agence</td><td className="droite">{fcfa(c.droit_agence)}</td></tr>
              <tr><th>Total</th><th className="droite">{fcfa(total)}</th></tr>
            </tbody>
          </table>
          <p>Loyer mensuel : <b>{fcfa(c.loyer_mensuel)}</b>. Première échéance après l'avance : <b>{dateFr(c.date_prochain_loyer)}</b>.</p>
          <div className="signatures">
            <div>Le preneur</div>
            <div>Pour l'agence</div>
          </div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
