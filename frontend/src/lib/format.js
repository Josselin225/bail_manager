export const MOIS = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
export const MOIS_COURTS = ["Jan", "Fév", "Mar", "Avr", "Mai", "Juin", "Juil", "Août", "Sep", "Oct", "Nov", "Déc"];

const nf = new Intl.NumberFormat("fr-FR", { maximumFractionDigits: 0 });

export function nombre(v) {
  return nf.format(Math.round(Number(v) || 0)).replace(/\u202f|\u00a0/g, " ");
}

export function fcfa(v) {
  return `${nombre(v)} FCFA`;
}

export function compact(v) {
  const n = Number(v) || 0;
  if (Math.abs(n) >= 1e9) return (n / 1e9).toFixed(1).replace(".", ",") + " Md";
  if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(1).replace(".", ",") + " M";
  if (Math.abs(n) >= 1e3) return Math.round(n / 1e3) + " k";
  return String(n);
}

function versDate(v) {
  if (!v) return null;
  if (v instanceof Date) return v;
  if (/^\d{4}-\d{2}-\d{2}$/.test(v)) {
    const [a, m, j] = v.split("-").map(Number);
    return new Date(a, m - 1, j);
  }
  const d = new Date(v);
  return isNaN(d) ? null : d;
}

export function dateFr(v) {
  const d = versDate(v);
  return d ? d.toLocaleDateString("fr-FR", { day: "2-digit", month: "2-digit", year: "numeric" }) : "";
}

/** Durée d'un mandat de gestion en clair : « Durée indéterminée », « Jusqu'au 31/12/2027 (tacite reconduction) »… */
export function dureeMandat(m, { longue = false } = {}) {
  if (!m) return "";
  const fin = m.echeance || m.date_fin;
  if (m.duree !== "determinee" || !fin) return "Durée indéterminée";
  const d = longue ? dateLongue(fin) : dateFr(fin);
  return m.tacite_reconduction ? `Jusqu'au ${d}, renouvelable par tacite reconduction` : `Jusqu'au ${d}`;
}

/** Jours restants avant la fin d'un mandat à durée déterminée non reconduit (null sinon). */
export function joursAvantFinMandat(m) {
  if (!m || m.duree !== "determinee" || m.tacite_reconduction || !(m.echeance || m.date_fin)) return null;
  return Math.round((new Date(`${m.echeance || m.date_fin}T00:00`) - new Date(new Date().toDateString())) / 86400000);
}

export function dateLongue(v) {
  const d = versDate(v);
  return d ? d.toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" }) : "";
}

export function dateHeure(v) {
  const d = versDate(v);
  return d ? `${dateFr(d)} à ${d.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}` : "";
}

export function isoDate(d = new Date()) {
  const p = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
}

export function joursDepuis(v) {
  const d = versDate(v);
  if (!d) return null;
  const auj = new Date();
  auj.setHours(0, 0, 0, 0);
  return Math.round((auj - d) / 86400000);
}

export function initiales(nom = "") {
  return nom.split(/\s+/).filter(Boolean).slice(0, 2).map((m) => m[0].toUpperCase()).join("") || "?";
}

export function montantEnLettres(n) {
  n = Math.round(Number(n) || 0);
  if (n === 0) return "zéro";
  const u = ["", "un", "deux", "trois", "quatre", "cinq", "six", "sept", "huit", "neuf", "dix", "onze", "douze", "treize", "quatorze", "quinze", "seize"];
  const d = ["", "dix", "vingt", "trente", "quarante", "cinquante", "soixante", "soixante", "quatre-vingt", "quatre-vingt"];
  const moinsCent = (x) => {
    if (x <= 16) return u[x];
    if (x < 20) return "dix-" + u[x - 10];
    const dz = Math.floor(x / 10), un = x % 10;
    if (dz === 7 || dz === 9) return d[dz] + (dz === 7 && un === 1 ? "-et-" : "-") + moinsCent(10 + un);
    if (un === 0) return d[dz] + (dz === 8 ? "s" : "");
    return d[dz] + (un === 1 && dz !== 8 ? "-et-" : "-") + u[un];
  };
  const moinsMille = (x) => {
    const c = Math.floor(x / 100), r = x % 100;
    let s = c === 0 ? "" : c === 1 ? "cent" : u[c] + "-cent" + (r === 0 ? "s" : "");
    if (r) s += (s ? "-" : "") + moinsCent(r);
    return s;
  };
  const parties = [];
  const md = Math.floor(n / 1e9), mi = Math.floor((n % 1e9) / 1e6), mil = Math.floor((n % 1e6) / 1000), r = n % 1000;
  if (md) parties.push(moinsMille(md) + " milliard" + (md > 1 ? "s" : ""));
  if (mi) parties.push(moinsMille(mi) + " million" + (mi > 1 ? "s" : ""));
  if (mil) parties.push(mil === 1 ? "mille" : moinsMille(mil).replace(/s$/, "") + "-mille");
  if (r) parties.push(moinsMille(r));
  return parties.join(" ");
}

export const MODES = [
  { valeur: "especes", libelle: "Espèces" },
  { valeur: "mobile_money", libelle: "Mobile Money" },
  { valeur: "virement", libelle: "Virement" },
  { valeur: "cheque", libelle: "Chèque" },
];
