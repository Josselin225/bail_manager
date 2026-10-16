import { useState } from "react";
import { Mail, MapPin, Phone, Send } from "lucide-react";
import { api } from "../../lib/api";
import { Bouton, Encart, Saisie, Zone } from "../../components/ui";
import Site, { BandeauPage, useVitrine } from "./Site";

export default function Contact() {
  const { data } = useVitrine();
  const e = data?.entreprise || {};
  const vide = { nom: "", email: "", telephone: "", sujet: "", message: "" };
  const [f, setF] = useState(vide);
  const [etat, setEtat] = useState({});
  const maj = (k) => (v) => setF({ ...f, [k]: v });
  const envoyer = async (ev) => {
    ev.preventDefault();
    setEtat({ envoi: true });
    try {
      const r = await api.post("/public/contact/", f);
      setEtat({ ok: r.detail });
      setF(vide);
    } catch (err) {
      setEtat({ erreur: err.message });
    }
  };
  const coordonnees = [
    e.telephone && { icone: Phone, titre: "Téléphone", valeur: e.telephone, lien: `tel:${e.telephone.replace(/\s/g, "")}` },
    e.email && { icone: Mail, titre: "E-mail", valeur: e.email, lien: `mailto:${e.email}` },
    e.adresse && { icone: MapPin, titre: "Adresse", valeur: e.adresse },
  ].filter(Boolean);
  return (
    <Site titre="Contact">
      <BandeauPage image="/vitrine/maison-4.jpg" surTitre="Contact" titre="Parlons de votre projet" texte="Bailleur, futur locataire ou partenaire : écrivez-nous, nous répondons rapidement." />
      <section className="site-section remonte contact-grille">
        <div className="contact-infos">
          {coordonnees.map(({ icone: I, titre, valeur, lien }) => (
            <div key={titre} className="carte-contact">
              <div className="pastille-icone"><I size={20} /></div>
              <div>
                <div className="discret petit-texte">{titre}</div>
                {lien ? <a href={lien} className="gras">{valeur}</a> : <div className="gras">{valeur}</div>}
              </div>
            </div>
          ))}
        </div>
        <form onSubmit={envoyer} className="carte-formulaire">
          <h2>Envoyer un message</h2>
          {etat.ok && <Encart ton="ok">{etat.ok}</Encart>}
          {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
          <div className="formulaire">
            <Saisie libelle="Nom complet" requis valeur={f.nom} onChange={maj("nom")} />
            <Saisie libelle="E-mail" type="email" requis valeur={f.email} onChange={maj("email")} />
            <Saisie libelle="Téléphone" type="tel" placeholder="07 00 00 00 00" valeur={f.telephone} onChange={maj("telephone")} />
            <Saisie libelle="Sujet" valeur={f.sujet} onChange={maj("sujet")} />
            <Zone libelle="Message" requis valeur={f.message} onChange={maj("message")} rows={6} />
          </div>
          <Bouton type="submit" variante="primaire" icone={Send} chargement={etat.envoi} className="grand">Envoyer le message</Bouton>
        </form>
      </section>
    </Site>
  );
}
