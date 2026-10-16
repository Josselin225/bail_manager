import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Download, Printer, Wallet } from "lucide-react";
import { api, exporterExcel } from "../../lib/api";
import { compact, dateHeure, fcfa, nombre } from "../../lib/format";
import { useListe } from "../../lib/hooks";
import { Bouton, Combo, EntetePage, LienBouton, Montant, Pagination, Panneau, Saisie, Segments, Tableau, Vide } from "../../components/ui";
import { FormVersement } from "./FicheBailleur";

const colonnes = [
  { cle: "date_operation", libelle: "Date", rendu: (o) => <span className="nowrap">{dateHeure(o.date_operation)}</span>, exporter: (o) => dateHeure(o.date_operation) },
  { cle: "bailleur_nom", libelle: "Bailleur", rendu: (o) => <Link to={`/bailleurs/${o.bailleur}`}>{o.bailleur_nom}</Link> },
  { cle: "type_libelle", libelle: "Opération", rendu: (o) => <><div className="principal-cellule">{o.type_libelle}</div><div className="sous-cellule">{o.commentaire}</div></> },
  { cle: "effectue_par", libelle: "Par" },
  { cle: "montant", libelle: "Montant", type: "montant", rendu: (o) => <Montant v={o.montant} signe /> },
];

export default function ComptesBailleurs() {
  const [verser, setVerser] = useState(null);
  const { data: soldes = [], refetch: rechargerSoldes } = useQuery({ queryKey: ["soldes-bailleurs"], queryFn: () => api.get("/compte-bailleurs/soldes/") });
  const liste = useListe("/compte-bailleurs/", { bailleur: "", type_operation: "", du: "", au: "" });
  const { page, ...f } = liste.filtres;
  const { data: syn } = useQuery({ queryKey: ["synthese-bailleurs", f], queryFn: () => api.get("/compte-bailleurs/synthese/", f) });
  const choisi = soldes.find((b) => String(b.id) === String(f.bailleur));
  return (
    <>
      <EntetePage titre="Comptes bailleurs" soustitre="Chaque loyer encaissé crédite le bailleur (net de commission) ; chaque versement le débite.">
        <Bouton icone={Download} onClick={async () => exporterExcel("Compte courant bailleurs", colonnes, (await api.get("/compte-bailleurs/", { ...f, taille: 5000 })).results)}>Excel</Bouton>
        {choisi && <LienBouton icone={Printer} to={`/impression/releve-bailleur/${choisi.id}?du=${f.du}&au=${f.au}`} target="_blank">Relevé</LienBouton>}
        <Bouton variante="primaire" icone={Wallet} disabled={!choisi || Number(choisi.solde) <= 0} onClick={() => setVerser(choisi)}>Verser au bailleur</Bouton>
      </EntetePage>
      <div className="barre-filtres">
        <div style={{ minWidth: 260 }}>
          <Combo valeur={f.bailleur} onChange={(bailleur) => liste.changer({ bailleur })} options={[{ valeur: "", libelle: "Tous les bailleurs" }, ...soldes.map((b) => ({ valeur: b.id, libelle: b.nom, sous: `Solde ${fcfa(b.solde)}` }))]} placeholder="Tous les bailleurs" />
        </div>
        <Segments valeur={f.type_operation} onChange={(type_operation) => liste.changer({ type_operation })} options={[{ valeur: "", libelle: "Toutes" }, { valeur: "loyer_encaisse", libelle: "Crédits" }, { valeur: "versement_bailleur", libelle: "Versements" }]} />
        <Saisie type="date" valeur={f.du} onChange={(du) => liste.changer({ du })} aria-label="Du" />
        <Saisie type="date" valeur={f.au} onChange={(au) => liste.changer({ au })} aria-label="Au" />
      </div>
      {syn && (
        <div className="grille g-2-1" style={{ marginBottom: 16 }}>
          <div className="registre">
            <div><div className="libelle">Crédits (loyers nets)</div><div className="valeur">{nombre(syn.credits)}<small>FCFA</small></div></div>
            <div><div className="libelle">Versés aux bailleurs</div><div className="valeur">{nombre(syn.retraits)}<small>FCFA</small></div></div>
            <div><div className="libelle">{choisi ? `Solde de ${choisi.nom}` : "Total dû aux bailleurs"}</div><div className="valeur">{nombre(syn.solde_global)}<small>FCFA</small></div><div className="note">{syn.nb_operations} opération(s) affichée(s)</div></div>
          </div>
          <Panneau>
            <div style={{ height: 110 }}>
              <ResponsiveContainer>
                <BarChart data={syn.par_mois} margin={{ left: -20, right: 0, top: 0, bottom: 0 }}>
                  <CartesianGrid vertical={false} stroke="var(--ligne)" />
                  <XAxis dataKey="mois" tick={{ fontSize: 10, fill: "var(--discret)" }} tickFormatter={(m) => m.slice(5)} axisLine={false} tickLine={false} />
                  <YAxis tickFormatter={compact} tick={{ fontSize: 10, fill: "var(--discret)" }} axisLine={false} tickLine={false} />
                  <Tooltip formatter={(v) => fcfa(v)} contentStyle={{ background: "var(--surface)", border: "1px solid var(--ligne)" }} />
                  <Bar dataKey="entrees" name="Crédits" fill="var(--ok)" />
                  <Bar dataKey="sorties" name="Versements" fill="var(--marine-3)" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Panneau>
        </div>
      )}
      <Panneau serre>
        <Tableau colonnes={colonnes} lignes={liste.lignes} chargement={liste.isLoading} vide={<Vide icone={Wallet} titre="Aucune opération" />} />
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
      <FormVersement ouvert={!!verser} bailleur={verser} solde={verser?.solde} onFermer={() => (setVerser(null), liste.refetch(), rechargerSoldes())} />
    </>
  );
}
