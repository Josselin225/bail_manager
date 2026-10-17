import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, dateLongue, fcfa, montantEnLettres } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

/** Lettre au locataire : notification de la révision de son loyer (art. 423-424, trois mois avant la date d'effet). */
export default function DocRevision() {
  const { id } = useParams();
  const e = useEntreprise();
  const { data: r, error } = useQuery({ queryKey: ["revision", id], queryFn: () => api.get(`/revisions/${id}/`) });
  const hausse = r && Number(r.nouveau_loyer) > Number(r.ancien_loyer);
  const notification = r && new Date(r.created_at);
  const preavis = r && (() => { const d = new Date(notification); d.setMonth(d.getMonth() + 3); return d; })();
  const preavisRespecte = r && new Date(`${r.date_effet}T00:00`) >= new Date(preavis.toDateString());
  return (
    <Imprimable titre={r ? `Révision du loyer — ${r.locataire_nom}` : "Révision du loyer"} chargement={!r || !e} erreur={error} suite={r && { libelle: "Terminer : retour au contrat", lien: `/contrats/${r.contrat}` }}>
      {r && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Contrat ${r.contrat_reference}`} date={`${e.ville_signature ? `${e.ville_signature}, le` : "Le"} ${dateFr(r.created_at)}`} />
          <div className="destinataire">
            <b>{r.locataire_nom}</b><br />
            {r.maison_nom}<br />
            {r.maison_adresse}{r.locataire_telephone && <><br />Tél. : {r.locataire_telephone}</>}
          </div>
          <h1>{hausse ? "Révision de votre loyer" : "Ajustement de votre loyer"}</h1>
          <p className="centre">Contrat de bail n° {r.contrat_reference} du {dateFr(r.date_debut)} — {r.maison_nom}</p>
          <p>Madame, Monsieur,</p>
          <p>
            Agissant pour le compte de votre bailleur, <b>{r.bailleur_nom}</b>, en vertu du mandat de gestion qu'il nous a confié, nous vous informons
            {r.origine === "triennale" ? " qu'en application de la révision triennale prévue par la loi n° 2019-576 du 26 juin 2019 (articles 423 et 424) et par votre contrat de bail," : r.origine === "accord" ? " que, conformément à l'accord intervenu entre les parties," : " que, à la demande de votre bailleur,"}
            {" "}votre loyer mensuel est {hausse ? "porté" : "ramené"} de <b>{fcfa(r.ancien_loyer)}</b> à <b>{fcfa(r.nouveau_loyer)}</b> ({montantEnLettres(r.nouveau_loyer)} francs CFA), soit une variation de {r.variation > 0 ? "+" : ""}{r.variation} %.
          </p>
          <table>
            <tbody>
              <tr><th>Loyer actuel</th><td>{fcfa(r.ancien_loyer)}</td></tr>
              <tr><th>Nouveau loyer</th><td><b>{fcfa(r.nouveau_loyer)}</b></td></tr>
              <tr><th>À compter du loyer de</th><td><b>{dateLongue(r.date_effet).replace(/^1er |^\d+ /, "")}</b> (échéance du {dateFr(r.date_effet)})</td></tr>
              {r.motif && <tr><th>Motif</th><td>{r.motif}</td></tr>}
            </tbody>
          </table>
          <p>Les loyers antérieurs à cette date restent dus à l'ancien montant. Les autres clauses de votre bail demeurent inchangées.</p>
          {r.origine === "triennale" && (
            <p>Conformément à la loi, la présente notification vous est adressée au moins trois (03) mois avant la date d'effet de la révision.</p>
          )}
          {r.origine === "triennale" && !preavisRespecte && (
            <p className="non-imprime" style={{ color: "#a86500", fontWeight: 600 }}>Attention : la date d'effet est à moins de trois mois de cette lettre ({dateFr(preavis)} au plus tôt). Corrigez la date d'effet avant de remettre la lettre.</p>
          )}
          <p>Nous restons à votre disposition pour toute question et vous prions d'agréer, Madame, Monsieur, l'expression de nos salutations distinguées.</p>
          <div className="signatures">
            <div>Reçu par le locataire, le<br />{r.locataire_nom}<br /><span style={{ fontSize: 10 }}>(date et signature)</span></div>
            <div>Pour le bailleur, l'agence mandataire<br />{e.nom_entreprise}{e.representant_nom && <><br />{e.representant_nom}{e.representant_qualite && `, ${e.representant_qualite}`}</>}</div>
          </div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
