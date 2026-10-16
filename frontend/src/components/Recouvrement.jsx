import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { CalendarRange, Mail, Printer } from "lucide-react";
import { api } from "../lib/api";
import { dateFr, fcfa, isoDate } from "../lib/format";
import { Badge, Bouton, FenetreFormulaire, LienBouton, Saisie, useToast } from "./ui";

const STATUTS = { honoree: ["ok", "Honorée"], en_retard: ["alerte", "En retard"], a_venir: ["", "À venir"] };
export const LIBELLES_NIVEAU = { 1: "Rappel amiable", 2: "Relance", 3: "Mise en demeure" };

/** Bouton « Relancer » : crée la relance du niveau suivant puis ouvre la lettre à imprimer. */
export function BoutonRelancer({ contratId, niveauActuel, taille = "petit" }) {
  const nav = useNavigate();
  const toast = useToast();
  const [envoi, setEnvoi] = useState(false);
  const suivant = Math.min((niveauActuel || 0) + 1, 3);
  return (
    <Bouton taille={taille} icone={Mail} chargement={envoi} variante={suivant === 3 ? "danger" : ""} onClick={async () => {
      setEnvoi(true);
      try {
        const r = await api.post(`/contrats/${contratId}/relances/`);
        nav(`/impression/relance/${contratId}/${r.relances[0].id}`);
      } catch (e) {
        toast.erreur(e.message);
        setEnvoi(false);
      }
    }}>{LIBELLES_NIVEAU[suivant]}</Bouton>
  );
}

/** Création d'un plan d'apurement : la dette actuelle est répartie en mensualités égales. */
export function FormPlanApurement({ ouvert, onFermer, contratId, dette }) {
  const nav = useNavigate();
  const premier = (() => { const d = new Date(); d.setMonth(d.getMonth() + 1, 5); return isoDate(d); })();
  const [f, setF] = useState({ nb_echeances: 3, premiere_echeance: premier, commentaire: "" });
  const n = Number(f.nb_echeances) || 0;
  return (
    <FenetreFormulaire titre="Plan d'apurement" ouvert={ouvert} onFermer={onFermer} libelleValider="Créer et imprimer l'accord" icone={CalendarRange}
      onValider={async () => {
        const r = await api.post(`/contrats/${contratId}/plan-apurement/`, f);
        nav(`/impression/plan-apurement/${contratId}/${r.plans[0].id}`);
      }}>
      <div className="formulaire">
        <Saisie libelle="Nombre de mensualités" type="number" min="2" max="24" requis valeur={f.nb_echeances} onChange={(nb_echeances) => setF({ ...f, nb_echeances })}
          aide={n >= 2 ? `${fcfa(dette)} répartis en ${n} mensualités d'environ ${fcfa(Math.round(dette / n))}, en plus du loyer courant.` : "Entre 2 et 24."} />
        <Saisie libelle="Première mensualité le" type="date" requis valeur={f.premiere_echeance} onChange={(premiere_echeance) => setF({ ...f, premiere_echeance })} />
        <Saisie libelle="Commentaire" plein valeur={f.commentaire} onChange={(commentaire) => setF({ ...f, commentaire })} placeholder="Ex. : difficultés passagères, accord du bailleur obtenu" />
      </div>
    </FenetreFormulaire>
  );
}

/** Suivi d'un plan : chaque mensualité honorée, en retard ou à venir. */
export function SuiviPlan({ plan, contratId }) {
  return (
    <div className="suivi-plan">
      <div className="ligne-flex">
        <b>{plan.reference}</b>
        <span className="discret">du {dateFr(plan.date_accord)} — {fcfa(plan.montant_total)}, versé {fcfa(plan.verse)}</span>
        {plan.en_retard ? <Badge ton="alerte">Échéance manquée</Badge> : Number(plan.reste) === 0 ? <Badge ton="ok">Soldé</Badge> : <Badge ton="info">En cours</Badge>}
        <span className="espace" />
        <LienBouton taille="petit" variante="fantome" icone={Printer} to={`/impression/plan-apurement/${contratId}/${plan.id}`}>Accord</LienBouton>
      </div>
      <div className="echeances-plan">
        {plan.echeances.map((e, i) => <div key={i} className={`echeance-plan ${e.statut}`}><span>{dateFr(e.date)}</span><b>{fcfa(e.montant)}</b><Badge ton={STATUTS[e.statut][0]}>{STATUTS[e.statut][1]}</Badge></div>)}
      </div>
    </div>
  );
}
