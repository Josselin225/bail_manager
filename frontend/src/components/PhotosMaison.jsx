import { useRef, useState } from "react";
import { ImagePlus, Loader2, Maximize2, Star, Trash2 } from "lucide-react";
import { api } from "../lib/api";
import { useConfirmer, useToast } from "./ui";
import Visionneuse from "./Visionneuse";

/** Galerie modifiable d'une maison : ajout multiple (clic ou glisser-déposer), photo principale, suppression. */
export default function PhotosMaison({ maison, onChange }) {
  const toast = useToast();
  const confirmer = useConfirmer();
  const champ = useRef(null);
  const [envoi, setEnvoi] = useState(false);
  const [survol, setSurvol] = useState(false);
  const [vue, setVue] = useState(null);
  const photos = maison.photos || [];

  const agir = async (fn, message) => {
    try {
      onChange(await fn());
      if (message) toast.succes(message);
    } catch (e) {
      toast.erreur(e.message);
    }
  };
  const ajouter = async (fichiers) => {
    const liste = [...fichiers].filter((f) => f.type.startsWith("image/"));
    if (!liste.length) return;
    const fd = new FormData();
    liste.forEach((f) => fd.append("photos", f));
    setEnvoi(true);
    await agir(() => api.post(`/maisons/${maison.id}/photos/`, fd), `${liste.length} photo${liste.length > 1 ? "s ajoutées" : " ajoutée"}.`);
    setEnvoi(false);
  };

  return (
    <div className="photos-maison">
      <div className="photos-grille">
        {photos.map((p, k) => (
          <figure key={p.id} className={k === 0 ? "principale" : ""}>
            <img src={p.url} alt={`Photo ${k + 1}`} loading="lazy" onClick={() => setVue(k)} />
            {k === 0 && <span className="marque-principale"><Star size={12} /> Principale</span>}
            <figcaption>
              <button onClick={() => setVue(k)} title="Agrandir" aria-label="Agrandir"><Maximize2 size={15} /></button>
              {k > 0 && <button onClick={() => agir(() => api.post(`/maisons/${maison.id}/photos/${p.id}/principale/`), "Photo principale mise à jour.")} title="Définir comme photo principale" aria-label="Définir comme photo principale"><Star size={15} /></button>}
              <button className="danger" onClick={async () => (await confirmer({ message: "Supprimer cette photo ?", danger: true, libelle: "Supprimer" })) && agir(() => api.post(`/maisons/${maison.id}/photos/${p.id}/supprimer/`), "Photo supprimée.")} title="Supprimer" aria-label="Supprimer"><Trash2 size={15} /></button>
            </figcaption>
          </figure>
        ))}
        <button
          className={`photos-ajout ${survol ? "survol" : ""}`}
          onClick={() => champ.current?.click()}
          onDragOver={(e) => (e.preventDefault(), setSurvol(true))}
          onDragLeave={() => setSurvol(false)}
          onDrop={(e) => (e.preventDefault(), setSurvol(false), ajouter(e.dataTransfer.files))}
          disabled={envoi}
        >
          {envoi ? <Loader2 size={24} className="tourne" /> : <ImagePlus size={24} />}
          <span>{envoi ? "Envoi…" : photos.length ? "Ajouter des photos" : "Ajouter les premières photos"}</span>
          <small>Glissez-les ici ou cliquez · JPG, PNG, WebP</small>
        </button>
      </div>
      <input ref={champ} type="file" accept="image/*" multiple hidden onChange={(e) => (ajouter(e.target.files), (e.target.value = ""))} />
      {vue !== null && <Visionneuse photos={photos.map((p) => p.url)} depart={vue} titre={maison.designation} onFermer={() => setVue(null)} />}
    </div>
  );
}
