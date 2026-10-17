import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Bar, BarChart, CartesianGrid, LabelList, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { api } from "../../lib/api";
import { compact, fcfa, isoDate, nombre } from "../../lib/format";
import { useSession } from "../../lib/session";
import { Chargement, EntetePage, Panneau, Saisie, Segments, Tableau } from "../../components/ui";
import { PanneauGraphe } from "../../components/Graphes";

function periode(cle) {
  const j = new Date();
  if (cle === "mois") return { du: isoDate(new Date(j.getFullYear(), j.getMonth(), 1)), au: isoDate(j) };
  if (cle === "precedent") return { du: isoDate(new Date(j.getFullYear(), j.getMonth() - 1, 1)), au: isoDate(new Date(j.getFullYear(), j.getMonth(), 0)) };
  return { du: isoDate(new Date(j.getFullYear(), 0, 1)), au: isoDate(j) };
}

const COLONNES = [
  { libelle: "Membre de l'équipe", rendu: (l) => <><div className="principal-cellule">{l.nom}</div><div className="sous-cellule">{l.role}</div></> },
  { libelle: "Encaissements", rendu: (l) => <><div className="principal-cellule">{fcfa(l.montant)}</div><div className="sous-cellule">{l.encaissements} reçu{l.encaissements > 1 ? "s" : ""}</div></>, droite: true },
  { libelle: "Baux signés", cle: "baux", droite: true },
  { libelle: "Relances", cle: "relances", droite: true },
  { libelle: "Visites faites", rendu: (l) => <><div className="principal-cellule">{l.visites}</div><div className="sous-cellule">{l.visiteurs_interesses} intéressé{l.visiteurs_interesses > 1 ? "s" : ""}{l.visites_prevues ? ` · ${l.visites_prevues} à venir` : ""}</div></>, droite: true },
  { libelle: "États des lieux", cle: "etats_des_lieux", droite: true },
];

/** Tableau de bord par agent : qui encaisse, signe, relance, fait visiter et établit les états des lieux. */
export default function Equipe() {
  const { estAdmin } = useSession();
  const [cle, setCle] = useState("mois");
  const [p, setP] = useState(periode("mois"));
  const { data: r, isLoading } = useQuery({ queryKey: ["activite-agents", p], queryFn: () => api.get("/rapports/activite-agents/", p) });
  const choisir = (c) => (setCle(c), c !== "autre" && setP(periode(c)));
  const lignes = (r?.lignes || []).map((l) => ({ ...l, montant: Number(l.montant) }));
  const actifs = lignes.filter((l) => l.montant > 0).sort((a, b) => b.montant - a.montant);
  return (
    <>
      <EntetePage titre={estAdmin ? "Activité de l'équipe" : "Mon activité"} soustitre="Encaissements, baux, relances, visites et états des lieux de chaque membre de l'agence." />
      <div className="barre-filtres">
        <Segments valeur={cle} onChange={choisir} options={[{ valeur: "mois", libelle: "Ce mois" }, { valeur: "precedent", libelle: "Mois dernier" }, { valeur: "annee", libelle: "Cette année" }, { valeur: "autre", libelle: "Période…" }]} />
        {cle === "autre" && (
          <div className="ligne-flex">
            <Saisie libelle="Du" type="date" valeur={p.du} onChange={(du) => setP({ ...p, du })} />
            <Saisie libelle="Au" type="date" valeur={p.au} onChange={(au) => setP({ ...p, au })} />
          </div>
        )}
      </div>
      {isLoading || !r ? <Chargement /> : (
        <div className="pile">
          <div className="registre">
            <div><div className="libelle">Encaissé par l'équipe</div><div className="valeur">{nombre(r.totaux.montant)}<small>FCFA</small></div><div className="note">{r.totaux.encaissements} reçus{Number(r.automatiques.montant) > 0 && ` · ${fcfa(r.automatiques.montant)} en ligne ou automatiques`}</div></div>
            <div><div className="libelle">Baux signés</div><div className="valeur">{r.totaux.baux}</div></div>
            <div><div className="libelle">Visites faites</div><div className="valeur">{r.totaux.visites}</div></div>
            <div><div className="libelle">Relances envoyées</div><div className="valeur">{r.totaux.relances}</div><div className="note">{r.totaux.etats_des_lieux} état{r.totaux.etats_des_lieux > 1 ? "s" : ""} des lieux</div></div>
          </div>
          {estAdmin && lignes.length > 1 && (
            <PanneauGraphe titre="Encaissements par membre de l'équipe" sousTitre="Montant des loyers enregistrés sur la période" lignes={actifs} cle="id"
              colonnes={[{ libelle: "Membre", cle: "nom" }, { libelle: "Encaissé", cle: "montant", type: "montant" }, { libelle: "Reçus", cle: "encaissements", droite: true }]}
              hauteur={Math.max(140, actifs.length * 46 + 30)} vide={actifs.length ? null : "Aucun encaissement sur la période"}>
              <ResponsiveContainer>
                <BarChart data={actifs} layout="vertical" margin={{ left: 8, right: 64, top: 4, bottom: 4 }} barCategoryGap="30%">
                  <CartesianGrid horizontal={false} stroke="var(--ligne)" />
                  <XAxis type="number" tickFormatter={compact} tickLine={false} axisLine={false} tick={{ fill: "var(--discret)", fontSize: 12 }} />
                  <YAxis type="category" dataKey="nom" width={130} tickLine={false} axisLine={false} tick={{ fill: "var(--texte-2)", fontSize: 12.5 }} />
                  <Tooltip cursor={{ fill: "var(--surface-2)" }} formatter={(v) => [fcfa(v), "Encaissé"]} contentStyle={{ borderRadius: 10, border: "1px solid var(--ligne)", background: "var(--surface)", color: "var(--texte)" }} />
                  <Bar dataKey="montant" name="Encaissé" fill="var(--viz-1)" radius={[0, 4, 4, 0]} maxBarSize={22}>
                    <LabelList dataKey="montant" position="right" formatter={compact} style={{ fill: "var(--texte-2)", fontSize: 11.5, fontWeight: 600 }} />
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </PanneauGraphe>
          )}
          <Panneau serre>
            <Tableau lignes={lignes} colonnes={COLONNES} />
          </Panneau>
        </div>
      )}
    </>
  );
}
