import { useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api } from "../../lib/api";
import { dateFr, dateLongue, fcfa, montantEnLettres } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

const OBJETS = { 1: "Rappel amiable — loyers impayés", 2: "Relance — loyers impayés", 3: "Mise en demeure de payer" };

/** Lettre de relance, adaptée au niveau : rappel amiable, relance, mise en demeure. */
export default function DocRelance() {
  const { contrat, relance } = useParams();
  const e = useEntreprise();
  const { data: c } = useQuery({ queryKey: ["contrat-impression", contrat], queryFn: () => api.get(`/contrats/${contrat}/impression/`) });
  const { data: r, error } = useQuery({ queryKey: ["relances", contrat], queryFn: () => api.get(`/contrats/${contrat}/relances/`) });
  const rel = r?.relances.find((x) => String(x.id) === relance);
  const precedente = rel && r.relances.filter((x) => x.niveau < rel.niveau).sort((a, b) => b.niveau - a.niveau)[0];
  const limite = rel && (() => { const d = new Date(`${rel.date_relance}T00:00`); d.setDate(d.getDate() + rel.delai_jours); return d; })();
  return (
    <Imprimable titre={rel ? `${OBJETS[rel.niveau]} — ${rel.reference}` : "Relance"} chargement={!c || !r || !e} erreur={error} suite={{ libelle: "Terminer : recouvrement", lien: "/recouvrement" }}>
      {c && rel && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={rel.reference} date={`${e.ville_signature ? `${e.ville_signature}, le` : "Le"} ${dateFr(rel.date_relance)}`} />
          <div className="destinataire">
            <b>{c.locataire_detail.nom}</b><br />
            {c.maison_nom}<br />
            {c.maison_adresse}<br />
            {c.locataire_detail.telephone && <>Tél. : {c.locataire_detail.telephone}</>}
          </div>
          <h1>{OBJETS[rel.niveau]}</h1>
          <p className="centre">Contrat de bail n° {c.reference} — {c.maison_nom}</p>
          <p>{rel.niveau === 3 ? "Madame, Monsieur," : "Chère locataire, cher locataire,"}</p>
          {rel.niveau === 1 && (
            <p>Sauf erreur de notre part, nous n'avons pas reçu le règlement des loyers suivants : <b>{rel.periodes}</b>, soit un montant de <b>{fcfa(rel.montant_du)}</b>. Il s'agit peut-être d'un simple oubli : nous vous remercions de bien vouloir régulariser votre situation avant le <b>{dateLongue(limite)}</b>. Si votre paiement a été effectué entre-temps, merci de ne pas tenir compte de ce courrier.</p>
          )}
          {rel.niveau === 2 && (
            <p>Malgré notre rappel{precedente ? ` du ${dateFr(precedente.date_relance)}` : ""}, les loyers suivants restent impayés : <b>{rel.periodes}</b>, soit un montant de <b>{fcfa(rel.montant_du)}</b>. Nous vous demandons de régler cette somme avant le <b>{dateLongue(limite)}</b>. À défaut, nous serons contraints de vous adresser une mise en demeure.</p>
          )}
          {rel.niveau === 3 && (
            <>
              <p>Malgré nos précédents courriers{precedente ? `, dont le dernier date du ${dateFr(precedente.date_relance)}` : ""}, vous restez redevable des loyers suivants : <b>{rel.periodes}</b>.</p>
              <p>Par la présente, nous vous <b>mettons en demeure</b> de payer la somme de <b>{fcfa(rel.montant_du)}</b> ({montantEnLettres(rel.montant_du)} francs CFA) dans un délai de <b>{rel.delai_jours} jours</b>, soit au plus tard le <b>{dateLongue(limite)}</b>.</p>
              <p>À défaut de paiement dans ce délai, nous nous réservons le droit d'engager toute procédure de recouvrement, y compris judiciaire, et de demander la résiliation du bail, conformément au contrat et à la loi n° 2019-576 du 26 juin 2019. Les frais engagés resteront à votre charge.</p>
            </>
          )}
          {rel.niveau < 3 && <p>Si vous rencontrez des difficultés passagères, prenez contact avec nous sans tarder : un échéancier de paiement peut être étudié.</p>}
          <table>
            <tbody>
              <tr><th>Mois impayés</th><td>{rel.periodes}</td></tr>
              <tr><th>Montant dû</th><td><b>{fcfa(rel.montant_du)}</b></td></tr>
              <tr><th>À régler avant le</th><td><b>{dateFr(limite)}</b></td></tr>
              <tr><th>Où payer</th><td>À l'agence {e.nom_entreprise}{e.adresse_siege && `, ${e.adresse_siege}`}{e.contact_telephone && ` — ${e.contact_telephone}`}</td></tr>
            </tbody>
          </table>
          <p>Veuillez agréer, {rel.niveau === 3 ? "Madame, Monsieur" : "chère locataire, cher locataire"}, l'expression de nos salutations distinguées.</p>
          <div className="signatures un-seul">
            <div>Pour le bailleur, l'agence mandataire<br />{e.nom_entreprise}{e.representant_nom && <><br />{e.representant_nom}{e.representant_qualite && `, ${e.representant_qualite}`}</>}</div>
          </div>
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
