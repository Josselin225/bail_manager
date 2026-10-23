import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Link, useNavigate } from "react-router-dom";
import { FileText, HandCoins, Paperclip, Trash2, Upload } from "lucide-react";
import { api } from "../lib/api";
import { MODES, MOIS, dateFr, fcfa, isoDate } from "../lib/format";
import { useAction } from "../lib/hooks";
import { useSession } from "../lib/session";
import {
  Bouton, Case, Champ, Combo, Encart, Fichier, FenetreFormulaire, Liste, Montant, Saisie, SaisieMontant, Segments, Tableau, Vide, Zone,
  useConfirmer, useToast,
} from "./ui";

/**
 * Fiches existantes qui ressemblent à la saisie (nom, téléphone, pièce d'identité, e-mail), cherchées pendant la frappe.
 * `type` : "locataires" ou "bailleurs" ; `exclure` : la fiche en cours de modification.
 */
function AlerteDoublons({ type, valeurs, exclure }) {
  const [differe, setDiffere] = useState(valeurs);
  useEffect(() => {
    const t = setTimeout(() => setDiffere(valeurs), 450);
    return () => clearTimeout(t);
  }, [JSON.stringify(valeurs)]); // eslint-disable-line
  const utile = Object.values(differe).some((v) => String(v || "").trim().length >= 3);
  const { data = [] } = useQuery({
    queryKey: ["doublons", type, differe, exclure],
    queryFn: () => api.get(`/${type}/doublons/`, { ...differe, exclure: exclure || "" }),
    enabled: utile,
    staleTime: 30000,
  });
  if (!utile || !data.length) return null;
  const forts = data.some((d) => d.fort);
  return (
    <div className="plein">
      <Encart ton={forts ? "attention" : "info"} titre={forts ? "Cette personne est peut-être déjà enregistrée" : "Fiches au nom proche"}>
        <ul className="liste-doublons">
          {data.map((d) => (
            <li key={d.id}>
              <span><b>{d.nom}</b>{d.telephone && <span className="discret"> — {d.telephone}</span>}</span>
              <span className="raisons-doublon">{d.raisons.map((r) => <span key={r} className={`badge ${r.startsWith("nom") ? "" : "attention"}`}>{r}</span>)}</span>
              <Link to={d.lien} className="petit-texte">Ouvrir la fiche</Link>
            </li>
          ))}
        </ul>
        {forts && <div className="petit-texte">Vérifiez avant d'enregistrer : un doublon éparpille les paiements et l'historique de la personne.</div>}
      </Encart>
    </div>
  );
}

/** Enregistre ; si le serveur signale un doublon probable, demande confirmation puis réessaie. */
async function enregistrerAvecControle(envoyer, confirmer) {
  try {
    return await envoyer({});
  } catch (e) {
    if (e.donnees?.code !== "doublon_possible") throw e;
    const ok = await confirmer({ titre: "Doublon possible", message: `${e.message.replace(/^Doublon possible : /, "")} Enregistrer quand même une nouvelle fiche ?`, libelle: "Enregistrer quand même" });
    if (!ok) throw new Error("Enregistrement annulé : vérifiez la fiche existante.");
    return envoyer({ confirmer_doublon: true });
  }
}

/** Ne garde que les champs modifiés + les fichiers nouvellement choisis. */
function nettoyer(f, champsFichiers = []) {
  const out = {};
  Object.entries(f).forEach(([k, v]) => {
    if (champsFichiers.includes(k)) {
      if (v instanceof File) out[k] = v;
    } else if (v !== undefined) out[k] = v === "" && k.startsWith("date") ? null : v;
  });
  return out;
}

const useFormulaire = (initial, ouvert) => {
  const [f, setF] = useState(initial);
  useEffect(() => {
    if (ouvert) setF(initial);
  }, [ouvert]); // eslint-disable-line
  const maj = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  return [f, maj, setF];
};

/* ─── Bailleur ───────────────────────────────────────────────────── */
export function FormBailleur({ ouvert, onFermer, bailleur, onEnregistre }) {
  const vide = { nom: "", sexe: "", numero_cni: "", telephone1: "", telephone2: "", email: "", adresse: "", photo: null };
  const [f, maj] = useFormulaire(bailleur ? { ...vide, ...bailleur, photo: null } : vide, ouvert);
  const confirmer = useConfirmer();
  return (
    <FenetreFormulaire
      titre={bailleur ? `Modifier ${bailleur.nom}` : "Nouveau bailleur"}
      ouvert={ouvert}
      onFermer={onFermer}
      taille="large"
      onValider={async () => {
        const donnees = nettoyer(
          { nom: f.nom, sexe: f.sexe, numero_cni: f.numero_cni, telephone1: f.telephone1, telephone2: f.telephone2, email: f.email, adresse: f.adresse, photo: f.photo },
          ["photo"],
        );
        const r = await enregistrerAvecControle((plus) => (bailleur ? api.patch(`/bailleurs/${bailleur.id}/`, { ...donnees, ...plus }) : api.post("/bailleurs/", { ...donnees, ...plus })), confirmer);
        onEnregistre?.(r, !bailleur);
        return bailleur ? "Bailleur mis à jour." : `Bailleur ${r.code_bailleur} enregistré.`;
      }}
    >
      <div className="formulaire">
        {ouvert && <AlerteDoublons type="bailleurs" exclure={bailleur?.id} valeurs={{ nom: f.nom, telephone1: f.telephone1, telephone2: f.telephone2, email: f.email, numero_cni: f.numero_cni }} />}
        <div className="titre-section">Identité</div>
        <Saisie libelle="Nom complet" valeur={f.nom} onChange={maj("nom")} requis />
        <Liste libelle="Sexe" valeur={f.sexe} onChange={maj("sexe")} vide="—" options={[{ valeur: "M", libelle: "Masculin" }, { valeur: "F", libelle: "Féminin" }]} />
        <Saisie libelle="N° pièce d'identité" valeur={f.numero_cni} onChange={maj("numero_cni")} aide="CNI, passeport ou carte consulaire." />
        <div className="titre-section">Coordonnées</div>
        <Saisie libelle="Téléphone principal" type="tel" placeholder="07 00 00 00 00" valeur={f.telephone1} onChange={maj("telephone1")} requis />
        <Saisie libelle="Téléphone secondaire" type="tel" placeholder="07 00 00 00 00" valeur={f.telephone2} onChange={maj("telephone2")} />
        <Saisie libelle="E-mail" type="email" valeur={f.email} onChange={maj("email")} aide="Pour recevoir ses relevés de compte." />
        <Zone libelle="Adresse" valeur={f.adresse} onChange={maj("adresse")} rows={2} />
        <div className="titre-section">Pièces</div>
        <Fichier libelle="Photo" valeur={f.photo} actuel={bailleur?.photo} onChange={maj("photo")} />
      </div>
      {!bailleur && <Encart>Après l'enregistrement, saisissez le mandat de gestion : il est obligatoire pour ajouter des biens à ce bailleur.</Encart>}
    </FenetreFormulaire>
  );
}

/* ─── Mandat de gestion ──────────────────────────────────────────── */
export function FormMandat({ ouvert, onFermer, bailleur, mandat, onEnregistre }) {
  const { data: params } = useQuery({ queryKey: ["parametres"], queryFn: () => api.get("/parametres/"), enabled: ouvert });
  const auj = isoDate();
  const initial = mandat
    ? { ...mandat, date_fin: mandat.date_fin || "", document: null }
    : { duree: "indeterminee", tacite_reconduction: false, date_signature: auj, date_debut: auj, date_fin: "", taux_commission: "", document: null };
  const [f, maj, setF] = useFormulaire(initial, ouvert);
  useEffect(() => {
    if (ouvert && !mandat && params && !f.taux_commission) setF((x) => ({ ...x, taux_commission: params.taux_commission }));
  }, [params, ouvert]); // eslint-disable-line
  return (
    <FenetreFormulaire
      titre={mandat ? `Modifier le mandat ${mandat.reference || ""}` : bailleur?.mandat ? `Renouveler le mandat — ${bailleur?.nom}` : `Nouveau mandat de gestion — ${bailleur?.nom}`}
      ouvert={ouvert}
      onFermer={onFermer}
      onValider={async () => {
        const d = nettoyer(
          { bailleur: bailleur?.id ?? mandat?.bailleur, duree: f.duree, tacite_reconduction: f.duree === "determinee" && f.tacite_reconduction, date_signature: f.date_signature, date_debut: f.date_debut, date_fin: f.duree === "determinee" ? f.date_fin || null : null, taux_commission: f.taux_commission, document: f.document },
          ["document"],
        );
        const r = mandat ? await api.patch(`/mandats/${mandat.id}/`, d) : await api.post("/mandats/", d);
        onEnregistre?.(r);
        return "Mandat de gestion enregistré.";
      }}
    >
      {!mandat && bailleur?.mandat && <Encart ton="attention">Le mandat en cours sera marqué résilié et remplacé par celui-ci (renouvellement). Les locataires des biens de ce bailleur recevront un message de confirmation.</Encart>}
      <div className="formulaire">
        <Saisie libelle="Date de signature" type="date" valeur={f.date_signature} onChange={maj("date_signature")} requis />
        <Saisie libelle="Taux de commission" type="number" step="0.5" min="0" max="100" suffixe="%" valeur={f.taux_commission} onChange={maj("taux_commission")} requis aide="Prélevé sur chaque loyer encaissé." />
        <Champ libelle="Durée du mandat" requis plein>
          <Segments valeur={f.duree} onChange={maj("duree")} options={[{ valeur: "indeterminee", libelle: "Durée indéterminée" }, { valeur: "determinee", libelle: "Durée déterminée" }]} />
        </Champ>
        <Saisie libelle="Date de début" type="date" valeur={f.date_debut} onChange={maj("date_debut")} requis />
        {f.duree === "determinee" ? (
          <>
            <Saisie libelle="Date de fin" type="date" min={f.date_debut} valeur={f.date_fin} onChange={maj("date_fin")} requis aide="Les locataires en seront informés dans leur bail." />
            <Case libelle="Renouvelable par tacite reconduction" aide="Sauf résiliation, le mandat se renouvelle automatiquement pour la même durée." valeur={f.tacite_reconduction} onChange={maj("tacite_reconduction")} />
          </>
        ) : (
          <div className="champ"><span className="aide" style={{ marginTop: 28 }}>Le mandat court jusqu'à sa résiliation (préavis prévu aux clauses du mandat).</span></div>
        )}
        <Fichier libelle="Mandat signé" accept="image/*,application/pdf" valeur={f.document} actuel={mandat?.document_signe} onChange={maj("document")} plein aide="Scan ou photo du mandat signé (JPG, PNG ou PDF)." />
      </div>
    </FenetreFormulaire>
  );
}

/* ─── Maison ─────────────────────────────────────────────────────── */
export function FormMaison({ ouvert, onFermer, maison, bailleurParDefaut, onEnregistre }) {
  const { data: bailleurs = [] } = useQuery({ queryKey: ["bailleurs-options"], queryFn: () => api.get("/bailleurs/options/"), enabled: ouvert });
  const { data: types = [] } = useQuery({ queryKey: ["types-maisons"], queryFn: () => api.get("/maisons/types/"), enabled: ouvert });
  const vide = { bailleur: bailleurParDefaut || "", designation: "", type_maison: "", adresse: "", description: "", loyer: "", condition: 4, visible_site: true, photos: [] };
  const [f, maj] = useFormulaire(maison ? { ...vide, ...maison, photos: [] } : vide, ouvert);
  const b = bailleurs.find((x) => String(x.id) === String(f.bailleur));
  return (
    <FenetreFormulaire
      titre={maison ? `Modifier ${maison.designation}` : "Nouvelle maison"}
      ouvert={ouvert}
      onFermer={onFermer}
      taille="large"
      onValider={async () => {
        const d = { bailleur: f.bailleur, designation: f.designation, type_maison: f.type_maison, adresse: f.adresse, description: f.description, loyer: f.loyer, condition: f.condition, visible_site: f.visible_site };
        let r = maison ? await api.patch(`/maisons/${maison.id}/`, d) : await api.post("/maisons/", d);
        if (!maison && f.photos.length) {
          const fd = new FormData();
          f.photos.forEach((p) => fd.append("photos", p));
          r = await api.post(`/maisons/${r.id}/photos/`, fd);
        }
        onEnregistre?.(r);
        return maison ? "Maison mise à jour." : "Maison enregistrée.";
      }}
    >
      <div className="formulaire">
        <div className="titre-section">Propriétaire</div>
        <Combo
          libelle="Bailleur"
          requis
          valeur={f.bailleur}
          onChange={maj("bailleur")}
          options={bailleurs.map((x) => ({ valeur: x.id, libelle: x.nom, sous: x.a_mandat ? x.code_bailleur : `${x.code_bailleur} — aucun mandat de gestion en vigueur`, desactive: !x.a_mandat }))}
          aide="Seuls les bailleurs ayant un mandat de gestion en vigueur peuvent recevoir un bien."
          plein
        />
        {b && !b.a_mandat && <div className="plein"><Encart ton="alerte">{b.nom} n'a pas de mandat de gestion en vigueur : enregistrez d'abord le mandat depuis sa fiche.</Encart></div>}
        <div className="titre-section">Le bien</div>
        <Saisie libelle="Désignation" valeur={f.designation} onChange={maj("designation")} requis placeholder="Ex. : Villa Cocody A1" />
        <Saisie libelle="Type" list="types-maisons" valeur={f.type_maison} onChange={maj("type_maison")} placeholder="Ex. : Studio, 3 pièces, Villa…" />
        <datalist id="types-maisons">{types.map((t) => <option key={t} value={t} />)}</datalist>
        <Zone libelle="Adresse" valeur={f.adresse} onChange={maj("adresse")} rows={2} aide="Commune, quartier, repères." />
        <Zone libelle="Description" valeur={f.description} onChange={maj("description")} rows={3} aide="Affichée sur le site vitrine." />
        <div className="titre-section">Conditions de location</div>
        {maison?.locataire_actuel ? (
          <SaisieMontant libelle="Loyer mensuel" valeur={f.loyer} readOnly tabIndex={-1} aide="Bien loué : le loyer se modifie par une révision sur le contrat en cours, à la demande du bailleur." />
        ) : (
          <SaisieMontant libelle="Loyer mensuel" valeur={f.loyer} onChange={maj("loyer")} requis aide="Repris tel quel dans les contrats de bail de ce bien." />
        )}
        <Saisie libelle="Condition d'entrée" type="number" min="0" max="12" suffixe="mois" valeur={f.condition} onChange={maj("condition")} aide="Nombre total de loyers exigés à l'entrée (avance + caution)." />
        <div className="titre-section">Site vitrine</div>
        {!maison && <Fichier libelle="Photos" multiple valeur={f.photos} onChange={maj("photos")} plein aide="Plusieurs à la fois ; la première sera la photo principale. Vous pourrez les gérer ensuite depuis la fiche." />}
        <Case libelle="Publier sur le site (« Nos maisons »)" aide="Elle y apparaît avec l'étiquette Disponible ou Occupée." valeur={f.visible_site} onChange={maj("visible_site")} />
      </div>
    </FenetreFormulaire>
  );
}

/* ─── Locataire ──────────────────────────────────────────────────── */
export function FormLocataire({ ouvert, onFermer, locataire, onEnregistre }) {
  const vide = { nom: "", telephone1: "", telephone2: "", email: "", piece_identite: "", profession: "", photo_locataire: null, photo_cni_recto: null, photo_cni_verso: null };
  const [f, maj] = useFormulaire(locataire ? { ...vide, ...locataire, photo_locataire: null, photo_cni_recto: null, photo_cni_verso: null } : vide, ouvert);
  const confirmer = useConfirmer();
  return (
    <FenetreFormulaire
      titre={locataire ? `Modifier ${locataire.nom}` : "Nouveau locataire"}
      ouvert={ouvert}
      onFermer={onFermer}
      taille="large"
      onValider={async () => {
        const d = nettoyer(
          { nom: f.nom, telephone1: f.telephone1, telephone2: f.telephone2, email: f.email, piece_identite: f.piece_identite, profession: f.profession, photo_locataire: f.photo_locataire, photo_cni_recto: f.photo_cni_recto, photo_cni_verso: f.photo_cni_verso },
          ["photo_locataire", "photo_cni_recto", "photo_cni_verso"],
        );
        const r = await enregistrerAvecControle((plus) => (locataire ? api.patch(`/locataires/${locataire.id}/`, { ...d, ...plus }) : api.post("/locataires/", { ...d, ...plus })), confirmer);
        onEnregistre?.(r);
        return locataire ? "Locataire mis à jour." : "Locataire enregistré.";
      }}
    >
      <div className="formulaire">
        {ouvert && <AlerteDoublons type="locataires" exclure={locataire?.id} valeurs={{ nom: f.nom, telephone1: f.telephone1, telephone2: f.telephone2, email: f.email, piece_identite: f.piece_identite }} />}
        <div className="titre-section">Identité</div>
        <Saisie libelle="Nom complet" valeur={f.nom} onChange={maj("nom")} requis />
        <Saisie libelle="Profession" valeur={f.profession} onChange={maj("profession")} />
        <Saisie libelle="N° pièce d'identité" valeur={f.piece_identite} onChange={maj("piece_identite")} aide="CNI, passeport ou carte consulaire." />
        <div className="titre-section">Coordonnées</div>
        <Saisie libelle="Téléphone principal" type="tel" placeholder="07 00 00 00 00" valeur={f.telephone1} onChange={maj("telephone1")} requis aide="Sert d'identifiant pour l'espace locataire." />
        <Saisie libelle="Téléphone secondaire" type="tel" placeholder="07 00 00 00 00" valeur={f.telephone2} onChange={maj("telephone2")} />
        <Saisie libelle="E-mail" type="email" valeur={f.email} onChange={maj("email")} aide="Pour recevoir quittances et rappels." />
        <div className="titre-section">Pièces</div>
        <div className="plein trois-colonnes">
          <Fichier libelle="Photo" valeur={f.photo_locataire} actuel={locataire?.photo_locataire} onChange={maj("photo_locataire")} />
          <Fichier libelle="Pièce d'identité (recto)" valeur={f.photo_cni_recto} actuel={locataire?.photo_cni_recto} onChange={maj("photo_cni_recto")} />
          <Fichier libelle="Pièce d'identité (verso)" valeur={f.photo_cni_verso} actuel={locataire?.photo_cni_verso} onChange={maj("photo_cni_verso")} />
        </div>
      </div>
    </FenetreFormulaire>
  );
}

/* ─── Encaissement d'un loyer ────────────────────────────────────── */
export function FormEncaissement({ ouvert, onFermer, contrat, onEnregistre }) {
  const nav = useNavigate();
  const auj = new Date();
  const [f, maj, setF] = useFormulaire({ mois: auj.getMonth() + 1, annee: auj.getFullYear(), montant: "", mode: "especes", observation: "" }, ouvert);
  const [situation, setSituation] = useState(null);
  const [initialise, setInitialise] = useState(false);
  const toast = useToast();

  useEffect(() => {
    if (!ouvert || !contrat) return;
    setInitialise(false);
    api.get(`/contrats/${contrat.id}/situation/`).then((s) => {
      const p = s.prochaine_periode_due;
      if (p) setF((x) => ({ ...x, mois: p.mois, annee: p.annee, montant: p.reste }));
      setInitialise(true);
    });
  }, [ouvert, contrat?.id]); // eslint-disable-line

  useEffect(() => {
    if (!ouvert || !contrat || !initialise) return;
    api.get(`/contrats/${contrat.id}/situation/`, { mois: f.mois, annee: f.annee }).then(setSituation).catch(() => setSituation(null));
  }, [ouvert, contrat?.id, f.mois, f.annee, initialise]); // eslint-disable-line

  const reste = situation ? Number(situation.reste) : 0;
  const bloque = situation && (situation.dette || reste === 0 || situation.anterieure_au_bail);
  const annees = [auj.getFullYear() - 2, auj.getFullYear() - 1, auj.getFullYear(), auj.getFullYear() + 1];

  return (
    <FenetreFormulaire
      titre="Encaisser un loyer"
      ouvert={ouvert}
      onFermer={onFermer}
      taille="large"
      icone={HandCoins}
      libelleValider="Encaisser et éditer la quittance"
      variante="succes"
      desactive={bloque}
      onValider={async () => {
        const r = await api.post("/encaissements/", { contrat: contrat.id, montant: f.montant, periode_mois: f.mois, periode_annee: f.annee, mode_paiement: f.mode, observation: f.observation });
        onEnregistre?.(r);
        toast.succes(`Reçu ${r.reference_recu} enregistré.`);
        nav(`/impression/quittance/${r.id}`);
      }}
    >
      <div className="rappel-fenetre">
        <div className="gras">{contrat?.locataire_nom}</div>
        <div className="discret">{contrat?.maison_nom} — loyer {fcfa(contrat?.loyer_mensuel)}</div>
      </div>
      <div className="formulaire">
        <Liste libelle="Mois concerné" valeur={f.mois} onChange={(v) => maj("mois")(Number(v))} options={MOIS.map((m, i) => ({ valeur: i + 1, libelle: m }))} />
        <Liste libelle="Année" valeur={f.annee} onChange={(v) => maj("annee")(Number(v))} options={annees.map((a) => ({ valeur: a, libelle: String(a) }))} />
      </div>
      {situation && (
        <div className="chiffres-fenetre">
          <div><span>Loyer du mois</span><Montant v={situation.loyer} /></div>
          <div><span>Déjà payé</span><Montant v={situation.deja_paye} /></div>
          <div><span>Reste à payer</span><b><Montant v={situation.reste} /></b></div>
        </div>
      )}
      {situation?.dette && (
        <Encart ton="alerte" titre={`Impayé sur ${situation.dette.libelle}`}>
          Il reste {fcfa(situation.dette.reste)} à régler sur ce mois. Soldez-le avant d'encaisser {situation.periode}.{" "}
          <Bouton taille="petit" style={{ marginTop: 6 }} onClick={() => setF((x) => ({ ...x, mois: situation.dette.mois, annee: situation.dette.annee, montant: situation.dette.reste }))}>
            Passer à {situation.dette.libelle}
          </Bouton>
        </Encart>
      )}
      {situation?.mandat_echu && (
        <Encart ton="attention" titre="Mandat de gestion échu">
          Le propriétaire n'a plus de mandat de gestion en vigueur avec l'agence. L'encaissement reste possible, mais régularisez le mandat (renouvellement) ou indiquez au locataire à qui verser ses loyers.
        </Encart>
      )}
      {situation?.anterieure_au_bail && <Encart ton="attention">Cette période est antérieure au début du bail.</Encart>}
      {situation && !situation.dette && reste === 0 && !situation.anterieure_au_bail && <Encart ton="ok">{situation.periode} est déjà soldé.</Encart>}
      <div className="formulaire">
        <SaisieMontant libelle="Montant reçu" valeur={f.montant} onChange={maj("montant")} requis max={reste || undefined} aide={reste ? `Maximum ${fcfa(reste)} — paiement partiel possible.` : undefined} />
        <Liste libelle="Mode de paiement" valeur={f.mode} onChange={maj("mode")} options={MODES} />
        <Saisie libelle="Commentaire" valeur={f.observation} onChange={maj("observation")} plein placeholder="Ex. : réf. transaction Wave / Orange Money" />
      </div>
    </FenetreFormulaire>
  );
}

/* ─── Documents scannés ──────────────────────────────────────────── */
export function Documents({ type, id }) {
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const [ouvert, setOuvert] = useState(false);
  const [fichier, setFichier] = useState(null);
  const { data, isLoading } = useQuery({ queryKey: ["documents", type, id], queryFn: () => api.get("/documents/", { entity_type: type, entity_id: id, taille: 100 }) });
  const supprimer = useAction((d) => api.delete(`/documents/${d.id}/`), { succes: "Document supprimé.", invalider: ["documents"] });
  const docs = data?.results || [];
  return (
    <>
      <div className="ligne-flex" style={{ marginBottom: 12 }}>
        <span className="discret">{docs.length} document(s) — contrats signés, pièces, états des lieux, reçus…</span>
        <span className="espace" />
        <Bouton icone={Upload} onClick={() => (setFichier(null), setOuvert(true))}>Ajouter un document</Bouton>
      </div>
      <Tableau
        chargement={isLoading}
        lignes={docs}
        vide={<Vide icone={Paperclip} titre="Aucun document" texte="Ajoutez les pièces scannées pour retrouver le dossier complet ici." />}
        colonnes={[
          { libelle: "Document", rendu: (d) => <a href={d.fichier} className="ligne-flex" style={{ gap: 8 }}><FileText size={16} />{d.nom_original}</a> },
          { libelle: "Taille", rendu: (d) => `${Math.max(1, Math.round(d.taille / 1024))} Ko` },
          { libelle: "Ajouté le", rendu: (d) => dateFr(d.created_at) },
          { libelle: "Par", cle: "uploaded_by_nom" },
          { libelle: "", rendu: (d) => estAdmin && <Bouton variante="fantome" taille="petit" icone={Trash2} aria-label="Supprimer" onClick={async () => (await confirmer({ message: `Supprimer « ${d.nom_original} » ?`, danger: true, libelle: "Supprimer" })) && supprimer.mutate(d)} /> },
        ]}
      />
      <FenetreFormulaire
        titre="Ajouter un document"
        ouvert={ouvert}
        onFermer={() => setOuvert(false)}
        libelleValider="Ajouter le document"
        icone={Upload}
        taille="etroite"
        onValider={async () => {
          await api.post("/documents/", { entity_type: type, entity_id: id, fichier });
          supprimer.reset();
          return "Document ajouté.";
        }}
      >
        <div className="formulaire"><Fichier libelle="Fichier" requis plein accept="image/*,application/pdf" valeur={fichier} onChange={setFichier} aide="JPG, PNG, WebP ou PDF — 8 Mo maximum." /></div>
      </FenetreFormulaire>
      <InvaliderDocs ouvert={ouvert} type={type} id={id} />
    </>
  );
}

import { useQueryClient } from "@tanstack/react-query";
function InvaliderDocs({ ouvert }) {
  const client = useQueryClient();
  useEffect(() => {
    if (!ouvert) client.invalidateQueries({ queryKey: ["documents"] });
  }, [ouvert, client]);
  return null;
}
