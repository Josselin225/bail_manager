import { useState } from "react";
import { Mail, Reply, Send, Trash2 } from "lucide-react";
import { api } from "../../lib/api";
import { dateHeure } from "../../lib/format";
import { useAction, useListe } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import { Badge, Bouton, EntetePage, FenetreFormulaire, Pagination, Panneau, Recherche, Segments, Vide, Zone, useConfirmer } from "../../components/ui";

export default function Messages() {
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const [ouvert, setOuvert] = useState(null);
  const [repondre, setRepondre] = useState(null);
  const [texte, setTexte] = useState("");
  const liste = useListe("/messages/", { search: "", lu: "" });
  const marquer = useAction((m) => api.patch(`/messages/${m.id}/`, { lu: !m.lu }));
  const suppr = useAction((m) => api.delete(`/messages/${m.id}/`), { succes: "Message supprimé." });
  const ouvrir = (m) => {
    setOuvert(ouvert === m.id ? null : m.id);
    if (!m.lu) marquer.mutate(m);
  };
  return (
    <>
      <EntetePage titre="Messages du site" soustitre="Messages envoyés depuis la page Contact." />
      <div className="barre-filtres">
        <Recherche valeur={liste.filtres.search} onChange={(search) => liste.changer({ search })} placeholder="Nom, e-mail, sujet…" />
        <Segments valeur={liste.filtres.lu} onChange={(lu) => liste.changer({ lu })} options={[{ valeur: "", libelle: "Tous" }, { valeur: "false", libelle: "Non lus" }, { valeur: "true", libelle: "Lus" }]} />
      </div>
      <Panneau serre>
        {liste.lignes.length === 0 && !liste.isLoading && <Vide icone={Mail} titre="Aucun message" />}
        {liste.lignes.map((m) => (
          <div key={m.id} style={{ borderBottom: "1px solid var(--ligne)", padding: "14px 18px", background: m.lu ? undefined : "var(--info-pale)" }}>
            <div className="ligne-flex" style={{ cursor: "pointer" }} onClick={() => ouvrir(m)}>
              {!m.lu && <Badge ton="info">Nouveau</Badge>}
              <b>{m.nom_visiteur}</b>
              <span className="discret">{m.email_visiteur}{m.telephone && ` — ${m.telephone}`}</span>
              <span className="espace" />
              <span className="discret petit-texte">{dateHeure(m.date_envoi)}</span>
            </div>
            <div className="gras" style={{ marginTop: 4 }}>{m.sujet || "(sans sujet)"}</div>
            <div className={ouvert === m.id ? "" : "tronque discret"} style={{ whiteSpace: ouvert === m.id ? "pre-wrap" : undefined, marginTop: 2 }}>{m.contenu}</div>
            {ouvert === m.id && (
              <div className="ligne-flex" style={{ marginTop: 10 }}>
                <Bouton taille="petit" icone={Reply} onClick={() => (setTexte(""), setRepondre(m))}>Répondre par e-mail</Bouton>
                <Bouton taille="petit" variante="fantome" onClick={() => marquer.mutate(m)}>Marquer comme {m.lu ? "non lu" : "lu"}</Bouton>
                {estAdmin && <Bouton taille="petit" variante="fantome" icone={Trash2} onClick={async () => (await confirmer({ message: "Supprimer ce message ?", danger: true })) && suppr.mutate(m)}>Supprimer</Bouton>}
              </div>
            )}
          </div>
        ))}
        <Pagination page={liste.page} pages={liste.pages} total={liste.total} onPage={liste.allerPage} />
      </Panneau>
      <FenetreFormulaire titre={`Répondre à ${repondre?.nom_visiteur || ""}`} ouvert={!!repondre} onFermer={() => setRepondre(null)} libelleValider="Envoyer la réponse" icone={Send} onValider={async () => (await api.post(`/messages/${repondre.id}/repondre/`, { contenu: texte }), "Réponse envoyée.")}>
        <div className="rappel-fenetre"><span className="discret">À :</span> <b>{repondre?.email_visiteur}</b></div>
        <div className="formulaire"><Zone libelle="Réponse" requis valeur={texte} onChange={setTexte} rows={7} /></div>
      </FenetreFormulaire>
    </>
  );
}
