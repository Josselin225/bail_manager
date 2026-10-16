import { useNavigate } from "react-router-dom";
import { Download, FilePlus2, FileSignature } from "lucide-react";
import { exporterExcel } from "../../lib/api";
import { dateFr } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import { Anciennete, Badge, Bouton, EntetePage, LienBouton, Pagination, Panneau, Recherche, Segments, Statut, Tableau, Vide } from "../../components/ui";

const colonnes = [
  { cle: "reference", libelle: "Contrat", rendu: (c) => <><div className="principal-cellule">{c.reference}</div><div className="sous-cellule">signé le {dateFr(c.date_contrat)}</div></> },
  { cle: "locataire_nom", libelle: "Locataire", rendu: (c) => <><div className="principal-cellule">{c.locataire_nom}</div><div className="sous-cellule">{c.locataire_telephone}</div></> },
  { cle: "maison_nom", libelle: "Bien", rendu: (c) => <><div>{c.maison_nom}</div><div className="sous-cellule">{c.bailleur_nom}</div>{c.mandat_echu && <Badge ton="attention">Mandat échu</Badge>}{c.date_sortie_prevue && c.statut_contrat === "actif" && <Badge ton="violet">Préavis — sortie le {dateFr(c.date_sortie_prevue)}</Badge>}</> },
  { cle: "date_debut", libelle: "Début", type: "date", rendu: (c) => dateFr(c.date_debut) },
  { cle: "anciennete", libelle: "Ancienneté", rendu: (c) => <Anciennete a={c.anciennete} />, exporter: (c) => c.anciennete?.libelle },
  { cle: "date_fin", libelle: "Échéance", type: "date", rendu: (c) => (c.date_fin ? dateFr(c.date_fin) : <span className="discret">Indéterminée</span>) },
  { cle: "statut_paiement", libelle: "Situation", rendu: (c) => (c.statut_contrat === "actif" ? <Statut s={c.statut_paiement} libelle={c.jours_retard ? `${c.jours_retard} j de retard` : undefined} /> : <Statut s="termine" />) },
  { cle: "loyer_mensuel", libelle: "Loyer", type: "montant" },
];

export default function Contrats() {
  const nav = useNavigate();
  const liste = useListe("/contrats/", { search: "", statut_contrat: "actif", statut_paiement: "" });
  return (
    <>
      <EntetePage titre="Contrats de bail" soustitre="Baux en cours et terminés, échéances et situation des paiements.">
        <Bouton icone={Download} onClick={() => exporterExcel("Contrats de bail", colonnes, liste.lignes)}>Excel</Bouton>
        <LienBouton variante="primaire" icone={FilePlus2} to="/contrats/nouveau">Nouveau contrat</LienBouton>
      </EntetePage>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Locataire, bien, bailleur…" />
        <Segments valeur={liste.filtres.statut_contrat} onChange={(statut_contrat) => liste.changer({ statut_contrat, statut_paiement: "" })} options={[{ valeur: "actif", libelle: "En cours" }, { valeur: "termine", libelle: "Terminés" }, { valeur: "", libelle: "Tous" }]} />
        {liste.filtres.statut_contrat === "actif" && (
          <Segments valeur={liste.filtres.statut_paiement} onChange={(statut_paiement) => liste.changer({ statut_paiement })} options={[{ valeur: "", libelle: "Toutes situations" }, { valeur: "en_retard", libelle: "En retard" }, { valeur: "echeance_proche", libelle: "Échéance proche" }, { valeur: "a_jour", libelle: "À jour" }]} />
        )}
      </div>
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} surLigne={(c) => nav(`/contrats/${c.id}`)} vide={<Vide icone={FileSignature} titre="Aucun contrat" action={<LienBouton variante="primaire" icone={FilePlus2} to="/contrats/nouveau">Nouveau contrat</LienBouton>} />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
    </>
  );
}
