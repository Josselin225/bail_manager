import { useEffect, useState } from "react";
import { Link, NavLink, useLocation } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Mail, MapPin, Menu, Phone, X } from "lucide-react";
import { api } from "../../lib/api";

export function useVitrine() {
  return useQuery({ queryKey: ["vitrine"], queryFn: () => api.get("/public/vitrine/"), staleTime: 60000 });
}

export function Marque({ nom }) {
  const n = nom || "BailManager";
  return /bailmanager/i.test(n) ? <>BAIL<b>MANAGER</b></> : n;
}

// Bandeau photo des pages intérieures du site (même langage visuel que le diaporama d'accueil)
export function BandeauPage({ surTitre, titre, texte, image = "/vitrine/maison-3.jpg", fil, children }) {
  return (
    <section className="bandeau-page">
      <img src={image} alt="" className="bandeau-fond" />
      <div className="bandeau-contenu">
        {fil}
        {surTitre && <span className="sur-titre">{surTitre}</span>}
        <h1>{titre}</h1>
        {texte && <p>{texte}</p>}
        {children}
      </div>
    </section>
  );
}

export function AppelAction({ titre = "Un projet de location ? Parlons-en.", texte = "Notre équipe vous répond et vous accompagne du premier appel à la remise des clés." }) {
  const { data } = useVitrine();
  const tel = data?.entreprise?.telephone;
  return (
    <section className="vitrine-appel">
      <div>
        <h2>{titre}</h2>
        <p>{texte}</p>
      </div>
      <div className="ligne-flex" style={{ gap: 12, flexWrap: "wrap" }}>
        {tel && <a href={`tel:${tel.replace(/\s/g, "")}`} className="btn grand clair"><Phone size={17} /> {tel}</a>}
        <Link to="/contact" className="btn grand primaire">Nous écrire</Link>
      </div>
    </section>
  );
}

const LIENS = [
  ["/", "Accueil"],
  ["/nos-maisons", "Nos maisons"],
  ["/services", "Services"],
  ["/contact", "Contact"],
];

// Vrai dès que la page a défilé au-delà du seuil : l'en-tête passe de transparent à opaque
export function useDefile(seuil = 24) {
  const [defile, setDefile] = useState(() => window.scrollY > seuil);
  useEffect(() => {
    const f = () => setDefile(window.scrollY > seuil);
    f();
    window.addEventListener("scroll", f, { passive: true });
    return () => window.removeEventListener("scroll", f);
  }, [seuil]);
  return defile;
}

// surPhoto : la page commence par une photo (diaporama ou bandeau), l'en-tête se pose dessus
export default function Site({ children, titre, surPhoto = true }) {
  const { data } = useVitrine();
  const e = data?.entreprise || {};
  const [ouvert, setOuvert] = useState(false);
  const defile = useDefile();
  const loc = useLocation();
  useEffect(() => {
    document.title = titre ? `${titre} — ${e.nom || "BailManager"}` : e.nom || "BailManager";
  }, [titre, e.nom]);
  useEffect(() => { setOuvert(false); }, [loc.pathname]);
  const transparent = surPhoto && !defile && !ouvert;
  return (
    <div className={`site ${surPhoto ? "sur-photo" : ""}`}>
      <nav className={`site-nav ${ouvert ? "ouvert" : ""} ${transparent ? "transparent" : ""} ${surPhoto && defile ? "defile" : ""}`}>
        <Link to="/" className="site-marque">{e.logo && <img src={e.logo} alt="" style={{ height: 34 }} />}<span><Marque nom={e.nom} /></span></Link>
        <div className="liens">
          {LIENS.map(([to, l]) => <NavLink key={to} to={to} end={to === "/"} className={({ isActive }) => (isActive ? "actif" : "")}>{l}</NavLink>)}
          <Link to="/espace-locataire" className="btn petit">Espace locataire</Link>
          <Link to="/connexion" className="btn petit primaire">Se connecter</Link>
        </div>
        <button className="btn fantome site-burger" onClick={() => setOuvert((o) => !o)} aria-label={ouvert ? "Fermer le menu" : "Ouvrir le menu"} aria-expanded={ouvert}>
          {ouvert ? <X size={22} /> : <Menu size={22} />}
        </button>
      </nav>
      {children}
      <footer className="site-pied">
        <div className="pied-colonnes">
          <div>
            <div className="pied-marque"><Marque nom={e.nom} /></div>
            <p>{e.activites || "Gestion locative, conseil aux bailleurs et location de biens immobiliers."}</p>
          </div>
          <div className="pied-liste">
            <h4>Nous joindre</h4>
            {e.telephone && <a href={`tel:${e.telephone.replace(/\s/g, "")}`}><Phone size={15} /> {e.telephone}</a>}
            {e.email && <a href={`mailto:${e.email}`}><Mail size={15} /> {e.email}</a>}
            {e.adresse && <span><MapPin size={15} /> {e.adresse}</span>}
          </div>
          <div className="pied-liste">
            <h4>Liens utiles</h4>
            <Link to="/nos-maisons?etat=disponible">Biens disponibles</Link>
            <Link to="/services">Nos services</Link>
            <Link to="/espace-locataire">Espace locataire</Link>
            <Link to="/contact">Nous écrire</Link>
          </div>
        </div>
        <div className="pied-bas">© {new Date().getFullYear()} {e.nom || "BailManager"} · Tous droits réservés</div>
      </footer>
    </div>
  );
}
