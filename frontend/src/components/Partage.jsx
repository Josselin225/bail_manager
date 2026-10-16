import { useState } from "react";
import { Check, Link2, Share2 } from "lucide-react";
import { fcfa } from "../lib/format";

/** Texte d'annonce prêt à partager (WhatsApp, Facebook, SMS…). */
export function texteAnnonce(m) {
  return `${m.designation}${m.type_maison ? ` (${m.type_maison})` : ""}${m.adresse ? ` — ${m.adresse}` : ""} : ${fcfa(m.loyer)} par mois. Visite sur rendez-vous.`;
}

function IconeWhatsapp() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
      <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3a.5.5 0 0 0 0-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.4.8 3.2.6a2.8 2.8 0 0 0 1.8-1.3 2.3 2.3 0 0 0 .2-1.3c-.1-.1-.3-.2-.6-.3Z" />
    </svg>
  );
}

function IconeFacebook() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
      <path d="M13.5 21v-7.5h2.5l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4a21 21 0 0 0-2.3-.1c-2.3 0-3.9 1.4-3.9 4v2.2H8v3h2.4V21h3.1Z" />
    </svg>
  );
}

/** Boutons de partage d'une annonce. Les liens s'ouvrent dans l'onglet courant (règle : un seul onglet). */
export default function Partage({ m, compact }) {
  const [copie, setCopie] = useState(false);
  const url = `${window.location.origin}/nos-maisons/${m.id}`;
  const texte = texteAnnonce(m);
  const natif = typeof navigator !== "undefined" && navigator.share;
  const copier = async () => {
    try {
      await navigator.clipboard.writeText(`${texte}\n${url}`);
      setCopie(true);
      setTimeout(() => setCopie(false), 2000);
    } catch {
      window.prompt("Copiez le lien de l'annonce :", url);
    }
  };
  return (
    <div className={`partage ${compact ? "compact" : ""}`}>
      {!compact && <span className="partage-titre">Partager l'annonce</span>}
      <div className="partage-boutons">
        <a className="btn petit partage-whatsapp" href={`https://wa.me/?text=${encodeURIComponent(`${texte}\n${url}`)}`}><IconeWhatsapp /> WhatsApp</a>
        <a className="btn petit partage-facebook" href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`}><IconeFacebook /> Facebook</a>
        <button type="button" className="btn petit" onClick={copier}>{copie ? <Check size={15} /> : <Link2 size={15} />} {copie ? "Copié" : "Copier le lien"}</button>
        {natif && <button type="button" className="btn petit" onClick={() => navigator.share({ title: m.designation, text: texte, url }).catch(() => {})}><Share2 size={15} /> Autres</button>}
      </div>
    </div>
  );
}
