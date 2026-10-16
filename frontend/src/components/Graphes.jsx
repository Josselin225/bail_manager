import { useState } from "react";
import { Link } from "react-router-dom";
import {
  Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, ComposedChart, LabelList, Legend, Line,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from "recharts";
import { BarChart3, Table2 } from "lucide-react";
import { MOIS_COURTS, compact, fcfa } from "../lib/format";
import { Bouton, Panneau, Tableau, Vide } from "./ui";

/*
 * Graphiques du tableau de bord.
 * Couleurs (validées daltonisme, clair et sombre) : --viz-1 bleu (série principale),
 * --viz-2 vert d'eau (biens disponibles), --viz-ref gris (référence : attendu, année précédente),
 * --viz-impaye-1…4 : rampe séquentielle rouge pour l'ancienneté des impayés.
 */
const AXE = { tickLine: false, axisLine: false, tick: { fill: "var(--discret)", fontSize: 12 } };
const GRILLE = <CartesianGrid vertical={false} stroke="var(--ligne)" strokeDasharray="0" />;
const ETIQUETTE = { fill: "var(--texte-2)", fontSize: 11.5, fontWeight: 600 };
const moisCourt = (m, a, avecAnnee) => `${MOIS_COURTS[m - 1]}${avecAnnee ? ` ${String(a).slice(2)}` : ""}`;

/** Valeur écrite au-dessus d'une barre, sur une seule ligne (pas de retour à la ligne sur les barres étroites). */
function EtiquetteHaut({ x, y, width, value }) {
  if (!value) return null;
  return <text x={x + width / 2} y={y - 7} textAnchor="middle" style={ETIQUETTE}>{compact(value)}</text>;
}

/** Infobulle commune : libellé + une ligne par série (pastille de couleur, nom, valeur en encre de texte). */
function InfoBulle({ active, payload, label, format = fcfa, extra }) {
  if (!active || !payload?.length) return null;
  return (
    <div className="infobulle">
      {label !== undefined && <div className="infobulle-titre">{label}</div>}
      {payload.map((p) => (
        <div key={p.dataKey} className="infobulle-ligne">
          <i style={{ background: p.color || p.payload?.couleur }} />
          <span>{p.name}</span>
          <b>{format(p.value)}</b>
        </div>
      ))}
      {extra?.(payload[0].payload)}
    </div>
  );
}

/** Panneau de graphique avec bascule vers la vue tableau (accessibilité, valeurs exactes). */
export function PanneauGraphe({ titre, sousTitre, colonnes, lignes, cle, hauteur = 260, actions, children, vide }) {
  const [tableau, setTableau] = useState(false);
  return (
    <Panneau
      titre={<div className="titre-graphe"><h2>{titre}</h2>{sousTitre && <span>{sousTitre}</span>}</div>}
      actions={
        <div className="ligne-flex" style={{ gap: 4 }}>
          {actions}
          {colonnes && (
            <Bouton taille="petit" variante="fantome" icone={tableau ? BarChart3 : Table2} onClick={() => setTableau((t) => !t)} aria-pressed={tableau} title={tableau ? "Afficher le graphique" : "Afficher les données"}>
              {tableau ? "Graphique" : "Données"}
            </Bouton>
          )}
        </div>
      }
      serre={tableau}
    >
      {vide ? <Vide titre={vide} /> : tableau ? <Tableau colonnes={colonnes} lignes={lignes} cle={cle} /> : <div className="zone-graphe" style={{ height: hauteur }}>{children}</div>}
    </Panneau>
  );
}

/* ─── Recouvrement des 12 derniers mois : encaissé (barres) face à l'attendu (ligne de référence) ─── */
export function GrapheRecouvrement({ donnees }) {
  const lignes = donnees.map((d, i) => ({
    ...d,
    libelle: moisCourt(d.mois, d.annee, i === 0 || d.mois === 1),
    attendu: Number(d.attendu),
    encaisse: Number(d.encaisse),
    taux: Number(d.attendu) ? Math.round((Number(d.encaisse) / Number(d.attendu)) * 100) : null,
  }));
  const dernier = lignes[lignes.length - 1];
  return (
    <PanneauGraphe
      titre="Recouvrement des loyers"
      sousTitre={dernier?.taux != null ? `${dernier.taux} % collectés ce mois-ci` : "12 derniers mois"}
      lignes={lignes}
      cle="libelle"
      actions={<Link to="/encaissements?vue=historique" className="petit-texte">Historique</Link>}
      colonnes={[
        { libelle: "Mois", cle: "libelle" },
        { libelle: "Attendu", cle: "attendu", type: "montant" },
        { libelle: "Encaissé", cle: "encaisse", type: "montant" },
        { libelle: "Taux", rendu: (l) => (l.taux == null ? "—" : `${l.taux} %`), droite: true },
      ]}
    >
      <ResponsiveContainer>
        <ComposedChart data={lignes} margin={{ left: -8, right: 8, top: 8 }} barCategoryGap="28%">
          {GRILLE}
          <XAxis dataKey="libelle" {...AXE} interval="preserveStartEnd" />
          <YAxis tickFormatter={compact} {...AXE} width={52} />
          <Tooltip cursor={{ fill: "var(--surface-2)" }} content={<InfoBulle extra={(l) => l.taux != null && <div className="infobulle-pied">{l.taux} % de l'attendu</div>} />} />
          <Legend iconType="square" iconSize={10} wrapperStyle={{ fontSize: 12, color: "var(--texte-2)" }} />
          <Bar dataKey="encaisse" name="Encaissé" fill="var(--viz-1)" radius={[4, 4, 0, 0]} maxBarSize={26} />
          <Line dataKey="attendu" name="Attendu" stroke="var(--viz-ref)" strokeWidth={2} strokeDasharray="5 4" dot={{ r: 3, fill: "var(--viz-ref)", strokeWidth: 0 }} activeDot={{ r: 5, stroke: "var(--surface)", strokeWidth: 2 }} />
        </ComposedChart>
      </ResponsiveContainer>
    </PanneauGraphe>
  );
}

/* ─── Impayés par ancienneté : rampe séquentielle (plus c'est ancien, plus c'est foncé) ─── */
export function GrapheImpayes({ donnees }) {
  const lignes = donnees.map((d, i) => ({ ...d, montant: Number(d.montant), couleur: `var(--viz-impaye-${i + 1})` }));
  const total = lignes.reduce((s, l) => s + l.montant, 0);
  return (
    <PanneauGraphe
      titre="Impayés par ancienneté"
      sousTitre={total ? `${fcfa(total)} au total` : "Aucun impayé"}
      lignes={lignes}
      cle="tranche"
      actions={<Link to="/encaissements" className="petit-texte">Relancer</Link>}
      colonnes={[{ libelle: "Ancienneté", cle: "tranche" }, { libelle: "Montant", cle: "montant", type: "montant" }]}
      vide={total ? null : "Aucun loyer impayé"}
    >
      <ResponsiveContainer>
        <BarChart data={lignes} margin={{ left: -8, right: 8, top: 22 }} barCategoryGap="30%">
          {GRILLE}
          <XAxis dataKey="tranche" {...AXE} />
          <YAxis tickFormatter={compact} {...AXE} width={52} />
          <Tooltip cursor={{ fill: "var(--surface-2)" }} content={<InfoBulle />} />
          <Bar dataKey="montant" name="Impayés" radius={[4, 4, 0, 0]} maxBarSize={44}>
            {lignes.map((l) => <Cell key={l.tranche} fill={l.couleur} />)}
            <LabelList dataKey="montant" content={EtiquetteHaut} />
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </PanneauGraphe>
  );
}

/* ─── Encaissements de l'année, comparés à l'année précédente (en gris, référence) ─── */
export function GrapheEncaissementsAnnee({ graphique }) {
  const a = graphique.annee;
  const lignes = MOIS_COURTS.map((m, i) => ({ mois: m, courante: Number(graphique.courante[i]), precedente: Number(graphique.precedente[i]) }));
  const total = lignes.reduce((s, l) => s + l.courante, 0);
  return (
    <PanneauGraphe
      titre={`Encaissements ${a}`}
      sousTitre={`${fcfa(total)} depuis janvier`}
      lignes={lignes}
      cle="mois"
      colonnes={[{ libelle: "Mois", cle: "mois" }, { libelle: String(a - 1), cle: "precedente", type: "montant" }, { libelle: String(a), cle: "courante", type: "montant" }]}
    >
      <ResponsiveContainer>
        <BarChart data={lignes} margin={{ left: -8, right: 8, top: 8 }} barGap={2} barCategoryGap="22%">
          {GRILLE}
          <XAxis dataKey="mois" {...AXE} />
          <YAxis tickFormatter={compact} {...AXE} width={52} />
          <Tooltip cursor={{ fill: "var(--surface-2)" }} content={<InfoBulle />} />
          <Legend iconType="square" iconSize={10} wrapperStyle={{ fontSize: 12, color: "var(--texte-2)" }} />
          <Bar dataKey="precedente" name={String(a - 1)} fill="var(--viz-ref-clair)" radius={[4, 4, 0, 0]} maxBarSize={14} />
          <Bar dataKey="courante" name={String(a)} fill="var(--viz-1)" radius={[4, 4, 0, 0]} maxBarSize={14} />
        </BarChart>
      </ResponsiveContainer>
    </PanneauGraphe>
  );
}

/* ─── Occupation par type de bien : barres empilées occupées / disponibles, valeurs écrites ─── */
export function GrapheOccupation({ donnees }) {
  const lignes = donnees.map((d) => ({ ...d, total: d.occupees + d.libres }));
  const hauteur = Math.max(160, lignes.length * 44 + 60);
  return (
    <PanneauGraphe
      titre="Occupation par type de bien"
      sousTitre={`${lignes.reduce((s, l) => s + l.occupees, 0)} occupées · ${lignes.reduce((s, l) => s + l.libres, 0)} disponibles`}
      lignes={lignes}
      cle="type"
      hauteur={hauteur}
      actions={<Link to="/maisons" className="petit-texte">Maisons</Link>}
      colonnes={[{ libelle: "Type", cle: "type" }, { libelle: "Occupées", cle: "occupees", droite: true }, { libelle: "Disponibles", cle: "libres", droite: true }, { libelle: "Total", cle: "total", droite: true }]}
      vide={lignes.length ? null : "Aucune maison enregistrée"}
    >
      <ResponsiveContainer>
        <BarChart data={lignes} layout="vertical" margin={{ left: 0, right: 16, top: 4 }} barCategoryGap="26%">
          <XAxis type="number" hide allowDecimals={false} />
          <YAxis type="category" dataKey="type" {...AXE} width={104} />
          <Tooltip cursor={{ fill: "var(--surface-2)" }} content={<InfoBulle format={(v) => `${v} maison${v > 1 ? "s" : ""}`} />} />
          <Legend iconType="square" iconSize={10} wrapperStyle={{ fontSize: 12, color: "var(--texte-2)" }} />
          <Bar dataKey="occupees" name="Occupées" stackId="o" fill="var(--viz-1)" stroke="var(--surface)" strokeWidth={2} maxBarSize={22}>
            <LabelList dataKey="occupees" position="center" formatter={(v) => v || ""} style={{ fill: "#fff", fontSize: 11.5, fontWeight: 700 }} />
          </Bar>
          <Bar dataKey="libres" name="Disponibles" stackId="o" fill="var(--viz-2)" stroke="var(--surface)" strokeWidth={2} radius={[0, 4, 4, 0]} maxBarSize={22}>
            <LabelList dataKey="libres" position="center" formatter={(v) => v || ""} style={{ fill: "#fff", fontSize: 11.5, fontWeight: 700 }} />
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </PanneauGraphe>
  );
}

/* ─── Commissions mensuelles de l'année : aire, une seule série ─── */
export function GrapheCommissions({ donnees, annee }) {
  const moisActuel = new Date().getFullYear() === annee ? new Date().getMonth() + 1 : 12;
  const lignes = donnees.filter((d) => d.mois <= moisActuel).map((d) => ({ mois: MOIS_COURTS[d.mois - 1], total: Number(d.total) }));
  const total = lignes.reduce((s, l) => s + l.total, 0);
  return (
    <PanneauGraphe
      titre={`Commissions ${annee}`}
      sousTitre={`${fcfa(total)} — revenus de l'agence`}
      lignes={lignes}
      cle="mois"
      actions={<Link to="/caisse" className="petit-texte">Caisse</Link>}
      colonnes={[{ libelle: "Mois", cle: "mois" }, { libelle: "Commissions", cle: "total", type: "montant" }]}
    >
      <ResponsiveContainer>
        <AreaChart data={lignes} margin={{ left: -8, right: 12, top: 8 }}>
          <defs>
            <linearGradient id="degrade-commissions" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="var(--viz-1)" stopOpacity={0.28} />
              <stop offset="100%" stopColor="var(--viz-1)" stopOpacity={0.02} />
            </linearGradient>
          </defs>
          {GRILLE}
          <XAxis dataKey="mois" {...AXE} />
          <YAxis tickFormatter={compact} {...AXE} width={52} />
          <Tooltip cursor={{ stroke: "var(--ligne-forte)", strokeWidth: 1 }} content={<InfoBulle />} />
          <Area dataKey="total" name="Commissions" type="monotone" stroke="var(--viz-1)" strokeWidth={2} fill="url(#degrade-commissions)" dot={false} activeDot={{ r: 5, stroke: "var(--surface)", strokeWidth: 2 }} />
        </AreaChart>
      </ResponsiveContainer>
    </PanneauGraphe>
  );
}

/* ─── Classement horizontal (modes de paiement, maisons qui rapportent le plus) ─── */
export function GrapheClassement({ titre, sousTitre, donnees, lien, libelleLien, colonneLibelle }) {
  const lignes = donnees.map((d) => ({ ...d, total: Number(d.total) }));
  const hauteur = Math.max(140, lignes.length * 42 + 20);
  return (
    <PanneauGraphe
      titre={titre}
      sousTitre={sousTitre}
      lignes={lignes}
      cle="libelle"
      hauteur={hauteur}
      actions={lien && <Link to={lien} className="petit-texte">{libelleLien}</Link>}
      colonnes={[{ libelle: colonneLibelle, cle: "libelle" }, { libelle: "Montant", cle: "total", type: "montant" }]}
      vide={lignes.length ? null : "Pas encore de données"}
    >
      <ResponsiveContainer>
        <BarChart data={lignes} layout="vertical" margin={{ left: 0, right: 56, top: 4 }} barCategoryGap="30%">
          <XAxis type="number" hide />
          <YAxis type="category" dataKey="libelle" {...AXE} width={150} tickFormatter={(v) => (v.length > 22 ? `${v.slice(0, 21)}…` : v)} />
          <Tooltip cursor={{ fill: "var(--surface-2)" }} content={<InfoBulle />} />
          <Bar dataKey="total" name="Montant" fill="var(--viz-1)" radius={[0, 4, 4, 0]} maxBarSize={20}>
            <LabelList dataKey="total" position="right" formatter={compact} style={ETIQUETTE} />
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </PanneauGraphe>
  );
}
