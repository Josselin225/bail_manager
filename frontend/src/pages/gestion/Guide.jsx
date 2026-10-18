import { useEffect, useMemo, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { BookOpen, ChevronLeft, ChevronRight, CircleHelp, Lightbulb, Maximize2, Printer, Search, ShieldCheck } from "lucide-react";
import { Bouton, EntetePage } from "../../components/ui";
import Visionneuse from "../../components/Visionneuse";
import { QUESTIONS, SECTIONS } from "./guideContenu";

const sansAccents = (t) => t.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
const texteFiche = (f) => sansAccents([f.titre, f.intro, f.legende, ...(f.etapes || []), ...(f.astuces || []), ...(f.regles || [])].join(" "));

function Fiche({ f, onZoom }) {
  return (
    <article id={f.id} className="fiche-guide">
      <h2>{f.titre}</h2>
      {f.intro && <p className="intro-fiche">{f.intro}</p>}
      <div className={`corps-fiche ${f.image ? "" : "sans-image"}`}>
        {f.image && (
          <figure className="capture-guide">
            <button type="button" onClick={() => onZoom(f)} aria-label={`Agrandir la capture : ${f.legende}`}>
              <img src={`/guide/${f.image}`} alt={f.legende} loading="lazy" />
              <span className="zoom-capture"><Maximize2 size={14} /> Agrandir</span>
            </button>
            <figcaption>{f.legende}</figcaption>
          </figure>
        )}
        {f.etapes?.length > 0 && (
          <ol className="etapes-guide">
            {f.etapes.map((e, i) => <li key={i}><span className="numero-etape" aria-hidden="true">{i + 1}</span><span>{e}</span></li>)}
          </ol>
        )}
      </div>
      {f.astuces?.length > 0 && (
        <div className="encadre-guide astuce">
          <div className="titre-encadre"><Lightbulb size={16} /> Bon à savoir</div>
          <ul>{f.astuces.map((a, i) => <li key={i}>{a}</li>)}</ul>
        </div>
      )}
      {f.regles?.length > 0 && (
        <div className="encadre-guide regle">
          <div className="titre-encadre"><ShieldCheck size={16} /> Règles appliquées par le logiciel</div>
          <ul>{f.regles.map((r, i) => <li key={i}>{r}</li>)}</ul>
        </div>
      )}
    </article>
  );
}

/** Guide utilisateur illustré : sommaire, recherche, pas-à-pas numérotés comme les pastilles des captures. */
export default function Guide() {
  const { hash } = useLocation();
  const nav = useNavigate();
  const [actif, setActif] = useState(() => SECTIONS.find((s) => s.fiches.some((f) => `#${f.id}` === hash))?.id || SECTIONS[0].id);
  const [recherche, setRecherche] = useState("");
  const [zoom, setZoom] = useState(null);
  const [tout, setTout] = useState(false);

  const resultats = useMemo(() => {
    const q = sansAccents(recherche.trim());
    if (q.length < 2) return null;
    return SECTIONS.flatMap((s) => s.fiches.filter((f) => q.split(/\s+/).every((m) => texteFiche(f).includes(m))).map((f) => ({ ...f, section: s })));
  }, [recherche]);

  useEffect(() => {
    if (!hash) return;
    const t = setTimeout(() => document.getElementById(hash.slice(1))?.scrollIntoView({ behavior: "smooth", block: "start" }), 80);
    return () => clearTimeout(t);
  }, [hash, actif]);

  // Impression de tout le guide : toutes les parties s'affichent, puis on revient à la partie en cours une fois l'impression finie
  const imprimer = () => {
    setTout(true);
    window.addEventListener("afterprint", () => setTout(false), { once: true });
    setTimeout(() => window.print(), 500);
  };
  const ouvrir = (s, f) => {
    setRecherche("");
    setActif(s.id);
    nav(f ? `#${f.id}` : "#", { replace: true });
    if (!f) window.scrollTo({ top: 0, behavior: "smooth" });
  };
  const index = SECTIONS.findIndex((s) => s.id === actif);
  const section = SECTIONS[index] || SECTIONS[0];
  const affichees = tout ? SECTIONS : [section];
  const images = (tout ? SECTIONS.flatMap((s) => s.fiches) : section.fiches).filter((f) => f.image);

  return (
    <>
      <EntetePage titre="Guide d'utilisation" soustitre="Chaque écran expliqué pas à pas, avec ses captures : les pastilles rouges correspondent aux étapes numérotées.">
        <Bouton icone={Printer} onClick={imprimer} className="non-imprime">Imprimer le guide</Bouton>
      </EntetePage>
      <div className="guide">
        <aside className="sommaire-guide non-imprime">
          <label className="recherche-guide">
            <Search size={16} />
            <input value={recherche} onChange={(e) => setRecherche(e.target.value)} placeholder="Chercher dans le guide…" aria-label="Chercher dans le guide" />
          </label>
          <nav aria-label="Sommaire du guide">
            {SECTIONS.map((s, i) => (
              <div key={s.id} className={`groupe-sommaire ${s.id === actif && !resultats ? "actif" : ""}`}>
                <button type="button" onClick={() => ouvrir(s)}><span className="num-section">{i + 1}</span>{s.titre}</button>
                {s.id === actif && !resultats && (
                  <ul>{s.fiches.map((f) => <li key={f.id}><a href={`#${f.id}`} onClick={(e) => { e.preventDefault(); ouvrir(s, f); }} className={hash === `#${f.id}` ? "courant" : ""}>{f.titre}</a></li>)}</ul>
                )}
              </div>
            ))}
            <div className={`groupe-sommaire ${actif === "faq" && !resultats ? "actif" : ""}`}>
              <button type="button" onClick={() => (setRecherche(""), setActif("faq"), window.scrollTo({ top: 0 }))}><span className="num-section"><CircleHelp size={13} /></span>Questions fréquentes</button>
            </div>
          </nav>
        </aside>

        <div className="contenu-guide">
          {resultats ? (
            <section className="section-guide">
              <h1 className="titre-section">{resultats.length} résultat{resultats.length > 1 ? "s" : ""} pour « {recherche} »</h1>
              {resultats.length === 0 && <p className="discret">Aucune fiche ne correspond. Essayez un autre mot (encaisser, caution, mandat, relance, licence…).</p>}
              <div className="resultats-guide">
                {resultats.map((f) => (
                  <button key={f.id} type="button" className="resultat-guide" onClick={() => ouvrir(f.section, f)}>
                    {f.image && <img src={`/guide/${f.image}`} alt="" loading="lazy" />}
                    <span><span className="petit-texte discret">{f.section.titre}</span><b>{f.titre}</b><span className="petit-texte">{f.intro || f.legende}</span></span>
                  </button>
                ))}
              </div>
            </section>
          ) : actif === "faq" && !tout ? (
            <section className="section-guide">
              <h1 className="titre-section">Questions fréquentes</h1>
              <div className="faq-guide">
                {QUESTIONS.map(([q, r]) => <details key={q}><summary>{q}</summary><p>{r}</p></details>)}
              </div>
              <p className="petit-texte discret">Une autre question ? <Link to="/a-propos">Contactez le support</Link>.</p>
            </section>
          ) : (
            <>
              {affichees.map((s) => (
                <section key={s.id} className="section-guide">
                  <div className="bandeau-section">
                    <BookOpen size={22} />
                    <div>
                      <span className="sur-titre-section">Partie {SECTIONS.indexOf(s) + 1} sur {SECTIONS.length}</span>
                      <h1 className="titre-section">{s.titre}</h1>
                      <p>{s.resume}</p>
                    </div>
                  </div>
                  {s.fiches.map((f) => <Fiche key={f.id} f={f} onZoom={setZoom} />)}
                </section>
              ))}
              {!tout && (
                <div className="navigation-guide non-imprime">
                  {index > 0 ? <Bouton icone={ChevronLeft} onClick={() => ouvrir(SECTIONS[index - 1])}>{SECTIONS[index - 1].titre}</Bouton> : <span />}
                  {index < SECTIONS.length - 1 && <Bouton variante="primaire" onClick={() => ouvrir(SECTIONS[index + 1])}>{SECTIONS[index + 1].titre} <ChevronRight size={16} /></Bouton>}
                </div>
              )}
            </>
          )}
        </div>
      </div>
      {zoom && (
        <Visionneuse
          photos={images.map((f) => `/guide/${f.image}`)}
          depart={Math.max(0, images.findIndex((f) => f.id === zoom.id))}
          titre={zoom.titre}
          onFermer={() => setZoom(null)}
        />
      )}
    </>
  );
}
