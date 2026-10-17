import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { CalendarClock, Download, Printer, TrendingUp } from "lucide-react";
import { api, exporterExcel } from "../../lib/api";
import { dateFr, fcfa } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import { Badge, Bouton, Encart, EntetePage, LienBouton, Onglets, Pagination, Panneau, Recherche, Tableau, Vide } from "../../components/ui";

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
  { libelle: "", rendu: (r) => <LienBouton taille="petit" variante="fantome" icone={Printer} to={`/impression/revision/${r.id}`}>Lettre</LienBouton> },
];

/** Baux dont le loyer n'a pas bougé depuis trois ans : révision triennale à proposer au bailleur. */
function AReviser() {
  const { estAdmin } = useSession();
  const { data = [], isLoading } = useQuery({ queryKey: ["revisions-a-echeance"], queryFn: () => api.get("/revisions/a-echeance/") });
  return (
    <Panneau serre>
      <Tableau
        lignes={data}
        cle="contrat"
        chargement={isLoading}
        vide={<Vide icone={CalendarClock} titre="Aucune révision à prévoir" texte="Aucun bail n'atteint trois ans sans révision dans les 90 prochains jours." />}
        colonnes={[
          { libelle: "Contrat", rendu: (r) => <Link to={`/contrats/${r.contrat}`}><div className="principal-cellule">{r.locataire}</div><div className="sous-cellule">{r.maison} — {r.reference}</div></Link> },
          { libelle: "Bailleur", cle: "bailleur" },
          { libelle: "Loyer inchangé depuis", rendu: (r) => <>{dateFr(r.depuis)}<div className="sous-cellule">{r.derniere_revision ? "dernière révision" : "début du bail"}</div></> },
          { libelle: "Révision possible", rendu: (r) => <>{dateFr(r.echeance)}<div className="sous-cellule">{r.jours > 0 ? `dans ${r.jours} j` : <Badge ton="attention">Possible dès maintenant</Badge>}</div></> },
          { libelle: "Loyer actuel", cle: "loyer", type: "montant" },
          { libelle: "", rendu: (r) => (estAdmin ? <LienBouton taille="petit" variante="primaire" icone={TrendingUp} to={`/contrats/${r.contrat}?reviser=triennale`}>Réviser</LienBouton> : null) },
        ]}
      />
    </Panneau>
  );
}

export default function Revisions() {
  const [params] = useSearchParams();
  const [onglet, setOnglet] = useState(params.get("onglet") || "historique");
  const liste = useListe("/revisions/", { search: "" });
  const { data: aReviser = [] } = useQuery({ queryKey: ["revisions-a-echeance"], queryFn: () => api.get("/revisions/a-echeance/") });
  return (
    <>
      <EntetePage titre="Révisions de loyer" soustitre="Baux à réviser, historique des hausses et baisses, et lettres d'information aux locataires.">
        {onglet === "historique" && <Bouton icone={Download} onClick={() => exporterExcel("Révisions de loyer", colonnes, liste.lignes)}>Excel</Bouton>}
      </EntetePage>
      <Encart>Art. 423-424 : le loyer peut être révisé tous les trois (03) ans, avec une notification écrite au moins trois (03) mois avant la date d'effet. Après chaque révision, imprimez la lettre et remettez-la au locataire.</Encart>
      <Onglets actif={onglet} onChange={setOnglet} onglets={[{ cle: "a-reviser", libelle: "À réviser", compte: aReviser.length || null }, { cle: "historique", libelle: "Historique" }]} />
      {onglet === "a-reviser" ? <AReviser /> : (
        <>
          <div className="barre-filtres">
            <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Locataire, bien, motif…" />
          </div>
          <Panneau serre>
            <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} vide={<Vide icone={TrendingUp} titre="Aucune révision" texte="Ouvrez un contrat puis « Réviser le loyer »." />} />
            <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
          </Panneau>
        </>
      )}
    </>
  );
}
