import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { BarChart3, CalendarDays, CalendarRange, FileWarning, Home, Printer, Sun, Wallet } from "lucide-react";
import { api } from "../../lib/api";
import { MOIS, isoDate } from "../../lib/format";
import { Combo, EntetePage, Liste, Panneau, Saisie } from "../../components/ui";

function Carte({ icone: Icone, titre, texte, lien, children }) {
  return (
    <Panneau>
      <div className="ligne-flex" style={{ alignItems: "flex-start", flexWrap: "nowrap" }}>
        <span className="avatar"><Icone size={18} /></span>
        <div style={{ flex: 1 }}>
          <h3>{titre}</h3>
          <p className="discret" style={{ margin: "2px 0 12px" }}>{texte}</p>
          {children && <div className="formulaire" style={{ marginBottom: 12 }}>{children}</div>}
          <a className="btn primaire petit" href={lien} target="_blank" rel="noreferrer"><Printer size={15} /> Ouvrir le rapport</a>
        </div>
      </div>
    </Panneau>
  );
}

export default function Rapports() {
  const auj = new Date();
  const [jour, setJour] = useState(isoDate());
  const [mois, setMois] = useState({ mois: auj.getMonth() + 1, annee: auj.getFullYear() });
  const [annee, setAnnee] = useState(auj.getFullYear());
  const [periode, setPeriode] = useState({ du: isoDate(new Date(auj.getFullYear(), auj.getMonth(), 1)), au: isoDate() });
  const [releve, setReleve] = useState({ bailleur: "", du: isoDate(new Date(auj.getFullYear(), 0, 1)), au: isoDate() });
  const { data: bailleurs = [] } = useQuery({ queryKey: ["bailleurs-options"], queryFn: () => api.get("/bailleurs/options/") });
  const annees = Array.from({ length: 6 }, (_, i) => auj.getFullYear() - i);

  return (
    <>
      <EntetePage titre="Rapports" soustitre="Chaque rapport s'ouvre dans un onglet, prêt à imprimer, à enregistrer en PDF ou à exporter vers Excel." />
      <div className="grille g2">
        <Carte icone={Sun} titre="Rapport journalier" texte="Encaissements, mouvements de caisse et versements d'une journée." lien={`/impression/rapport/journalier?date=${jour}`}>
          <Saisie libelle="Date" type="date" valeur={jour} onChange={setJour} />
        </Carte>
        <Carte icone={CalendarDays} titre="Rapport mensuel" texte="Activité du mois et taux de recouvrement des loyers attendus." lien={`/impression/rapport/mensuel?mois=${mois.mois}&annee=${mois.annee}`}>
          <Liste libelle="Mois" valeur={mois.mois} onChange={(m) => setMois({ ...mois, mois: m })} options={MOIS.map((m, i) => ({ valeur: i + 1, libelle: m }))} />
          <Liste libelle="Année" valeur={mois.annee} onChange={(a) => setMois({ ...mois, annee: a })} options={annees.map(String)} />
        </Carte>
        <Carte icone={BarChart3} titre="Rapport annuel" texte="Synthèse mois par mois, par bailleur et mouvements des contrats." lien={`/impression/rapport/annuel?annee=${annee}`}>
          <Liste libelle="Année" valeur={annee} onChange={setAnnee} options={annees.map(String)} />
        </Carte>
        <Carte icone={CalendarRange} titre="Rapport sur une période" texte="Même contenu que le mensuel, entre deux dates de votre choix." lien={`/impression/rapport/periode?du=${periode.du}&au=${periode.au}`}>
          <Saisie libelle="Du" type="date" valeur={periode.du} onChange={(du) => setPeriode({ ...periode, du })} />
          <Saisie libelle="Au" type="date" valeur={periode.au} onChange={(au) => setPeriode({ ...periode, au })} />
        </Carte>
        <Carte icone={Home} titre="État d'occupation" texte="Tous les biens, occupés ou libres, avec locataire et loyer." lien="/impression/rapport/occupation" />
        <Carte icone={FileWarning} titre="Liste des impayés" texte="Contrats en retard, mois concernés, montants dus et caution disponible." lien="/impression/rapport/impayes" />
        <Carte icone={Wallet} titre="Relevé de compte bailleur" texte="Crédits, versements et solde d'un bailleur sur une période." lien={releve.bailleur ? `/impression/releve-bailleur/${releve.bailleur}?du=${releve.du}&au=${releve.au}` : "#"}>
          <Combo libelle="Bailleur" plein valeur={releve.bailleur} onChange={(bailleur) => setReleve({ ...releve, bailleur })} options={bailleurs.map((b) => ({ valeur: b.id, libelle: b.nom, sous: b.code_bailleur }))} />
          <Saisie libelle="Du" type="date" valeur={releve.du} onChange={(du) => setReleve({ ...releve, du })} />
          <Saisie libelle="Au" type="date" valeur={releve.au} onChange={(au) => setReleve({ ...releve, au })} />
        </Carte>
      </div>
    </>
  );
}
