import { useEffect, useRef, useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Building2, DoorOpen, FileText, LogOut, MonitorDown, PenLine, Send, Smartphone, Wrench } from "lucide-react";
import { api } from "../../lib/api";
import { useInstallation } from "../../lib/pwa";
import { MOIS_COURTS, dateFr, dateHeure, dureeMandat, fcfa } from "../../lib/format";
import { Badge, Bouton, Case, Chargement, Encart, FenetreFormulaire, Fichier, Liste, Onglets, Panneau, Saisie, Statut, Tableau, Vide, Zone } from "../../components/ui";
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

const CATEGORIES = [
  { valeur: "plomberie", libelle: "Plomberie (fuite, robinet, WC)" }, { valeur: "electricite", libelle: "Électricité (prise, lumière, disjoncteur)" },
  { valeur: "menuiserie", libelle: "Porte, fenêtre, serrure" }, { valeur: "maconnerie", libelle: "Murs, sol, plafond" },
  { valeur: "climatisation", libelle: "Climatisation" }, { valeur: "peinture", libelle: "Peinture" }, { valeur: "autre", libelle: "Autre" },
];
const TONS_INTERVENTION = { nouvelle: "attention", en_cours: "info", terminee: "ok", annulee: "" };

/** Demandes d'intervention du locataire : suivi et signalement d'un nouveau problème (avec photos). */
function Interventions({ actif }) {
  const { data: demandes = [], refetch } = useQuery({ queryKey: ["interventions-locataire"], queryFn: () => api.get("/portail/interventions/") });
  const vide = { categorie: "plomberie", titre: "", description: "", urgente: false, photos: [] };
  const [ouvert, setOuvert] = useState(false);
  const [f, setF] = useState(vide);
  return (
    <div className="pile">
      {actif && (
        <div className="ligne-flex" style={{ justifyContent: "space-between" }}>
          <span className="discret">Une panne, une fuite, une réparation à faire ? Signalez-la ici : l'agence la prend en charge.</span>
          <Bouton variante="primaire" icone={Wrench} onClick={() => (setF(vide), setOuvert(true))}>Signaler un problème</Bouton>
        </div>
      )}
      {demandes.length === 0 ? <Vide titre="Aucune demande" /> : (
        <div className="liste-mobile">
          {demandes.map((d) => (
            <div key={d.id} className="ligne">
              <div style={{ flex: 1, minWidth: 0 }}>
                <div className="ligne-flex" style={{ justifyContent: "space-between" }}><b>{d.titre}</b><Badge ton={TONS_INTERVENTION[d.statut]}>{d.statut_libelle}</Badge></div>
                <div className="petit-texte discret">{d.reference} — {d.categorie_libelle} — signalée le {dateFr(d.created_at)}{d.date_intervention && ` — intervention le ${dateFr(d.date_intervention)}`}</div>
                {d.commentaire_agence && <div className="petit-texte">Agence : {d.commentaire_agence}</div>}
              </div>
            </div>
          ))}
        </div>
      )}
      <FenetreFormulaire titre="Signaler un problème" ouvert={ouvert} onFermer={() => setOuvert(false)} libelleValider="Envoyer à l'agence" icone={Send}
        onValider={async () => {
          const fd = new FormData();
          Object.entries(f).forEach(([k, v]) => (k === "photos" ? v.forEach((ph) => fd.append("photos", ph)) : fd.append(k, v)));
          await api.post("/portail/interventions/", fd);
          refetch();
          return "Demande envoyée. L'agence vous recontactera.";
        }}>
        <div className="formulaire">
          <Liste libelle="Type de problème" plein valeur={f.categorie} onChange={(categorie) => setF({ ...f, categorie })} options={CATEGORIES} />
          <Saisie libelle="En quelques mots" requis plein valeur={f.titre} onChange={(titre) => setF({ ...f, titre })} placeholder="Ex. : fuite sous l'évier de la cuisine" />
          <Zone libelle="Détails" valeur={f.description} onChange={(description) => setF({ ...f, description })} rows={3} aide="Depuis quand, où exactement, vos disponibilités pour l'intervention…" />
          <Fichier libelle="Photos" multiple valeur={f.photos} onChange={(photos) => setF({ ...f, photos })} plein aide="Facultatif, mais très utile à l'artisan." />
          <Case libelle="C'est urgent" aide="Fuite importante, plus d'électricité, porte qui ne ferme plus…" valeur={f.urgente} onChange={(urgente) => setF({ ...f, urgente })} />
        </div>
      </FenetreFormulaire>
    </div>
  );
}

/** Paiement du loyer par Mobile Money : choix de l'opérateur, puis page de l'agrégateur (ou de simulation en mode test). */
function FormPaiement({ ouvert, onFermer, offre, telephone }) {
  const nav = useNavigate();
  const [f, setF] = useState({ operateur: "", telephone });
  return (
    <FenetreFormulaire titre="Payer mon loyer en ligne" ouvert={ouvert} onFermer={onFermer} libelleValider={`Payer ${fcfa(offre.montant)}`} icone={Smartphone} desactive={!f.operateur}
      onValider={async () => {
        const r = await api.post("/portail/paiements/", f);
        if (/^https?:/.test(r.url)) window.location.assign(r.url);
        else nav(r.url);
      }}>
      {offre.test && <Encart ton="info" titre="Mode test">Aucun argent ne sera débité : le paiement se valide sur une page de simulation.</Encart>}
      <p style={{ margin: 0 }}>Loyer de <b>{offre.periode}</b> : <b>{fcfa(offre.montant)}</b>. La quittance est émise dès la confirmation du paiement.</p>
      <div className="choix-operateurs" role="radiogroup" aria-label="Opérateur">
        {offre.operateurs.map((o) => (
          <button key={o.valeur} type="button" role="radio" aria-checked={f.operateur === o.valeur} className={`operateur op-${o.valeur} ${f.operateur === o.valeur ? "choisi" : ""}`} onClick={() => setF({ ...f, operateur: o.valeur })}>
            <span className="pastille-op" aria-hidden>{o.libelle[0]}</span>{o.libelle}
          </button>
        ))}
      </div>
      <div className="formulaire"><Saisie libelle="Numéro Mobile Money" type="tel" requis plein valeur={f.telephone} onChange={(telephone) => setF({ ...f, telephone })} /></div>
    </FenetreFormulaire>
  );
}

/** Retour de la page de paiement : statut relu auprès du serveur jusqu'à confirmation. */
function ResultatPaiement({ reference, onTermine }) {
  const { data: p } = useQuery({
    queryKey: ["paiement", reference], queryFn: () => api.get(`/portail/paiements/${reference}/`),
    refetchInterval: (q) => (q.state.data?.statut === "en_attente" ? 4000 : false),
  });
  useEffect(() => { p && p.statut !== "en_attente" && onTermine(); }, [p?.statut]); // eslint-disable-line
  if (!p) return null;
  if (p.statut === "reussi" && p.fournisseur === "test") return <Encart ton="info" titre="Paiement simulé (mode test)">Aucun argent n'a été débité et aucun loyer n'a été encaissé : le paiement en ligne de l'agence est en cours d'essai.</Encart>;
  if (p.statut === "reussi") return <Encart ton="ok" titre={`Paiement reçu : ${fcfa(p.montant)}`}>Merci ! {p.recu ? <>Votre quittance n° {p.recu} est disponible ci-dessous.</> : "L'agence va l'imputer sur votre compte."}</Encart>;
  if (p.statut === "echoue") return <Encart ton="alerte" titre="Paiement non abouti">Aucun montant n'a été encaissé. Vous pouvez réessayer.</Encart>;
  return <Encart ton="attention" titre="Paiement en cours de confirmation">Nous attendons la confirmation de votre opérateur ({p.operateur})…</Encart>;
}

/** Signature du bail : le code reçu par SMS vaut signature. */
function SignatureBail({ contrat, onSigne }) {
  const [code, setCode] = useState("");
  const [etat, setEtat] = useState({});
  return (
    <Panneau titre="Signer mon bail" icone={PenLine}>
      <form className="pile" onSubmit={async (e) => {
        e.preventDefault();
        setEtat({ envoi: true });
        try {
          await api.post("/portail/signature/", { code });
          onSigne();
        } catch (err) {
          setEtat({ erreur: err.message });
        }
      }}>
        <p style={{ margin: 0 }}>L'agence vous a envoyé un code par SMS pour signer le bail <b>{contrat.reference}</b> ({contrat.maison}, {fcfa(contrat.loyer)} par mois). Saisir ce code vaut signature.</p>
        {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
        <div className="ligne-flex" style={{ alignItems: "flex-end" }}>
          <Saisie libelle="Code reçu" requis valeur={code} onChange={(v) => setCode(v.replace(/\D/g, "").slice(0, 6))} inputMode="numeric" autoComplete="one-time-code" placeholder="000000" pattern="\d{6}" />
          <Bouton type="submit" variante="primaire" icone={PenLine} chargement={etat.envoi}>Je signe mon bail</Bouton>
        </div>
      </form>
    </Panneau>
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
  const [preavis, setPreavis] = useState(false);
  const [motif, setMotif] = useState("");
  const [payer, setPayer] = useState(false);
  const [params] = useSearchParams();
  const retourPaiement = params.get("paiement") || params.get("transaction_id");
  const defile = useDefile();
  const installer = useInstallation();
  const { data, isLoading, error, refetch } = useQuery({ queryKey: ["espace-locataire"], queryFn: () => api.get("/portail/espace/"), retry: false });
  useEffect(() => {
    document.title = "Espace locataire";
  }, []);
  if (isLoading) return <Chargement plein />;
  if (error || !data) return <ConnexionLocataire onConnecte={() => refetch()} />;
  const actif = data.contrats.find((c) => c.statut === "actif");
  const finPreavis = (() => { const d = new Date(); d.setMonth(d.getMonth() + 3); return d.toLocaleDateString("fr-FR"); })();
  const totalImpaye = actif ? actif.impayes.reduce((s, p) => s + Number(p.reste), 0) : 0;

  return (
    <div className="site sur-photo" style={{ background: "var(--fond)" }}>
      <header className={`site-nav ${defile ? "defile" : "transparent"}`}>
        <Link to="/" className="site-marque"><Marque nom={data.entreprise.nom} /></Link>
        <div className="liens">
          {installer && <Bouton taille="petit" icone={MonitorDown} onClick={installer}>Installer</Bouton>}
          <span className="nom-locataire">{data.locataire.nom}</span>
          <Bouton taille="petit" icone={LogOut} onClick={async () => (await api.post("/portail/deconnexion/"), client.removeQueries({ queryKey: ["espace-locataire"] }), refetch())}>Quitter</Bouton>
        </div>
      </header>
      <BandeauPage image="/vitrine/maison-3.jpg" surTitre="Espace locataire" titre={`Bonjour ${data.locataire.nom.split(" ")[0]}`} texte={actif ? `${actif.maison}${actif.adresse ? ` — ${actif.adresse}` : ""}` : "Aucun bail en cours."} />
      <main className="contenu pile remonte" style={{ maxWidth: 980, margin: "0 auto" }}>
        {retourPaiement && <ResultatPaiement reference={retourPaiement} onTermine={() => refetch()} />}
        {actif?.signature?.en_attente && <SignatureBail contrat={actif} onSigne={() => refetch()} />}
        {actif && (
          <>
            {actif.date_sortie_prevue ? (
              <Encart ton="attention" titre={`Préavis de départ — sortie prévue le ${dateFr(actif.date_sortie_prevue)}`}>
                Notifié le {dateFr(actif.preavis_le)}. L'agence vous contactera pour fixer l'état des lieux de sortie et la remise des clés.
              </Encart>
            ) : (
              <div className="ligne-flex" style={{ justifyContent: "flex-end" }}>
                <Bouton taille="petit" icone={DoorOpen} onClick={() => setPreavis(true)}>Donner mon préavis de départ</Bouton>
              </div>
            )}
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
              <Encart ton="alerte" titre={`${fcfa(totalImpaye)} restent à régler`}>Mois concernés : {actif.impayes.map((p) => p.libelle).join(", ")}. {data.paiement_en_ligne ? "Réglez-les en ligne ou à l'agence." : "Rapprochez-vous de l'agence pour régulariser."}</Encart>
            ) : (
              <Encart ton="ok" titre="Vous êtes à jour de vos loyers">Prochaine échéance le {dateFr(actif.date_prochain_loyer)}.</Encart>
            )}
            {data.paiement_en_ligne?.montant > 0 && (
              <div className="bandeau-paiement">
                <div className="pastille-icone"><Smartphone size={20} /></div>
                <div className="texte-paiement">
                  <div className="gras">Payer par Mobile Money</div>
                  <div className="petit-texte discret">Wave, Orange Money, MTN MoMo ou Moov Money — {data.paiement_en_ligne.periode} : {fcfa(data.paiement_en_ligne.montant)}</div>
                </div>
                <Bouton variante="primaire" icone={Smartphone} onClick={() => setPayer(true)}>Payer {fcfa(data.paiement_en_ligne.montant)}</Bouton>
              </div>
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
          <Onglets actif={onglet} onChange={setOnglet} onglets={[{ cle: "paiements", libelle: "Paiements et quittances" }, { cle: "charges", libelle: "Charges" }, { cle: "interventions", libelle: "Interventions" }, { cle: "messages", libelle: "Messages", compte: data.messages.length || null }]} />
          {onglet === "paiements" && (
            data.paiements.length === 0 ? <Vide titre="Aucun paiement enregistré" /> : (
              <div className="liste-mobile">
                {data.paiements.map((p) => (
                  <div key={p.id} className="ligne">
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ligne-flex" style={{ justifyContent: "space-between" }}><b>{p.periode}</b><b>{fcfa(p.montant)}</b></div>
                      <div className="petit-texte discret">Payé le {dateFr(p.date)} — {p.mode} — {p.reference}</div>
                    </div>
                    <Link className="btn petit icone" to={`/espace-locataire/quittance/${p.id}`} aria-label="Quittance" title="Quittance"><FileText size={15} /></Link>
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
          {onglet === "interventions" && <Interventions actif={!!actif} />}
          {onglet === "messages" && <Messagerie messages={data.messages} onEnvoye={refetch} />}
        </Panneau>
        {payer && <FormPaiement ouvert offre={data.paiement_en_ligne} telephone={data.locataire.telephone} onFermer={() => setPayer(false)} />}
        <FenetreFormulaire
          titre="Donner mon préavis de départ"
          ouvert={preavis}
          onFermer={() => setPreavis(false)}
          libelleValider="Confirmer mon préavis"
          variante="danger"
          onValider={async () => {
            await api.post("/portail/preavis/", { motif });
            refetch();
            return "Préavis envoyé à l'agence.";
          }}
        >
          <Encart ton="attention">Votre préavis est de trois mois : votre sortie sera prévue le <b>{finPreavis}</b>. Vos loyers restent dus jusqu'à cette date.</Encart>
          <div className="formulaire"><Zone libelle="Motif (facultatif)" valeur={motif} onChange={setMotif} rows={2} /></div>
        </FenetreFormulaire>
        <p className="discret petit-texte centre">{data.entreprise.nom}{data.entreprise.telephone && ` — ${data.entreprise.telephone}`}{data.entreprise.email && ` — ${data.entreprise.email}`}</p>
      </main>
    </div>
  );
}


