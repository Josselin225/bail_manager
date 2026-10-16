import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Download, Printer, ShieldCheck } from "lucide-react";
import { api, exporterExcel } from "../../lib/api";
import { dateFr, nombre } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import { Bouton, EntetePage, LienBouton, Montant, Pagination, Panneau, Recherche, Segments, Tableau, Vide } from "../../components/ui";

const colonnes = [
  { cle: "locataire_nom", libelle: "Locataire", rendu: (c) => <><div className="principal-cellule">{c.locataire_nom}</div><div className="sous-cellule">{c.maison_nom}</div></> },
  { cle: "reference", libelle: "Contrat" },
  { cle: "date_debut", libelle: "Entrée", rendu: (c) => dateFr(c.date_debut), exporter: (c) => dateFr(c.date_debut) },
  { cle: "depot_garantie", libelle: "Déposée", type: "montant" },
  { cle: "retenues", libelle: "Retenues", type: "montant", rendu: (c) => <Montant v={c.caution_disponible - c.depot_garantie} signe />, exporter: (c) => c.caution_disponible - c.depot_garantie },
  { cle: "caution_disponible", libelle: "Disponible", type: "montant", rendu: (c) => <b><Montant v={c.caution_disponible} /></b> },
  { libelle: "", export: false, rendu: (c) => <LienBouton taille="petit" variante="fantome" icone={Printer} to={`/impression/releve-caution/${c.id}`} target="_blank" aria-label="Relevé" /> },
];

export default function Cautions() {
  const nav = useNavigate();
  const liste = useListe("/cautions/", { search: "", statut: "actif" });
  const { data: s } = useQuery({ queryKey: ["cautions-synthese"], queryFn: () => api.get("/cautions/synthese/") });
  return (
    <>
      <EntetePage titre="Cautions" soustitre="Dépôts de garantie détenus par l'agence. Retenues et restitutions se font depuis la fiche du contrat.">
        <Bouton icone={Download} onClick={() => exporterExcel("Registre des cautions", colonnes, liste.lignes)}>Excel</Bouton>
      </EntetePage>
      {s && (
        <div className="registre" style={{ marginBottom: 16 }}>
          <div><div className="libelle">Cautions disponibles</div><div className="valeur">{nombre(s.total_disponible)}<small>FCFA</small></div><div className="note">{s.contrats_actifs} contrat(s) actif(s)</div></div>
          <div><div className="libelle">Total déposé (actifs)</div><div className="valeur">{nombre(s.total_depose)}<small>FCFA</small></div></div>
          <div><div className="libelle">Retenues cumulées</div><div className="valeur">{nombre(s.total_retenues)}<small>FCFA</small></div></div>
          <div><div className="libelle">Restitué aux locataires</div><div className="valeur">{nombre(s.total_restitue)}<small>FCFA</small></div><div className="note">{s.sans_mouvement} caution(s) intacte(s)</div></div>
        </div>
      )}
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Locataire, bien…" />
        <Segments valeur={liste.filtres.statut} onChange={(statut) => liste.changer({ statut })} options={[{ valeur: "actif", libelle: "Baux en cours" }, { valeur: "termine", libelle: "Baux terminés" }]} />
      </div>
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} surLigne={(c) => nav(`/contrats/${c.id}`)} vide={<Vide icone={ShieldCheck} titre="Aucune caution" />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
    </>
  );
}
