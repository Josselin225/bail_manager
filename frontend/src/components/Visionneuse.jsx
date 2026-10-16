import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { ChevronLeft, ChevronRight, X } from "lucide-react";

/**
 * Affichage plein écran d'une série de photos.
 * photos : liste d'URL ; depart : index affiché à l'ouverture ; titre : légende optionnelle.
 */
export default function Visionneuse({ photos, depart = 0, titre, onFermer }) {
  const [i, setI] = useState(depart);
  const toucher = useRef(null);
  const n = photos.length;
  const aller = (k) => setI(((k % n) + n) % n);

  useEffect(() => {
    const f = (e) => {
      if (e.key === "Escape") onFermer();
      if (e.key === "ArrowRight") setI((x) => (x + 1) % n);
      if (e.key === "ArrowLeft") setI((x) => (x - 1 + n) % n);
    };
    document.addEventListener("keydown", f);
    const debord = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", f);
      document.body.style.overflow = debord;
    };
  }, [n, onFermer]);

  useEffect(() => {
    document.querySelector(".visionneuse-vignettes .actif")?.scrollIntoView({ block: "nearest", inline: "center", behavior: "smooth" });
  }, [i]);

  return createPortal(
    <div
      className="visionneuse"
      role="dialog"
      aria-modal="true"
      aria-label={titre ? `Photos — ${titre}` : "Photos"}
      onMouseDown={(e) => e.target === e.currentTarget && onFermer()}
      onTouchStart={(e) => { toucher.current = e.touches[0].clientX; }}
      onTouchEnd={(e) => {
        const dx = e.changedTouches[0].clientX - (toucher.current ?? 0);
        if (Math.abs(dx) > 50) aller(i + (dx < 0 ? 1 : -1));
      }}
    >
      <div className="visionneuse-haut">
        <span className="compteur">{i + 1} / {n}</span>
        {titre && <span className="titre">{titre}</span>}
        <button onClick={onFermer} aria-label="Fermer" autoFocus><X size={22} /></button>
      </div>
      <div className="visionneuse-scene" onMouseDown={(e) => e.target === e.currentTarget && onFermer()}>
        {n > 1 && <button className="fleche gauche" onClick={() => aller(i - 1)} aria-label="Photo précédente"><ChevronLeft size={28} /></button>}
        <img key={photos[i]} src={photos[i]} alt={`Photo ${i + 1} sur ${n}`} />
        {n > 1 && <button className="fleche droite" onClick={() => aller(i + 1)} aria-label="Photo suivante"><ChevronRight size={28} /></button>}
      </div>
      {n > 1 && (
        <div className="visionneuse-vignettes">
          {photos.map((src, k) => (
            <button key={src} className={k === i ? "actif" : ""} onClick={() => setI(k)} aria-label={`Photo ${k + 1}`}><img src={src} alt="" loading="lazy" /></button>
          ))}
        </div>
      )}
    </div>,
    document.body,
  );
}
