import { useEffect, useRef, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Camera, ClipboardCheck, Plus, Printer, Save, Trash2, X } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr } from "../../lib/format";
import { Bouton, Chargement, Encart, EntetePage, LienBouton, Panneau, Saisie, Zone, useToast } from "../../components/ui";
import Visionneuse from "../../components/Visionneuse";

const ORDRE_ETATS = ["neuf", "bon", "usage", "mauvais"];

/** Photos d'une pièce : ajout (plusieurs à la fois), vignettes, suppression, agrandissement. */
function PhotosPiece({ piece, photos, base, actif, onChange }) {
  const toast = useToast();
  const champ = useRef(null);
  const [envoi, setEnvoi] = useState(false);
  const [vue, setVue] = useState(null);
  const ajouter = async (fichiers) => {
    if (!fichiers.length) return;
    const fd = new FormData();
    fd.append("piece", piece);
    [...fichiers].forEach((f) => fd.append("photos", f));
    setEnvoi(true);
    try {
      onChange((await api.post(`${base}photos/`, fd)).photos);
    } catch (e) {
      toast.erreur(e.message);
    } finally {
      setEnvoi(false);
    }
  };
  const supprimer = async (ph) => {
    try { onChange((await api.post(`${base}photos/${ph.id}/supprimer/`)).photos); } catch (e) { toast.erreur(e.message); }
  };
  return (
    <div className="edl-photos">
      {photos.map((ph, k) => (
        <figure key={ph.id}>
          <img src={ph.url} alt={`${piece} — photo ${k + 1}`} onClick={() => setVue(k)} />
          <button type="button" onClick={() => supprimer(ph)} aria-label="Supprimer la photo"><X size={13} /></button>
        </figure>
      ))}
      <button type="button" className="edl-photo-ajout" disabled={!actif || envoi} onClick={() => champ.current?.click()} title={actif ? "Ajouter des photos" : "Enregistrez d'abord l'état des lieux"}>
        <Camera size={18} /> <span>{envoi ? "Envoi…" : actif ? "Photos" : "Photos après enregistrement"}</span>
      </button>
      <input ref={champ} type="file" accept="image/*" capture="environment" multiple hidden onChange={(e) => (ajouter(e.target.files), (e.target.value = ""))} />
      {vue !== null && <Visionneuse photos={photos.map((x) => x.url)} depart={vue} titre={piece} onFermer={() => setVue(null)} />}
    </div>
  );
}

/** Saisie de l'état des lieux d'un bail (entrée ou sortie), pièce par pièce. */
export default function EtatDesLieux() {
  const { id, type = "entree" } = useParams();
  const nav = useNavigate();
  const toast = useToast();
  const client = useQueryClient();
  const url = `/contrats/${id}/etat-des-lieux/${type}/`;
  const { data } = useQuery({ queryKey: ["etat-des-lieux", id, type], queryFn: () => api.get(url) });
  const [f, setF] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const [photos, setPhotos] = useState([]);
  useEffect(() => { if (data) setPhotos(data.photos || []); }, [data]);
  useEffect(() => {
    if (data) setF({ date_etat: data.date_etat || "", index_eau: data.index_eau, index_electricite: data.index_electricite, nombre_cles: data.nombre_cles, observations: data.observations, pieces: data.pieces });
  }, [data]);
  if (!data || !f) return <Chargement />;

  const majPiece = (i, piece) => setF((x) => ({ ...x, pieces: x.pieces.map((p, k) => (k === i ? piece : p)) }));
  const majElement = (i, j, champ, valeur) => {
    const p = f.pieces[i];
    majPiece(i, { ...p, elements: p.elements.map((e, k) => (k === j ? { ...e, [champ]: valeur } : e)) });
  };
  const remplis = f.pieces.reduce((s, p) => s + p.elements.filter((e) => e.etat).length, 0);
  const total = f.pieces.reduce((s, p) => s + p.elements.length, 0);

  const enregistrer = async (e) => {
    e.preventDefault();
    setEnvoi(true);
    try {
      await api.post(url, f);
      client.invalidateQueries({ queryKey: ["etat-des-lieux", id, type] });
      client.invalidateQueries({ queryKey: ["contrat", id] });
      toast.succes(`${data.libelle} enregistré.`);
      nav(`/contrats/${id}`);
    } catch (err) {
      toast.erreur(err.message);
    } finally {
      setEnvoi(false);
    }
  };

  return (
    <form onSubmit={enregistrer} className="pile">
      <EntetePage
        titre={data.libelle}
        soustitre={`${data.contrat.reference} — ${data.locataire.nom} — ${data.bien.designation}`}
        fil={[{ libelle: "Contrats", lien: "/contrats" }, { libelle: data.contrat.reference, lien: `/contrats/${id}` }]}
      >
        <LienBouton icone={Printer} to={`/impression/etat-des-lieux/${id}/${type}`}>{data.etabli ? "Imprimer" : "Imprimer la grille vierge"}</LienBouton>
        <Bouton type="submit" variante="primaire" icone={Save} chargement={envoi}>Enregistrer</Bouton>
      </EntetePage>

      <Encart titre="Établi contradictoirement">
        À remplir avec le locataire lors de la remise des clés, puis à imprimer et faire signer par les deux parties. Il est annexé au bail et servira de référence à l'état des lieux de sortie (retenues sur caution).
        {data.etabli && <> Dernière mise à jour par {data.etabli_par || "—"}.</>}
      </Encart>
      {type === "sortie" && !data.entree && <Encart ton="attention">Aucun état des lieux d'entrée n'a été établi pour ce bail : la comparaison des dégradations ne sera pas possible.</Encart>}
      {type === "sortie" && data.degradations?.length > 0 && (
        <Encart ton="alerte" titre={`${data.degradations.length} dégradation(s) par rapport à l'entrée`}>
          {data.degradations.map((d) => `${d.piece} — ${d.element} (${data.etats[d.etat_entree] || "—"} → ${data.etats[d.etat_sortie]})`).join(" · ")}.
          Elles vous seront proposées à la résiliation pour chiffrer les retenues sur caution.
        </Encart>
      )}

      <Panneau titre="Informations générales" icone={ClipboardCheck}>
        <div className="formulaire">
          <Saisie libelle="Date de l'état des lieux" type="date" requis valeur={f.date_etat} onChange={(v) => setF({ ...f, date_etat: v })} />
          <Saisie libelle={type === "sortie" ? "Nombre de clés rendues" : "Nombre de clés remises"} aide={data.entree ? `Remises à l'entrée : ${data.entree.nombre_cles}` : undefined} type="number" min="0" valeur={f.nombre_cles} onChange={(v) => setF({ ...f, nombre_cles: v })} />
          <Saisie libelle="Index du compteur d'eau" aide={data.entree?.index_eau ? `À l'entrée : ${data.entree.index_eau}` : undefined} valeur={f.index_eau} onChange={(v) => setF({ ...f, index_eau: v })} placeholder="Ex. : 00412" />
          <Saisie libelle="Index du compteur d'électricité" aide={data.entree?.index_electricite ? `À l'entrée : ${data.entree.index_electricite}` : undefined} valeur={f.index_electricite} onChange={(v) => setF({ ...f, index_electricite: v })} placeholder="Ex. : 15873" />
        </div>
      </Panneau>

      <div className="edl-avancement">
        <span><b>{remplis}</b> / {total} éléments renseignés</span>
        <div className="echeancier-barre"><span style={{ width: `${total ? (remplis / total) * 100 : 0}%`, background: "var(--ok)" }} /></div>
      </div>

      {f.pieces.map((p, i) => (
        <Panneau
          key={i}
          titre={<input className="edl-nom-piece" value={p.nom} onChange={(e) => majPiece(i, { ...p, nom: e.target.value })} aria-label="Nom de la pièce" required />}
          actions={<Bouton taille="petit" variante="fantome" icone={Trash2} onClick={() => setF({ ...f, pieces: f.pieces.filter((_, k) => k !== i) })} aria-label={`Retirer la pièce ${p.nom}`} title="Retirer la pièce" />}
        >
          <div className="edl-elements">
            {p.elements.map((el, j) => (
              <div key={j} className="edl-element">
                <div className="edl-nom-bloc">
                  <input className="saisie edl-nom" value={el.nom} onChange={(e) => majElement(i, j, "nom", e.target.value)} aria-label="Élément" />
                  {type === "sortie" && <span className={`edl-entree etat-${el.etat_entree || "aucun"}`}>Entrée : {data.etats[el.etat_entree] || "non renseigné"}</span>}
                </div>
                <div className="edl-etats" role="radiogroup" aria-label={`État — ${el.nom}`}>
                  {ORDRE_ETATS.map((k) => (
                    <button key={k} type="button" role="radio" aria-checked={el.etat === k} className={`etat-${k} ${el.etat === k ? "actif" : ""}`} onClick={() => majElement(i, j, "etat", el.etat === k ? "" : k)}>
                      {data.etats[k]}
                    </button>
                  ))}
                </div>
                <input className="saisie edl-obs" value={el.observation} onChange={(e) => majElement(i, j, "observation", e.target.value)} placeholder="Observation (tache, fissure, élément manquant…)" />
                <Bouton taille="petit" variante="fantome" icone={X} onClick={() => majPiece(i, { ...p, elements: p.elements.filter((_, k) => k !== j) })} aria-label={`Retirer ${el.nom}`} />
              </div>
            ))}
          </div>
          <div className="ligne-flex" style={{ justifyContent: "space-between", alignItems: "flex-start" }}>
            <Bouton taille="petit" variante="fantome" icone={Plus} onClick={() => majPiece(i, { ...p, elements: [...p.elements, { nom: "", etat: "", observation: "" }] })}>Ajouter un élément</Bouton>
            <PhotosPiece piece={p.nom} photos={photos.filter((ph) => ph.piece === p.nom)} base={url} actif={data.etabli} onChange={setPhotos} />
          </div>
        </Panneau>
      ))}
      <Bouton icone={Plus} onClick={() => setF({ ...f, pieces: [...f.pieces, { nom: "Nouvelle pièce", elements: [{ nom: "Sol", etat: "", observation: "" }, { nom: "Murs", etat: "", observation: "" }, { nom: "Plafond", etat: "", observation: "" }] }] })}>
        Ajouter une pièce
      </Bouton>

      <Panneau titre="Observations générales">
        <div className="formulaire">
          <Zone libelle="Observations" valeur={f.observations} onChange={(v) => setF({ ...f, observations: v })} rows={4} aide="Équipements fournis, réserves du locataire, travaux prévus…" />
        </div>
      </Panneau>
      <div className="ligne-flex" style={{ justifyContent: "flex-end" }}>
        <span className="discret petit-texte">{data.etabli ? `Établi le ${dateFr(data.date_etat)}` : "Pas encore enregistré"}</span>
        <Bouton type="submit" variante="primaire" icone={Save} chargement={envoi}>Enregistrer l'état des lieux</Bouton>
      </div>
    </form>
  );
}
