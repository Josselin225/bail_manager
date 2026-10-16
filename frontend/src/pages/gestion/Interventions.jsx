import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, Plus, Save, Wrench } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr, dateHeure, fcfa } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import {
  Badge, Bouton, Case, Combo, Encart, EntetePage, Fenetre, FenetreFormulaire, Fichier, Liste, Pagination, Panneau, Recherche,
  Saisie, SaisieMontant, Segments, Tableau, Vide, Zone, useToast,
} from "../../components/ui";
import Visionneuse from "../../components/Visionneuse";

export const CATEGORIES = [
  { valeur: "plomberie", libelle: "Plomberie" }, { valeur: "electricite", libelle: "Électricité" }, { valeur: "maconnerie", libelle: "Maçonnerie" },
  { valeur: "menuiserie", libelle: "Menuiserie, serrurerie" }, { valeur: "peinture", libelle: "Peinture" },
  { valeur: "climatisation", libelle: "Climatisation" }, { valeur: "autre", libelle: "Autre" },
];
const TONS = { nouvelle: "alerte", en_cours: "attention", terminee: "ok", annulee: "" };
const IMPUTATIONS = [
  { valeur: "bailleur", libelle: "Au bailleur — débitée de son compte courant" },
  { valeur: "locataire", libelle: "Au locataire — ajoutée à ses charges" },
  { valeur: "agence", libelle: "À l'agence — sortie de caisse" },
];

export function BadgeStatutIntervention({ d }) {
  return <Badge ton={TONS[d.statut]}>{d.statut_libelle}</Badge>;
}

const colonnes = [
  { cle: "reference", libelle: "Demande", rendu: (d) => <><div className="principal-cellule">{d.reference}</div><div className="sous-cellule">{dateFr(d.created_at)} · {d.origine === "locataire" ? "par le locataire" : "par l'agence"}</div></> },
  { cle: "titre", libelle: "Problème", rendu: (d) => <><div className="principal-cellule">{d.titre} {d.urgente && <Badge ton="alerte">Urgent</Badge>}</div><div className="sous-cellule">{d.categorie_libelle}</div></> },
  { cle: "maison_nom", libelle: "Bien", rendu: (d) => <><div>{d.maison_nom}</div><div className="sous-cellule">{d.locataire_nom || d.bailleur_nom}</div></> },
  { cle: "artisan", libelle: "Artisan", rendu: (d) => d.artisan || <span className="discret">—</span> },
  { cle: "statut", libelle: "Statut", rendu: (d) => <BadgeStatutIntervention d={d} /> },
  { cle: "cout", libelle: "Coût", type: "montant" },
];

function NouvelleIntervention({ ouvert, onFermer }) {
  const { data: maisons = { results: [] } } = useQuery({ queryKey: ["maisons-options"], queryFn: () => api.get("/maisons/", { taille: 500 }), enabled: ouvert });
  const vide = { maison: "", categorie: "plomberie", titre: "", description: "", urgente: false, photos: [] };
  const [f, setF] = useState(vide);
  useEffect(() => { if (ouvert) setF(vide); }, [ouvert]); // eslint-disable-line
  return (
    <FenetreFormulaire titre="Nouvelle demande d'intervention" ouvert={ouvert} onFermer={onFermer} taille="large" icone={Wrench}
      onValider={async () => {
        const { photos, ...d } = f;
        const r = await api.post("/interventions/", d);
        if (photos.length) {
          const fd = new FormData();
          photos.forEach((p) => fd.append("photos", p));
          await api.post(`/interventions/${r.id}/photos/`, fd);
        }
        return `Demande ${r.reference} enregistrée.`;
      }}>
      <div className="formulaire">
        <Combo libelle="Bien concerné" requis plein valeur={f.maison} onChange={(maison) => setF({ ...f, maison })} options={maisons.results.map((m) => ({ valeur: m.id, libelle: m.designation, sous: m.locataire_actuel ? `Loué à ${m.locataire_actuel.nom}` : "Libre" }))} aide="La demande est rattachée automatiquement au bail en cours." />
        <Liste libelle="Catégorie" valeur={f.categorie} onChange={(categorie) => setF({ ...f, categorie })} options={CATEGORIES} />
        <Saisie libelle="Problème" requis valeur={f.titre} onChange={(titre) => setF({ ...f, titre })} placeholder="Ex. : fuite sous l'évier" />
        <Zone libelle="Description" valeur={f.description} onChange={(description) => setF({ ...f, description })} rows={3} />
        <Fichier libelle="Photos" multiple valeur={f.photos} onChange={(photos) => setF({ ...f, photos })} plein />
        <Case libelle="Intervention urgente" aide="Fuite importante, panne électrique, porte qui ne ferme plus…" valeur={f.urgente} onChange={(urgente) => setF({ ...f, urgente })} />
      </div>
    </FenetreFormulaire>
  );
}

function FicheIntervention({ demande, onFermer }) {
  const toast = useToast();
  const client = useQueryClient();
  const [f, setF] = useState(null);
  const [cl, setCl] = useState({ cout: "", imputation: "bailleur" });
  const [envoi, setEnvoi] = useState(false);
  const [vue, setVue] = useState(null);
  useEffect(() => {
    if (demande) {
      setF({ statut: demande.statut, artisan: demande.artisan, artisan_telephone: demande.artisan_telephone, date_intervention: demande.date_intervention || "", commentaire_agence: demande.commentaire_agence });
      setCl({ cout: "", imputation: "bailleur" });
    }
  }, [demande]);
  if (!demande || !f) return null;
  const close = demande.statut === "terminee" || demande.statut === "annulee";
  const agir = async (fn, msg) => {
    setEnvoi(true);
    try {
      await fn();
      toast.succes(msg);
      client.invalidateQueries({ queryKey: ["/interventions/"] });
      client.invalidateQueries({ queryKey: ["compteurs"] });
      onFermer();
    } catch (e) {
      toast.erreur(e.message);
    } finally {
      setEnvoi(false);
    }
  };
  return (
    <Fenetre titre={`${demande.reference} — ${demande.titre}`} ouvert onFermer={onFermer} taille="large">
      <div className="pile">
        <div className="ligne-flex">
          <BadgeStatutIntervention d={demande} />
          {demande.urgente && <Badge ton="alerte">Urgent</Badge>}
          <Badge>{demande.categorie_libelle}</Badge>
          <span className="discret petit-texte">Signalée le {dateHeure(demande.created_at)} {demande.origine === "locataire" ? "par le locataire" : "par l'agence"}</span>
        </div>
        <div className="rappel-fenetre">
          <div className="gras">{demande.maison_nom} <span className="discret">— propriétaire : {demande.bailleur_nom}</span></div>
          {demande.locataire_nom && <div className="discret">Locataire : {demande.locataire_nom}{demande.locataire_telephone && ` — ${demande.locataire_telephone}`}{demande.contrat && <> — <Link to={`/contrats/${demande.contrat}`}>voir le bail</Link></>}</div>}
          {demande.description && <p style={{ margin: "8px 0 0", whiteSpace: "pre-wrap" }}>{demande.description}</p>}
        </div>
        {demande.photos.length > 0 && (
          <div className="photos-intervention">{demande.photos.map((p, k) => <img key={p.id} src={p.url} alt="" onClick={() => setVue(k)} />)}</div>
        )}
        {close ? (
          <Encart ton={demande.statut === "terminee" ? "ok" : ""} titre={demande.statut === "terminee" ? "Intervention terminée" : "Demande annulée"}>
            {demande.statut === "terminee" && <>Coût : <b>{fcfa(demande.cout)}</b>{Number(demande.cout) > 0 && ` — ${demande.imputation_libelle.toLowerCase()}`}. </>}
            {demande.artisan && `Artisan : ${demande.artisan}. `}{demande.date_intervention && `Intervention le ${dateFr(demande.date_intervention)}.`}
          </Encart>
        ) : (
          <>
            <Panneau titre="Suivi">
              <div className="formulaire">
                <Liste libelle="Statut" valeur={f.statut} onChange={(statut) => setF({ ...f, statut })} options={[{ valeur: "nouvelle", libelle: "Nouvelle" }, { valeur: "en_cours", libelle: "En cours" }, { valeur: "annulee", libelle: "Annulée" }]} />
                <Saisie libelle="Date d'intervention" type="date" valeur={f.date_intervention} onChange={(date_intervention) => setF({ ...f, date_intervention })} />
                <Saisie libelle="Artisan" valeur={f.artisan} onChange={(artisan) => setF({ ...f, artisan })} placeholder="Nom de l'artisan ou de l'entreprise" />
                <Saisie libelle="Téléphone de l'artisan" type="tel" placeholder="07 00 00 00 00" valeur={f.artisan_telephone} onChange={(artisan_telephone) => setF({ ...f, artisan_telephone })} />
                <Zone libelle="Commentaire de l'agence" valeur={f.commentaire_agence} onChange={(commentaire_agence) => setF({ ...f, commentaire_agence })} rows={2} />
              </div>
              <div className="ligne-flex" style={{ justifyContent: "flex-end", marginTop: 12 }}>
                <Bouton icone={Save} chargement={envoi} onClick={() => agir(() => api.patch(`/interventions/${demande.id}/`, { ...f, date_intervention: f.date_intervention || null }), "Demande mise à jour.")}>Enregistrer le suivi</Bouton>
              </div>
            </Panneau>
            <Panneau titre="Clôturer l'intervention">
              <div className="formulaire">
                <SaisieMontant libelle="Coût total" valeur={cl.cout} onChange={(cout) => setCl({ ...cl, cout })} aide="0 si l'intervention est sans frais." />
                <Liste libelle="Coût imputé" valeur={cl.imputation} onChange={(imputation) => setCl({ ...cl, imputation })} options={IMPUTATIONS} />
              </div>
              <div className="ligne-flex" style={{ justifyContent: "flex-end", marginTop: 12 }}>
                <Bouton variante="succes" icone={CheckCircle2} chargement={envoi} onClick={() => agir(() => api.post(`/interventions/${demande.id}/cloturer/`, cl), "Intervention clôturée.")}>Clôturer</Bouton>
              </div>
            </Panneau>
          </>
        )}
      </div>
      {vue !== null && <Visionneuse photos={demande.photos.map((p) => p.url)} depart={vue} titre={demande.titre} onFermer={() => setVue(null)} />}
    </Fenetre>
  );
}

export default function Interventions() {
  const liste = useListe("/interventions/", { search: "", statut: "" });
  const [nouvelle, setNouvelle] = useState(false);
  const [choisie, setChoisie] = useState(null);
  return (
    <>
      <EntetePage titre="Interventions" soustitre="Pannes et travaux signalés par les locataires ou par l'agence, jusqu'à leur clôture.">
        <Bouton variante="primaire" icone={Plus} onClick={() => setNouvelle(true)}>Nouvelle demande</Bouton>
      </EntetePage>
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Problème, bien, locataire, artisan…" />
        <Segments valeur={liste.filtres.statut} onChange={(statut) => liste.changer({ statut })} options={[{ valeur: "", libelle: "Toutes" }, { valeur: "nouvelle", libelle: "Nouvelles" }, { valeur: "en_cours", libelle: "En cours" }, { valeur: "terminee", libelle: "Terminées" }]} />
      </div>
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} surLigne={setChoisie} vide={<Vide icone={Wrench} titre="Aucune demande" texte="Les pannes signalées par les locataires depuis leur espace apparaîtront ici." />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
      <NouvelleIntervention ouvert={nouvelle} onFermer={() => (setNouvelle(false), liste.refetch())} />
      <FicheIntervention demande={choisie} onFermer={() => (setChoisie(null), liste.refetch())} />
    </>
  );
}
