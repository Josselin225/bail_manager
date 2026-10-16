import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { CalendarRange, HandCoins } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr, fcfa, nombre } from "../../lib/format";
import { Badge, Bouton, Chargement, EntetePage, Panneau, Tableau, Vide } from "../../components/ui";
import { BoutonRelancer, FormPlanApurement, LIBELLES_NIVEAU } from "../../components/Recouvrement";

/** Tous les locataires en retard : dette, ancienneté, relances envoyées et plans d'apurement. */
export default function Recouvrement() {
  const { data: lignes, isLoading, refetch } = useQuery({ queryKey: ["recouvrement"], queryFn: () => api.get("/contrats/recouvrement/") });
  const [plan, setPlan] = useState(null);
  if (isLoading) return <Chargement />;
  const total = lignes.reduce((s, l) => s + Number(l.montant), 0);
  return (
    <>
      <EntetePage titre="Recouvrement" soustitre="Loyers impayés, relances graduées (rappel, relance, mise en demeure) et plans d'apurement." />
      <div className="registre">
        <div className={total ? "alerte" : "bon"}><div className="libelle">Total impayé</div><div className="valeur">{nombre(total)}<small>FCFA</small></div></div>
        <div><div className="libelle">Locataires en retard</div><div className="valeur">{lignes.length}</div></div>
        <div><div className="libelle">Mises en demeure envoyées</div><div className="valeur">{lignes.filter((l) => l.derniere_relance?.niveau === 3).length}</div></div>
        <div className={lignes.some((l) => l.plan?.en_retard) ? "alerte" : ""}><div className="libelle">Plans d'apurement</div><div className="valeur">{lignes.filter((l) => l.plan).length}</div><div className="note">{lignes.filter((l) => l.plan?.en_retard).length} avec échéance manquée</div></div>
      </div>
      <Panneau serre>
        <Tableau
          lignes={lignes}
          cle="contrat"
          vide={<Vide icone={HandCoins} titre="Aucun impayé" texte="Tous les locataires sont à jour de leurs loyers." />}
          colonnes={[
            { libelle: "Locataire", rendu: (l) => <Link to={`/contrats/${l.contrat}`}><div className="principal-cellule">{l.locataire}</div><div className="sous-cellule">{l.maison} — {l.telephone}</div></Link> },
            { libelle: "Retard", rendu: (l) => <><div className="principal-cellule">{l.nb_mois} mois</div><div className="sous-cellule">depuis {l.plus_ancien}</div></> },
            { libelle: "Dernière relance", rendu: (l) => (l.derniere_relance ? <><Badge ton={l.derniere_relance.niveau === 3 ? "alerte" : "attention"}>{LIBELLES_NIVEAU[l.derniere_relance.niveau]}</Badge><div className="sous-cellule">le {dateFr(l.derniere_relance.date)}</div></> : <span className="discret">Aucune</span>) },
            { libelle: "Plan", rendu: (l) => (l.plan ? <Badge ton={l.plan.en_retard ? "alerte" : "info"}>{l.plan.en_retard ? "Échéance manquée" : `Reste ${fcfa(l.plan.reste)}`}</Badge> : <span className="discret">—</span>) },
            { libelle: "Montant dû", cle: "montant", type: "montant" },
            { libelle: "", rendu: (l) => <div className="actions-cellule"><BoutonRelancer contratId={l.contrat} niveauActuel={l.derniere_relance?.niveau} /><Bouton taille="petit" variante="fantome" icone={CalendarRange} onClick={() => setPlan(l)}>Plan</Bouton></div> },
          ]}
        />
      </Panneau>
      {plan && <FormPlanApurement ouvert contratId={plan.contrat} dette={Number(plan.montant)} onFermer={() => (setPlan(null), refetch())} />}
    </>
  );
}
