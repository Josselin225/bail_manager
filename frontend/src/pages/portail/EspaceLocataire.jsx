import { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Building2, FileText, LogOut, Send } from "lucide-react";
import { api } from "../../lib/api";
import { MOIS_COURTS, dateFr, dateHeure, dureeMandat, fcfa } from "../../lib/format";
import { Bouton, Chargement, Encart, Onglets, Panneau, Saisie, Statut, Tableau, Vide } from "../../components/ui";
import AuthCadre from "../public/AuthCadre";
import { BandeauPage, Marque, useDefile } from "../public/Site";

function ConnexionLocataire({ onConnecte }) {
  const [f, setF] = useState({ telephone: "", code: "" });
  const [etat, setEtat] = useState({});
  return (
    <AuthCadre image="/vitrine/maison-3.jpg" titre="Votre logement, vos paiements, en un coup d'œil." texte="Historique des loyers, quittances à télécharger et messagerie directe avec l'agence, depuis votre téléphone.">
      <form
        onSubmit={async (e) => {
          e.preventDefault();
          setEtat({ envoi: true });
          try {
            await api.post("/portail/connexion/", f);
            onConnecte();
          } catch (err) {
            setEtat({ erreur: err.message });
          }
        }}
      >
        <div>
          <h1>Espace locataire</h1>
          <p className="discret" style={{ marginTop: 4 }}>Consultez vos paiements, téléchargez vos quittances et écrivez à l'agence.</p>
        </div>
        {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
        <Saisie libelle="Téléphone" type="tel" inputMode="tel" requis autoFocus valeur={f.telephone} onChange={(telephone) => setF({ ...f, telephone })} placeholder="07 00 00 00 00" />
        <Saisie libelle="Code d'accès" requis valeur={f.code} onChange={(code) => setF({ ...f, code: code.toUpperCase() })} autoComplete="one-time-code" aide="Remis par l'agence. Vous l'avez perdu ? Demandez-en un nouveau." />
        <Bouton type="submit" variante="primaire" className="bloc grand" chargement={etat.envoi}>Accéder à mon espace</Bouton>
        <Link to="/connexion" className="petit-texte">Vous faites partie de l'agence ? Connexion du personnel</Link>
      </form>
    </AuthCadre>
  );
}

function Messagerie({ messages, onEnvoye }) {
  const [texte, setTexte] = useState("");
  const [envoi, setEnvoi] = useState(false);
  const fin = useRef(null);
  useEffect(() => { fin.current?.scrollIntoView({ block: "end" }); }, [messages.length]);
  return (
    <div style={{ display: "flex", flexDirection: "column", height: 460, border: "1px solid var(--ligne)", borderRadius: 12 }}>
      <div className="fil-messages">
        {messages.length === 0 && <div className="discret centre">Une question sur un paiement, une réparation ? Écrivez à l'agence.</div>}
        {messages.map((m) => (
          <div key={m.id} className={`bulle ${m.expediteur === "locataire" ? "moi" : ""}`}>{m.contenu}<time>{dateHeure(m.date)}</time></div>
        ))}
        <div ref={fin} />
      </div>
      <form className="ligne-flex" style={{ padding: 10, borderTop: "1px solid var(--ligne)", flexWrap: "nowrap" }} onSubmit={async (e) => {
        e.preventDefault();
        if (!texte.trim()) return;
        setEnvoi(true);
        try {
          await api.post("/portail/messages/", { contenu: texte });
          setTexte("");
          onEnvoye();
        } finally {
          setEnvoi(false);
        }
      }}>
        <input className="saisie" value={texte} onChange={(e) => setTexte(e.target.value)} placeholder="Votre message…" />
        <Bouton type="submit" variante="primaire" icone={Send} chargement={envoi} aria-label="Envoyer" />
      </form>
    </div>
  );
}

export default function EspaceLocataire() {
  const client = useQueryClient();
  const [onglet, setOnglet] = useState("paiements");
  const defile = useDefile();
  const { data, isLoading, error, refetch } = useQuery({ queryKey: ["espace-locataire"], queryFn: () => api.get("/portail/espace/"), retry: false });
  useEffect(() => {
    document.title = "Espace locataire";
  }, []);
  if (isLoading) return <Chargement plein />;
  if (error || !data) return <ConnexionLocataire onConnecte={() => refetch()} />;
  const actif = data.contrats.find((c) => c.statut === "actif");
  const totalImpaye = actif ? actif.impayes.reduce((s, p) => s + Number(p.reste), 0) : 0;

  return (
    <div className="site sur-photo" style={{ background: "var(--fond)" }}>
      <header className={`site-nav ${defile ? "defile" : "transparent"}`}>
        <Link to="/" className="site-marque"><Marque nom={data.entreprise.nom} /></Link>
        <div className="liens">
          <span className="nom-locataire">{data.locataire.nom}</span>
          <Bouton taille="petit" icone={LogOut} onClick={async () => (await api.post("/portail/deconnexion/"), client.removeQueries({ queryKey: ["espace-locataire"] }), refetch())}>Quitter</Bouton>
        </div>
      </header>
      <BandeauPage image="/vitrine/maison-3.jpg" surTitre="Espace locataire" titre={`Bonjour ${data.locataire.nom.split(" ")[0]}`} texte={actif ? `${actif.maison}${actif.adresse ? ` — ${actif.adresse}` : ""}` : "Aucun bail en cours."} />
      <main className="contenu pile remonte" style={{ maxWidth: 980, margin: "0 auto" }}>
        {actif && (
          <>
            {actif.gestion && (
              <div className="encart-gestion">
                <div className="pastille-icone"><Building2 size={20} /></div>
                <div>
                  <div className="gras">Gestion de votre logement</div>
                  <div>
                    {actif.gestion.agence} gère votre logement pour le compte de son propriétaire, <b>{actif.gestion.proprietaire}</b>
                    {actif.gestion.mandat
                      ? <> — mandat de gestion n° {actif.gestion.mandat.reference} : {dureeMandat(actif.gestion.mandat).replace(/^J/, "j").replace(/^D/, "d")}.</>
                      : "."}
                  </div>
                  <div className="discret petit-texte">Votre bail reste valable même si ce mandat prend fin : nous vous indiquerions alors par écrit à qui verser vos loyers.</div>
                </div>
              </div>
            )}
            {totalImpaye > 0 ? (
              <Encart ton="alerte" titre={`${fcfa(totalImpaye)} restent à régler`}>Mois concernés : {actif.impayes.map((p) => p.libelle).join(", ")}. Rapprochez-vous de l'agence pour régulariser.</Encart>
            ) : (
              <Encart ton="ok" titre="Vous êtes à jour de vos loyers">Prochaine échéance le {dateFr(actif.date_prochain_loyer)}.</Encart>
            )}
            <div className="registre">
              <div><div className="libelle">Loyer mensuel</div><div className="valeur">{Math.round(actif.loyer).toLocaleString("fr-FR")}<small>FCFA</small></div></div>
              <div><div className="libelle">Prochaine échéance</div><div className="valeur" style={{ fontSize: 19 }}>{dateFr(actif.date_prochain_loyer)}</div><div className="note"><Statut s={actif.statut_paiement} /></div></div>
              <div><div className="libelle">Caution détenue</div><div className="valeur">{Math.round(actif.caution_disponible).toLocaleString("fr-FR")}<small>FCFA</small></div></div>
            </div>
            <Panneau titre="Mes 12 derniers mois">
              <div className="mois-grille">
                {actif.echeancier.map((p) => (
                  <div key={`${p.annee}-${p.mois}`} className={`mois-case ${p.statut}`}>
                    <b>{MOIS_COURTS[p.mois - 1]} {p.annee}</b>
                    {p.statut === "solde" ? "Payé" : `Reste ${Math.round(p.reste).toLocaleString("fr-FR")}`}
                  </div>
                ))}
              </div>
            </Panneau>
          </>
        )}
        <Panneau>
          <Onglets actif={onglet} onChange={setOnglet} onglets={[{ cle: "paiements", libelle: "Paiements et quittances" }, { cle: "charges", libelle: "Charges" }, { cle: "messages", libelle: "Messages", compte: data.messages.length || null }]} />
          {onglet === "paiements" && (
            data.paiements.length === 0 ? <Vide titre="Aucun paiement enregistré" /> : (
              <div className="liste-mobile">
                {data.paiements.map((p) => (
                  <div key={p.id} className="ligne">
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ligne-flex" style={{ justifyContent: "space-between" }}><b>{p.periode}</b><b>{fcfa(p.montant)}</b></div>
                      <div className="petit-texte discret">Payé le {dateFr(p.date)} — {p.mode} — {p.reference}</div>
                    </div>
                    <Link className="btn petit icone" to={`/espace-locataire/quittance/${p.id}`} target="_blank" aria-label="Quittance" title="Quittance"><FileText size={15} /></Link>
                  </div>
                ))}
              </div>
            )
          )}
          {onglet === "charges" && (
            <Tableau
              lignes={data.charges}
              vide={<Vide titre="Aucune charge" />}
              colonnes={[
                { libelle: "Date", rendu: (c) => dateFr(c.date) },
                { libelle: "Type", cle: "type" },
                { libelle: "Détail", cle: "description" },
                { libelle: "Montant", cle: "montant", type: "montant" },
              ]}
            />
          )}
          {onglet === "messages" && <Messagerie messages={data.messages} onEnvoye={refetch} />}
        </Panneau>
        <p className="discret petit-texte centre">{data.entreprise.nom}{data.entreprise.telephone && ` — ${data.entreprise.telephone}`}{data.entreprise.email && ` — ${data.entreprise.email}`}</p>
      </main>
    </div>
  );
}


