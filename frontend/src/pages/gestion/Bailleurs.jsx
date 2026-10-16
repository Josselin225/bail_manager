import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Download, Plus, UserRound } from "lucide-react";
import { exporterExcel } from "../../lib/api";
import { useListe } from "../../lib/hooks";
import { Avatar, Badge, Bouton, EntetePage, Montant, Pagination, Panneau, Recherche, Segments, Tableau, Vide } from "../../components/ui";
import { FormBailleur, FormMandat } from "../../components/formulaires";

const colonnes = [
  { cle: "nom", libelle: "Bailleur", rendu: (b) => <div className="identite"><Avatar nom={b.nom} src={b.photo} /><div><div className="principal-cellule">{b.nom}</div><div className="sous-cellule">{b.code_bailleur}</div></div></div> },
  { cle: "telephone1", libelle: "Téléphone" },
  { cle: "mandat", libelle: "Mandat", rendu: (b) => (b.mandat ? <Badge ton="ok">{b.mandat.taux_commission} %</Badge> : <Badge ton="alerte">Sans mandat</Badge>), exporter: (b) => (b.mandat ? `${b.mandat.taux_commission} %` : "Aucun") },
  { cle: "nb_maisons", libelle: "Biens", rendu: (b) => `${b.nb_occupees}/${b.nb_maisons} occupé(s)`, exporter: (b) => b.nb_maisons },
  { cle: "commissions", libelle: "Commissions", type: "montant" },
  { cle: "solde", libelle: "Solde dû", type: "montant", rendu: (b) => <b><Montant v={b.solde} /></b> },
];

export default function Bailleurs() {
  const nav = useNavigate();
  const liste = useListe("/bailleurs/", { search: "" });
  const [form, setForm] = useState(false);
  const [mandatPour, setMandatPour] = useState(null);
  const [vue, setVue] = useState("tous");
  const lignes = vue === "sans_mandat" ? liste.lignes.filter((b) => !b.mandat) : liste.lignes;

  return (
    <>
      <EntetePage titre="Bailleurs" soustitre="Propriétaires dont l'agence gère les biens, avec leur mandat et leur compte.">
        <Bouton icone={Download} onClick={() => exporterExcel("Liste des bailleurs", colonnes, liste.lignes)}>Excel</Bouton>
        <Bouton variante="primaire" icone={Plus} onClick={() => setForm(true)}>Nouveau bailleur</Bouton>
      </EntetePage>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Nom, code, téléphone, e-mail…" />
        <Segments valeur={vue} onChange={setVue} options={[{ valeur: "tous", libelle: "Tous" }, { valeur: "sans_mandat", libelle: "Sans mandat" }]} />
      </div>
      <Panneau serre>
        <Tableau
          colonnes={colonnes}
          lignes={lignes}
          chargement={liste.isLoading}
          surLigne={(b) => nav(`/bailleurs/${b.id}`)}
          vide={<Vide icone={UserRound} titre="Aucun bailleur" texte="Enregistrez un premier propriétaire pour commencer à gérer ses biens." action={<Bouton variante="primaire" icone={Plus} onClick={() => setForm(true)}>Nouveau bailleur</Bouton>} />}
        />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
      <FormBailleur ouvert={form} onFermer={() => setForm(false)} onEnregistre={(b, nouveau) => (liste.refetch(), nouveau && setMandatPour(b))} />
      <FormMandat ouvert={!!mandatPour} bailleur={mandatPour} onFermer={() => setMandatPour(null)} onEnregistre={() => nav(`/bailleurs/${mandatPour.id}`)} />
    </>
  );
}
