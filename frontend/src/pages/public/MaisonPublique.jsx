import { useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, CalendarCheck, ChevronLeft, ChevronRight, Home, Images, MapPin, Send } from "lucide-react";
import { api } from "../../lib/api";
import { fcfa, isoDate } from "../../lib/format";
import { Bouton, Chargement, Encart, Saisie, Zone } from "../../components/ui";
import Site, { BandeauPage } from "./Site";
import { EtiquetteEtat } from "./Accueil";
import Visionneuse from "../../components/Visionneuse";
import Partage from "../../components/Partage";

// Mosaïque (grande photo + 4 vignettes) sur grand écran, défilement au doigt sur mobile ; clic = plein écran
function Galerie({ photos, titre }) {
  const [vue, setVue] = useState(null);
  const [i, setI] = useState(0);
  const toucher = useRef(null);
  const n = photos.length;
  if (!n) return <div className="galerie-vide"><Home size={48} strokeWidth={1.2} /><span>Photos bientôt disponibles</span></div>;
  const reste = n - 5;
  return (
    <>
      <div className={`mosaique n${Math.min(n, 5)}`}>
        {photos.slice(0, 5).map((src, k) => (
          <button key={src} onClick={() => setVue(k)} aria-label={`Agrandir la photo ${k + 1}`}>
            <img src={src} alt={k === 0 ? titre : ""} loading={k === 0 ? "eager" : "lazy"} />
            {k === 4 && reste > 0 && <span className="plus">+{reste}</span>}
          </button>
        ))}
        <button className="tout-voir" onClick={() => setVue(0)}><Images size={16} /> Voir les {n} photo{n > 1 ? "s" : ""}</button>
      </div>
      <div
        className="carrousel-mobile"
        onTouchStart={(e) => { toucher.current = e.touches[0].clientX; }}
        onTouchEnd={(e) => {
          const dx = e.changedTouches[0].clientX - (toucher.current ?? 0);
          if (Math.abs(dx) > 40) setI((x) => (x + (dx < 0 ? 1 : -1) + n) % n);
        }}
      >
        <img src={photos[i]} alt={titre} onClick={() => setVue(i)} />
        {n > 1 && (
          <>
            <button className="fleche gauche" onClick={() => setI((i - 1 + n) % n)} aria-label="Photo précédente"><ChevronLeft size={20} /></button>
            <button className="fleche droite" onClick={() => setI((i + 1) % n)} aria-label="Photo suivante"><ChevronRight size={20} /></button>
          </>
        )}
        <span className="compteur"><Images size={14} /> {i + 1} / {n}</span>
      </div>
      {vue !== null && <Visionneuse photos={photos} depart={vue} titre={titre} onFermer={() => setVue(null)} />}
    </>
  );
}

export default function MaisonPublique() {
  const { id } = useParams();
  const { data: m, isLoading, error } = useQuery({ queryKey: ["maison-publique", id], queryFn: () => api.get(`/public/maisons/${id}/`) });
  const [f, setF] = useState({ nom_visiteur: "", tel_visiteur: "", date_visite: isoDate(new Date(Date.now() + 86400000)), message: "" });
  const [etat, setEtat] = useState({ envoi: false, ok: "", erreur: "" });
  const reserver = async (e) => {
    e.preventDefault();
    setEtat({ envoi: true, ok: "", erreur: "" });
    try {
      const r = await api.post("/public/reservations/", { ...f, maison: id });
      setEtat({ envoi: false, ok: r.detail, erreur: "" });
    } catch (err) {
      setEtat({ envoi: false, ok: "", erreur: err.message });
    }
  };
  if (isLoading) return <Site surPhoto={false}><Chargement /></Site>;
  if (error) return (
    <Site titre="Bien indisponible" surPhoto={false}>
      <section className="site-section"><Encart ton="alerte">Ce bien n'est plus proposé. <Link to="/nos-maisons">Voir les biens disponibles</Link></Encart></section>
    </Site>
  );
  return (
    <Site titre={m.designation}>
      <BandeauPage
        image={m.images[0] || "/vitrine/maison-1.jpg"}
        fil={<Link to="/nos-maisons" className="bandeau-retour"><ArrowLeft size={15} /> Tous les biens</Link>}
        surTitre={m.type_maison || "À louer"}
        titre={m.designation}
      >
        <div className="bandeau-infos">
          <EtiquetteEtat occupee={m.occupee} />
          {m.adresse && <span className="bandeau-adresse"><MapPin size={16} /> {m.adresse}</span>}
        </div>
      </BandeauPage>
      <section className="site-section remonte">
        <Galerie photos={m.images} titre={m.designation} />
        <div className="fiche-bien">
          <div>
            {m.description && (
              <div className="bloc-description">
                <h2>Description</h2>
                <p>{m.description}</p>
              </div>
            )}
          </div>
          <aside className="carte-reservation">
            <div className="prix">{fcfa(m.loyer)} <span>/ mois</span></div>
            <p className="discret">À l'entrée : {m.condition} mois de loyer (avance et caution), soit <b>{fcfa(m.condition * m.loyer)}</b>, plus les frais d'agence.</p>
            <Partage m={m} />
            {m.occupee ? (
              <div className="encart-occupee">
                <Encart ton="attention" titre="Maison actuellement occupée">Elle fait l'objet d'un bail en cours : les visites ne sont pas possibles pour le moment.</Encart>
                <Link to="/nos-maisons?etat=disponible" className="btn primaire bloc grand">Voir les maisons disponibles</Link>
              </div>
            ) : etat.ok ? (
              <Encart ton="ok" titre="Demande envoyée">{etat.ok}</Encart>
            ) : (
              <form onSubmit={reserver} className="pile">
                <h3 className="ligne-flex" style={{ gap: 8 }}><CalendarCheck size={18} /> Réserver une visite</h3>
                {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
                <Saisie libelle="Nom complet" requis valeur={f.nom_visiteur} onChange={(v) => setF({ ...f, nom_visiteur: v })} />
                <Saisie libelle="Téléphone" type="tel" placeholder="07 00 00 00 00" requis valeur={f.tel_visiteur} onChange={(v) => setF({ ...f, tel_visiteur: v })} />
                <Saisie libelle="Date de visite souhaitée" type="date" requis min={isoDate()} valeur={f.date_visite} onChange={(v) => setF({ ...f, date_visite: v })} />
                <Zone libelle="Message" valeur={f.message} onChange={(v) => setF({ ...f, message: v })} rows={2} />
                <Bouton type="submit" variante="primaire" chargement={etat.envoi} icone={Send} className="bloc grand">Envoyer la demande</Bouton>
              </form>
            )}
          </aside>
        </div>
      </section>
    </Site>
  );
}
