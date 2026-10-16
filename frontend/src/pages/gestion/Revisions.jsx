import { Link } from "react-router-dom";
import { Download, TrendingUp } from "lucide-react";
import { exporterExcel } from "../../lib/api";
import { dateFr } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import { Badge, Bouton, Encart, EntetePage, Pagination, Panneau, Recherche, Tableau, Vide } from "../../components/ui";

const colonnes = [
  { cle: "created_at", libelle: "Enregistrée le", rendu: (r) => dateFr(r.created_at), exporter: (r) => dateFr(r.created_at) },
  { cle: "locataire_nom", libelle: "Contrat", rendu: (r) => <Link to={`/contrats/${r.contrat}`}><div className="principal-cellule">{r.locataire_nom}</div><div className="sous-cellule">{r.maison_nom} — {r.contrat_reference}</div></Link> },
  { cle: "date_effet", libelle: "Effet", rendu: (r) => dateFr(r.date_effet), exporter: (r) => dateFr(r.date_effet) },
  { cle: "ancien_loyer", libelle: "Ancien loyer", type: "montant" },
  { cle: "nouveau_loyer", libelle: "Nouveau loyer", type: "montant" },
  { cle: "variation", libelle: "Variation", rendu: (r) => <Badge ton={r.variation > 0 ? "attention" : "ok"}>{r.variation > 0 ? "+" : ""}{r.variation} %</Badge>, exporter: (r) => `${r.variation} %` },
  { cle: "origine_libelle", libelle: "Origine" },
  { cle: "motif", libelle: "Motif" },
  { cle: "effectue_par_nom", libelle: "Par" },
];

export default function Revisions() {
  const liste = useListe("/revisions/", { search: "" });
  return (
    <>
      <EntetePage titre="Révisions de loyer" soustitre="Historique des hausses et baisses de loyer. Une révision se fait depuis la fiche du contrat.">
        <Bouton icone={Download} onClick={() => exporterExcel("Révisions de loyer", colonnes, liste.lignes)}>Excel</Bouton>
      </EntetePage>
      <Encart>Art. 423-424 : le loyer peut être révisé tous les trois (03) ans, avec une notification écrite au moins trois (03) mois avant la date d'effet.</Encart>
      <div className="barre-filtres" style={{ marginTop: 14 }}>
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Locataire, bien, motif…" />
      </div>
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} vide={<Vide icone={TrendingUp} titre="Aucune révision" texte="Ouvrez un contrat puis « Réviser le loyer »." />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
    </>
  );
}
