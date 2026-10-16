import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Download, HandCoins, Printer } from "lucide-react";
import { api, exporterExcel } from "../../lib/api";
import { MODES, dateFr, dateHeure, isoDate, nombre } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import { Bouton, EntetePage, Liste, Montant, Onglets, Pagination, Panneau, Recherche, Saisie, Segments, Statut, Tableau, Vide } from "../../components/ui";
import { FormEncaissement } from "../../components/formulaires";

function AEncaisser() {
  const [contrat, setContrat] = useState(null);
  const liste = useListe("/contrats/", { statut_contrat: "actif", statut_paiement: "", search: "", ordering: "date_prochain_loyer", taille: 50 });
  return (
    <>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Locataire, bien…" />
        <Segments valeur={liste.filtres.statut_paiement} onChange={(statut_paiement) => liste.changer({ statut_paiement })} options={[{ valeur: "", libelle: "Tous" }, { valeur: "en_retard", libelle: "En retard" }, { valeur: "echeance_proche", libelle: "Échéance proche" }, { valeur: "a_jour", libelle: "À jour" }]} />
      </div>
      <Panneau serre>
        <Tableau
          lignes={liste.lignes}
          chargement={liste.isLoading}
          vide={<Vide icone={HandCoins} titre="Aucun contrat à encaisser" />}
          colonnes={[
            { libelle: "Locataire", rendu: (c) => <Link to={`/contrats/${c.id}`}><div className="principal-cellule">{c.locataire_nom}</div><div className="sous-cellule">{c.maison_nom}</div></Link> },
            { libelle: "Téléphone", cle: "locataire_telephone" },
            { libelle: "Prochaine échéance", rendu: (c) => dateFr(c.date_prochain_loyer) },
            { libelle: "Situation", rendu: (c) => <Statut s={c.statut_paiement} libelle={c.jours_retard ? `${c.jours_retard} j de retard` : undefined} /> },
            { libelle: "Loyer", cle: "loyer_mensuel", type: "montant" },
            { libelle: "", rendu: (c) => <Bouton taille="petit" variante="succes" icone={HandCoins} onClick={() => setContrat(c)}>Encaisser</Bouton> },
          ]}
        />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
      <FormEncaissement ouvert={!!contrat} contrat={contrat} onFermer={() => (setContrat(null), liste.refetch())} />
    </>
  );
}

const colonnesHisto = [
  { cle: "reference_recu", libelle: "Reçu", rendu: (e) => <Link to={`/impression/quittance/${e.id}`} target="_blank">{e.reference_recu}</Link> },
  { cle: "date_encaissement", libelle: "Date", rendu: (e) => <span className="nowrap">{dateHeure(e.date_encaissement)}</span>, exporter: (e) => dateHeure(e.date_encaissement) },
  { cle: "locataire_nom", libelle: "Locataire", rendu: (e) => <><div className="principal-cellule">{e.locataire_nom}</div><div className="sous-cellule">{e.maison_nom}</div></> },
  { cle: "periode_concernee", libelle: "Période" },
  { cle: "mode_libelle", libelle: "Mode" },
  { cle: "enregistre_par_nom", libelle: "Par" },
  { cle: "commission", libelle: "Commission", type: "montant" },
  { cle: "montant_recu", libelle: "Montant", type: "montant" },
  { cle: "imprimer", libelle: "", export: false, rendu: (e) => <Link className="btn petit fantome icone" to={`/impression/quittance/${e.id}`} target="_blank" aria-label="Imprimer"><Printer size={15} /></Link> },
];

function Historique() {
  const debutMois = isoDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
  const liste = useListe("/encaissements/", { search: "", du: debutMois, au: isoDate(), mode_paiement: "" });
  const { page, ...sansPage } = liste.filtres;
  const { data: totaux } = useQuery({ queryKey: ["encaissements-totaux", sansPage], queryFn: () => api.get("/encaissements/totaux/", sansPage) });
  const exporter = async () => {
    const tout = await api.get("/encaissements/", { ...sansPage, taille: 5000 });
    exporterExcel(`Encaissements du ${dateFr(liste.filtres.du)} au ${dateFr(liste.filtres.au)}`, colonnesHisto, tout.results);
  };
  return (
    <>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="N° de reçu, locataire, bien…" />
        <Saisie type="date" valeur={liste.filtres.du} onChange={(du) => liste.changer({ du })} aria-label="Du" />
        <Saisie type="date" valeur={liste.filtres.au} onChange={(au) => liste.changer({ au })} aria-label="Au" />
        <Liste valeur={liste.filtres.mode_paiement} onChange={(mode_paiement) => liste.changer({ mode_paiement })} vide="Tous les modes" options={MODES} />
        <Bouton icone={Download} onClick={exporter}>Excel</Bouton>
      </div>
      {totaux && (
        <div className="registre" style={{ marginBottom: 14 }}>
          <div><div className="libelle">Encaissé</div><div className="valeur">{nombre(totaux.montant)}<small>FCFA</small></div><div className="note">{totaux.nombre} reçu(s)</div></div>
          <div><div className="libelle">Commissions agence</div><div className="valeur">{nombre(totaux.commission)}<small>FCFA</small></div></div>
          <div><div className="libelle">Net bailleurs</div><div className="valeur">{nombre(totaux.net)}<small>FCFA</small></div></div>
        </div>
      )}
      <Panneau serre>
        <Tableau colonnes={colonnesHisto} lignes={liste.lignes} chargement={liste.isLoading} vide={<Vide titre="Aucun encaissement sur cette période" />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
    </>
  );
}

export default function Encaissements() {
  const [params, setParams] = useSearchParams();
  const vue = params.get("vue") || "a_encaisser";
  return (
    <>
      <EntetePage titre="Encaissements" soustitre="Enregistrez les loyers reçus : la quittance, la part du bailleur et la commission sont générées automatiquement." />
      <Onglets actif={vue} onChange={(v) => setParams({ vue: v })} onglets={[{ cle: "a_encaisser", libelle: "À encaisser" }, { cle: "historique", libelle: "Historique des reçus" }]} />
      {vue === "historique" ? <Historique /> : <AEncaisser />}
    </>
  );
}
