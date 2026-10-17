import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { CalendarCheck, Check, ChevronLeft, ChevronRight, Clock, MapPin, Phone, Plus, Trash2, UserRound, X } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr, isoDate } from "../../lib/format";
import { useAction, useListe } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import {
  Badge, Bouton, Case, Combo, EntetePage, FenetreFormulaire, Liste, Onglets, Pagination, Panneau, Recherche, Saisie, Segments, Statut, Tableau, Vide, Zone, useConfirmer,
} from "../../components/ui";

const JOURS = ["dimanche", "lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi"];
const decaler = (iso, n) => { const d = new Date(`${iso}T00:00`); d.setDate(d.getDate() + n); return isoDate(d); };
const libelleJour = (iso) => {
  const d = new Date(`${iso}T00:00`);
  const auj = isoDate();
  const prefixe = iso === auj ? "Aujourd'hui" : iso === decaler(auj, 1) ? "Demain" : JOURS[d.getDay()].replace(/^./, (c) => c.toUpperCase());
  return `${prefixe} ${d.toLocaleDateString("fr-FR", { day: "numeric", month: "long" })}`;
};

/** Nouvelle visite prise au téléphone ou à l'agence. */
function FormVisite({ ouvert, onFermer }) {
  const { utilisateur } = useSession();
  const vide = { maison: "", nom_visiteur: "", tel_visiteur: "", date_visite: isoDate(), heure_visite: "", origine: "telephone", agent: utilisateur?.id || "", message: "" };
  const [f, setF] = useState(vide);
  const { data: maisons } = useQuery({ queryKey: ["maisons-disponibles"], queryFn: () => api.get("/maisons/", { statut: "disponible", taille: 500 }), enabled: ouvert });
  const { data: agents = [] } = useQuery({ queryKey: ["agents"], queryFn: () => api.get("/reservations/agents/"), enabled: ouvert });
  return (
    <FenetreFormulaire titre="Nouvelle visite" ouvert={ouvert} onFermer={() => (setF(vide), onFermer())} icone={CalendarCheck}
      onValider={async () => { await api.post("/reservations/", { ...f, heure_visite: f.heure_visite || null, agent: f.agent || null }); setF(vide); return "Visite enregistrée."; }}>
      <div className="formulaire">
        <Combo libelle="Bien à visiter" plein requis valeur={f.maison} onChange={(maison) => setF({ ...f, maison })}
          options={(maisons?.results || []).map((m) => ({ valeur: m.id, libelle: m.designation, sous: `${m.adresse} — ${Math.round(m.loyer).toLocaleString("fr-FR")} FCFA` }))} />
        <Saisie libelle="Visiteur" requis valeur={f.nom_visiteur} onChange={(nom_visiteur) => setF({ ...f, nom_visiteur })} />
        <Saisie libelle="Téléphone" type="tel" requis valeur={f.tel_visiteur} onChange={(tel_visiteur) => setF({ ...f, tel_visiteur })} />
        <Saisie libelle="Date" type="date" requis valeur={f.date_visite} onChange={(date_visite) => setF({ ...f, date_visite })} />
        <Saisie libelle="Heure" type="time" valeur={f.heure_visite} onChange={(heure_visite) => setF({ ...f, heure_visite })} />
        <Liste libelle="Agent qui fait visiter" valeur={f.agent} onChange={(agent) => setF({ ...f, agent })} options={[{ valeur: "", libelle: "— à attribuer —" }, ...agents]} />
        <Liste libelle="Demande reçue" valeur={f.origine} onChange={(origine) => setF({ ...f, origine })} options={[{ valeur: "telephone", libelle: "Par téléphone" }, { valeur: "agence", libelle: "À l'agence" }]} />
        <Zone libelle="Note" plein valeur={f.message} onChange={(message) => setF({ ...f, message })} rows={2} />
      </div>
    </FenetreFormulaire>
  );
}

/** Compte rendu de visite : impression du visiteur, suite à donner. */
function FormCompteRendu({ visite, onFermer }) {
  const [f, setF] = useState({ compte_rendu: "", interesse: false });
  return (
    <FenetreFormulaire titre={`Visite faite — ${visite?.nom_visiteur || ""}`} ouvert={!!visite} onFermer={onFermer} libelleValider="Enregistrer" icone={Check}
      onValider={async () => { await api.patch(`/reservations/${visite.id}/`, { statut: "termine", ...f }); return "Compte rendu enregistré."; }}>
      <div className="formulaire">
        <Case libelle="Le visiteur est intéressé" aide="À rappeler pour constituer son dossier." valeur={f.interesse} onChange={(interesse) => setF({ ...f, interesse })} />
        <Zone libelle="Compte rendu" plein valeur={f.compte_rendu} onChange={(compte_rendu) => setF({ ...f, compte_rendu })} rows={3} placeholder="Remarques du visiteur, documents demandés, date de rappel…" />
      </div>
    </FenetreFormulaire>
  );
}

function Agenda() {
  const { utilisateur } = useSession();
  const [debut, setDebut] = useState(isoDate());
  const [miennes, setMiennes] = useState("");
  const [fait, setFait] = useState(null);
  const fin = decaler(debut, 6);
  const { data = [], isLoading, refetch } = useQuery({ queryKey: ["agenda-visites", debut, miennes], queryFn: () => api.get("/reservations/agenda/", { du: debut, au: fin, moi: miennes }) });
  const maj = useAction(({ v, d }) => api.patch(`/reservations/${v.id}/`, d), { succes: "Visite mise à jour.", apres: () => refetch() });
  const jours = useMemo(() => {
    const g = {};
    data.forEach((v) => (g[v.date_visite] ||= []).push(v));
    return Object.entries(g);
  }, [data]);
  return (
    <div className="pile">
      <div className="barre-filtres">
        <div className="ligne-flex">
          <Bouton taille="petit" icone={ChevronLeft} onClick={() => setDebut(decaler(debut, -7))} aria-label="Semaine précédente" />
          <b>Du {dateFr(debut)} au {dateFr(fin)}</b>
          <Bouton taille="petit" icone={ChevronRight} onClick={() => setDebut(decaler(debut, 7))} aria-label="Semaine suivante" />
          {debut !== isoDate() && <Bouton taille="petit" variante="fantome" onClick={() => setDebut(isoDate())}>Aujourd'hui</Bouton>}
        </div>
        <Segments valeur={miennes} onChange={setMiennes} options={[{ valeur: "", libelle: "Toutes les visites" }, { valeur: "1", libelle: "Mes visites" }]} />
      </div>
      {isLoading ? null : jours.length === 0 ? (
        <Panneau><Vide icone={CalendarCheck} titre="Aucune visite cette semaine" texte="Les demandes du site et les visites prises au téléphone apparaissent ici." /></Panneau>
      ) : jours.map(([jour, visites]) => (
        <Panneau key={jour} titre={libelleJour(jour)} actions={<span className="petit-texte discret">{visites.length} visite{visites.length > 1 ? "s" : ""}</span>}>
          <div className="liste-visites">
            {visites.map((v) => (
              <div key={v.id} className={`carte-visite statut-${v.statut}`}>
                <div className="heure-visite"><Clock size={14} /> {v.heure_visite ? v.heure_visite.slice(0, 5) : "Heure à fixer"}</div>
                <div className="corps-visite">
                  <div className="ligne-flex" style={{ justifyContent: "space-between" }}><b>{v.nom_visiteur}</b><Statut s={v.statut} /></div>
                  <div className="petit-texte"><MapPin size={12} /> {v.maison_nom}{v.maison_adresse && ` — ${v.maison_adresse}`}</div>
                  <div className="petit-texte"><a href={`tel:${v.tel_visiteur}`}><Phone size={12} /> {v.tel_visiteur}</a> <span className="discret">· {v.origine_libelle}</span></div>
                  <div className="petit-texte discret"><UserRound size={12} /> {v.agent_nom || "Agent à attribuer"}</div>
                  {v.message && <div className="petit-texte discret">« {v.message} »</div>}
                  {v.compte_rendu && <div className="petit-texte">{v.interesse && <Badge ton="ok">Intéressé</Badge>} {v.compte_rendu}</div>}
                  {v.maison_statut === "occupe" && v.statut !== "termine" && <div className="petit-texte texte-alerte">Bien désormais loué</div>}
                </div>
                <div className="actions-visite">
                  {!v.agent && <Bouton taille="petit" onClick={() => maj.mutate({ v, d: { agent: utilisateur.id } })}>Je m'en charge</Bouton>}
                  {v.statut === "en_attente" && <Bouton taille="petit" icone={Check} onClick={() => maj.mutate({ v, d: { statut: "confirme" } })}>Confirmer</Bouton>}
                  {["en_attente", "confirme"].includes(v.statut) && <Bouton taille="petit" variante="primaire" icone={Check} onClick={() => setFait(v)}>Visite faite</Bouton>}
                  {["en_attente", "confirme"].includes(v.statut) && <Bouton taille="petit" variante="fantome" icone={X} onClick={() => maj.mutate({ v, d: { statut: "annule" } })} aria-label="Annuler la visite" title="Annuler" />}
                </div>
              </div>
            ))}
          </div>
        </Panneau>
      ))}
      <FormCompteRendu visite={fait} onFermer={() => (setFait(null), refetch())} />
    </div>
  );
}

function Demandes() {
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const liste = useListe("/reservations/", { search: "", statut: "en_attente" });
  const statut = useAction(({ r, s }) => api.patch(`/reservations/${r.id}/`, { statut: s }), { succes: "Réservation mise à jour." });
  const suppr = useAction((r) => api.delete(`/reservations/${r.id}/`), { succes: "Réservation supprimée." });
  return (
    <>
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
            { libelle: "Visite le", rendu: (r) => <>{dateFr(r.date_visite)}{r.heure_visite && <div className="sous-cellule">à {r.heure_visite.slice(0, 5)}</div>}</> },
            { libelle: "Agent", rendu: (r) => r.agent_nom || <span className="discret">—</span> },
            { libelle: "Message", rendu: (r) => <span className="discret">{r.compte_rendu || r.message}</span> },
            { libelle: "Statut", rendu: (r) => <><Statut s={r.statut} /><div className="sous-cellule">{r.origine_libelle}</div></> },
            {
              libelle: "",
              rendu: (r) => (
                <div className="actions-cellule">
                  {r.statut === "en_attente" && <Bouton taille="petit" icone={Check} onClick={() => statut.mutate({ r, s: "confirme" })}>Confirmer</Bouton>}
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

/** Agenda des visites (semaine, par agent) et demandes reçues du site. */
export default function Reservations() {
  const [onglet, setOnglet] = useState("agenda");
  const [nouvelle, setNouvelle] = useState(false);
  const [cle, setCle] = useState(0);
  return (
    <>
      <EntetePage titre="Visites" soustitre="Agenda de la semaine, visites prises au téléphone et demandes reçues du site.">
        <Bouton variante="primaire" icone={Plus} onClick={() => setNouvelle(true)}>Nouvelle visite</Bouton>
      </EntetePage>
      <Onglets actif={onglet} onChange={setOnglet} onglets={[{ cle: "agenda", libelle: "Agenda" }, { cle: "demandes", libelle: "Demandes et historique" }]} />
      {onglet === "agenda" ? <Agenda key={cle} /> : <Demandes key={cle} />}
      <FormVisite ouvert={nouvelle} onFermer={() => (setNouvelle(false), setCle((k) => k + 1))} />
    </>
  );
}
