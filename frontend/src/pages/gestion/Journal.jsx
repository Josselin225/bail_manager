import { useQuery } from "@tanstack/react-query";
import { Download, Eraser, History } from "lucide-react";
import { api, exporterExcel } from "../../lib/api";
import { dateHeure } from "../../lib/format";
import { useAction, useListe } from "../../lib/hooks";
import { Bouton, EntetePage, Liste, Pagination, Panneau, Recherche, Saisie, Tableau, Vide, useConfirmer } from "../../components/ui";

const colonnes = [
  { cle: "date_action", libelle: "Date", rendu: (j) => <span className="nowrap">{dateHeure(j.date_action)}</span>, exporter: (j) => dateHeure(j.date_action) },
  { cle: "utilisateur_nom", libelle: "Utilisateur" },
  { cle: "action", libelle: "Action", rendu: (j) => <b>{j.action}</b> },
  { cle: "details", libelle: "Détails", rendu: (j) => <span className="discret">{j.details}</span> },
  { cle: "ip_adresse", libelle: "Adresse IP" },
];

export default function Journal() {
  const confirmer = useConfirmer();
  const liste = useListe("/journal/", { search: "", action: "", du: "", au: "", taille: 50 });
  const { data: actions = [] } = useQuery({ queryKey: ["journal-actions"], queryFn: () => api.get("/journal/actions/") });
  const purger = useAction(() => api.post("/journal/vider/", { jours: 90 }), { succes: (r) => `${r.supprimees} entrée(s) supprimée(s).` });
  return (
    <>
      <EntetePage titre="Journal d'activité" soustitre="Chaque opération est tracée : qui, quoi, quand et depuis quel poste.">
        <Bouton icone={Download} onClick={() => exporterExcel("Journal d'activité", colonnes, liste.lignes)}>Excel</Bouton>
        <Bouton icone={Eraser} onClick={async () => (await confirmer({ message: "Supprimer les entrées de plus de 90 jours ? Les plus récentes sont conservées.", danger: true, libelle: "Purger" })) && purger.mutate()}>Purger (+ de 90 j)</Bouton>
      </EntetePage>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Action, détail, IP…" />
        <Liste valeur={liste.filtres.action} onChange={(action) => liste.changer({ action })} vide="Toutes les actions" options={actions} />
        <Saisie type="date" valeur={liste.filtres.du} onChange={(du) => liste.changer({ du })} aria-label="Du" />
        <Saisie type="date" valeur={liste.filtres.au} onChange={(au) => liste.changer({ au })} aria-label="Au" />
      </div>
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} vide={<Vide icone={History} titre="Aucune entrée" />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
    </>
  );
}
