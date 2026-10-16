import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, dateLongue, fcfa, montantEnLettres } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

export default function DocContrat() {
  const { id } = useParams();
  const e = useEntreprise();
  const { data: c, isLoading, error } = useQuery({ queryKey: ["contrat-impression", id], queryFn: () => api.get(`/contrats/${id}/impression/`) });
  const total = c ? Number(c.depot_garantie) + Number(c.avance_loyer) + Number(c.droit_agence) : 0;
  return (
    <Imprimable titre={c ? `Contrat de bail ${c.reference}` : "Contrat"} chargement={isLoading || !e} erreur={error}>
      {c && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={`Contrat N° ${c.reference}`} date={`Signé le ${dateFr(c.date_contrat)}`} />
          <h1>Contrat de bail à usage d'habitation</h1>
          <p className="centre" style={{ fontSize: 11, color: "#555" }}>Loi n° 2019-576 du 26 juin 2019 instituant le Code de la Construction et de l'Habitat</p>
          <h2>Entre les soussignés</h2>
          <div className="deux">
            <div className="cadre">
              <b>L'agence, mandataire du bailleur</b><br />
              <b>{e.nom_entreprise}</b><br />
              {e.adresse_siege && <>{e.adresse_siege}<br /></>}
              {e.rccm_numero && <>RCCM : {e.rccm_numero}<br /></>}
              {e.representant_nom && <>Représentée par {e.representant_nom}{e.representant_qualite && `, ${e.representant_qualite}`}<br /></>}
              agissant au nom et pour le compte de :<br />
              <b>{c.bailleur_detail.nom}</b> ({c.bailleur_detail.code}){c.bailleur_detail.cni && <>, CNI {c.bailleur_detail.cni}</>}
              {c.bailleur_detail.adresse && <>, {c.bailleur_detail.adresse}</>}<br />
              {c.mandat_detail
                ? <>en vertu du mandat de gestion n° <b>{c.mandat_detail.reference}</b> signé le {dateFr(c.mandat_detail.date_signature)},</>
                : <>en vertu d'un mandat de gestion,</>}
              <br />ci-après « le bailleur »
            </div>
            <div className="cadre">
              <b>Le locataire (preneur)</b><br />
              <b>{c.locataire_detail.nom}</b><br />
              {c.locataire_detail.piece_identite && <>Pièce d'identité : {c.locataire_detail.piece_identite}<br /></>}
              {c.locataire_detail.telephone && <>Tél. : {c.locataire_detail.telephone}<br /></>}
              {c.locataire_detail.profession && <>Profession : {c.locataire_detail.profession}<br /></>}
              ci-après « le preneur »
            </div>
          </div>
          <h2>Désignation du bien</h2>
          <p>{c.maison_nom}{c.maison_type && `, ${c.maison_type}`}{c.maison_adresse && `, situé ${c.maison_adresse}`}. Le bien est loué à usage exclusif d'habitation.</p>
          <h2>Conditions financières</h2>
          <table>
            <tbody>
              <tr><th>Loyer mensuel</th><td className="droite">{fcfa(c.loyer_mensuel)}</td><td>{montantEnLettres(c.loyer_mensuel)} francs CFA</td></tr>
              <tr><th>Dépôt de garantie (2 mois)</th><td className="droite">{fcfa(c.depot_garantie)}</td><td>Restituable, déduction faite des sommes dues</td></tr>
              <tr><th>Avance de loyer ({c.mois_avance} mois)</th><td className="droite">{fcfa(c.avance_loyer)}</td><td>Imputée sur les premiers mois</td></tr>
              <tr><th>Frais d'agence</th><td className="droite">{fcfa(c.droit_agence)}</td><td>Non remboursables</td></tr>
              <tr><th>Total versé à la signature</th><td className="droite"><b>{fcfa(total)}</b></td><td>{montantEnLettres(total)} francs CFA</td></tr>
            </tbody>
          </table>
          <p>Prise d'effet : <b>{dateLongue(c.date_debut)}</b>{c.date_fin ? `, jusqu'au ${dateLongue(c.date_fin)}` : ", pour une durée indéterminée renouvelable par tacite reconduction"}.</p>
          {c.mandat_detail && (
            <>
              <h2>Information du preneur sur le mandat de gestion</h2>
              <p>
                Le bien appartient à <b>{c.bailleur_detail.nom}</b>, qui en a confié la gestion à <b>{e.nom_entreprise}</b> par le mandat de gestion
                n° <b>{c.mandat_detail.reference}</b> signé le {dateFr(c.mandat_detail.date_signature)},{" "}
                {c.mandat_detail.duree === "determinee"
                  ? <>conclu pour une durée déterminée prenant fin le <b>{dateLongue(c.mandat_detail.date_fin)}</b>{c.mandat_detail.tacite_reconduction && ", renouvelable par tacite reconduction"}.</>
                  : <>conclu pour une durée indéterminée.</>}{" "}
                L'expiration ou la résiliation de ce mandat n'affecte pas le présent bail, qui se poursuit avec le bailleur ; le preneur sera alors informé par écrit
                de la personne à qui verser ses loyers.
              </p>
            </>
          )}
          <h2>Clauses</h2>
          {c.clauses.map((cl, i) => (
            <div key={i} className="clause"><b>Article {i + 1} — {cl.titre}.</b> {cl.contenu}</div>
          ))}
          <p style={{ marginTop: 12 }}>Fait en deux exemplaires originaux, {e.ville_signature ? `à ${e.ville_signature}, ` : ""}le {dateLongue(c.date_contrat)}.</p>
          <div className="signatures">
            <div>Le preneur<br />{c.locataire_detail.nom}<br /><span style={{ fontSize: 10 }}>(« Lu et approuvé »)</span></div>
            <div>Pour le bailleur, l'agence mandataire<br />{e.nom_entreprise}{e.representant_nom && <><br />{e.representant_nom}{e.representant_qualite && `, ${e.representant_qualite}`}</>}</div>
          </div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
