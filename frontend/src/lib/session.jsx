import { createContext, useCallback, useContext, useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { api, definirSurExpiration } from "./api";

const ContexteSession = createContext(null);

export function FournisseurSession({ children }) {
  const [etat, setEtat] = useState({ pret: false, utilisateur: null, entreprise: {}, version: "" });
  const client = useQueryClient();

  const rafraichir = useCallback(async () => {
    try {
      const d = await api.get("/auth/session/");
      setEtat({ pret: true, utilisateur: d.utilisateur, entreprise: d.entreprise || {}, version: d.version, licence: d.licence });
      appliquerCouleur(d.entreprise?.couleur);
      return d;
    } catch {
      setEtat((e) => ({ ...e, pret: true }));
    }
  }, []);

  useEffect(() => {
    rafraichir();
    definirSurExpiration(() => {
      setEtat((e) => (e.utilisateur ? { ...e, utilisateur: null, expire: true } : e));
      client.clear();
    });
  }, [rafraichir, client]);

  const connexion = async (email, password) => {
    const d = await api.post("/auth/connexion/", { email, password });
    setEtat({ pret: true, utilisateur: d.utilisateur, entreprise: d.entreprise || {}, version: d.version, licence: d.licence });
    appliquerCouleur(d.entreprise?.couleur);
    return d;
  };
  const deconnexion = async () => {
    await api.post("/auth/deconnexion/").catch(() => {});
    client.clear();
    setEtat((e) => ({ ...e, utilisateur: null, expire: false }));
    await rafraichir();
  };
  const estAdmin = etat.utilisateur?.role === "admin";
  return (
    <ContexteSession.Provider value={{ ...etat, estAdmin, rafraichir, connexion, deconnexion }}>{children}</ContexteSession.Provider>
  );
}

export const useSession = () => useContext(ContexteSession);

export function appliquerCouleur(c) {
  if (c && /^#[0-9a-f]{6}$/i.test(c)) document.documentElement.style.setProperty("--menu", c);
  else document.documentElement.style.removeProperty("--menu");
}

export function appliquerTheme(t) {
  document.documentElement.dataset.theme = t;
  try {
    localStorage.setItem("bm-theme", t);
  } catch {}
}

export function themeInitial() {
  try {
    const t = localStorage.getItem("bm-theme");
    if (t) return t;
  } catch {}
  return window.matchMedia?.("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}
