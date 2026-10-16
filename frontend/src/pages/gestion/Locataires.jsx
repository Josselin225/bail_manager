import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Download, Plus, Users } from "lucide-react";
import { exporterExcel } from "../../lib/api";
import { dateFr } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import { Avatar, Badge, Bouton, EntetePage, Pagination, Panneau, Recherche, Segments, Statut, Tableau, Vide } from "../../components/ui";
import { FormLocataire } from "../../components/formulaires";

const colonnes = [
  { cle: "nom", libelle: "Locataire", rendu: (l) => <div className="identite"><Avatar nom={l.nom} src={l.photo_locataire} /><div><div className="principal-cellule">{l.nom}</div><div className="sous-cellule">{l.profession}</div></div></div> },
  { cle: "telephone1", libelle: "Téléphone" },
  { cle: "logement", libelle: "Logement", rendu: (l) => l.contrat_actif?.maison || <span className="discret">Sans bail en cours</span>, exporter: (l) => l.contrat_actif?.maison || "" },
  { cle: "paiement", libelle: "Paiements", rendu: (l) => (l.contrat_actif ? <Statut s={l.contrat_actif.statut_paiement} /> : null), exporter: (l) => l.contrat_actif?.statut_paiement || "" },
  { cle: "echeance", libelle: "Prochaine échéance", rendu: (l) => dateFr(l.contrat_actif?.date_prochain_loyer), exporter: (l) => dateFr(l.contrat_actif?.date_prochain_loyer) },
  { cle: "acces_portail", libelle: "Espace locataire", rendu: (l) => (l.acces_portail ? <Badge ton="ok">Activé</Badge> : <span className="discret">—</span>), exporter: (l) => (l.acces_portail ? "Oui" : "Non") },
];

export default function Locataires() {
  const nav = useNavigate();
  const [form, setForm] = useState(false);
  const liste = useListe("/locataires/", { search: "", statut: "" });
  return (
    <>
      <EntetePage titre="Locataires" soustitre="Dossiers des locataires, pièces d'identité et accès à l'espace en ligne.">
        <Bouton icone={Download} onClick={() => exporterExcel("Liste des locataires", colonnes, liste.lignes)}>Excel</Bouton>
        <Bouton variante="primaire" icone={Plus} onClick={() => setForm(true)}>Nouveau locataire</Bouton>
      </EntetePage>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Nom, téléphone, e-mail, pièce…" />
        <Segments valeur={liste.filtres.statut} onChange={(statut) => liste.changer({ statut })} options={[{ valeur: "", libelle: "Tous" }, { valeur: "actif", libelle: "Avec bail" }, { valeur: "sans_contrat", libelle: "Sans bail" }]} />
      </div>
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} surLigne={(l) => nav(`/locataires/${l.id}`)} vide={<Vide icone={Users} titre="Aucun locataire" action={<Bouton variante="primaire" icone={Plus} onClick={() => setForm(true)}>Nouveau locataire</Bouton>} />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
      <FormLocataire ouvert={form} onFermer={() => setForm(false)} onEnregistre={(l) => nav(`/locataires/${l.id}`)} />
    </>
  );
}
