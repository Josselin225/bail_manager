import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { api } from "../../lib/api";
import { dateHeure } from "../../lib/format";
import { useAction } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import { Avatar, Bouton, Case, EntetePage, FenetreFormulaire, Liste, Panneau, Saisie, Statut, Tableau, useConfirmer } from "../../components/ui";

export default function Utilisateurs() {
  const { utilisateur } = useSession();
  const confirmer = useConfirmer();
  const [edit, setEdit] = useState(null);
  const [f, setF] = useState({});
  const { data = [], refetch } = useQuery({ queryKey: ["utilisateurs"], queryFn: () => api.get("/utilisateurs/") });
  const suppr = useAction((u) => api.delete(`/utilisateurs/${u.id}/`), { succes: "Utilisateur supprimé." });
  useEffect(() => {
    if (edit) setF(edit === "nouveau" ? { nom_complet: "", email: "", role: "agent", session_timeout_minutes: 15, is_active: true, password: "" } : { ...edit, password: "" });
  }, [edit]);
  const maj = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  return (
    <>
      <EntetePage titre="Utilisateurs" soustitre="Comptes du personnel. Les agents ne peuvent ni supprimer, ni résilier, ni modifier les paramètres.">
        <Bouton variante="primaire" icone={Plus} onClick={() => setEdit("nouveau")}>Nouvel utilisateur</Bouton>
      </EntetePage>
      <Panneau serre>
        <Tableau
          lignes={data}
          colonnes={[
            { libelle: "Nom", rendu: (u) => <div className="identite"><Avatar nom={u.nom_complet} src={u.photo_profil} /><div><div className="principal-cellule">{u.nom_complet}</div><div className="sous-cellule">{u.email}</div></div></div> },
            { libelle: "Rôle", rendu: (u) => <Statut s={u.role} /> },
            { libelle: "Inactivité", rendu: (u) => `${u.session_timeout_minutes} min` },
            { libelle: "Dernier accès", rendu: (u) => dateHeure(u.dernier_acces) || "Jamais" },
            { libelle: "Compte", rendu: (u) => <Statut s={u.is_active ? "actif" : "termine"} libelle={u.is_active ? "Actif" : "Désactivé"} /> },
            { libelle: "", rendu: (u) => <div className="actions-cellule"><Bouton taille="petit" variante="fantome" icone={Pencil} onClick={() => setEdit(u)} aria-label="Modifier" />{u.id !== utilisateur.id && <Bouton taille="petit" variante="fantome" icone={Trash2} aria-label="Supprimer" onClick={async () => (await confirmer({ message: `Supprimer le compte de ${u.nom_complet} ?`, danger: true })) && suppr.mutate(u)} />}</div> },
          ]}
        />
      </Panneau>
      <FenetreFormulaire
        titre={edit === "nouveau" ? "Nouvel utilisateur" : "Modifier l'utilisateur"}
        ouvert={!!edit}
        onFermer={() => (setEdit(null), refetch())}
        onValider={async () => {
          const d = { nom_complet: f.nom_complet, email: f.email, role: f.role, session_timeout_minutes: f.session_timeout_minutes, is_active: f.is_active };
          if (f.password) d.password = f.password;
          edit === "nouveau" ? await api.post("/utilisateurs/", d) : await api.patch(`/utilisateurs/${edit.id}/`, d);
          return "Utilisateur enregistré.";
        }}
      >
        <div className="formulaire">
          <Saisie libelle="Nom complet" requis valeur={f.nom_complet} onChange={maj("nom_complet")} />
          <Saisie libelle="E-mail" type="email" requis valeur={f.email} onChange={maj("email")} aide="Sert d'identifiant de connexion." />
          <Liste libelle="Rôle" valeur={f.role} onChange={maj("role")} options={[{ valeur: "agent", libelle: "Agent" }, { valeur: "admin", libelle: "Administrateur" }]} />
          <Saisie libelle="Déconnexion après inactivité" type="number" min="1" max="480" suffixe="min" valeur={f.session_timeout_minutes} onChange={maj("session_timeout_minutes")} aide="Entre 1 et 480 minutes." />
          <Saisie libelle={edit === "nouveau" ? "Mot de passe" : "Nouveau mot de passe"} type="password" valeur={f.password} onChange={maj("password")} requis={edit === "nouveau"} aide={edit === "nouveau" ? "8 caractères minimum." : "Laisser vide pour ne pas changer."} autoComplete="new-password" />
          <Case libelle="Compte actif" aide="Un compte désactivé ne peut plus se connecter." valeur={f.is_active} onChange={maj("is_active")} />
        </div>
      </FenetreFormulaire>
    </>
  );
}
