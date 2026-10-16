import { Fragment } from "react";
import { useParams, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { api, exporterExcel } from "../../lib/api";
import { dateFr, dateHeure, fcfa } from "../../lib/format";
import { EnteteDoc, Imprimable, PiedDoc, useEntreprise } from "../../components/Document";

const URLS = {
  journalier: "/rapports/journalier/",
  periode: "/rapports/periode/",
  mensuel: "/rapports/mensuel/",
  annuel: "/rapports/annuel/",
  occupation: "/rapports/occupation/",
  impayes: "/rapports/impayes/",
};

const COLS = {
  encaissements: [
    { cle: "reference", libelle: "Reçu" },
    { cle: "date", libelle: "Date", exporter: (l) => dateHeure(l.date), rendu: (l) => dateFr(l.date) },
    { cle: "locataire", libelle: "Locataire" },
    { cle: "maison", libelle: "Bien" },
    { cle: "periode", libelle: "Période" },
    { cle: "mode", libelle: "Mode" },
    { cle: "commission", libelle: "Commission", type: "montant" },
    { cle: "montant", libelle: "Montant", type: "montant" },
  ],
  caisse: [
    { cle: "date", libelle: "Date", rendu: (l) => dateFr(l.date), exporter: (l) => dateHeure(l.date) },
    { cle: "type", libelle: "Type" },
    { cle: "commentaire", libelle: "Détail" },
    { cle: "par", libelle: "Par" },
    { cle: "montant", libelle: "Montant", type: "montant" },
  ],
  versements: [
    { cle: "date", libelle: "Date", rendu: (l) => dateFr(l.date), exporter: (l) => dateHeure(l.date) },
    { cle: "bailleur", libelle: "Bailleur" },
    { cle: "commentaire", libelle: "Détail" },
    { cle: "montant", libelle: "Montant", type: "montant" },
  ],
  par_bailleur: [
    { cle: "bailleur", libelle: "Bailleur" },
    { cle: "nb", libelle: "Reçus" },
    { cle: "montant", libelle: "Encaissé", type: "montant" },
    { cle: "commission", libelle: "Commission", type: "montant" },
    { cle: "net", libelle: "Net bailleur", type: "montant" },
  ],
  par_mois: [
    { cle: "mois", libelle: "Mois" },
    { cle: "nb", libelle: "Reçus" },
    { cle: "encaisse", libelle: "Encaissé", type: "montant" },
    { cle: "commissions", libelle: "Commissions", type: "montant" },
    { cle: "net", libelle: "Net bailleurs", type: "montant" },
    { cle: "depenses", libelle: "Dépenses", type: "montant" },
  ],
  recouvrement: [
    { cle: "locataire", libelle: "Locataire" },
    { cle: "maison", libelle: "Bien" },
    { cle: "echeance", libelle: "Échéance", rendu: (l) => dateFr(l.echeance), exporter: (l) => dateFr(l.echeance) },
    { cle: "loyer", libelle: "Loyer", type: "montant" },
    { cle: "paye", libelle: "Payé", type: "montant" },
    { cle: "reste", libelle: "Reste", type: "montant" },
  ],
  occupation: [
    { cle: "maison", libelle: "Bien" },
    { cle: "type", libelle: "Type" },
    { cle: "bailleur", libelle: "Bailleur" },
    { cle: "statut", libelle: "Statut" },
    { cle: "locataire", libelle: "Locataire" },
    { cle: "telephone", libelle: "Téléphone" },
    { cle: "loyer", libelle: "Loyer", type: "montant" },
  ],
  impayes: [
    { cle: "locataire", libelle: "Locataire" },
    { cle: "telephone", libelle: "Téléphone" },
    { cle: "maison", libelle: "Bien" },
    { cle: "periodes", libelle: "Mois impayés" },
    { cle: "jours_retard", libelle: "Retard (j)" },
    { cle: "caution_disponible", libelle: "Caution", type: "montant" },
    { cle: "montant_du", libelle: "Montant dû", type: "montant" },
  ],
};

function TableauDoc({ titre, colonnes, lignes, total }) {
  if (!lignes?.length) return null;
  return (
    <>
      {titre && <h2>{titre}</h2>}
      <table style={{ fontSize: 11 }}>
        <thead><tr>{colonnes.map((c) => <th key={c.cle} className={c.type === "montant" ? "droite" : ""}>{c.libelle}</th>)}</tr></thead>
        <tbody>
          {lignes.map((l, i) => (
            <tr key={i}>{colonnes.map((c) => <td key={c.cle} className={c.type === "montant" ? "droite" : ""}>{c.rendu ? c.rendu(l) : c.type === "montant" ? fcfa(l[c.cle]) : l[c.cle]}</td>)}</tr>
          ))}
          {total && (
            <tr>{colonnes.map((c, i) => <th key={c.cle} className={c.type === "montant" ? "droite" : ""}>{i === 0 ? "Total" : c.type === "montant" && total.includes(c.cle) ? fcfa(lignes.reduce((s, l) => s + Number(l[c.cle] || 0), 0)) : ""}</th>)}</tr>
          )}
        </tbody>
      </table>
    </>
  );
}

function Synthese({ items }) {
  return (
    <table className="synthese">
      <tbody>
        {items.reduce((rangs, it, i) => (i % 2 ? rangs[rangs.length - 1].push(it) : rangs.push([it]), rangs), []).map((r, i) => (
          <tr key={i}>{r.map(([k, v]) => <Fragment key={k}><th>{k}</th><td className="droite">{v}</td></Fragment>)}</tr>
        ))}
      </tbody>
    </table>
  );
}

export default function ImpressionRapport() {
  const { type } = useParams();
  const [p] = useSearchParams();
  const e = useEntreprise();
  const params = Object.fromEntries(p.entries());
  const { data: r, error } = useQuery({ queryKey: ["rapport", type, params], queryFn: () => api.get(URLS[type], params), enabled: !!URLS[type] });

  const excel = () => {
    if (type === "occupation") return exporterExcel(r.titre, COLS.occupation, r.lignes);
    if (type === "impayes") return exporterExcel(r.titre, COLS.impayes, r.lignes);
    if (type === "annuel") return exporterExcel(r.titre, COLS.par_mois, r.par_mois);
    return exporterExcel(r.titre, COLS.encaissements, r.encaissements);
  };
  const t = r?.totaux;

  return (
    <Imprimable titre={r?.titre || "Rapport"} chargement={!r || !e} erreur={error} surExcel={r && excel}>
      {r && e && (
        <div className="doc">
          <EnteteDoc e={e} reference={r.titre} date={`Édité le ${dateFr(new Date())}`} />
          <h1>{r.titre}</h1>
          {["journalier", "periode", "mensuel", "annuel"].includes(type) && (
            <>
              <Synthese items={[
                ["Encaissé", fcfa(t.encaisse)], ["Nombre de reçus", t.nb_encaissements],
                ["Commissions agence", fcfa(t.commissions)], ["Net bailleurs", fcfa(t.net_bailleurs)],
                ["Entrées de caisse", fcfa(t.entrees_caisse)], ["Dépenses", fcfa(t.depenses)],
                ["Versé aux bailleurs", fcfa(t.versements_bailleurs)], ["Modes", r.par_mode.map((m) => `${m.mode} ${fcfa(m.montant)}`).join(" — ") || "—"],
              ]} />
              {r.recouvrement && (
                <>
                  <h2>Recouvrement des loyers du mois</h2>
                  <p>Attendu : <b>{fcfa(r.recouvrement.attendu)}</b> — collecté : <b>{fcfa(r.recouvrement.collecte)}</b> ({r.recouvrement.attendu ? Math.round((r.recouvrement.collecte / r.recouvrement.attendu) * 100) : 0} %)</p>
                  <TableauDoc colonnes={COLS.recouvrement} lignes={r.recouvrement.lignes.filter((l) => Number(l.reste) > 0)} titre="Loyers non soldés" total={["reste"]} />
                </>
              )}
              {r.par_mois && <TableauDoc titre="Mois par mois" colonnes={COLS.par_mois} lignes={r.par_mois} total={["encaisse", "commissions", "net", "depenses"]} />}
              {r.contrats && <p>Contrats signés : <b>{r.contrats.nouveaux}</b> — résiliés : <b>{r.contrats.resilies}</b> — actifs à ce jour : <b>{r.contrats.actifs}</b></p>}
              <TableauDoc titre="Par bailleur" colonnes={COLS.par_bailleur} lignes={r.par_bailleur} total={["montant", "commission", "net"]} />
              <TableauDoc titre="Encaissements" colonnes={COLS.encaissements} lignes={r.encaissements} total={["commission", "montant"]} />
              <TableauDoc titre="Mouvements de caisse" colonnes={COLS.caisse} lignes={r.caisse} total={["montant"]} />
              <TableauDoc titre="Versements aux bailleurs" colonnes={COLS.versements} lignes={r.versements} total={["montant"]} />
              {!r.encaissements?.length && !r.caisse?.length && !r.par_mois && <p className="centre" style={{ color: "#666", marginTop: 20 }}>Aucune opération sur cette période.</p>}
            </>
          )}
          {type === "occupation" && (
            <>
              <Synthese items={[["Biens", t.total], ["Taux d'occupation", `${t.taux} %`], ["Occupés", t.occupees], ["Libres", t.libres], ["Loyers des biens occupés", fcfa(t.loyers_occupes)], ["Loyers potentiels (libres)", fcfa(t.loyers_libres)]]} />
              <TableauDoc colonnes={COLS.occupation} lignes={r.lignes} />
            </>
          )}
          {type === "impayes" && (
            <>
              <Synthese items={[["Contrats en retard", t.contrats], ["Total dû", fcfa(t.montant)]]} />
              {r.lignes.length ? <TableauDoc colonnes={COLS.impayes} lignes={r.lignes} total={["montant_du"]} /> : <p className="centre" style={{ marginTop: 20 }}>Aucun impayé. Tous les locataires sont à jour.</p>}
            </>
          )}
          <PiedDoc e={e} />
        </div>
      )}
    </Imprimable>
  );
}
