import { createContext, useCallback, useContext, useEffect, useId, useMemo, useRef, useState } from "react";
import { AlertTriangle, CheckCircle2, ChevronLeft, ChevronRight, Info, Loader2, Search, Upload, X, XCircle } from "lucide-react";
import { Link } from "react-router-dom";
import { useIsFetching, useIsMutating } from "@tanstack/react-query";
import { createPortal } from "react-dom";
import { fcfa, initiales, nombre } from "../lib/format";

/* ─── Montants & statuts ─────────────────────────────────────────── */
export function Montant({ v, signe = false, devise = true, className = "" }) {
  const n = Number(v) || 0;
  const cls = signe ? (n < 0 ? "negatif" : n > 0 ? "positif" : "") : "";
  return (
    <span className={`montant ${cls} ${className}`}>
      {signe && n > 0 ? "+" : ""}
      {nombre(n)}
      {devise && <span className="devise">FCFA</span>}
    </span>
  );
}

const STATUTS = {
  actif: ["ok", "Actif"],
  termine: ["", "Terminé"],
  resilie: ["", "Résilié"],
  expire: ["attention", "Expiré"],
  disponible: ["ok", "Disponible"],
  occupe: ["info", "Occupée"],
  a_jour: ["ok", "À jour"],
  echeance_proche: ["attention", "Échéance proche"],
  en_retard: ["alerte", "En retard"],
  paye: ["ok", "Payé"],
  solde: ["ok", "Soldé"],
  partiel: ["attention", "Partiel"],
  partiel_retard: ["alerte", "Partiel, en retard"],
  impaye: ["alerte", "Impayé"],
  a_venir: ["info", "À venir"],
  en_attente: ["attention", "En attente"],
  confirme: ["info", "Confirmée"],
  annule: ["", "Annulée"],
  admin: ["violet", "Administrateur"],
  agent: ["info", "Agent"],
};

/** Ancienneté d'un bail depuis sa conclusion (calculée par le serveur). */
export function Anciennete({ a, detail = false }) {
  if (!a) return "—";
  const total = `${a.jours_total.toLocaleString("fr-FR")} jour${a.jours_total > 1 ? "s" : ""}`;
  return (
    <span className={`anciennete ${a.en_cours ? "" : "figee"}`} title={`${total} depuis la signature${a.en_cours ? "" : " (bail terminé)"}`}>
      {a.libelle}
      {detail && <span className="discret"> — {total}{a.en_cours ? "" : ", jusqu'à la résiliation"}</span>}
    </span>
  );
}

export function Statut({ s, libelle }) {
  const [ton, lib] = STATUTS[s] || ["", s];
  return <span className={`badge ${ton}`}>{libelle || lib}</span>;
}

export function Badge({ ton = "", children, point = true }) {
  return <span className={`badge ${ton} ${point ? "" : "sans-point"}`}>{children}</span>;
}

/* ─── Boutons ────────────────────────────────────────────────────── */
export function Bouton({ variante = "", taille = "", icone: Icone, chargement, children, className = "", ...rest }) {
  return (
    <button type="button" className={`btn ${variante} ${taille} ${!children ? "icone" : ""} ${className}`} disabled={chargement || rest.disabled} {...rest}>
      {chargement ? <Loader2 size={16} className="tourne" /> : Icone && <Icone size={16} />}
      {children}
    </button>
  );
}

export function LienBouton({ to, variante = "", taille = "", icone: Icone, children, className = "", ...rest }) {
  return (
    <Link to={to} className={`btn ${variante} ${taille} ${!children ? "icone" : ""} ${className}`} {...rest}>
      {Icone && <Icone size={16} />}
      {children}
    </Link>
  );
}

/* ─── Formulaires ────────────────────────────────────────────────── */
export function Champ({ libelle, aide, erreur, children, plein, requis, className = "" }) {
  const id = useId();
  const enfant = typeof children === "function" ? children(id) : children;
  return (
    <div className={`champ ${plein ? "plein" : ""} ${className}`}>
      {libelle && (
        <label htmlFor={id}>
          {libelle}
          {requis && <span className="requis" aria-hidden="true"> *</span>}
        </label>
      )}
      {enfant}
      {erreur ? <span className="erreur">{erreur}</span> : aide ? <span className="aide">{aide}</span> : null}
    </div>
  );
}

export function Saisie({ libelle, aide, erreur, plein, suffixe, valeur, onChange, type = "text", requis, ...rest }) {
  return (
    <Champ libelle={libelle} aide={aide} erreur={erreur} plein={plein} requis={requis}>
      {(id) => {
        const input = (
          <input
            id={id}
            className="saisie"
            type={type}
            value={valeur ?? ""}
            required={requis}
            onChange={(e) => onChange?.(e.target.value)}
            {...rest}
          />
        );
        return suffixe ? (
          <div className="saisie-groupe">
            {input}
            <span className="suffixe">{suffixe}</span>
          </div>
        ) : (
          input
        );
      }}
    </Champ>
  );
}

export function SaisieMontant(props) {
  return <Saisie type="number" min="0" step="1" inputMode="numeric" suffixe="FCFA" {...props} />;
}

export function Liste({ libelle, aide, erreur, plein, valeur, onChange, options, vide, requis, ...rest }) {
  return (
    <Champ libelle={libelle} aide={aide} erreur={erreur} plein={plein} requis={requis}>
      {(id) => (
        <select id={id} className="saisie" value={valeur ?? ""} required={requis} onChange={(e) => onChange?.(e.target.value)} {...rest}>
          {vide !== undefined && <option value="">{vide}</option>}
          {options.map((o) =>
            typeof o === "string" ? (
              <option key={o} value={o}>
                {o}
              </option>
            ) : (
              <option key={o.valeur} value={o.valeur}>
                {o.libelle}
              </option>
            ),
          )}
        </select>
      )}
    </Champ>
  );
}

export function Zone({ libelle, aide, erreur, plein = true, valeur, onChange, requis, rows = 3, ...rest }) {
  return (
    <Champ libelle={libelle} aide={aide} erreur={erreur} plein={plein} requis={requis}>
      {(id) => <textarea id={id} className="saisie" rows={rows} value={valeur ?? ""} required={requis} onChange={(e) => onChange?.(e.target.value)} {...rest} />}
    </Champ>
  );
}

export function Case({ libelle, aide, valeur, onChange, plein = true }) {
  return (
    <div className={`champ champ-case ${plein ? "plein" : ""}`}>
      <label className="case">
        <input type="checkbox" checked={!!valeur} onChange={(e) => onChange(e.target.checked)} />
        <span>{libelle}</span>
      </label>
      {aide && <span className="aide">{aide}</span>}
    </div>
  );
}

/** Sélecteur de fichier(s) avec aperçu (image) ou nom (PDF). Avec « multiple », valeur et onChange portent une liste. */
export function Fichier({ libelle, aide, valeur, actuel, onChange, accept = "image/*", plein, requis, multiple }) {
  const liste = multiple ? valeur || [] : valeur instanceof File ? [valeur] : [];
  const [apercus, setApercus] = useState([]);
  useEffect(() => {
    const urls = liste.filter((f) => f.type.startsWith("image/")).slice(0, 6).map((f) => URL.createObjectURL(f));
    setApercus(urls);
    return () => urls.forEach((u) => URL.revokeObjectURL(u));
  }, [valeur]); // eslint-disable-line
  const actuelImage = actuel && /\.(jpe?g|png|gif|webp)$/i.test(actuel) ? actuel : null;
  const images = apercus.length ? apercus : actuelImage ? [actuelImage] : [];
  const texte = liste.length > 1 ? `${liste.length} fichiers choisis` : liste[0]?.name || (actuel ? "Fichier enregistré — cliquer pour remplacer" : multiple ? "Choisir un ou plusieurs fichiers (8 Mo max. chacun)" : "Choisir un fichier (8 Mo max.)");
  return (
    <Champ libelle={libelle} aide={aide} plein={plein} requis={requis}>
      {(id) => (
        <label className={`fichier ${liste.length ? "rempli" : ""}`} htmlFor={id}>
          {images.length ? <span className="fichier-apercus">{images.map((u) => <img key={u} src={u} alt="" />)}</span> : <Upload size={22} className="discret" />}
          <span className="tronque" style={{ flex: 1 }}>{texte}</span>
          <input id={id} type="file" accept={accept} multiple={multiple} required={requis && !liste.length && !actuel} onChange={(e) => onChange(multiple ? [...(e.target.files || [])] : e.target.files?.[0] || null)} />
        </label>
      )}
    </Champ>
  );
}

/** Liste déroulante avec recherche (remplace searchable-select.js). */
export function Combo({ libelle, aide, plein, options, valeur, onChange, placeholder = "Rechercher…", requis, desactive }) {
  const [ouvert, setOuvert] = useState(false);
  const [texte, setTexte] = useState("");
  const [index, setIndex] = useState(0);
  const ref = useRef(null);
  const refSaisie = useRef(null);
  const choisi = options.find((o) => String(o.valeur) === String(valeur));
  // Obligatoire : bloque l'envoi du formulaire tant qu'aucune option n'est choisie
  useEffect(() => {
    refSaisie.current?.setCustomValidity(requis && !choisi ? "Choisissez un élément dans la liste." : "");
  }, [requis, choisi]);
  const filtrees = useMemo(() => {
    const t = texte.trim().toLowerCase();
    return (t ? options.filter((o) => `${o.libelle} ${o.sous || ""}`.toLowerCase().includes(t)) : options).slice(0, 80);
  }, [texte, options]);
  useEffect(() => {
    const fermer = (e) => ref.current && !ref.current.contains(e.target) && setOuvert(false);
    document.addEventListener("mousedown", fermer);
    return () => document.removeEventListener("mousedown", fermer);
  }, []);
  const choisir = (o) => {
    if (o.desactive) return;
    onChange(o.valeur, o);
    setOuvert(false);
    setTexte("");
  };
  return (
    <Champ libelle={libelle} aide={aide} plein={plein} requis={requis}>
      {(id) => (
        <div className="combo" ref={ref}>
          <input
            id={id}
            ref={refSaisie}
            className="saisie"
            disabled={desactive}
            placeholder={choisi ? choisi.libelle : placeholder}
            value={ouvert ? texte : choisi?.libelle || ""}
            onFocus={() => setOuvert(true)}
            onChange={(e) => {
              setTexte(e.target.value);
              setOuvert(true);
              setIndex(0);
            }}
            onKeyDown={(e) => {
              if (e.key === "ArrowDown") setIndex((i) => Math.min(i + 1, filtrees.length - 1));
              else if (e.key === "ArrowUp") setIndex((i) => Math.max(i - 1, 0));
              else if (e.key === "Enter" && filtrees[index]) {
                e.preventDefault();
                choisir(filtrees[index]);
              } else if (e.key === "Escape") setOuvert(false);
            }}
            autoComplete="off"
          />
          {ouvert && (
            <div className="combo-liste">
              {filtrees.length === 0 && <div className="discret" style={{ padding: 10 }}>Aucun résultat</div>}
              {filtrees.map((o, i) => (
                <button type="button" key={o.valeur} className={i === index ? "actif" : ""} onMouseDown={() => choisir(o)} disabled={o.desactive}>
                  <div style={{ opacity: o.desactive ? 0.5 : 1 }}>{o.libelle}</div>
                  {o.sous && <div className="sous">{o.sous}</div>}
                </button>
              ))}
            </div>
          )}
        </div>
      )}
    </Champ>
  );
}

export function Segments({ options, valeur, onChange }) {
  return (
    <div className="segments" role="tablist">
      {options.map((o) => (
        <button key={o.valeur} type="button" className={valeur === o.valeur ? "actif" : ""} onClick={() => onChange(o.valeur)}>
          {o.libelle}
        </button>
      ))}
    </div>
  );
}

export function Recherche({ valeur, onChange, placeholder = "Rechercher…" }) {
  const [local, setLocal] = useState(valeur || "");
  useEffect(() => { setLocal(valeur || ""); }, [valeur]);
  useEffect(() => {
    const t = setTimeout(() => local !== (valeur || "") && onChange(local), 320);
    return () => clearTimeout(t);
  }, [local]); // eslint-disable-line
  return (
    <div className="recherche">
      <Search size={16} />
      <input className="saisie" value={local} onChange={(e) => setLocal(e.target.value)} placeholder={placeholder} type="search" />
    </div>
  );
}

/* ─── Mise en page ───────────────────────────────────────────────── */
// L'en-tête reste collé sous la barre du haut ; « colle » le compacte une fois la page défilée
export function EntetePage({ titre, soustitre, fil, children, photo }) {
  const ref = useRef(null);
  const [colle, setColle] = useState(false);
  useEffect(() => {
    document.title = `${titre} — BailManager`;
  }, [titre]);
  useEffect(() => {
    let attente = 0;
    const verifier = () => {
      attente = 0;
      const el = ref.current;
      if (el) setColle(window.scrollY > 8 && el.getBoundingClientRect().top <= parseFloat(getComputedStyle(el).top) + 1);
    };
    const f = () => { if (!attente) attente = requestAnimationFrame(verifier); };
    verifier();
    window.addEventListener("scroll", f, { passive: true });
    window.addEventListener("resize", f);
    return () => {
      window.removeEventListener("scroll", f);
      window.removeEventListener("resize", f);
      cancelAnimationFrame(attente);
    };
  }, []);
  return (
    <div ref={ref} className={`entete-page ${photo ? "avec-photo" : ""} ${colle ? "colle" : ""}`} style={photo ? { "--photo": `url("${photo}")` } : undefined}>
      <div className="titres">
        {fil && (
          <div className="fil">
            {fil.map((f, i) => (
              <span key={i} className="ligne-flex" style={{ gap: 6 }}>
                {f.lien ? <Link to={f.lien}>{f.libelle}</Link> : f.libelle}
                <ChevronRight size={13} />
              </span>
            ))}
          </div>
        )}
        <h1>{titre}</h1>
        {soustitre && <div className="soustitre">{soustitre}</div>}
      </div>
      {children && <div className="actions">{children}</div>}
    </div>
  );
}

export function Panneau({ titre, actions, children, serre, className = "", icone: Icone }) {
  return (
    <section className={`panneau ${className}`}>
      {(titre || actions) && (
        <div className="panneau-tete">
          {Icone && <Icone size={18} className="discret" />}
          {typeof titre === "string" ? <h2>{titre}</h2> : titre}
          {actions}
        </div>
      )}
      <div className={`panneau-corps ${serre ? "serre" : ""}`}>{children}</div>
    </section>
  );
}

export function Encart({ ton = "", titre, children }) {
  const Icone = ton === "alerte" ? XCircle : ton === "attention" ? AlertTriangle : ton === "ok" ? CheckCircle2 : Info;
  return (
    <div className={`encart ${ton}`}>
      <Icone size={18} />
      <div>
        {titre && <div className="gras">{titre}</div>}
        {children}
      </div>
    </div>
  );
}

export function Vide({ icone: Icone, titre, texte, action }) {
  return (
    <div className="vide">
      {Icone && <Icone size={36} />}
      <h3>{titre}</h3>
      {texte && <p>{texte}</p>}
      {action}
    </div>
  );
}

/** Loader de la marque : une maison qui se dessine. « plein » occupe tout l'écran (premier chargement). */
export function Chargement({ texte = "Chargement…", plein = false }) {
  return (
    <div className={`chargement ${plein ? "plein" : ""}`} role="status" aria-live="polite">
      <svg className="loader-maison" viewBox="0 0 48 48" aria-hidden="true">
        <path className="trace toit" d="M5 23 L24 8 L43 23" />
        <path className="trace murs" d="M10 20 V41 H38 V20" />
        <path className="trace porte" d="M20 41 V30 H28 V41" />
      </svg>
      <span>{texte}</span>
    </div>
  );
}

/** Fine barre en haut de l'écran pendant les échanges avec le serveur (affichée après un court délai). */
export function BarreChargement() {
  const actif = useIsFetching() + useIsMutating() > 0;
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    if (!actif) return setVisible(false);
    const t = setTimeout(() => setVisible(true), 180);
    return () => clearTimeout(t);
  }, [actif]);
  return <div className={`barre-chargement ${visible ? "active" : ""}`} role="progressbar" aria-hidden={!visible} aria-label="Chargement en cours" />;
}

export function Avatar({ nom, src, grand }) {
  return <span className={`avatar ${grand ? "grand" : ""}`}>{src ? <img src={src} alt="" /> : initiales(nom)}</span>;
}

export function Onglets({ onglets, actif, onChange }) {
  return (
    <div className="onglets" role="tablist">
      {onglets.map((o) => (
        <button key={o.cle} type="button" role="tab" className={actif === o.cle ? "actif" : ""} onClick={() => onChange(o.cle)}>
          {o.libelle}
          {o.compte ? <span className="badge sans-point">{o.compte}</span> : null}
        </button>
      ))}
    </div>
  );
}

export function Definitions({ items }) {
  return (
    <dl className="definitions">
      {items
        .filter(([, v]) => v !== undefined && v !== null && v !== "")
        .map(([k, v]) => (
          <div key={k} style={{ display: "contents" }}>
            <dt>{k}</dt>
            <dd>{v}</dd>
          </div>
        ))}
    </dl>
  );
}

/* ─── Tableau de données ─────────────────────────────────────────── */
/**
 * colonnes : [{ cle, libelle, rendu?(ligne), type?('montant'|'date'), droite?, largeur? }]
 */
export function Tableau({ colonnes, lignes, chargement, surLigne, vide, pied, cle = "id" }) {
  if (chargement && !lignes?.length) return <Chargement />;
  if (!lignes?.length) return vide || <Vide titre="Aucun élément" texte="Rien à afficher pour ces critères." />;
  return (
    <div className="tableau-conteneur">
      <table className="tableau">
        <thead>
          <tr>
            {colonnes.map((c) => (
              <th key={c.cle || c.libelle} className={c.droite || c.type === "montant" ? "droite" : ""} style={c.largeur ? { width: c.largeur } : undefined}>
                {c.libelle}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {lignes.map((l, i) => (
            <tr key={l[cle] ?? i} className={surLigne ? "cliquable" : ""} onClick={surLigne ? (e) => !e.target.closest("button,a") && surLigne(l) : undefined}>
              {colonnes.map((c) => (
                <td key={c.cle || c.libelle} data-libelle={c.libelle || undefined} className={[c.droite || c.type === "montant" ? "droite" : "", c.libelle ? "" : "sans-libelle"].filter(Boolean).join(" ") || undefined}>
                  {c.rendu ? c.rendu(l) : c.type === "montant" ? <Montant v={l[c.cle]} /> : l[c.cle]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
        {pied && <tfoot>{pied}</tfoot>}
      </table>
    </div>
  );
}

export function Pagination({ page, pages, total, onPage }) {
  if (!pages || pages <= 1) return total ? <div className="pagination">{total} élément(s)</div> : null;
  return (
    <div className="pagination">
      <span>{total} élément(s)</span>
      <span className="espace" />
      <Bouton taille="petit" icone={ChevronLeft} disabled={page <= 1} onClick={() => onPage(page - 1)} aria-label="Page précédente" />
      <span>
        Page {page} / {pages}
      </span>
      <Bouton taille="petit" icone={ChevronRight} disabled={page >= pages} onClick={() => onPage(page + 1)} aria-label="Page suivante" />
    </div>
  );
}

/* ─── Fenêtres ───────────────────────────────────────────────────── */
export function Fenetre({ titre, ouvert, onFermer, children, pied, taille = "" }) {
  useEffect(() => {
    if (!ouvert) return;
    const f = (e) => e.key === "Escape" && onFermer?.();
    document.addEventListener("keydown", f);
    const ancien = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", f);
      document.body.style.overflow = ancien;
    };
  }, [ouvert, onFermer]);
  if (!ouvert) return null;
  return createPortal(
    <div className="voile" onMouseDown={(e) => e.target === e.currentTarget && onFermer?.()}>
      <div className={`fenetre ${taille}`} role="dialog" aria-modal="true" aria-label={titre}>
        <div className="fenetre-tete">
          <h2>{titre}</h2>
          <Bouton variante="fantome" icone={X} onClick={onFermer} aria-label="Fermer" />
        </div>
        <div className="fenetre-corps">{children}</div>
        {pied && <div className="fenetre-pied">{pied}</div>}
      </div>
    </div>,
    document.body,
  );
}

/** Fenêtre-formulaire : gère l'envoi, l'état de chargement et les erreurs. */
export function FenetreFormulaire({ titre, ouvert, onFermer, onValider, libelleValider = "Enregistrer", variante = "primaire", taille, children, desactive, icone }) {
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState("");
  const toast = useToast();
  const idForm = useId();
  const refForm = useRef(null);
  useEffect(() => {
    if (!ouvert) return undefined;
    setErreur("");
    // Premier champ saisissable, hors listes à recherche (qui s'ouvriraient d'elles-mêmes)
    const t = setTimeout(() => [...(refForm.current?.querySelectorAll("input:not([type=hidden]):not([type=checkbox]):not([type=file]):not([disabled]), select, textarea") || [])].find((el) => !el.closest(".combo"))?.focus(), 60);
    return () => clearTimeout(t);
  }, [ouvert]);
  const soumettre = async (e) => {
    e?.preventDefault();
    setEnvoi(true);
    setErreur("");
    try {
      const msg = await onValider();
      if (msg) toast.succes(msg);
      onFermer();
    } catch (err) {
      setErreur(err.message);
    } finally {
      setEnvoi(false);
    }
  };
  if (!ouvert) return null;
  return (
    <Fenetre
      titre={titre}
      ouvert={ouvert}
      onFermer={onFermer}
      taille={taille}
      pied={
        <>
          <Bouton onClick={onFermer}>Annuler</Bouton>
          <Bouton type="submit" form={idForm} variante={variante} icone={icone} chargement={envoi} disabled={desactive}>
            {libelleValider}
          </Bouton>
        </>
      }
    >
      <form id={idForm} ref={refForm} onSubmit={soumettre} className="pile formulaire-fenetre">
        {erreur && <Encart ton="alerte">{erreur}</Encart>}
        {children}
      </form>
    </Fenetre>
  );
}

/* ─── Notifications & confirmations (contexte global) ─────────────── */
const ContexteUI = createContext(null);

export function FournisseurUI({ children }) {
  const [toasts, setToasts] = useState([]);
  const [confirmation, setConfirmation] = useState(null);
  // Dès qu'un champ obligatoire est refusé à l'envoi, le formulaire montre ses champs en erreur (classe « tente »)
  useEffect(() => {
    const f = (e) => e.target.form?.classList.add("tente");
    document.addEventListener("invalid", f, true);
    return () => document.removeEventListener("invalid", f, true);
  }, []);
  const ajouter = useCallback((type, texte) => {
    const id = Math.random().toString(36).slice(2);
    setToasts((t) => [...t.slice(-3), { id, type, texte }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), type === "erreur" ? 7000 : 4200);
  }, []);
  const toast = useMemo(
    () => ({
      succes: (t) => ajouter("succes", t),
      erreur: (t) => ajouter("erreur", t),
      info: (t) => ajouter("info", t),
      attention: (t) => ajouter("attention", t),
    }),
    [ajouter],
  );
  const confirmer = useCallback(
    (opts) => new Promise((resoudre) => setConfirmation({ ...(typeof opts === "string" ? { message: opts } : opts), resoudre })),
    [],
  );
  const fermer = (r) => {
    confirmation?.resoudre(r);
    setConfirmation(null);
  };
  return (
    <ContexteUI.Provider value={{ toast, confirmer }}>
      {children}
      <div className="toasts" aria-live="polite">
        {toasts.map((t) => (
          <div key={t.id} className={`toast ${t.type}`}>
            {t.type === "succes" ? <CheckCircle2 size={18} color="var(--ok)" /> : t.type === "erreur" ? <XCircle size={18} color="var(--signal)" /> : <Info size={18} color="var(--info)" />}
            <p>{t.texte}</p>
          </div>
        ))}
      </div>
      <Fenetre
        titre={confirmation?.titre || "Confirmation"}
        ouvert={!!confirmation}
        onFermer={() => fermer(false)}
        taille="etroite"
        pied={
          <>
            <Bouton onClick={() => fermer(false)}>Annuler</Bouton>
            <Bouton variante={confirmation?.danger ? "danger" : "primaire"} onClick={() => fermer(true)} autoFocus>
              {confirmation?.libelle || "Confirmer"}
            </Bouton>
          </>
        }
      >
        <p style={{ margin: 0 }}>{confirmation?.message}</p>
      </Fenetre>
    </ContexteUI.Provider>
  );
}

export const useToast = () => useContext(ContexteUI).toast;
export const useConfirmer = () => useContext(ContexteUI).confirmer;

export function Montants({ libelle, v }) {
  return (
    <div className="ligne-flex" style={{ justifyContent: "space-between" }}>
      <span className="discret">{libelle}</span>
      <b>{fcfa(v)}</b>
    </div>
  );
}
