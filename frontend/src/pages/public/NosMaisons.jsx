import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Search } from "lucide-react";
import { api } from "../../lib/api";
import { Chargement } from "../../components/ui";
import Site, { AppelAction, BandeauPage, useVitrine } from "./Site";
import { CarteBien } from "./Accueil";

export default function NosMaisons() {
  const { data: v } = useVitrine();
  const [params] = useSearchParams();
  const [f, setF] = useState({ q: params.get("q") || "", type: params.get("type") || "", loyer_max: params.get("loyer_max") || "", etat: params.get("etat") || "" });
  const { data, isLoading } = useQuery({ queryKey: ["maisons-publiques", f], queryFn: () => api.get("/public/maisons/", f), placeholderData: (p) => p });
  const etats = [
    ["", "Toutes", v ? v.nb_disponibles + v.nb_occupees : null],
    ["disponible", "Disponibles", v?.nb_disponibles],
    ["occupee", "Occupées", v?.nb_occupees],
  ];
  return (
    <Site titre="Nos maisons">
      <BandeauPage image="/vitrine/maison-1.jpg" surTitre="Nos maisons" titre="Les maisons que nous gérons" texte="Les maisons disponibles peuvent être visitées : réservez en ligne, nous vous rappelons pour confirmer le rendez-vous." />
      <section className="site-section remonte">
        <div className="filtres-vitrine">
          <label className="recherche">
            <Search size={18} />
            <input placeholder="Quartier, commune, désignation…" value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} />
          </label>
          <select value={f.type} onChange={(e) => setF({ ...f, type: e.target.value })} aria-label="Type de bien">
            <option value="">Tous les types</option>
            {(v?.types || []).map((t) => <option key={t}>{t}</option>)}
          </select>
          <select value={f.loyer_max} onChange={(e) => setF({ ...f, loyer_max: e.target.value })} aria-label="Budget mensuel">
            <option value="">Tous les loyers</option>
            {[50000, 100000, 150000, 250000, 500000].map((x) => <option key={x} value={x}>Jusqu'à {x.toLocaleString("fr-FR")} FCFA</option>)}
          </select>
        </div>
        <div className="barre-etat">
          <div className="pilules" role="tablist" aria-label="Filtrer par disponibilité">
            {etats.map(([cle, libelle, n]) => (
              <button key={cle} role="tab" aria-selected={f.etat === cle} className={f.etat === cle ? "actif" : ""} onClick={() => setF({ ...f, etat: cle })}>
                {cle && <i className={`point ${cle}`} />}{libelle}{n != null && <span>{n}</span>}
              </button>
            ))}
          </div>
          {data && <span className="discret">{data.length} maison{data.length > 1 ? "s" : ""} affichée{data.length > 1 ? "s" : ""}</span>}
        </div>
        {isLoading ? <Chargement /> : data?.length ? <div className="cartes-biens">{data.map((m) => <CarteBien key={m.id} m={m} />)}</div> : (
          <div className="vide-vitrine">
            <h3>Aucune maison ne correspond à ces critères</h3>
            <p className="discret">Élargissez votre recherche ou <Link to="/contact">laissez-nous vos coordonnées</Link> : nous vous appellerons dès qu'un logement correspond.</p>
          </div>
        )}
      </section>
      <AppelAction titre="Vous ne trouvez pas votre bonheur ?" texte="Dites-nous ce que vous cherchez : nous vous prévenons dès qu'un logement correspondant se libère." />
    </Site>
  );
}
