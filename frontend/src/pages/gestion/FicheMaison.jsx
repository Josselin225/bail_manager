import { useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Camera, FilePlus2, Pencil, Trash2 } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr } from "../../lib/format";
import { useAction } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import { Bouton, Chargement, Definitions, EntetePage, LienBouton, Montant, Onglets, Panneau, Statut, Tableau, Vide, useConfirmer } from "../../components/ui";
import { Documents, FormMaison } from "../../components/formulaires";
import PhotosMaison from "../../components/PhotosMaison";
import Partage from "../../components/Partage";

export default function FicheMaison() {
  const { id } = useParams();
  const nav = useNavigate();
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const [onglet, setOnglet] = useState("historique");
  const [modif, setModif] = useState(false);
  const client = useQueryClient();
  const { data: m, refetch } = useQuery({ queryKey: ["maison", id], queryFn: () => api.get(`/maisons/${id}/`) });
  const { data: contrats } = useQuery({ queryKey: ["contrats", { maison: id }], queryFn: () => api.get("/contrats/", { maison: id, taille: 100 }) });
  const supprimer = useAction(() => api.delete(`/maisons/${id}/`), { succes: "Maison supprimée.", apres: () => nav("/maisons") });
  if (!m) return <Chargement />;

  return (
    <div className="pile">
      <EntetePage titre={m.designation} soustitre={<span className="ligne-flex"><Statut s={m.statut} /> {m.type_maison}</span>} fil={[{ libelle: "Maisons", lien: "/maisons" }]}>
        <Bouton icone={Pencil} onClick={() => setModif(true)}>Modifier</Bouton>
        {m.statut === "disponible" && <LienBouton variante="primaire" icone={FilePlus2} to={`/contrats/nouveau?maison=${m.id}`}>Louer ce bien</LienBouton>}
      </EntetePage>
      <Panneau titre={`Photos (${m.photos.length})`} icone={Camera} actions={<span className="discret petit-texte">La photo principale illustre la maison dans les listes et sur le site.</span>}>
        <PhotosMaison maison={m} onChange={(maj) => (client.setQueryData(["maison", id], maj), client.invalidateQueries({ queryKey: ["maisons"] }))} />
      </Panneau>
      <div className="grille g-1-2">
        <Panneau>
          <Definitions
            items={[
              ["Bailleur", <Link to={`/bailleurs/${m.bailleur}`}>{m.bailleur_nom}</Link>],
              ["Loyer", <Montant v={m.loyer} />],
              ["Condition d'entrée", `${m.condition} mois`],
              ["Adresse", m.adresse],
              ["Site vitrine", m.visible_site ? "Publiée quand libre" : "Non publiée"],
              ["Enregistrée le", dateFr(m.created_at)],
            ]}
          />
          {m.description && <p style={{ marginTop: 14, whiteSpace: "pre-wrap" }}>{m.description}</p>}
          {m.visible_site && m.statut === "disponible" && <div style={{ marginTop: 14 }}><Partage m={m} /></div>}
          {estAdmin && m.statut !== "occupe" && (
            <Bouton variante="fantome" taille="petit" icone={Trash2} style={{ color: "var(--signal)", marginTop: 8 }} onClick={async () => (await confirmer({ message: `Supprimer « ${m.designation} » ?`, danger: true, libelle: "Supprimer" })) && supprimer.mutate()}>Supprimer</Bouton>
          )}
        </Panneau>
        <Panneau>
          {m.locataire_actuel && (
            <div className="encart ok" style={{ marginBottom: 16 }}>
              <div>Occupée par <Link to={`/locataires/${m.locataire_actuel.id}`} className="gras">{m.locataire_actuel.nom}</Link> depuis le {dateFr(m.locataire_actuel.date_debut)} — <Link to={`/contrats/${m.locataire_actuel.contrat}`}>voir le contrat</Link></div>
            </div>
          )}
          <Onglets actif={onglet} onChange={setOnglet} onglets={[{ cle: "historique", libelle: "Historique des baux", compte: contrats?.count }, { cle: "documents", libelle: "Documents" }]} />
          {onglet === "historique" ? (
            <Tableau
              lignes={contrats?.results || []}
              surLigne={(c) => nav(`/contrats/${c.id}`)}
              vide={<Vide titre="Jamais louée" />}
              colonnes={[
                { libelle: "Contrat", cle: "reference" },
                { libelle: "Locataire", cle: "locataire_nom" },
                { libelle: "Période", rendu: (c) => `${dateFr(c.date_debut)} → ${c.date_resiliation ? dateFr(c.date_resiliation) : "en cours"}` },
                { libelle: "Statut", rendu: (c) => <Statut s={c.statut_contrat} /> },
                { libelle: "Loyer", cle: "loyer_mensuel", type: "montant" },
              ]}
            />
          ) : (
            <Documents type="maison" id={m.id} />
          )}
        </Panneau>
      </div>
      <FormMaison ouvert={modif} maison={m} onFermer={() => (setModif(false), refetch())} />
    </div>
  );
}
