import { useEffect, useState } from "react";

/** Service worker (application installable, ouverture hors connexion) : production uniquement. */
export function enregistrerApplication() {
  if (import.meta.env.PROD && "serviceWorker" in navigator) {
    window.addEventListener("load", () => navigator.serviceWorker.register("/sw.js").catch(() => {}));
  }
}

let invite = null;
const abonnes = new Set();
if (typeof window !== "undefined") {
  window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    invite = e;
    abonnes.forEach((f) => f(true));
  });
  window.addEventListener("appinstalled", () => {
    invite = null;
    abonnes.forEach((f) => f(false));
  });
}

/** Proposition d'installation (Chrome, Edge, Android) : `installer` est null si le navigateur ne la propose pas. */
export function useInstallation() {
  const [dispo, setDispo] = useState(!!invite);
  useEffect(() => {
    abonnes.add(setDispo);
    return () => { abonnes.delete(setDispo); };
  }, []);
  if (!dispo) return null;
  return async () => {
    const e = invite;
    invite = null;
    setDispo(false);
    await e?.prompt();
  };
}
