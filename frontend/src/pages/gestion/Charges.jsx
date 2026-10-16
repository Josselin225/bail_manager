import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Download, Pencil, Plus, Trash2, Zap } from "lucide-react";
import { api, exporterExcel } from "../../lib/api";
import { dateFr, nombre } from "../../lib/format";
import { useAction, useListe } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import { Bouton, EntetePage, Liste, Pagination, Panneau, Recherche, Tableau, Vide, useConfirmer } from "../../components/ui";
import { FormCharge } from "./FicheContrat";

export default function Charges() {
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const annee = new Date().getFullYear();
  const [modal, setModal] = useState(null);
  const liste = useListe("/charges/", { search: "", type_charge: "", annee });
  const { data: types = [] } = useQuery({ queryKey: ["types-charges"], queryFn: () => api.get("/charges/types/") });
  const suppr = useAction((c) => api.delete(`/charges/${c.id}/`), { succes: "Charge supprimée." });
  const total = liste.lignes.reduce((s, c) => s + Number(c.montant), 0);
  const colonnes = [
    { cle: "date_charge", libelle: "Date", rendu: (c) => dateFr(c.date_charge), type: "date" },
    { cle: "locataire_nom", libelle: "Locataire", rendu: (c) => <Link to={`/contrats/${c.contrat}`}><div className="principal-cellule">{c.locataire_nom}</div><div className="sous-cellule">{c.maison_nom}</div></Link> },
    { cle: "type_charge", libelle: "Type" },
    { cle: "description", libelle: "Description" },
    { cle: "montant", libelle: "Montant", type: "montant" },
    { libelle: "", export: false, rendu: (c) => <div className="actions-cellule"><Bouton taille="petit" variante="fantome" icone={Pencil} onClick={() => setModal(c)} aria-label="Modifier" />{estAdmin && <Bouton taille="petit" variante="fantome" icone={Trash2} aria-label="Supprimer" onClick={async () => (await confirmer({ message: "Supprimer cette charge ?", danger: true })) && suppr.mutate(c)} />}</div> },
  ];
  return (
    <>
      <EntetePage titre="Charges locatives" soustitre="Eau, électricité, ordures, entretien… imputés aux locataires (Art. 417), visibles dans leur espace.">
        <Bouton icone={Download} onClick={() => exporterExcel(`Charges locatives ${liste.filtres.annee}`, colonnes, liste.lignes)}>Excel</Bouton>
        <Bouton variante="primaire" icone={Plus} onClick={() => setModal("nouveau")}>Nouvelle charge</Bouton>
      </EntetePage>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Locataire, bien, description…" />
        <Liste valeur={liste.filtres.type_charge} onChange={(type_charge) => liste.changer({ type_charge })} vide="Tous les types" options={types} />
        <Liste valeur={liste.filtres.annee} onChange={(a) => liste.changer({ annee: a })} options={[annee - 2, annee - 1, annee].map((a) => ({ valeur: a, libelle: String(a) }))} />
      </div>
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} vide={<Vide icone={Zap} titre="Aucune charge enregistrée" />} pied={liste.lignes.length ? <tr><td colSpan={4}>Total de la page</td><td className="droite">{nombre(total)} FCFA</td><td /></tr> : null} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
      <FormCharge ouvert={!!modal} charge={modal !== "nouveau" ? modal : null} onFermer={() => (setModal(null), liste.refetch())} />
    </>
  );
}
