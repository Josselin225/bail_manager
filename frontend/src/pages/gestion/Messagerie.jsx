import { useEffect, useRef, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { MessagesSquare, Send } from "lucide-react";
import { api } from "../../lib/api";
import { dateHeure } from "../../lib/format";
import { useInvalider } from "../../lib/hooks";
import { Avatar, Bouton, EntetePage, Panneau, Vide, useToast } from "../../components/ui";

export default function Messagerie() {
  const [params, setParams] = useSearchParams();
  const actif = params.get("locataire");
  const [texte, setTexte] = useState("");
  const [envoi, setEnvoi] = useState(false);
  const fin = useRef(null);
  const toast = useToast();
  const invalider = useInvalider();
  const { data: convs = [], refetch: rechargerConvs } = useQuery({ queryKey: ["conversations"], queryFn: () => api.get("/messagerie/"), refetchInterval: 30000 });
  const { data: fil, refetch } = useQuery({ queryKey: ["fil", actif], queryFn: () => api.get(`/messagerie/${actif}/`), enabled: !!actif, refetchInterval: 20000 });
  useEffect(() => {
    fin.current?.scrollIntoView({ block: "end" });
    if (fil) invalider("compteurs");
  }, [fil?.messages?.length]); // eslint-disable-line

  const envoyer = async (e) => {
    e.preventDefault();
    if (!texte.trim()) return;
    setEnvoi(true);
    try {
      await api.post(`/messagerie/${actif}/repondre/`, { contenu: texte });
      setTexte("");
      refetch();
      rechargerConvs();
    } catch (err) {
      toast.erreur(err.message);
    } finally {
      setEnvoi(false);
    }
  };

  return (
    <>
      <EntetePage titre="Messagerie locataires" soustitre="Échanges avec les locataires depuis leur espace en ligne." />
      <Panneau serre>
        <div className="messagerie">
          <div className="conversations">
            {convs.length === 0 && <Vide icone={MessagesSquare} titre="Aucune conversation" texte="Les messages envoyés depuis l'espace locataire arrivent ici. Vous pouvez aussi écrire depuis la fiche d'un locataire." />}
            {convs.map((c) => (
              <button key={c.locataire} className={`conversation ${String(c.locataire) === actif ? "actif" : ""}`} onClick={() => setParams({ locataire: c.locataire })}>
                <div className="ligne-flex">
                  <b className="tronque" style={{ flex: 1 }}>{c.nom}</b>
                  {c.non_lus > 0 && <span className="pastille">{c.non_lus}</span>}
                </div>
                <div className="petit-texte discret tronque">{c.expediteur === "agence" ? "Vous : " : ""}{c.dernier_message}</div>
              </button>
            ))}
          </div>
          <div style={{ display: "flex", flexDirection: "column", minHeight: 0 }}>
            {!actif ? (
              <Vide titre="Choisissez une conversation" />
            ) : (
              <>
                {fil && (
                  <div className="panneau-tete">
                    <Avatar nom={fil.locataire.nom} />
                    <div><b>{fil.locataire.nom}</b><div className="petit-texte discret">{fil.locataire.telephone}</div></div>
                  </div>
                )}
                <div className="fil-messages">
                  {fil?.messages.length === 0 && <div className="discret centre">Aucun message. Écrivez le premier.</div>}
                  {fil?.messages.map((m) => (
                    <div key={m.id} className={`bulle ${m.expediteur === "agence" ? "moi" : ""}`}>
                      {m.contenu}
                      <time>{dateHeure(m.created_at)}</time>
                    </div>
                  ))}
                  <div ref={fin} />
                </div>
                <form onSubmit={envoyer} className="ligne-flex" style={{ padding: 12, borderTop: "1px solid var(--ligne)", flexWrap: "nowrap" }}>
                  <textarea className="saisie" rows={2} style={{ minHeight: 44 }} value={texte} onChange={(e) => setTexte(e.target.value)} placeholder="Votre message…" onKeyDown={(e) => e.key === "Enter" && !e.shiftKey && envoyer(e)} />
                  <Bouton type="submit" variante="primaire" icone={Send} chargement={envoi} aria-label="Envoyer" />
                </form>
              </>
            )}
          </div>
        </div>
      </Panneau>
    </>
  );
}
