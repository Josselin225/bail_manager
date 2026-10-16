import { useState } from "react";
import { Link } from "react-router-dom";
import { Send } from "lucide-react";
import { api } from "../../lib/api";
import { Bouton, Encart, Saisie } from "../../components/ui";
import AuthCadre from "./AuthCadre";

export default function MotDePasseOublie() {
  const [email, setEmail] = useState("");
  const [etat, setEtat] = useState({});
  return (
    <AuthCadre>
      <form onSubmit={async (e) => { e.preventDefault(); setEtat({ envoi: true }); try { setEtat({ ok: (await api.post("/auth/mot-de-passe-oublie/", { email })).detail }); } catch (err) { setEtat({ erreur: err.message }); } }}>
        <div><h1>Mot de passe oublié</h1><p className="discret" style={{ marginTop: 4 }}>Saisissez votre adresse : vous recevrez un lien pour choisir un nouveau mot de passe.</p></div>
        {etat.ok && <Encart ton="ok">{etat.ok}</Encart>}
        {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
        <Saisie libelle="E-mail" type="email" requis autoFocus valeur={email} onChange={setEmail} />
        <Bouton type="submit" variante="primaire" icone={Send} className="bloc grand" chargement={etat.envoi}>Envoyer le lien</Bouton>
        <p className="discret petit-texte">Pas d'e-mail configuré sur le serveur ? Demandez à l'administrateur de réinitialiser votre mot de passe depuis Utilisateurs.</p>
        <Link to="/connexion">Retour à la connexion</Link>
      </form>
    </AuthCadre>
  );
}
