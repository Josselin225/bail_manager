import { useId } from "react";

/*
 * Illustrations de la vue « Icônes » du Menu : une scène par fonction, dessinée en SVG
 * (nette à toutes les tailles, identique en thème clair et sombre). Le fond suit la rubrique.
 */
const FONDS = {
  accueil: ["#17395d", "#0e2a47"],
  patrimoine: ["#2f8f7a", "#1c5f52"],
  contrats: ["#2c5f9e", "#0e2a47"],
  gestion: ["#c98a2b", "#8a5410"],
  finances: ["#2f9461", "#1b6040"],
  relations: ["#8466c4", "#563a92"],
  pilotage: ["#3a78b8", "#1f4b7c"],
  administration: ["#64748b", "#334155"],
  aide: ["#d9534a", "#a12a21"],
};

const B = "#ffffff";
const O = "#f4c35a"; // or (argent, accents)
const R = "#ef6a5e"; // rouge clair (alertes)
const M = "#0e2a47"; // marine (traits)
const C = "#dbe7f5"; // bleu très clair (surfaces)

const SCENES = {
  "/tableau-de-bord": ["accueil", <>
    <rect x="12" y="14" width="40" height="36" rx="5" fill={B} />
    <rect x="17" y="35" width="6" height="10" rx="1.5" fill="#2c5f9e" />
    <rect x="26" y="28" width="6" height="17" rx="1.5" fill="#2c5f9e" />
    <rect x="35" y="22" width="6" height="23" rx="1.5" fill={O} />
    <path d="M17 26 L26 21 L33 24 L45 17" stroke={R} strokeWidth="2.6" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    <circle cx="45" cy="17" r="2.6" fill={R} />
  </>],
  "/bailleurs": ["patrimoine", <>
    <circle cx="26" cy="23" r="8" fill={B} />
    <path d="M12 47c1.5-9 7-13 14-13s12.5 4 14 13z" fill={B} />
    <path d="M38 33 L46 26 L54 33 V46 H38z" fill={O} />
    <rect x="44" y="38" width="4.5" height="8" fill="#8a5410" />
    <path d="M36 34 L46 25 L56 34" stroke={B} strokeWidth="2.4" fill="none" strokeLinecap="round" strokeLinejoin="round" />
  </>],
  "/maisons": ["patrimoine", <>
    <path d="M12 32 L32 15 L52 32" stroke={B} strokeWidth="3.4" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    <path d="M17 30 L32 18 L47 30 V49 H17z" fill={B} />
    <rect x="28" y="36" width="8" height="13" rx="1.5" fill="#1c5f52" />
    <rect x="20.5" y="33" width="6" height="6" rx="1" fill={C} />
    <rect x="37.5" y="33" width="6" height="6" rx="1" fill={C} />
    <rect x="41" y="18" width="5" height="8" fill={R} />
  </>],
  "/locataires": ["patrimoine", <>
    <circle cx="24" cy="24" r="7" fill={B} opacity="0.75" />
    <path d="M11 46c1.2-8 6-11.5 13-11.5" stroke={B} strokeOpacity="0.75" strokeWidth="0" fill="none" />
    <path d="M10 47c1.3-8 6.2-12 14-12s12.7 4 14 12z" fill={B} opacity="0.75" />
    <circle cx="38" cy="26" r="8" fill={B} />
    <path d="M23 49c1.5-9 7.4-13.5 15-13.5S51.5 40 53 49z" fill={B} />
    <circle cx="48" cy="17" r="5.5" fill={O} />
    <rect x="46" y="20" width="4" height="9" rx="1" fill={O} />
  </>],
  "/contrats": ["contrats", <>
    <rect x="16" y="11" width="30" height="40" rx="3.5" fill={B} />
    <rect x="21" y="18" width="20" height="2.6" rx="1.3" fill={C} />
    <rect x="21" y="24" width="20" height="2.6" rx="1.3" fill={C} />
    <rect x="21" y="30" width="14" height="2.6" rx="1.3" fill={C} />
    <path d="M21 43c3-4 5 2 8-1s3-3 5 0" stroke="#2c5f9e" strokeWidth="2" fill="none" strokeLinecap="round" />
    <path d="M44 30 L52 22 L55 25 L47 33 L43 34z" fill={O} />
    <path d="M52 22 L55 25" stroke="#8a5410" strokeWidth="1.6" />
  </>],
  "/encaissements": ["contrats", <>
    <rect x="10" y="22" width="34" height="22" rx="4" fill="#3fb47a" />
    <circle cx="27" cy="33" r="6" fill="#b7ebcf" />
    <rect x="13" y="25" width="5" height="3" rx="1.5" fill="#b7ebcf" />
    <circle cx="44" cy="40" r="10" fill={O} />
    <circle cx="44" cy="40" r="6.5" fill="none" stroke="#c98a2b" strokeWidth="1.6" />
    <path d="M44 36.5v7M41.5 40h5" stroke="#8a5410" strokeWidth="1.8" strokeLinecap="round" />
    <path d="M40 14 l4 4 8-8" stroke={B} strokeWidth="3" fill="none" strokeLinecap="round" strokeLinejoin="round" />
  </>],
  "/recouvrement": ["contrats", <>
    <circle cx="32" cy="35" r="15" fill={B} />
    <circle cx="32" cy="35" r="11" fill={C} />
    <path d="M32 27v8l6 4" stroke={M} strokeWidth="2.6" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    <path d="M15 21 l5-5M49 21 l-5-5" stroke={B} strokeWidth="3.2" strokeLinecap="round" />
    <circle cx="47" cy="47" r="7" fill={R} />
    <rect x="46" y="42.5" width="2" height="5.5" rx="1" fill={B} />
    <circle cx="47" cy="50.5" r="1.2" fill={B} />
  </>],
  "/calendrier": ["contrats", <>
    <rect x="12" y="16" width="40" height="34" rx="5" fill={B} />
    <path d="M12 21a5 5 0 0 1 5-5h30a5 5 0 0 1 5 5v5H12z" fill={R} />
    <rect x="20" y="11" width="3.4" height="9" rx="1.7" fill={M} />
    <rect x="40.6" y="11" width="3.4" height="9" rx="1.7" fill={M} />
    {[0, 1, 2, 3].map((c) => [0, 1, 2].map((l) => <rect key={`${c}${l}`} x={17 + c * 8} y={30 + l * 6} width="5" height="3.6" rx="1" fill={c === 2 && l === 1 ? O : C} />))}
  </>],
  "/charges": ["gestion", <>
    <path d="M30 10 L18 34 H29 L25 54 L46 26 H34 L39 10z" fill={O} />
    <path d="M30 10 L18 34 H29 L25 54" fill="none" stroke="#fff3cf" strokeWidth="1.4" />
    <circle cx="48" cy="45" r="7" fill="#7cc4f2" />
    <path d="M48 36 C44 42 44 44 44 45 a4 4 0 0 0 8 0 c0-1 0-3-4-9z" fill="#7cc4f2" />
  </>],
  "/compteurs": ["gestion", <>
    <path d="M32 10 C22 24 18 30 18 37 a14 14 0 0 0 28 0 c0-7-4-13-14-27z" fill="#7cc4f2" />
    <path d="M25 38 a7 7 0 0 0 7 7" stroke={B} strokeWidth="2.6" fill="none" strokeLinecap="round" />
    <rect x="38" y="38" width="16" height="12" rx="3" fill={B} />
    <rect x="41" y="41" width="10" height="4" rx="1" fill={M} />
    <path d="M42 47.5h8" stroke={O} strokeWidth="1.6" />
  </>],
  "/revisions": ["gestion", <>
    <rect x="12" y="14" width="40" height="36" rx="5" fill={B} />
    <path d="M17 42 L27 33 L34 37 L46 23" stroke="#2f9461" strokeWidth="3.2" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    <path d="M39 22 H47 V30" stroke="#2f9461" strokeWidth="3.2" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    <circle cx="20" cy="20" r="3" fill={O} />
  </>],
  "/interventions": ["gestion", <>
    <path d="M40 12a10 10 0 0 0-9.4 13.4L14 42a4.2 4.2 0 0 0 6 6l16.6-16.6A10 10 0 0 0 50 22l-6 6-6-2-2-6 6-6a10 10 0 0 0-2-2z" fill={B} />
    <circle cx="17.5" cy="44.5" r="1.8" fill="#8a5410" />
    <path d="M44 40 l10 10" stroke={O} strokeWidth="5" strokeLinecap="round" />
    <path d="M40 36 l6 6" stroke="#8a5410" strokeWidth="5" strokeLinecap="round" />
  </>],
  "/comptes-bailleurs": ["finances", <>
    <rect x="10" y="20" width="40" height="28" rx="5" fill={B} />
    <path d="M10 26h40" stroke={C} strokeWidth="4" />
    <rect x="36" y="31" width="18" height="11" rx="3" fill={O} />
    <circle cx="42" cy="36.5" r="2.2" fill="#8a5410" />
    <path d="M16 16 h22" stroke={B} strokeOpacity="0.6" strokeWidth="3" strokeLinecap="round" />
  </>],
  "/caisse": ["finances", <>
    <rect x="12" y="28" width="40" height="22" rx="4" fill={B} />
    <rect x="17" y="16" width="30" height="14" rx="3" fill={C} />
    <rect x="21" y="20" width="22" height="6" rx="1.5" fill={M} />
    <path d="M24 23h6" stroke="#3fb47a" strokeWidth="2" />
    {[0, 1, 2].map((c) => [0, 1].map((l) => <rect key={`${c}${l}`} x={18 + c * 9} y={33 + l * 6} width="6" height="3.5" rx="1" fill={C} />))}
    <rect x="44" y="33" width="4" height="9.5" rx="1" fill={O} />
  </>],
  "/rapprochement": ["finances", <>
    <rect x="9" y="14" width="22" height="30" rx="3" fill={B} />
    <rect x="33" y="20" width="22" height="30" rx="3" fill={B} opacity="0.9" />
    {[0, 1, 2].map((i) => <rect key={i} x="13" y={20 + i * 7} width="14" height="2.6" rx="1.3" fill={C} />)}
    {[0, 1, 2].map((i) => <rect key={i} x="37" y={26 + i * 7} width="14" height="2.6" rx="1.3" fill={C} />)}
    <circle cx="32" cy="32" r="8" fill={O} />
    <path d="M28.5 32 l2.5 2.5 5-5" stroke="#8a5410" strokeWidth="2.4" fill="none" strokeLinecap="round" strokeLinejoin="round" />
  </>],
  "/cautions": ["finances", <>
    <path d="M32 10 L50 17 V31 C50 42 42 50 32 54 C22 50 14 42 14 31 V17z" fill={B} />
    <path d="M32 15 L45 20 V31 C45 39 39.5 45 32 48.5z" fill={C} />
    <circle cx="32" cy="30" r="7" fill={O} />
    <path d="M32 26.5v7M29.5 30h5" stroke="#8a5410" strokeWidth="1.8" strokeLinecap="round" />
  </>],
  "/reservations": ["relations", <>
    <path d="M10 34 L27 20 L44 34" stroke={B} strokeWidth="3" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    <path d="M14 32 L27 22 L40 32 V48 H14z" fill={B} />
    <rect x="23.5" y="37" width="7" height="11" rx="1.2" fill="#563a92" />
    <circle cx="46" cy="40" r="10" fill={O} />
    <path d="M46 34v6l4 2.5" stroke="#8a5410" strokeWidth="2.2" fill="none" strokeLinecap="round" strokeLinejoin="round" />
  </>],
  "/messages": ["relations", <>
    <rect x="10" y="17" width="44" height="30" rx="4" fill={B} />
    <path d="M12 20 L32 35 L52 20" stroke={C} strokeWidth="3" fill="none" strokeLinejoin="round" />
    <circle cx="50" cy="17" r="6.5" fill={R} />
    <rect x="49" y="13.5" width="2" height="4.5" rx="1" fill={B} />
    <circle cx="50" cy="20.3" r="1.1" fill={B} />
  </>],
  "/messagerie": ["relations", <>
    <path d="M10 18a5 5 0 0 1 5-5h22a5 5 0 0 1 5 5v12a5 5 0 0 1-5 5H22l-7 6v-6a5 5 0 0 1-5-5z" fill={B} />
    <path d="M54 30a5 5 0 0 0-5-5H30a5 5 0 0 0-5 5v10a5 5 0 0 0 5 5h12l7 6v-6a5 5 0 0 0 5-5z" fill={O} />
    {[0, 1, 2].map((i) => <circle key={i} cx={20 + i * 6} cy="24" r="1.9" fill="#8466c4" />)}
  </>],
  "/rapports": ["pilotage", <>
    <rect x="15" y="10" width="34" height="44" rx="4" fill={B} />
    <rect x="20" y="16" width="16" height="3" rx="1.5" fill={C} />
    <circle cx="32" cy="34" r="10" fill={C} />
    <path d="M32 24 A10 10 0 0 1 42 34 H32z" fill={O} />
    <path d="M32 34 L24 40.5 A10 10 0 0 1 22 34z" fill={R} />
    <rect x="20" y="47" width="24" height="2.6" rx="1.3" fill={C} />
  </>],
  "/equipe": ["pilotage", <>
    <circle cx="20" cy="24" r="6" fill={B} opacity="0.75" />
    <path d="M9 44c1-7 5-10 11-10s10 3 11 10z" fill={B} opacity="0.75" />
    <circle cx="44" cy="24" r="6" fill={B} opacity="0.75" />
    <path d="M33 44c1-7 5-10 11-10s10 3 11 10z" fill={B} opacity="0.75" />
    <circle cx="32" cy="27" r="7.5" fill={B} />
    <path d="M18 50c1.3-8.5 6.5-12.5 14-12.5S44.7 41.5 46 50z" fill={B} />
    <path d="M27 14 l3 3 6-6" stroke={O} strokeWidth="2.8" fill="none" strokeLinecap="round" strokeLinejoin="round" />
  </>],
  "/journal": ["pilotage", <>
    <rect x="14" y="12" width="32" height="40" rx="4" fill={B} />
    {[0, 1, 2, 3].map((i) => <g key={i}><circle cx="21" cy={21 + i * 8} r="2" fill={i === 0 ? R : "#3a78b8"} /><rect x="26" y={19.7 + i * 8} width="14" height="2.6" rx="1.3" fill={C} /></g>)}
    <circle cx="46" cy="44" r="9" fill={O} />
    <path d="M46 39.5v5l3 2" stroke="#8a5410" strokeWidth="2" fill="none" strokeLinecap="round" />
  </>],
  "/parametres": ["administration", <>
    <path d="M28.5 10h7l1.2 5.4 4 1.7 4.7-3 5 5-3 4.7 1.7 4 5.4 1.2v7l-5.4 1.2-1.7 4 3 4.7-5 5-4.7-3-4 1.7-1.2 5.4h-7l-1.2-5.4-4-1.7-4.7 3-5-5 3-4.7-1.7-4L10 35.5v-7l5.4-1.2 1.7-4-3-4.7 5-5 4.7 3 4-1.7z" fill={B} />
    <circle cx="32" cy="32" r="8" fill="#64748b" />
    <circle cx="32" cy="32" r="4" fill={O} />
  </>],
  "/utilisateurs": ["administration", <>
    <circle cx="26" cy="23" r="8" fill={B} />
    <path d="M11 48c1.5-9 7.3-13.5 15-13.5S39.5 39 41 48z" fill={B} />
    <circle cx="46" cy="40" r="9" fill={O} />
    <path d="M46 36 v8 M42 40 h8" stroke="#8a5410" strokeWidth="2.6" strokeLinecap="round" />
  </>],
  "/profil": ["administration", <>
    <circle cx="32" cy="32" r="21" fill={B} />
    <circle cx="32" cy="27" r="8" fill="#64748b" />
    <path d="M18 47c2-8 7.5-11 14-11s12 3 14 11a20 20 0 0 1-28 0z" fill="#64748b" />
    <circle cx="47" cy="17" r="5" fill={O} />
  </>],
  "/guide": ["aide", <>
    <path d="M10 18c7-3 15-3 22 2v32c-7-5-15-5-22-2z" fill={B} />
    <path d="M54 18c-7-3-15-3-22 2v32c7-5 15-5 22-2z" fill="#ffe3df" />
    {[0, 1, 2].map((i) => <rect key={i} x="15" y={25 + i * 6} width="12" height="2.2" rx="1.1" fill={C} />)}
    <circle cx="43" cy="31" r="6" fill={O} />
    <path d="M43 28.5v3M43 34v.1" stroke="#8a5410" strokeWidth="2.2" strokeLinecap="round" />
  </>],
  "/a-propos": ["aide", <>
    <circle cx="32" cy="32" r="20" fill={B} />
    <circle cx="32" cy="22.5" r="3.2" fill="#a12a21" />
    <rect x="29" y="29" width="6" height="16" rx="3" fill="#a12a21" />
  </>],
};

export default function IllustrationMenu({ lien, taille = 64 }) {
  const id = useId().replace(/:/g, "");
  const [fond, dessin] = SCENES[lien] || ["administration", <circle cx="32" cy="32" r="12" fill={B} />];
  const [c1, c2] = FONDS[fond];
  return (
    <svg width={taille} height={taille} viewBox="0 0 64 64" aria-hidden="true" className="illustration-menu">
      <defs>
        <linearGradient id={`f${id}`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor={c1} />
          <stop offset="1" stopColor={c2} />
        </linearGradient>
        <radialGradient id={`l${id}`} cx="0.25" cy="0.15" r="0.9">
          <stop offset="0" stopColor="#ffffff" stopOpacity="0.28" />
          <stop offset="1" stopColor="#ffffff" stopOpacity="0" />
        </radialGradient>
      </defs>
      <rect width="64" height="64" rx="16" fill={`url(#f${id})`} />
      <rect width="64" height="64" rx="16" fill={`url(#l${id})`} />
      {dessin}
    </svg>
  );
}
