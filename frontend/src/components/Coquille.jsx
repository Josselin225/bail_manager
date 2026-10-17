import { Suspense, useEffect, useRef, useState } from "react";
import { Link, NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowUpRight, BarChart3, BookOpen, Building2, CalendarCheck, CalendarDays, ChevronDown, ChevronsLeft, ChevronsRight, CircleUserRound,
  ClipboardList, DoorOpen, FileSignature, FileText, Gauge, HandCoins, Handshake, History, Home, Info, KeyRound, Landmark, LayoutDashboard, LifeBuoy, LogOut, Mail,
  Menu, MessagesSquare, Moon, Receipt, Search, Settings, ShieldCheck, Sun, TrendingUp, UserRound, Users, Wallet, Wrench, Zap,
  AlarmClock, Droplets, UsersRound, GitCompareArrows, MonitorDown,
} from "lucide-react";
import { useInstallation } from "../lib/pwa";
import { BandeauLicence } from "./Licence";
import { api } from "../lib/api";
import { appliquerTheme, themeInitial, useSession } from "../lib/session";
import { Avatar, Bouton, Chargement } from "./ui";

function useCompteurs(actif) {
  return useQuery({
    queryKey: ["compteurs"],
    enabled: actif,
    refetchInterval: 60000,
    queryFn: async () => {
      const [r, m, l, i] = await Promise.all([
        api.get("/reservations/", { statut: "en_attente", taille: 1 }),
        api.get("/messages/", { lu: false, taille: 1 }),
        api.get("/messagerie/compteur/"),
        api.get("/interventions/compteur/"),
      ]);
      return { reservations: r.count, messages: m.count, locataires: l.non_lus, interventions: i.nouvelles };
    },
  });
}

const NAV = [
  { lien: "/tableau-de-bord", libelle: "Tableau de bord", icone: LayoutDashboard },
  {
    groupe: "Patrimoine",
    icone: Building2,
    liens: [
      { lien: "/bailleurs", libelle: "Bailleurs", icone: UserRound },
      { lien: "/maisons", libelle: "Maisons", icone: Home },
      { lien: "/locataires", libelle: "Locataires", icone: Users },
    ],
  },
  {
    groupe: "Contrats & loyers",
    icone: ClipboardList,
    liens: [
      { lien: "/contrats", libelle: "Contrats de bail", icone: FileSignature },
      { lien: "/encaissements", libelle: "Encaissements", icone: HandCoins },
      { lien: "/recouvrement", libelle: "Recouvrement", icone: AlarmClock },
      { lien: "/calendrier", libelle: "Calendrier", icone: CalendarDays },
    ],
  },
  {
    groupe: "Gestion locative",
    icone: KeyRound,
    liens: [
      { lien: "/charges", libelle: "Charges locatives", icone: Zap },
      { lien: "/compteurs", libelle: "Compteurs partagés", icone: Droplets, module: "compteurs" },
      { lien: "/revisions", libelle: "Révisions de loyer", icone: TrendingUp },
      { lien: "/interventions", libelle: "Interventions", icone: Wrench, compteur: "interventions" },
    ],
  },
  {
    groupe: "Finances",
    icone: Landmark,
    liens: [
      { lien: "/comptes-bailleurs", libelle: "Comptes bailleurs", icone: Wallet },
      { lien: "/caisse", libelle: "Caisse agence", icone: Receipt },
      { lien: "/rapprochement", libelle: "Rapprochement", icone: GitCompareArrows, module: "rapprochement" },
      { lien: "/cautions", libelle: "Cautions", icone: ShieldCheck },
    ],
  },
  {
    groupe: "Relations",
    icone: Handshake,
    liens: [
      { lien: "/reservations", libelle: "Visites", icone: CalendarCheck, compteur: "reservations" },
      { lien: "/messages", libelle: "Messages du site", icone: Mail, compteur: "messages" },
      { lien: "/messagerie", libelle: "Messagerie locataires", icone: MessagesSquare, compteur: "locataires" },
    ],
  },
  {
    groupe: "Pilotage",
    icone: Gauge,
    liens: [
      { lien: "/rapports", libelle: "Rapports", icone: BarChart3 },
      { lien: "/equipe", libelle: "Activité de l'équipe", icone: UsersRound },
      { lien: "/journal", libelle: "Journal d'activité", icone: History, admin: true },
    ],
  },
  {
    groupe: "Aide",
    icone: LifeBuoy,
    liens: [
      { lien: "/guide", libelle: "Guide d'utilisation", icone: BookOpen },
      { lien: "/a-propos", libelle: "À propos", icone: Info },
    ],
  },
];

/** Groupe du menu en tiroir : l'en-tête ouvre/ferme ; fermé, il affiche la somme de ses compteurs. */
function TiroirMenu({ item, ouvert, onBasculer, estAdmin, compteurs, reduit }) {
  const { licence } = useSession();
  const liens = item.liens.filter((l) => (!l.admin || estAdmin) && (!l.module || !licence || licence.modules.includes(l.module)));
  const total = liens.reduce((s, l) => s + (l.compteur ? compteurs?.[l.compteur] || 0 : 0), 0);
  const visible = ouvert || reduit;
  const id = `tiroir-${item.groupe.replace(/\W+/g, "-")}`;
  return (
    <div className={`menu-groupe tiroir ${visible ? "ouvert" : ""}`}>
      <button className="menu-groupe-titre" onClick={onBasculer} aria-expanded={visible} aria-controls={id}>
        <item.icone size={17} />
        <span>{item.groupe}</span>
        {!visible && total > 0 && <span className="pastille">{total}</span>}
        <ChevronDown size={15} className="chevron" />
      </button>
      <div className="tiroir-corps" id={id}>
        <div>
          {liens.map((l) => (
            <NavLink key={l.lien} to={l.lien} className={({ isActive }) => `menu-lien ${isActive ? "actif" : ""}`} title={l.libelle} tabIndex={visible ? undefined : -1}>
              <l.icone size={18} />
              <span>{l.libelle}</span>
              {l.compteur && compteurs?.[l.compteur] > 0 && <span className="pastille">{compteurs[l.compteur]}</span>}
            </NavLink>
          ))}
        </div>
      </div>
    </div>
  );
}

// Groupe contenant la page affichée (ouvert d'office)
const groupeDe = (chemin) => NAV.find((g) => g.liens?.some((l) => chemin === l.lien || chemin.startsWith(`${l.lien}/`)))?.groupe;

const ICONES_TYPE = { bailleur: UserRound, locataire: Users, maison: Home, contrat: FileSignature, quittance: FileText };

function PaletteRecherche({ ouvert, onFermer }) {
  const [q, setQ] = useState("");
  const [res, setRes] = useState([]);
  const [i, setI] = useState(0);
  const nav = useNavigate();
  useEffect(() => {
    if (!ouvert) {
      setQ("");
      setRes([]);
    }
  }, [ouvert]);
  useEffect(() => {
    if (q.trim().length < 2) return setRes([]);
    const t = setTimeout(() => api.get("/recherche/", { q }).then((r) => (setRes(r), setI(0))).catch(() => {}), 220);
    return () => clearTimeout(t);
  }, [q]);
  if (!ouvert) return null;
  const aller = (r) => {
    onFermer();
    nav(r.lien);
  };
  return (
    <div className="voile" onMouseDown={(e) => e.target === e.currentTarget && onFermer()}>
      <div className="fenetre palette" role="dialog" aria-label="Recherche">
        <input
          autoFocus
          value={q}
          placeholder="Bailleur, locataire, maison, contrat, n° de reçu…"
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Escape") onFermer();
            if (e.key === "ArrowDown") setI((x) => Math.min(x + 1, res.length - 1));
            if (e.key === "ArrowUp") setI((x) => Math.max(x - 1, 0));
            if (e.key === "Enter" && res[i]) aller(res[i]);
          }}
        />
        <div className="palette-resultats">
          {q.length >= 2 && res.length === 0 && <div className="discret" style={{ padding: 14 }}>Aucun résultat pour « {q} ».</div>}
          {q.length < 2 && <div className="discret" style={{ padding: 14 }}>Saisissez au moins deux caractères.</div>}
          {res.map((r, k) => {
            const Icone = ICONES_TYPE[r.type] || Search;
            return (
              <a key={k} href={r.lien} className={k === i ? "actif" : ""} onClick={(e) => (e.preventDefault(), aller(r))} onMouseEnter={() => setI(k)}>
                <Icone size={17} className="discret" />
                <div style={{ minWidth: 0 }}>
                  <div className="gras tronque">{r.label}</div>
                  {r.sub && <div className="petit-texte discret tronque">{r.sub}</div>}
                </div>
                <span className="type">{r.type}</span>
              </a>
            );
          })}
        </div>
      </div>
    </div>
  );
}

function MenuUtilisateur() {
  const { utilisateur, estAdmin, deconnexion } = useSession();
  const installer = useInstallation();
  const [ouvert, setOuvert] = useState(false);
  const ref = useRef(null);
  const nav = useNavigate();
  useEffect(() => {
    const f = (e) => ref.current && !ref.current.contains(e.target) && setOuvert(false);
    document.addEventListener("mousedown", f);
    return () => document.removeEventListener("mousedown", f);
  }, []);
  return (
    <div className="deroulant" ref={ref}>
      <button className="btn fantome" onClick={() => setOuvert((o) => !o)} style={{ height: 40, paddingLeft: 6 }}>
        <Avatar nom={utilisateur?.nom_complet} src={utilisateur?.photo_profil} />
        <span className="tronque nom-utilisateur" style={{ maxWidth: 140, textAlign: "left" }}>
          <span style={{ display: "block", lineHeight: 1.2 }}>{utilisateur?.nom_complet}</span>
          <span className="petit-texte discret" style={{ fontWeight: 500 }}>{estAdmin ? "Administrateur" : "Agent"}</span>
        </span>
      </button>
      {ouvert && (
        <div className="deroulant-liste" onClick={() => setOuvert(false)}>
          <Link to="/profil"><CircleUserRound size={16} /> Mon profil</Link>
          <Link to="/profil?onglet=securite"><KeyRound size={16} /> Changer le mot de passe</Link>
          <hr className="seulement-mobile" />
          <a className="seulement-mobile" href="/"><ArrowUpRight size={16} /> Voir le site public</a>
          <a className="seulement-mobile" href="/espace-locataire"><DoorOpen size={16} /> Espace locataire</a>
          {estAdmin && (
            <>
              <hr />
              <Link to="/utilisateurs"><Users size={16} /> Utilisateurs</Link>
              <Link to="/parametres"><Settings size={16} /> Paramètres</Link>
            </>
          )}
          {installer && (
            <>
              <hr />
              <button onClick={installer}><MonitorDown size={16} /> Installer l'application</button>
            </>
          )}
          <hr />
          <button onClick={async () => (await deconnexion(), nav("/connexion"))}>
            <LogOut size={16} /> Se déconnecter
          </button>
        </div>
      )}
    </div>
  );
}

export default function Coquille() {
  const { utilisateur, estAdmin, entreprise } = useSession();
  const [reduit, setReduit] = useState(() => localStorage.getItem("bm-menu") === "reduit");
  const [mobile, setMobile] = useState(false);
  const [palette, setPalette] = useState(false);
  const [theme, setTheme] = useState(themeInitial);
  const { data: compteurs } = useCompteurs(!!utilisateur);
  const loc = useLocation();

  const [tiroir, setTiroir] = useState(() => groupeDe(loc.pathname) ?? null);
  useEffect(() => { setMobile(false); }, [loc.pathname]);
  useEffect(() => {
    const g = groupeDe(loc.pathname);
    if (g) setTiroir(g);
  }, [loc.pathname]);
  useEffect(() => { appliquerTheme(theme); }, [theme]);
  useEffect(() => {
    const f = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setPalette(true);
      }
    };
    document.addEventListener("keydown", f);
    return () => document.removeEventListener("keydown", f);
  }, []);

  const basculer = () => {
    setReduit((r) => {
      localStorage.setItem("bm-menu", r ? "" : "reduit");
      return !r;
    });
  };
  const nom = entreprise?.nom || "BailManager";
  const marque = /bailmanager/i.test(nom) ? (
    <span>
      BAIL<b>MANAGER</b>
    </span>
  ) : (
    <span className="tronque">{nom}</span>
  );

  return (
    <div className={`coquille ${reduit ? "reduit" : ""} ${mobile ? "mobile-ouvert" : ""}`}>
      {mobile && <div className="voile-mobile" onClick={() => setMobile(false)} />}
      <aside className="menu" aria-label="Navigation principale">
        <Link to="/tableau-de-bord" className="menu-marque">
          <span className="logo-carre">{entreprise?.logo ? <img src={entreprise.logo} alt="" /> : "B"}</span>
          {marque}
        </Link>
        <nav className="menu-defile">
          {NAV.map((item) =>
            item.lien ? (
              <NavLink key={item.lien} to={item.lien} className={({ isActive }) => `menu-lien ${isActive ? "actif" : ""}`} title={item.libelle}>
                <item.icone size={18} />
                <span>{item.libelle}</span>
              </NavLink>
            ) : (
              <TiroirMenu
                key={item.groupe}
                item={item}
                ouvert={tiroir === item.groupe}
                onBasculer={() => setTiroir((t) => (t === item.groupe ? null : item.groupe))}
                estAdmin={estAdmin}
                compteurs={compteurs}
                reduit={reduit && !mobile}
              />
            ),
          )}
        </nav>
        <div className="menu-pied">
          <Bouton variante="fantome" icone={reduit ? ChevronsRight : ChevronsLeft} onClick={basculer}>
            {!reduit && "Réduire le menu"}
          </Bouton>
        </div>
      </aside>

      <div className="principal">
        <header className="barre">
          <Bouton variante="fantome" className="bouton-menu" icone={Menu} onClick={() => setMobile(true)} aria-label="Ouvrir le menu" />
          <div className="recherche-globale" onClick={() => setPalette(true)} role="button" tabIndex={0} onKeyDown={(e) => e.key === "Enter" && setPalette(true)}>
            <Search size={16} />
            <span>Rechercher…</span>
            <kbd>Ctrl K</kbd>
          </div>
          <div className="barre-droite">
            <a className="btn fantome masque-mobile" href="/espace-locataire" title="Ouvrir l'espace locataire">
              <DoorOpen size={17} />
            </a>
            <a className="btn fantome masque-mobile" href="/" title="Voir le site public">
              <ArrowUpRight size={17} />
            </a>
            <Bouton variante="fantome" icone={theme === "dark" ? Sun : Moon} onClick={() => setTheme((t) => (t === "dark" ? "light" : "dark"))} aria-label="Changer de thème" title="Mode clair / sombre" />
            <MenuUtilisateur />
          </div>
        </header>
        <main className="contenu">
          <BandeauLicence />
          <Suspense fallback={<Chargement />}>
            <Outlet />
          </Suspense>
        </main>
      </div>
      <PaletteRecherche ouvert={palette} onFermer={() => setPalette(false)} />
    </div>
  );
}

