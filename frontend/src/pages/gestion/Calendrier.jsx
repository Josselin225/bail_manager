import { useMemo } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { CalendarDays, ChevronLeft, ChevronRight } from "lucide-react";
import { api } from "../../lib/api";
import { MOIS, isoDate } from "../../lib/format";
import { Bouton, EntetePage, Panneau, Segments } from "../../components/ui";

const JOURS = ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"];
const VUES = [{ valeur: "jour", libelle: "Jour" }, { valeur: "mois", libelle: "Mois" }, { valeur: "annee", libelle: "Année" }];
const LEGENDE = [
  ["var(--ok)", "Loyer payé"], ["var(--info)", "À venir"], ["var(--signal)", "En retard"],
  ["var(--ambre)", "Visite / partiel"], ["var(--violet)", "Fin de bail ou mandat"],
];
// Ordre d'importance : la couleur d'un jour en vue année est celle de son événement le plus important
const PRIORITE = ["en_retard", "partiel_retard", "partiel", "visite", "fin_contrat", "fin_mandat", "a_venir", "paye"];

const cle = (e) => (e.type === "loyer" ? e.statut : e.type);
const classeEvt = (e) => (e.type === "loyer" ? `loyer-${e.statut}` : e.type);
const versDate = (iso) => new Date(`${iso}T00:00`);
const plusJours = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

/** Les 42 jours (6 semaines, lundi en tête) affichés pour le mois de « d ». */
function grilleMois(d) {
  const premier = new Date(d.getFullYear(), d.getMonth(), 1);
  const debut = plusJours(premier, -((premier.getDay() + 6) % 7));
  return Array.from({ length: 42 }, (_, i) => plusJours(debut, i));
}

function Legende() {
  return <div className="legende">{LEGENDE.map(([c, l]) => <span key={l}><i style={{ background: c }} />{l}</span>)}</div>;
}

function ListeEvenements({ liste }) {
  if (!liste.length) return <p className="discret" style={{ margin: 0 }}>Aucun événement ce jour-là.</p>;
  return (
    <div className="liste-evts">
      {liste.map((e) => (
        <Link key={e.id} to={e.lien} className={`evt-ligne ${classeEvt(e)}`}>
          <span className="gras">{e.titre}</span>
          {e.detail && <span className="discret petit-texte">{e.detail}</span>}
          <ChevronRight size={16} className="discret" />
        </Link>
      ))}
    </div>
  );
}

/* ─── Vue jour ─────────────────────────────────────────────────────── */
function VueJour({ date, parJour, choisirJour }) {
  const iso = isoDate(date);
  const liste = parJour[iso] || [];
  const comptes = [
    ["Loyers", liste.filter((e) => e.type === "loyer").length],
    ["En retard", liste.filter((e) => ["en_retard", "partiel_retard"].includes(e.statut)).length],
    ["Visites", liste.filter((e) => e.type === "visite").length],
    ["Fins de bail / mandat", liste.filter((e) => e.type.startsWith("fin_")).length],
  ];
  return (
    <div className="vue-jour">
      <Panneau titre={<h2 className="titre-jour">{date.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric" })}</h2>} actions={<Legende />}>
        <div className="resume-jour">
          {comptes.map(([l, n]) => <div key={l} className={n ? "" : "vide"}><b>{n}</b><span>{l}</span></div>)}
        </div>
        <ListeEvenements liste={liste} />
      </Panneau>
      <Panneau titre={`${MOIS[date.getMonth()]} ${date.getFullYear()}`} className="mini-mois-panneau">
        <MiniMois mois={date} parJour={parJour} choisi={iso} onJour={choisirJour} />
      </Panneau>
    </div>
  );
}

/* ─── Vue mois ─────────────────────────────────────────────────────── */
function VueMois({ date, parJour, choisi, choisirJour, ouvrirJour }) {
  const auj = isoDate();
  const jours = grilleMois(date);
  return (
    <>
      <Panneau actions={<Legende />} serre>
        <div className="calendrier">
          {JOURS.map((j) => <div key={j} className="jour-nom">{j}</div>)}
          {jours.map((d) => {
            const k = isoDate(d);
            const liste = parJour[k] || [];
            return (
              <div
                key={k}
                className={`case-jour ${d.getMonth() !== date.getMonth() ? "hors" : ""} ${k === auj ? "aujourdhui" : ""} ${k === choisi ? "choisi" : ""}`}
                onClick={(e) => !e.target.closest("a") && choisirJour(k)}
                onDoubleClick={() => ouvrirJour(k)}
              >
                <span className="num">{d.getDate()}</span>
                {liste.slice(0, 4).map((e) => (
                  <Link key={e.id} to={e.lien} className={`evt ${classeEvt(e)}`} title={`${e.titre} — ${e.detail}`}>{e.titre}</Link>
                ))}
                {liste.length > 4 && <div className="petit-texte discret">+{liste.length - 4} autre(s)</div>}
              </div>
            );
          })}
        </div>
      </Panneau>
      <Panneau
        titre={`Le ${versDate(choisi).toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" })}`}
        className="jour-detail"
        actions={<Bouton taille="petit" variante="fantome" icone={CalendarDays} onClick={() => ouvrirJour(choisi)}>Vue jour</Bouton>}
      >
        <ListeEvenements liste={parJour[choisi] || []} />
      </Panneau>
    </>
  );
}

/* ─── Vue année ────────────────────────────────────────────────────── */
function MiniMois({ mois, parJour, choisi, onJour }) {
  const auj = isoDate();
  return (
    <div className="mini-mois">
      {JOURS.map((j) => <span key={j} className="mini-nom">{j[0]}</span>)}
      {grilleMois(mois).map((d) => {
        const k = isoDate(d);
        const liste = parJour[k] || [];
        const dominant = liste.length ? PRIORITE.find((p) => liste.some((e) => cle(e) === p)) : null;
        const hors = d.getMonth() !== mois.getMonth();
        return (
          <button
            key={k}
            type="button"
            className={`mini-jour ${hors ? "hors" : ""} ${k === auj ? "aujourdhui" : ""} ${k === choisi ? "choisi" : ""} ${dominant ? `a-evt evt-${dominant}` : ""}`}
            onClick={() => onJour(k)}
            title={liste.length ? `${d.toLocaleDateString("fr-FR")} — ${liste.length} événement(s)` : undefined}
            disabled={hors}
            tabIndex={hors ? -1 : undefined}
          >
            {d.getDate()}
          </button>
        );
      })}
    </div>
  );
}

function VueAnnee({ date, parJour, ouvrirJour, ouvrirMois, evts }) {
  const annee = date.getFullYear();
  const parMois = Array.from({ length: 12 }, (_, m) => evts.filter((e) => versDate(e.date).getMonth() === m && versDate(e.date).getFullYear() === annee));
  return (
    <Panneau actions={<Legende />}>
      <div className="grille-annee">
        {MOIS.map((nom, m) => {
          const liste = parMois[m];
          const retards = liste.filter((e) => ["en_retard", "partiel_retard"].includes(e.statut)).length;
          return (
            <div key={nom} className="carte-mois">
              <button type="button" className="titre-mois" onClick={() => ouvrirMois(m)}>
                <span>{nom}</span>
                <span className="compte">{liste.length ? `${liste.length} évt.` : "—"}{retards > 0 && <b> · {retards} retard{retards > 1 ? "s" : ""}</b>}</span>
              </button>
              <MiniMois mois={new Date(annee, m, 1)} parJour={parJour} onJour={ouvrirJour} />
            </div>
          );
        })}
      </div>
    </Panneau>
  );
}

/* ─── Page ─────────────────────────────────────────────────────────── */
export default function Calendrier() {
  const [params, setParams] = useSearchParams();
  const vue = VUES.some((v) => v.valeur === params.get("vue")) ? params.get("vue") : "mois";
  const dateIso = /^\d{4}-\d{2}-\d{2}$/.test(params.get("date") || "") ? params.get("date") : isoDate();
  const date = versDate(dateIso);
  const aller = (v, d) => setParams({ vue: v, date: typeof d === "string" ? d : isoDate(d) }, { replace: true });

  // Plage chargée selon la vue
  const [du, au] = useMemo(() => {
    if (vue === "jour") return [isoDate(new Date(date.getFullYear(), date.getMonth(), 1) ), isoDate(new Date(date.getFullYear(), date.getMonth() + 1, 0))];
    if (vue === "annee") return [`${date.getFullYear()}-01-01`, `${date.getFullYear()}-12-31`];
    const g = grilleMois(date);
    return [isoDate(g[0]), isoDate(g[41])];
  }, [vue, dateIso]); // eslint-disable-line
  const { data: evts = [], isFetching } = useQuery({ queryKey: ["calendrier", du, au], queryFn: () => api.get("/calendrier/", { du, au }), placeholderData: (p) => p });
  const parJour = useMemo(() => evts.reduce((acc, e) => ((acc[e.date] = acc[e.date] || []).push(e), acc), {}), [evts]);

  const decaler = (n) => {
    if (vue === "jour") return aller(vue, plusJours(date, n));
    if (vue === "annee") return aller(vue, new Date(date.getFullYear() + n, date.getMonth(), 1));
    const d = new Date(date.getFullYear(), date.getMonth() + n, 1);
    return aller(vue, d);
  };
  const periode = vue === "jour"
    ? date.toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" })
    : vue === "annee" ? String(date.getFullYear()) : `${MOIS[date.getMonth()]} ${date.getFullYear()}`;
  const unite = { jour: "Jour", mois: "Mois", annee: "Année" }[vue];

  return (
    <>
      <EntetePage titre="Calendrier" soustitre="Échéances de loyer, visites programmées, fins de bail et de mandat.">
        <Segments options={VUES} valeur={vue} onChange={(v) => aller(v, date)} />
      </EntetePage>
      <div className="barre-calendrier">
        <Bouton icone={ChevronLeft} onClick={() => decaler(-1)} aria-label={`${unite} précédent${vue === "annee" ? "e" : ""}`} />
        <Bouton onClick={() => aller(vue, new Date())}>Aujourd'hui</Bouton>
        <Bouton icone={ChevronRight} onClick={() => decaler(1)} aria-label={`${unite} suivant${vue === "annee" ? "e" : ""}`} />
        <h2 className={`periode ${isFetching ? "charge" : ""}`}>{periode}</h2>
      </div>
      {vue === "jour" && <VueJour date={date} parJour={parJour} choisirJour={(k) => aller("jour", k)} />}
      {vue === "mois" && <VueMois date={date} parJour={parJour} choisi={dateIso} choisirJour={(k) => aller("mois", k)} ouvrirJour={(k) => aller("jour", k)} />}
      {vue === "annee" && <VueAnnee date={date} evts={evts} parJour={parJour} ouvrirJour={(k) => aller("jour", k)} ouvrirMois={(m) => aller("mois", new Date(date.getFullYear(), m, 1))} />}
    </>
  );
}
