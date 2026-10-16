import { useEffect, useState } from "react";
import { Link, Navigate, useLocation, useNavigate } from "react-router-dom";
import { LogIn } from "lucide-react";
import { useSession } from "../../lib/session";
import { Bouton, Encart, Saisie } from "../../components/ui";
import AuthCadre from "./AuthCadre";

export default function Connexion() {
  const { utilisateur, connexion, pret } = useSession();
  const loc = useLocation();
  const nav = useNavigate();
  const [f, setF] = useState({ email: "", password: "" });
  const [etat, setEtat] = useState({});
  useEffect(() => {
    document.title = "Connexion — BailManager";
  }, []);
  if (pret && utilisateur) return <Navigate to={loc.state?.retour || "/tableau-de-bord"} replace />;
  const envoyer = async (e) => {
    e.preventDefault();
    setEtat({ envoi: true });
    try {
      await connexion(f.email, f.password);
      nav(loc.state?.retour || "/tableau-de-bord", { replace: true });
    } catch (err) {
      setEtat({ erreur: err.message });
    }
  };
  return (
    <AuthCadre>
      <form onSubmit={envoyer}>
        <div>
          <h1>Connexion</h1>
          <p className="discret" style={{ marginTop: 4 }}>Espace réservé au personnel de l'agence.</p>
        </div>
        {loc.state?.expire && !etat.erreur && <Encart ton="attention">Votre session a expiré après une période d'inactivité. Reconnectez-vous.</Encart>}
        {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
        <Saisie libelle="E-mail" type="email" requis autoFocus autoComplete="username" valeur={f.email} onChange={(email) => setF({ ...f, email })} />
        <Saisie libelle="Mot de passe" type="password" requis autoComplete="current-password" valeur={f.password} onChange={(password) => setF({ ...f, password })} />
        <Bouton type="submit" variante="primaire" icone={LogIn} chargement={etat.envoi} className="bloc grand">Se connecter</Bouton>
        <div className="ligne-flex" style={{ justifyContent: "space-between" }}>
          <Link to="/mot-de-passe-oublie">Mot de passe oublié ?</Link>
          <span className="ligne-flex" style={{ gap: 14 }}><Link to="/espace-locataire">Je suis locataire</Link><Link to="/espace-bailleur">Je suis propriétaire</Link></span>
        </div>
      </form>
    </AuthCadre>
  );
}
