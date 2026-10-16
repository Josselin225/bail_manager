import { useState } from "react";
import { useParams } from "react-router-dom";
import { KeyRound, LogIn } from "lucide-react";
import { api } from "../../lib/api";
import { Bouton, Encart, LienBouton, Saisie } from "../../components/ui";
import AuthCadre from "./AuthCadre";

export default function Reinitialiser() {
  const { uid, jeton } = useParams();
  const [f, setF] = useState({ password: "", confirmation: "" });
  const [etat, setEtat] = useState({});
  const envoyer = async (e) => {
    e.preventDefault();
    if (f.password !== f.confirmation) return setEtat({ erreur: "Les deux mots de passe ne correspondent pas." });
    setEtat({ envoi: true });
    try {
      setEtat({ ok: (await api.post("/auth/reinitialiser/", { uid, jeton, password: f.password })).detail });
    } catch (err) {
      setEtat({ erreur: err.message });
    }
  };
  return (
    <AuthCadre>
      <form onSubmit={envoyer}>
        <h1>Nouveau mot de passe</h1>
        {etat.ok ? <><Encart ton="ok">{etat.ok}</Encart><LienBouton to="/connexion" variante="primaire" icone={LogIn} className="bloc grand">Se connecter</LienBouton></> : (
          <>
            {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
            <Saisie libelle="Nouveau mot de passe" type="password" requis aide="8 caractères minimum." valeur={f.password} onChange={(password) => setF({ ...f, password })} autoComplete="new-password" />
            <Saisie libelle="Confirmer le mot de passe" type="password" requis valeur={f.confirmation} onChange={(confirmation) => setF({ ...f, confirmation })} autoComplete="new-password" />
            <Bouton type="submit" variante="primaire" icone={KeyRound} className="bloc grand" chargement={etat.envoi}>Changer le mot de passe</Bouton>
          </>
        )}
      </form>
    </AuthCadre>
  );
}
