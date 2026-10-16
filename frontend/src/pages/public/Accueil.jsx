import { useCallback, useEffect, useRef, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { ArrowRight, Banknote, Building2, CheckCircle2, ChevronLeft, ChevronRight, FileSignature, Home, Images, KeyRound, MapPin, Pause, Play, Scale, Search, ShieldCheck } from "lucide-react";
import { fcfa } from "../../lib/format";
import Site, { AppelAction, useVitrine } from "./Site";

// Disponible : aucun contrat actif sur la maison ; Occupée : un contrat actif la concerne
export function EtiquetteEtat({ occupee }) {
  return <span className={`etiquette-etat ${occupee ? "occupee" : "disponible"}`}>{occupee ? "Occupée" : "Disponible"}</span>;
}

export function CarteBien({ m }) {
  return (
    <Link to={`/nos-maisons/${m.id}`} className={`bien ${m.occupee ? "occupee" : ""}`}>
      <div className="photo">
        {m.images[0] ? <img src={m.images[0]} alt="" loading="lazy" /> : <div className="sans-photo"><Home size={38} strokeWidth={1.4} /></div>}
        {m.type_maison && <span className="etiquette-type">{m.type_maison}</span>}
        <EtiquetteEtat occupee={m.occupee} />
        {m.images.length > 1 && <span className="nb-photos"><Images size={13} /> {m.images.length}</span>}
      </div>
      <div className="infos">
        <div className="prix">{fcfa(m.loyer)} <span className="discret" style={{ fontSize: 13, fontWeight: 500 }}>/ mois</span></div>
        <div className="gras" style={{ marginTop: 2 }}>{m.designation}</div>
        {m.adresse && <div className="discret petit-texte ligne-flex" style={{ gap: 4, marginTop: 4 }}><MapPin size={13} /> {m.adresse}</div>}
      </div>
    </Link>
  );
}

const SERVICES = [
  { icone: KeyRound, titre: "Gestion locative", texte: "Recherche de locataires, baux, états des lieux, encaissement des loyers et quittances." },
  { icone: Banknote, titre: "Reversements transparents", texte: "Loyer net reversé au bailleur, commission déduite, relevé de compte disponible à tout moment." },
  { icone: Scale, titre: "Assistance juridique", texte: "Baux conformes à la loi n° 2019-576, préavis, résiliations et recouvrement des impayés." },
  { icone: ShieldCheck, titre: "Cautions sécurisées", texte: "Cautions conservées par l'agence et décomptées clairement à la sortie du locataire." },
];

function Recherche({ types }) {
  const nav = useNavigate();
  const [f, setF] = useState({ q: "", type: "", loyer_max: "" });
  const envoyer = (e) => {
    e.preventDefault();
    const p = new URLSearchParams(Object.entries(f).filter(([, v]) => v));
    nav(`/nos-maisons${p.size ? `?${p}` : ""}`);
  };
  return (
    <form className="hero-recherche" onSubmit={envoyer}>
      <label>
        <span>Où ?</span>
        <input placeholder="Quartier, commune…" value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} />
      </label>
      <label>
        <span>Type de bien</span>
        <select value={f.type} onChange={(e) => setF({ ...f, type: e.target.value })}>
          <option value="">Tous les types</option>
          {types.map((t) => <option key={t}>{t}</option>)}
        </select>
      </label>
      <label>
        <span>Budget mensuel</span>
        <select value={f.loyer_max} onChange={(e) => setF({ ...f, loyer_max: e.target.value })}>
          <option value="">Tous les loyers</option>
          {[50000, 100000, 150000, 250000, 500000].map((x) => <option key={x} value={x}>Jusqu'à {x.toLocaleString("fr-FR")} FCFA</option>)}
        </select>
      </label>
      <button className="btn primaire"><Search size={17} /> Rechercher</button>
    </form>
  );
}

// Photos libres de droits (Unsplash) — voir public/vitrine/CREDITS.txt
const DIAPOS = [
  { src: "/vitrine/maison-1.jpg", legende: "Villas avec piscine" },
  { src: "/vitrine/maison-2.jpg", legende: "Résidences contemporaines" },
  { src: "/vitrine/maison-3.jpg", legende: "Maisons avec jardin" },
  { src: "/vitrine/maison-4.jpg", legende: "Cadres de vie d'exception" },
  { src: "/vitrine/maison-5.jpg", legende: "Intérieurs lumineux et soignés" },
];
const DUREE = 6000;

function useDiaporama(n) {
  const [i, setI] = useState(0);
  const [pause, setPause] = useState(false);
  const [lecture, setLecture] = useState(() => !window.matchMedia?.("(prefers-reduced-motion: reduce)").matches);
  const aller = useCallback((k) => setI(((k % n) + n) % n), [n]);
  useEffect(() => {
    if (!lecture || pause) return undefined;
    const t = setTimeout(() => aller(i + 1), DUREE);
    return () => clearTimeout(t);
  }, [i, lecture, pause, aller]);
  return { i, aller, lecture, setLecture, pause, setPause };
}

export default function Accueil() {
  const { data } = useVitrine();
  const e = data?.entreprise || {};
  const une = data?.a_la_une || [];
  const d = useDiaporama(DIAPOS.length);
  const toucher = useRef(null);
  return (
    <Site>
      <section
        className={`vitrine-hero diaporama ${d.lecture && !d.pause ? "en-lecture" : ""}`}
        aria-roledescription="diaporama"
        onMouseEnter={() => d.setPause(true)}
        onMouseLeave={() => d.setPause(false)}
        onTouchStart={(ev) => { toucher.current = ev.touches[0].clientX; }}
        onTouchEnd={(ev) => {
          const dx = ev.changedTouches[0].clientX - (toucher.current ?? 0);
          if (Math.abs(dx) > 50 && !ev.target.closest("form")) d.aller(d.i + (dx < 0 ? 1 : -1));
        }}
        onKeyDown={(ev) => {
          if (ev.target.closest("form")) return;
          if (ev.key === "ArrowRight") d.aller(d.i + 1);
          if (ev.key === "ArrowLeft") d.aller(d.i - 1);
        }}
      >
        <div className="diapos" aria-hidden>
          {DIAPOS.map((x, k) => (
            <div key={x.src} className={`diapo ${k === d.i ? "active" : ""}`}>
              <img src={x.src} alt="" loading={k === 0 ? "eager" : "lazy"} fetchpriority={k === 0 ? "high" : "low"} decoding="async" />
            </div>
          ))}
        </div>
        <div className="contenu-hero">
          <span className="sur-titre">{e.nom || "Agence immobilière"}{e.adresse ? ` · ${e.adresse.split(",")[0]}` : ""}</span>
          <h1>Trouvez votre prochain logement, confiez-nous votre bien.</h1>
          <p>Location de maisons et appartements, gestion locative complète pour les propriétaires : nous nous occupons de tout, en toute transparence.</p>
          <Recherche types={data?.types || []} />
          <div className="hero-liens">
            <Link to="/nos-maisons?etat=disponible">{data?.nb_disponibles ? `${data.nb_disponibles} bien${data.nb_disponibles > 1 ? "s" : ""} disponible${data.nb_disponibles > 1 ? "s" : ""}` : "Voir les biens"} <ArrowRight size={15} /></Link>
            <Link to="/espace-locataire">Espace locataire <ArrowRight size={15} /></Link>
            <Link to="/espace-bailleur">Espace propriétaire <ArrowRight size={15} /></Link>
          </div>
        </div>
        <div className="diapo-controles">
          <div className="diapo-legende" aria-live="polite">
            <span className="compteur">{String(d.i + 1).padStart(2, "0")} <i>/ {String(DIAPOS.length).padStart(2, "0")}</i></span>
            <span key={d.i} className="texte">{DIAPOS[d.i].legende}</span>
          </div>
          <div className="diapo-points" role="tablist" aria-label="Choisir une photo">
            {DIAPOS.map((x, k) => (
              <button key={x.src} role="tab" aria-selected={k === d.i} aria-label={`Photo ${k + 1} : ${x.legende}`} className={k === d.i ? "actif" : ""} onClick={() => d.aller(k)}>
                <span style={{ animationDuration: `${DUREE}ms` }} />
              </button>
            ))}
          </div>
          <div className="diapo-fleches">
            <button onClick={() => d.setLecture((l) => !l)} aria-label={d.lecture ? "Mettre en pause le diaporama" : "Lancer le diaporama"}>{d.lecture ? <Pause size={16} /> : <Play size={16} />}</button>
            <button onClick={() => d.aller(d.i - 1)} aria-label="Photo précédente"><ChevronLeft size={20} /></button>
            <button onClick={() => d.aller(d.i + 1)} aria-label="Photo suivante"><ChevronRight size={20} /></button>
          </div>
        </div>
      </section>

      <section className="site-section vitrine-services">
        <div className="entete-section">
          <span className="sur-titre">Nos services</span>
          <h2>Un seul interlocuteur pour louer et faire louer</h2>
        </div>
        <div className="grille-services">
          {SERVICES.map(({ icone: I, titre, texte }) => (
            <div key={titre} className="carte-service">
              <div className="pastille"><I size={22} /></div>
              <h3>{titre}</h3>
              <p>{texte}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="site-section" style={{ background: "var(--fond)" }}>
        <div className="entete-section ligne">
          <div>
            <span className="sur-titre">À louer</span>
            <h2>Biens disponibles en ce moment</h2>
          </div>
          <Link to="/nos-maisons" className="btn">Tout le catalogue <ArrowRight size={15} /></Link>
        </div>
        {une.length ? <div className="cartes-biens">{une.map((m) => <CarteBien key={m.id} m={m} />)}</div> : <p className="discret">Aucun bien libre pour l'instant. <Link to="/contact">Laissez-nous vos coordonnées</Link>, nous vous appellerons dès qu'un logement se libère.</p>}
      </section>

      <section className="site-section">
        <div className="deux-publics">
          <div className="public proprietaire">
            <Building2 size={30} />
            <h2>Vous êtes propriétaire</h2>
            <p>Confiez-nous votre maison : nous trouvons le locataire, encaissons les loyers et vous reversons le net chaque mois.</p>
            <ul>
              {["Mandat de gestion clair, sans surprise", "Relevé de compte détaillé", "Suivi de l'entretien et des impayés"].map((t) => <li key={t}><CheckCircle2 size={17} /> {t}</li>)}
            </ul>
            <Link to="/contact" className="btn primaire">Confier un bien <ArrowRight size={15} /></Link>
          </div>
          <div className="public locataire">
            <FileSignature size={30} />
            <h2>Vous cherchez un logement</h2>
            <p>Réservez une visite en ligne, signez un bail conforme à la loi et suivez vos paiements depuis votre espace.</p>
            <ul>
              {["Caution et avance limitées à deux mois", "Quittances téléchargeables", "Messagerie directe avec l'agence"].map((t) => <li key={t}><CheckCircle2 size={17} /> {t}</li>)}
            </ul>
            <Link to="/nos-maisons" className="btn">Voir les logements <ArrowRight size={15} /></Link>
          </div>
        </div>
      </section>

      <AppelAction />
    </Site>
  );
}
