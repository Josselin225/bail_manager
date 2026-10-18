import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { ArrowDownAZ, Clock, LayoutGrid, List, Rows3, Search, Star, SquareDashedMousePointer } from "lucide-react";
import { NAV, basculerFavori, favoris as lireFavoris, lienVisible, recents, trouverLien, useCompteurs } from "../../lib/navigation";
import { useSession } from "../../lib/session";
import { EntetePage, Vide } from "../../components/ui";
import IllustrationMenu from "../../components/IllustrationsMenu";

const VUES = [
  { valeur: "tuiles", libelle: "Tuiles", icone: LayoutGrid },
  { valeur: "liste", libelle: "Liste", icone: List },
  { valeur: "icones", libelle: "Icônes", icone: SquareDashedMousePointer },
];
const TRIS = [
  { valeur: "rubrique", libelle: "Par rubrique", icone: Rows3 },
  { valeur: "az", libelle: "De A à Z", icone: ArrowDownAZ },
];
const sansAccents = (t) => t.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
// Toutes les vues présentent les fonctions de A à Z (accents et majuscules ignorés)
const parNom = (a, b) => a.libelle.localeCompare(b.libelle, "fr", { sensitivity: "base" });
const lire = (cle, defaut) => { try { return localStorage.getItem(cle) || defaut; } catch { return defaut; } };
// L'ancienne vue « A à Z » devient la vue Liste triée de A à Z
const lireVue = () => { const v = lire("bm-vue-menu", "tuiles"); return v === "az" ? "liste" : v; };
const lireTri = () => (lire("bm-vue-menu", "") === "az" ? "az" : lire("bm-tri-menu", "rubrique"));

function BoutonFavori({ lien, actif, onBasculer }) {
  return (
    <button type="button" className={`favori-menu ${actif ? "actif" : ""}`} aria-pressed={actif} title={actif ? "Retirer des favoris" : "Épingler en favori"}
      aria-label={actif ? "Retirer des favoris" : "Épingler en favori"} onClick={(e) => { e.preventDefault(); e.stopPropagation(); onBasculer(lien); }}>
      <Star size={15} />
    </button>
  );
}

/** Une entrée du menu, rendue selon la vue choisie. */
function Entree({ l, vue, compteur, favori, onFavori, groupe }) {
  const badge = compteur > 0 && <span className="pastille">{compteur}</span>;
  if (vue === "icones") {
    return (
      <Link to={l.lien} className="entree-icone" title={l.description}>
        <span className="pave-icone"><IllustrationMenu lien={l.lien} />{badge}</span>
        <span className="libelle-icone">{l.libelle}</span>
      </Link>
    );
  }
  if (vue === "liste" || vue === "az") {
    return (
      <Link to={l.lien} className="entree-liste">
        <span className="pave-liste"><l.icone size={17} /></span>
        <b>{l.libelle}</b>
        <span className="description-liste">{groupe && <span className="rubrique-az">{groupe}</span>}{l.description}</span>
        <span className="case-pastille">{badge}</span>
        <BoutonFavori lien={l.lien} actif={favori} onBasculer={onFavori} />
      </Link>
    );
  }
  return (
    <Link to={l.lien} className="entree-tuile">
      <span className="pave-tuile"><l.icone size={22} /></span>
      <span className="texte-tuile"><b>{l.libelle}</b><span>{l.description}</span></span>
      {badge}
      <BoutonFavori lien={l.lien} actif={favori} onBasculer={onFavori} />
    </Link>
  );
}

/** Page « Menu » : toutes les fonctions du logiciel, en tuiles, en liste ou en icônes, avec favoris et pages récentes. */
export default function Menu() {
  const { estAdmin, licence } = useSession();
  const { data: compteurs } = useCompteurs(true);
  const [vue, setVue] = useState(lireVue);
  const [tri, setTri] = useState(lireTri);
  const [recherche, setRecherche] = useState("");
  const [favoris, setFavoris] = useState(lireFavoris);
  const choisirVue = (v) => { setVue(v); try { localStorage.setItem("bm-vue-menu", v); } catch { /* sans effet */ } };
  const choisirTri = (t) => { setTri(t); try { localStorage.setItem("bm-tri-menu", t); } catch { /* sans effet */ } };

  const groupes = useMemo(() => {
    const q = sansAccents(recherche.trim());
    return NAV.filter((x) => x.liens).map((g) => ({
      ...g,
      liens: g.liens.filter((l) => lienVisible(l, estAdmin, licence) && (!q || sansAccents(`${l.libelle} ${l.description} ${g.groupe}`).includes(q))).sort(parNom),
    })).filter((g) => g.liens.length);
  }, [recherche, estAdmin, licence]);
  const accueil = NAV.filter((x) => x.lien && x.lien !== "/menu");
  // Vue A à Z : toutes les fonctions visibles, triées par libellé et regroupées par initiale
  const alphabet = useMemo(() => {
    const q = sansAccents(recherche.trim());
    const tous = [
      ...accueil.map((l) => ({ ...l, groupe: "Accueil" })),
      ...groupes.flatMap((g) => g.liens.map((l) => ({ ...l, groupe: g.groupe }))),
    ].filter((l, i, t) => t.findIndex((x) => x.lien === l.lien) === i && (!q || sansAccents(`${l.libelle} ${l.description} ${l.groupe}`).includes(q)));
    tous.sort((a, b) => a.libelle.localeCompare(b.libelle, "fr", { sensitivity: "base" }));
    const parLettre = {};
    tous.forEach((l) => (parLettre[sansAccents(l.libelle)[0].toUpperCase()] ||= []).push(l));
    return Object.entries(parLettre);
  }, [groupes, recherche]); // eslint-disable-line
  const visibles = (chemins) => chemins.map(trouverLien).filter((l) => l && lienVisible(l, estAdmin, licence));
  const mesFavoris = visibles(favoris).sort(parNom);
  const mesRecents = visibles(recents()).filter((l) => !favoris.includes(l.lien)).slice(0, 4);
  const entree = (l) => <Entree key={l.lien} l={l} vue={vue} groupe={tri === "az" && vue === "liste" ? l.groupe : undefined} compteur={l.compteur ? compteurs?.[l.compteur] : 0} favori={favoris.includes(l.lien)} onFavori={(lien) => setFavoris(basculerFavori(lien))} />;
  const rangee = (liens) => <div className={`menu-vue-${vue}`}>{liens.map(entree)}</div>;

  return (
    <>
      <EntetePage titre="Menu" soustitre="Toutes les fonctions de BailManager. Épinglez vos favoris avec l'étoile ; l'ordre et la vue choisis sont mémorisés." />
      <div className="barre-menu">
        <label className="recherche-menu">
          <Search size={16} />
          <input value={recherche} onChange={(e) => setRecherche(e.target.value)} placeholder="Filtrer : encaisser, caution, visite…" aria-label="Filtrer le menu" />
        </label>
        <div className="reglages-menu">
          <div className="segments" role="radiogroup" aria-label="Ordre du menu">
            {TRIS.map((t) => (
              <button key={t.valeur} type="button" role="radio" aria-checked={tri === t.valeur} className={tri === t.valeur ? "actif" : ""} onClick={() => choisirTri(t.valeur)}>
                <t.icone size={15} /> {t.libelle}
              </button>
            ))}
          </div>
          <div className="segments" role="tablist" aria-label="Vue du menu">
            {VUES.map((v) => (
              <button key={v.valeur} type="button" role="tab" aria-selected={vue === v.valeur} className={vue === v.valeur ? "actif" : ""} onClick={() => choisirVue(v.valeur)}>
                <v.icone size={15} /> {v.libelle}
              </button>
            ))}
          </div>
        </div>
      </div>

      {tri === "az" ? (
        alphabet.length === 0 ? <Vide icone={Search} titre="Aucune fonction ne correspond" texte="Essayez un autre mot." /> : (
          <>
            <nav className="index-az" aria-label="Aller à la lettre">
              {"ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("").map((c) => {
                const present = alphabet.some(([lettre]) => lettre === c);
                return present
                  ? <a key={c} href={`#lettre-${c}`} onClick={(e) => { e.preventDefault(); document.getElementById(`lettre-${c}`)?.scrollIntoView({ behavior: "smooth", block: "start" }); }}>{c}</a>
                  : <span key={c} aria-hidden="true">{c}</span>;
              })}
            </nav>
            <div className="groupes-menu">
              {alphabet.map(([lettre, liens]) => (
                <section key={lettre} id={`lettre-${lettre}`} className="groupe-menu lettre-az">
                  <h2><span className="lettre">{lettre}</span></h2>
                  {rangee(liens)}
                </section>
              ))}
            </div>
          </>
        )
      ) : (
      <>
      {!recherche && (
        <div className="menu-raccourcis">
          {mesFavoris.length > 0 && (
            <section className="groupe-menu">
              <h2><Star size={16} /> Favoris</h2>
              {rangee(mesFavoris)}
            </section>
          )}
          <section className="groupe-menu">
            <h2><Clock size={16} /> {mesRecents.length ? "Récemment ouverts" : "Pour commencer"}</h2>
            {rangee(mesRecents.length ? mesRecents : [...accueil, ...visibles(["/encaissements", "/contrats", "/recouvrement"])].sort(parNom))}
          </section>
        </div>
      )}

      {groupes.length === 0 ? (
        <Vide icone={Search} titre="Aucune fonction ne correspond" texte="Essayez un autre mot." />
      ) : (
        <div className={`groupes-menu vue-${vue}`}>
          {groupes.map((g) => (
            <section key={g.groupe} className="groupe-menu">
              <h2><g.icone size={16} /> {g.groupe}</h2>
              {rangee(g.liens)}
            </section>
          ))}
        </div>
      )}
      </>
      )}
    </>
  );
}
