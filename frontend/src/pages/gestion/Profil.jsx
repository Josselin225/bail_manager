import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { KeyRound, Save } from "lucide-react";
import { api } from "../../lib/api";
import { dateHeure } from "../../lib/format";
import { useSession } from "../../lib/session";
import { Avatar, Bouton, EntetePage, Fichier, Onglets, Panneau, Saisie, useToast } from "../../components/ui";

export default function Profil() {
  const { utilisateur, rafraichir } = useSession();
  const [params, setParams] = useSearchParams();
  const onglet = params.get("onglet") || "profil";
  const toast = useToast();
  const [f, setF] = useState({});
  const [mdp, setMdp] = useState({ ancien: "", nouveau: "", confirmation: "" });
  const [envoi, setEnvoi] = useState(false);
  useEffect(() => { utilisateur && setF({ nom_complet: utilisateur.nom_complet, email: utilisateur.email, session_timeout_minutes: utilisateur.session_timeout_minutes, photo: null }); }, [utilisateur]);
  const action = async (fn, msg) => {
    setEnvoi(true);
    try {
      await fn();
      toast.succes(msg);
      rafraichir();
    } catch (e) {
      toast.erreur(e.message);
    } finally {
      setEnvoi(false);
    }
  };
  return (
    <>
      <EntetePage titre="Mon profil" soustitre={`Dernière connexion : ${dateHeure(utilisateur?.dernier_acces)}`} />
      <Onglets actif={onglet} onChange={(o) => setParams({ onglet: o })} onglets={[{ cle: "profil", libelle: "Informations" }, { cle: "securite", libelle: "Mot de passe" }]} />
      <div style={{ maxWidth: 640 }}>
        {onglet === "profil" ? (
          <Panneau>
            <form className="pile" onSubmit={(e) => (e.preventDefault(), action(() => api.patch("/auth/profil/", f.photo ? f : { ...f, photo: undefined }), "Profil mis à jour."))}>
              <div className="identite"><Avatar nom={utilisateur?.nom_complet} src={utilisateur?.photo_profil} grand /><div><b>{utilisateur?.nom_complet}</b><div className="discret">{utilisateur?.role === "admin" ? "Administrateur" : "Agent"}</div></div></div>
              <div className="formulaire">
                <Saisie libelle="Nom complet" valeur={f.nom_complet} onChange={(v) => setF({ ...f, nom_complet: v })} requis />
                <Saisie libelle="E-mail" type="email" valeur={f.email} onChange={(v) => setF({ ...f, email: v })} requis />
                <Saisie libelle="Déconnexion après inactivité" type="number" min="1" max="480" suffixe="min" valeur={f.session_timeout_minutes} onChange={(v) => setF({ ...f, session_timeout_minutes: v })} aide="Entre 1 et 480 minutes." />
                <Fichier libelle="Photo de profil" valeur={f.photo} onChange={(v) => setF({ ...f, photo: v })} />
              </div>
              <div><Bouton type="submit" variante="primaire" icone={Save} chargement={envoi}>Enregistrer</Bouton></div>
            </form>
          </Panneau>
        ) : (
          <Panneau>
            <form className="pile" onSubmit={(e) => {
              e.preventDefault();
              if (mdp.nouveau !== mdp.confirmation) return toast.erreur("Les deux mots de passe ne correspondent pas.");
              action(() => api.post("/auth/mot-de-passe/", mdp), "Mot de passe modifié.").then(() => setMdp({ ancien: "", nouveau: "", confirmation: "" }));
            }}>
              <div className="formulaire">
              <Saisie libelle="Mot de passe actuel" plein type="password" valeur={mdp.ancien} onChange={(v) => setMdp({ ...mdp, ancien: v })} requis autoComplete="current-password" />
              <Saisie libelle="Nouveau mot de passe" type="password" valeur={mdp.nouveau} onChange={(v) => setMdp({ ...mdp, nouveau: v })} requis aide="8 caractères minimum." autoComplete="new-password" />
              <Saisie libelle="Confirmer le mot de passe" type="password" valeur={mdp.confirmation} onChange={(v) => setMdp({ ...mdp, confirmation: v })} requis autoComplete="new-password" />
              </div>
              <div><Bouton type="submit" variante="primaire" icone={KeyRound} chargement={envoi}>Changer le mot de passe</Bouton></div>
            </form>
          </Panneau>
        )}
      </div>
    </>
  );
}
