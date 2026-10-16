import { CalendarCheck, Check, Phone, Trash2, X } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr } from "../../lib/format";
import { useAction, useListe } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import { Bouton, EntetePage, Pagination, Panneau, Recherche, Segments, Statut, Tableau, Vide, useConfirmer } from "../../components/ui";

export default function Reservations() {
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const liste = useListe("/reservations/", { search: "", statut: "en_attente" });
  const statut = useAction(({ r, s }) => api.patch(`/reservations/${r.id}/`, { statut: s }), { succes: "Réservation mise à jour." });
  const suppr = useAction((r) => api.delete(`/reservations/${r.id}/`), { succes: "Réservation supprimée." });
  return (
    <>
      <EntetePage titre="Réservations de visite" soustitre="Demandes envoyées depuis la page « Nos maisons » du site." />
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Visiteur, téléphone, bien…" />
        <Segments valeur={liste.filtres.statut} onChange={(s) => liste.changer({ statut: s })} options={[{ valeur: "en_attente", libelle: "À traiter" }, { valeur: "confirme", libelle: "Confirmées" }, { valeur: "termine", libelle: "Effectuées" }, { valeur: "annule", libelle: "Annulées" }, { valeur: "", libelle: "Toutes" }]} />
      </div>
      <Panneau serre>
        <Tableau
          lignes={liste.lignes}
          chargement={liste.isLoading}
          vide={<Vide icone={CalendarCheck} titre="Aucune réservation" texte="Les demandes de visite du site apparaîtront ici." />}
          colonnes={[
            { libelle: "Visiteur", rendu: (r) => <><div className="principal-cellule">{r.nom_visiteur}</div><a className="sous-cellule" href={`tel:${r.tel_visiteur}`}><Phone size={11} /> {r.tel_visiteur}</a></> },
            { libelle: "Bien", rendu: (r) => <>{r.maison_nom}{r.maison_statut === "occupe" && <div className="sous-cellule" style={{ color: "var(--signal)" }}>Désormais occupé</div>}</> },
            { libelle: "Visite le", rendu: (r) => dateFr(r.date_visite) },
            { libelle: "Message", rendu: (r) => <span className="discret">{r.message}</span> },
            { libelle: "Reçue le", rendu: (r) => dateFr(r.created_at) },
            { libelle: "Statut", rendu: (r) => <Statut s={r.statut} /> },
            {
              libelle: "",
              rendu: (r) => (
                <div className="actions-cellule">
                  {r.statut === "en_attente" && <Bouton taille="petit" icone={Check} onClick={() => statut.mutate({ r, s: "confirme" })}>Confirmer</Bouton>}
                  {r.statut === "confirme" && <Bouton taille="petit" icone={Check} onClick={() => statut.mutate({ r, s: "termine" })}>Visite faite</Bouton>}
                  {["en_attente", "confirme"].includes(r.statut) && <Bouton taille="petit" variante="fantome" icone={X} onClick={() => statut.mutate({ r, s: "annule" })} aria-label="Annuler" title="Annuler" />}
                  {estAdmin && <Bouton taille="petit" variante="fantome" icone={Trash2} aria-label="Supprimer" onClick={async () => (await confirmer({ message: "Supprimer cette réservation ?", danger: true })) && suppr.mutate(r)} />}
                </div>
              ),
            },
          ]}
        />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
    </>
  );
}
