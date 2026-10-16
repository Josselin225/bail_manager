// Client HTTP : session Django (cookie) + jeton CSRF envoyé dans X-CSRFToken.
import { dateFr } from "./format";

function cookie(nom) {
  const m = document.cookie.match(new RegExp("(^|;\\s*)" + nom + "=([^;]*)"));
  return m ? decodeURIComponent(m[2]) : null;
}

export class ErreurApi extends Error {
  constructor(message, statut, donnees) {
    super(message);
    this.statut = statut;
    this.donnees = donnees;
  }
}

let surExpiration = () => {};
export function definirSurExpiration(fn) {
  surExpiration = fn;
}

function messageErreur(data, statut) {
  if (!data) return statut >= 500 ? "Erreur du serveur. Réessayez dans un instant." : "La requête a échoué.";
  if (typeof data === "string") return data;
  if (data.detail) return data.detail;
  const premier = Object.entries(data)[0];
  if (premier) {
    const [champ, val] = premier;
    return `${champ} : ${Array.isArray(val) ? val[0] : val}`;
  }
  return "La requête a échoué.";
}

const estFichier = (v) => v instanceof File || v instanceof Blob;

function versFormData(obj) {
  const fd = new FormData();
  Object.entries(obj).forEach(([k, v]) => {
    if (v === undefined) return;
    if (v === null) return;
    if (typeof v === "boolean") fd.append(k, v ? "true" : "false");
    else fd.append(k, v);
  });
  return fd;
}

export async function requete(methode, url, corps, { brut = false } = {}) {
  const options = { method: methode, credentials: "same-origin", headers: {} };
  if (methode !== "GET") options.headers["X-CSRFToken"] = cookie("bm_csrf") || "";
  if (corps !== undefined) {
    if (corps instanceof FormData) options.body = corps;
    else if (corps && Object.values(corps).some(estFichier)) options.body = versFormData(corps);
    else {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(corps);
    }
  }
  let rep;
  try {
    rep = await fetch("/api" + url, options);
  } catch {
    throw new ErreurApi("Serveur injoignable. Vérifiez la connexion au réseau.", 0);
  }
  if (brut && rep.ok) return rep;
  if (rep.status === 204) return null;
  const type = rep.headers.get("content-type") || "";
  const data = type.includes("json") ? await rep.json().catch(() => null) : null;
  if (!rep.ok) {
    if (rep.status === 401) surExpiration();
    throw new ErreurApi(messageErreur(data, rep.status), rep.status, data);
  }
  return data;
}

function chaine(params) {
  if (!params) return "";
  const p = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== "" && v !== null && v !== undefined && v !== false));
  const s = p.toString();
  return s ? "?" + s : "";
}

export const api = {
  get: (url, params) => requete("GET", url + chaine(params)),
  post: (url, corps) => requete("POST", url, corps ?? {}),
  patch: (url, corps) => requete("PATCH", url, corps),
  delete: (url) => requete("DELETE", url),
};

export async function telecharger(url, corps, nomParDefaut = "export") {
  const rep = await requete("POST", url, corps, { brut: true });
  const blob = await rep.blob();
  const dispo = rep.headers.get("content-disposition") || "";
  const nom = (dispo.match(/filename="([^"]+)"/) || [])[1] || nomParDefaut;
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = nom;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 2000);
}

/** Exporte un tableau (définition de colonnes de <Tableau>) vers Excel via le serveur. */
export function exporterExcel(titre, colonnes, lignes) {
  const cols = colonnes.filter((c) => c.cle && c.export !== false);
  return telecharger(
    "/export/excel/",
    {
      titre,
      colonnes: cols.map((c) => ({ cle: c.cle, libelle: c.libelle, type: c.type })),
      lignes: lignes.map((l) =>
        Object.fromEntries(
          cols.map((c) => [c.cle, c.exporter ? c.exporter(l) : c.type === "date" ? dateFr(l[c.cle]) : l[c.cle] ?? ""]),
        ),
      ),
    },
    `${titre}.xlsx`,
  );
}
