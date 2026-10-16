import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Download, Home, LayoutGrid, List, Plus } from "lucide-react";
import { api, exporterExcel } from "../../lib/api";
import { useListe } from "../../lib/hooks";
import { Bouton, EntetePage, Liste, Montant, Pagination, Panneau, Recherche, Segments, Statut, Tableau, Vide } from "../../components/ui";
import { FormMaison } from "../../components/formulaires";

const colonnes = [
  { cle: "designation", libelle: "Bien", rendu: (m) => <div className="identite">{m.photo ? <img src={m.photo} alt="" style={{ width: 44, height: 34, objectFit: "cover", borderRadius: 5 }} /> : <span className="avatar"><Home size={16} /></span>}<div><div className="principal-cellule">{m.designation}</div><div className="sous-cellule">{m.type_maison}</div></div></div> },
  { cle: "bailleur_nom", libelle: "Bailleur" },
  { cle: "locataire", libelle: "Locataire", rendu: (m) => m.locataire_actuel?.nom || <span className="discret">—</span>, exporter: (m) => m.locataire_actuel?.nom || "" },
  { cle: "statut_libelle", libelle: "Statut", rendu: (m) => <Statut s={m.statut} /> },
  { cle: "loyer", libelle: "Loyer", type: "montant" },
];

export default function Maisons() {
  const nav = useNavigate();
  const [vue, setVue] = useState(() => localStorage.getItem("bm-vue-maisons") || "liste");
  const [form, setForm] = useState(false);
  const liste = useListe("/maisons/", { search: "", statut: "", bailleur: "" });
  const { data: bailleurs = [] } = useQuery({ queryKey: ["bailleurs-options"], queryFn: () => api.get("/bailleurs/options/") });
  const changerVue = (v) => (setVue(v), localStorage.setItem("bm-vue-maisons", v));

  return (
    <>
      <EntetePage titre="Maisons" soustitre="Les biens confiés à l'agence, libres ou occupés.">
        <Bouton icone={Download} onClick={() => exporterExcel("Liste des maisons", colonnes, liste.lignes)}>Excel</Bouton>
        <Bouton variante="primaire" icone={Plus} onClick={() => setForm(true)}>Nouvelle maison</Bouton>
      </EntetePage>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Désignation, adresse, bailleur…" />
        <Segments valeur={liste.filtres.statut} onChange={(statut) => liste.changer({ statut })} options={[{ valeur: "", libelle: "Toutes" }, { valeur: "disponible", libelle: "Libres" }, { valeur: "occupe", libelle: "Occupées" }]} />
        <Liste valeur={liste.filtres.bailleur} onChange={(bailleur) => liste.changer({ bailleur })} vide="Tous les bailleurs" options={bailleurs.map((b) => ({ valeur: b.id, libelle: b.nom }))} />
        <Segments valeur={vue} onChange={changerVue} options={[{ valeur: "liste", libelle: <List size={15} /> }, { valeur: "cartes", libelle: <LayoutGrid size={15} /> }]} />
      </div>
      {vue === "liste" ? (
        <Panneau serre>
          <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} surLigne={(m) => nav(`/maisons/${m.id}`)} vide={<Vide icone={Home} titre="Aucune maison" texte="Ajoutez un bien à un bailleur sous mandat." />} />
          <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
        </Panneau>
      ) : (
        <>
          <div className="cartes-biens">
            {liste.lignes.map((m) => (
              <Link key={m.id} to={`/maisons/${m.id}`} className="bien">
                <div className="photo">{m.photo ? <img src={m.photo} alt="" /> : <Home size={30} />}</div>
                <div className="infos">
                  <div className="ligne-flex"><b className="tronque" style={{ flex: 1 }}>{m.designation}</b><Statut s={m.statut} /></div>
                  <div className="discret petit-texte">{m.type_maison} — {m.bailleur_nom}</div>
                  <div style={{ marginTop: 6 }}><Montant v={m.loyer} /> <span className="discret">/ mois</span></div>
                  {m.locataire_actuel && <div className="petit-texte">Occupée par {m.locataire_actuel.nom}</div>}
                </div>
              </Link>
            ))}
          </div>
          <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
        </>
      )}
      <FormMaison ouvert={form} onFermer={() => setForm(false)} onEnregistre={(m) => nav(`/maisons/${m.id}`)} />
    </>
  );
}
