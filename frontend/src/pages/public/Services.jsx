import { CheckCircle2, KeyRound, Lightbulb, Scale } from "lucide-react";
import Site, { AppelAction, BandeauPage } from "./Site";

const SERVICES = [
  { icone: KeyRound, image: "/vitrine/maison-2.jpg", titre: "Gestion locative", texte: "Recherche et sélection de locataires, rédaction et signature des baux, états des lieux d'entrée et de sortie, encaissement des loyers, quittances et relances en cas de retard, suivi de l'entretien courant.", points: ["Reversement net, commission déduite", "Relevé de compte à tout moment", "Cautions conservées et décomptées à la sortie"] },
  { icone: Lightbulb, image: "/vitrine/maison-5.jpg", titre: "Conseil aux bailleurs", texte: "Fixation d'un loyer juste, valorisation du bien avant mise en location, choix du bon profil de locataire, conseils sur la révision triennale du loyer.", points: ["Estimation du loyer de marché", "Mise en avant sur notre site", "Suivi du taux d'occupation"] },
  { icone: Scale, image: "/vitrine/maison-4.jpg", titre: "Assistance juridique", texte: "Baux conformes à la loi n° 2019-576, accompagnement en cas de litige, procédures de recouvrement, gestion des préavis et résiliations.", points: ["Clauses à jour de la loi ivoirienne", "Préavis de trois mois respecté", "Solde de tout compte documenté"] },
];

export default function Services() {
  return (
    <Site titre="Nos services">
      <BandeauPage image="/vitrine/maison-2.jpg" surTitre="Nos services" titre="Confiez-nous votre bien, gardez une vue claire sur chaque franc." />
      <section className="site-section">
        <div className="liste-services">
          {SERVICES.map(({ icone: I, image, titre, texte, points }, k) => (
            <article key={titre} className={`service-detail ${k % 2 ? "inverse" : ""}`}>
              <div className="service-photo"><img src={image} alt="" loading="lazy" /></div>
              <div className="service-texte">
                <div className="pastille-icone"><I size={22} /></div>
                <h2>{titre}</h2>
                <p>{texte}</p>
                <ul className="liste-coches">{points.map((p) => <li key={p}><CheckCircle2 size={17} /> {p}</li>)}</ul>
              </div>
            </article>
          ))}
        </div>
      </section>
      <AppelAction titre="Discutons de votre projet" texte="Un bien à confier, une question sur la gestion ? Nous vous répondons rapidement." />
    </Site>
  );
}
