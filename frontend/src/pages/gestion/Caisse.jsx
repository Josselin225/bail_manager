import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Download, MinusCircle, PlusCircle, Receipt } from "lucide-react";
import { api, exporterExcel } from "../../lib/api";
import { dateHeure, fcfa, nombre } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import { Bouton, Encart, EntetePage, FenetreFormulaire, Liste, Montant, Pagination, Panneau, Recherche, Saisie, SaisieMontant, Segments, Tableau, Vide } from "../../components/ui";

const colonnes = [
  { cle: "date_operation", libelle: "Date", rendu: (m) => <span className="nowrap">{dateHeure(m.date_operation)}</span>, exporter: (m) => dateHeure(m.date_operation) },
  { cle: "type_mouvement", libelle: "Type", rendu: (m) => <><div className="principal-cellule">{m.type_mouvement}</div><div className="sous-cellule">{m.commentaire}</div></> },
  { cle: "bailleur_nom", libelle: "Bailleur" },
  { cle: "reference_paiement", libelle: "Référence" },
  { cle: "effectue_par", libelle: "Par" },
  { cle: "montant", libelle: "Montant", type: "montant", rendu: (m) => <Montant v={m.montant} signe /> },
];

export default function Caisse() {
  const [modal, setModal] = useState(null);
  const [f, setF] = useState({});
  const liste = useListe("/caisse/", { search: "", sens: "", du: "", au: "", effectue_par: "" });
  const { page, ...filtres } = liste.filtres;
  const { data: syn, refetch } = useQuery({ queryKey: ["caisse-synthese", filtres], queryFn: () => api.get("/caisse/synthese/", filtres) });
  const fermer = () => (setModal(null), liste.refetch(), refetch());
  return (
    <>
      <EntetePage titre="Caisse agence" soustitre="Commissions, frais d'agence, retenues pour réparations et dépenses de l'agence.">
        <Bouton icone={Download} onClick={async () => exporterExcel("Caisse agence", colonnes, (await api.get("/caisse/", { ...filtres, taille: 5000 })).results)}>Excel</Bouton>
        <Bouton icone={PlusCircle} onClick={() => (setF({ type_mouvement: "Autre entrée", montant: "", commentaire: "" }), setModal("entree"))}>Entrée</Bouton>
        <Bouton variante="primaire" icone={MinusCircle} onClick={() => (setF({ motif: "", montant: "", commentaire: "" }), setModal("retrait"))}>Retrait / dépense</Bouton>
      </EntetePage>
      {syn && (
        <div className="registre" style={{ marginBottom: 16 }}>
          <div className="bon"><div className="libelle">Solde de caisse</div><div className="valeur">{nombre(syn.solde)}<small>FCFA</small></div></div>
          <div><div className="libelle">Entrées (filtre)</div><div className="valeur">{nombre(syn.entrees)}<small>FCFA</small></div></div>
          <div><div className="libelle">Sorties (filtre)</div><div className="valeur">{nombre(syn.sorties)}<small>FCFA</small></div></div>
          <div><div className="libelle">Principales sources</div>{syn.par_type.slice(0, 3).map((t) => <div key={t.type_mouvement} className="note">{t.type_mouvement} : {fcfa(t.total)}</div>)}</div>
        </div>
      )}
      <div className="barre-filtres">
        <Recherche valeur={filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Commentaire, type, référence…" />
        <Segments valeur={filtres.sens} onChange={(sens) => liste.changer({ sens })} options={[{ valeur: "", libelle: "Tout" }, { valeur: "entree", libelle: "Entrées" }, { valeur: "sortie", libelle: "Sorties" }]} />
        <Saisie type="date" valeur={filtres.du} onChange={(du) => liste.changer({ du })} aria-label="Du" />
        <Saisie type="date" valeur={filtres.au} onChange={(au) => liste.changer({ au })} aria-label="Au" />
        <Liste valeur={filtres.effectue_par} onChange={(effectue_par) => liste.changer({ effectue_par })} vide="Tous les utilisateurs" options={syn?.auteurs || []} />
      </div>
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} vide={<Vide icone={Receipt} titre="Aucun mouvement" />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
      <FenetreFormulaire titre="Sortie de caisse" ouvert={modal === "retrait"} onFermer={fermer} variante="danger" libelleValider="Enregistrer la sortie" onValider={async () => (await api.post("/caisse/retrait/", f), "Retrait enregistré.")}>
        {syn && <Encart>Solde disponible : <b>{fcfa(syn.solde)}</b></Encart>}
        <div className="formulaire">
          <Saisie libelle="Motif" plein requis valeur={f.motif} onChange={(motif) => setF({ ...f, motif })} placeholder="Ex. : achat de fournitures, facture CIE…" />
          <SaisieMontant libelle="Montant" requis max={syn?.solde || undefined} valeur={f.montant} onChange={(montant) => setF({ ...f, montant })} />
          <Saisie libelle="Commentaire" valeur={f.commentaire} onChange={(commentaire) => setF({ ...f, commentaire })} />
        </div>
      </FenetreFormulaire>
      <FenetreFormulaire titre="Entrée en caisse" ouvert={modal === "entree"} onFermer={fermer} libelleValider="Enregistrer l'entrée" onValider={async () => (await api.post("/caisse/entree/", f), "Entrée enregistrée.")}>
        <div className="formulaire">
          <Liste libelle="Type d'entrée" valeur={f.type_mouvement} onChange={(type_mouvement) => setF({ ...f, type_mouvement })} options={["Autre entrée", "Droit d'agence", "Entrée (Réparation)", "Commission Gestion"]} />
          <SaisieMontant libelle="Montant" requis valeur={f.montant} onChange={(montant) => setF({ ...f, montant })} />
          <Saisie libelle="Commentaire" valeur={f.commentaire} onChange={(commentaire) => setF({ ...f, commentaire })} />
        </div>
      </FenetreFormulaire>
    </>
  );
}
