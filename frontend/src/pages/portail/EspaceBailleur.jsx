import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { Building2, FileText, Home, LogOut, MonitorDown, Printer } from "lucide-react";
import { api } from "../../lib/api";
import { useInstallation } from "../../lib/pwa";
import { compact, dateFr, dureeMandat, fcfa, isoDate, nombre } from "../../lib/format";
import { Badge, Bouton, Chargement, Encart, Onglets, Panneau, Saisie, Statut, Tableau, Vide } from "../../components/ui";
import { PanneauGraphe } from "../../components/Graphes";
import AuthCadre from "../public/AuthCadre";
import { BandeauPage, Marque, useDefile } from "../public/Site";

function ConnexionBailleur({ onConnecte }) {
  const [f, setF] = useState({ telephone: "", code: "" });
  const [etat, setEtat] = useState({});
  return (
    <AuthCadre image="/vitrine/maison-1.jpg" titre="Vos biens, vos loyers, vos versements, en toute transparence." texte="Suivez l'occupation de vos biens, les loyers encaissés pour votre compte et les versements de l'agence, où que vous soyez.">
      <form
        onSubmit={async (e) => {
          e.preventDefault();
          setEtat({ envoi: true });
          try {
            await api.post("/portail-bailleur/connexion/", f);
            onConnecte();
          } catch (err) {
            setEtat({ erreur: err.message });
          }
        }}
      >
        <div>
          <h1>Espace bailleur</h1>
          <p className="discret" style={{ marginTop: 4 }}>Consultez votre compte, vos relevés et l'occupation de vos biens.</p>
        </div>
        {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
        <Saisie libelle="Téléphone" type="tel" inputMode="tel" requis autoFocus valeur={f.telephone} onChange={(telephone) => setF({ ...f, telephone })} placeholder="07 00 00 00 00" />
        <Saisie libelle="Code d'accès" requis valeur={f.code} onChange={(code) => setF({ ...f, code: code.toUpperCase() })} autoComplete="one-time-code" aide="Remis par l'agence. Vous l'avez perdu ? Demandez-en un nouveau." />
        <Bouton type="submit" variante="primaire" className="bloc grand" chargement={etat.envoi}>Accéder à mon espace</Bouton>
        <div className="ligne-flex" style={{ justifyContent: "space-between" }}>
          <Link to="/espace-locataire" className="petit-texte">Je suis locataire</Link>
          <Link to="/connexion" className="petit-texte">Personnel de l'agence</Link>
        </div>
      </form>
    </AuthCadre>
  );
}

/** Net encaissé pour le bailleur et versements reçus, mois par mois (deux séries de la palette validée). */
function GrapheCompte({ mois }) {
  const lignes = mois.map((m) => ({ ...m, net: Number(m.net), verse: Number(m.verse) }));
  return (
    <PanneauGraphe
      titre="Loyers et versements"
      sousTitre="12 derniers mois"
      lignes={lignes}
      cle="libelle"
      colonnes={[{ libelle: "Mois", cle: "libelle" }, { libelle: "Loyers nets encaissés", cle: "net", type: "montant" }, { libelle: "Versés par l'agence", cle: "verse", type: "montant" }]}
    >
      <ResponsiveContainer>
        <BarChart data={lignes} margin={{ left: -8, right: 8, top: 8 }} barCategoryGap="24%" barGap={2}>
          <CartesianGrid vertical={false} stroke="var(--ligne)" />
          <XAxis dataKey="libelle" tickLine={false} axisLine={false} tick={{ fill: "var(--discret)", fontSize: 12 }} interval="preserveStartEnd" />
          <YAxis tickFormatter={compact} tickLine={false} axisLine={false} tick={{ fill: "var(--discret)", fontSize: 12 }} width={52} />
          <Tooltip cursor={{ fill: "var(--surface-2)" }} formatter={(v) => fcfa(v)} contentStyle={{ borderRadius: 10, border: "1px solid var(--ligne)", background: "var(--surface)", color: "var(--texte)" }} />
          <Legend iconType="square" iconSize={10} wrapperStyle={{ fontSize: 12, color: "var(--texte-2)" }} />
          <Bar dataKey="net" name="Loyers nets encaissés" fill="var(--viz-1)" radius={[4, 4, 0, 0]} maxBarSize={18} />
          <Bar dataKey="verse" name="Versés par l'agence" fill="var(--viz-2)" radius={[4, 4, 0, 0]} maxBarSize={18} />
        </BarChart>
      </ResponsiveContainer>
    </PanneauGraphe>
  );
}

function Biens({ biens }) {
  if (!biens.length) return <Vide icone={Home} titre="Aucun bien enregistré" />;
  return (
    <div className="biens-bailleur">
      {biens.map((b) => (
        <article key={b.id} className="bien-bailleur">
          <div className="bien-photo">{b.photo ? <img src={b.photo} alt="" loading="lazy" /> : <Home size={28} />}</div>
          <div className="bien-corps">
            <div className="ligne-flex" style={{ justifyContent: "space-between" }}>
              <b>{b.designation}</b>
              {b.occupe ? <Badge ton="info">Occupée</Badge> : <Badge ton="ok">Disponible</Badge>}
            </div>
            <div className="petit-texte discret">{[b.type, b.adresse].filter(Boolean).join(" — ")}</div>
            <div className="ligne-flex" style={{ justifyContent: "space-between", marginTop: 6 }}>
              <span>Loyer <b>{fcfa(b.loyer)}</b></span>
              {b.occupe && <Statut s={b.statut_paiement} />}
            </div>
            {b.occupe && (
              <div className="petit-texte">
                Loué à {b.locataire} depuis le {dateFr(b.date_debut)}
                {Number(b.impaye) > 0 && <span className="texte-alerte"> — {fcfa(b.impaye)} impayés</span>}
                {b.date_sortie_prevue && <div className="texte-attention">Départ prévu le {dateFr(b.date_sortie_prevue)}</div>}
              </div>
            )}
          </div>
        </article>
      ))}
    </div>
  );
}

function Releve() {
  const nav = useNavigate();
  const j = new Date();
  const [f, setF] = useState({ du: isoDate(new Date(j.getFullYear(), 0, 1)), au: isoDate(j) });
  return (
    <form className="pile" onSubmit={(e) => (e.preventDefault(), nav(`/espace-bailleur/releve?du=${f.du}&au=${f.au}`))}>
      <p className="discret" style={{ margin: 0 }}>Toutes les opérations de votre compte sur la période : loyers encaissés (nets de la commission), versements reçus et ajustements.</p>
      <div className="formulaire">
        <Saisie libelle="Du" type="date" requis valeur={f.du} onChange={(du) => setF({ ...f, du })} />
        <Saisie libelle="Au" type="date" requis valeur={f.au} onChange={(au) => setF({ ...f, au })} />
      </div>
      <div><Bouton type="submit" variante="primaire" icone={Printer}>Afficher le relevé</Bouton></div>
    </form>
  );
}

const TONS_INTERVENTION = { nouvelle: "attention", en_cours: "info", terminee: "ok", annulee: "" };

export default function EspaceBailleur() {
  const client = useQueryClient();
  const defile = useDefile();
  const installer = useInstallation();
  const [onglet, setOnglet] = useState("biens");
  const { data, isLoading, error, refetch } = useQuery({ queryKey: ["espace-bailleur"], queryFn: () => api.get("/portail-bailleur/espace/"), retry: false });
  useEffect(() => {
    document.title = "Espace bailleur";
  }, []);
  if (isLoading) return <Chargement plein />;
  if (error || !data) return <ConnexionBailleur onConnecte={() => refetch()} />;
  const ch = data.chiffres;
  const taux = ch.nb_biens ? Math.round((ch.nb_occupes / ch.nb_biens) * 100) : 0;

  return (
    <div className="site sur-photo" style={{ background: "var(--fond)" }}>
      <header className={`site-nav ${defile ? "defile" : "transparent"}`}>
        <Link to="/" className="site-marque"><Marque nom={data.entreprise.nom} /></Link>
        <div className="liens">
          {installer && <Bouton taille="petit" icone={MonitorDown} onClick={installer}>Installer</Bouton>}
          <span className="nom-locataire">{data.bailleur.nom}</span>
          <Bouton taille="petit" icone={LogOut} onClick={async () => (await api.post("/portail-bailleur/deconnexion/"), client.removeQueries({ queryKey: ["espace-bailleur"] }), refetch())}>Quitter</Bouton>
        </div>
      </header>
      <BandeauPage image="/vitrine/maison-1.jpg" surTitre="Espace bailleur" titre={`Bonjour ${data.bailleur.nom.split(" ")[0]}`} texte={`${ch.nb_biens} bien${ch.nb_biens > 1 ? "s" : ""} confié${ch.nb_biens > 1 ? "s" : ""} à ${data.entreprise.nom} — compte ${data.bailleur.code}`} />
      <main className="contenu pile remonte" style={{ maxWidth: 1080, margin: "0 auto" }}>
        <div className="encart-gestion">
          <div className="pastille-icone"><Building2 size={20} /></div>
          <div>
            <div className="gras">Votre mandat de gestion</div>
            {data.mandat
              ? <div>Mandat n° {data.mandat.reference} — {dureeMandat(data.mandat).replace(/^J/, "j").replace(/^D/, "d")}{data.mandat.taux_commission != null && `, commission de ${data.mandat.taux_commission} %`}.</div>
              : <div>Aucun mandat en vigueur : contactez l'agence pour le renouveler.</div>}
          </div>
        </div>
        <div className="registre">
          <div className="bon"><div className="libelle">Solde à vous verser</div><div className="valeur">{nombre(ch.solde)}<small>FCFA</small></div><div className="note">{ch.dernier_versement ? `Dernier versement : ${fcfa(ch.dernier_versement.montant)} le ${dateFr(ch.dernier_versement.date)}` : "Aucun versement pour l'instant"}</div></div>
          <div><div className="libelle">Occupation</div><div className="valeur">{taux}<small>%</small></div><div className="note">{ch.nb_occupes} occupé{ch.nb_occupes > 1 ? "s" : ""} sur {ch.nb_biens}</div></div>
          <div><div className="libelle">Loyers mensuels</div><div className="valeur">{nombre(ch.loyers_mensuels)}<small>FCFA</small></div><div className="note">des baux en cours</div></div>
          <div className={Number(ch.impaye) > 0 ? "alerte" : ""}><div className="libelle">Impayés locataires</div><div className="valeur">{nombre(ch.impaye)}<small>FCFA</small></div><div className="note">relancés par l'agence</div></div>
        </div>
        <div className="grille g-2-1">
          <GrapheCompte mois={data.mois} />
          <Panneau titre={`Année ${new Date().getFullYear()}`}>
            <dl className="bilan-annee">
              <div><dt>Loyers nets encaissés</dt><dd>{fcfa(ch.net_annee)}</dd></div>
              <div><dt>Versés par l'agence</dt><dd>{fcfa(ch.verse_annee)}</dd></div>
              <div><dt>Reste à vous verser</dt><dd className="gras">{fcfa(ch.solde)}</dd></div>
            </dl>
            <div className="ligne-flex" style={{ marginTop: 12 }}>
              <Link to={`/espace-bailleur/releve?du=${new Date().getFullYear()}-01-01&au=${isoDate()}`} className="btn petit"><Printer size={15} /> Relevé de l'année</Link>
              <Link to={`/espace-bailleur/recap-fiscal?annee=${new Date().getFullYear()}`} className="btn petit"><Printer size={15} /> Récapitulatif fiscal</Link>
            </div>
          </Panneau>
        </div>
        <Panneau>
          <Onglets actif={onglet} onChange={setOnglet} onglets={[
            { cle: "biens", libelle: "Mes biens", compte: data.biens.length },
            { cle: "releve", libelle: "Relevé de compte" },
            { cle: "versements", libelle: "Versements", compte: data.versements.length || null },
            { cle: "quittances", libelle: "Quittances", compte: data.quittances.length || null },
            { cle: "interventions", libelle: "Travaux", compte: data.interventions.length || null },
          ]} />
          {onglet === "biens" && <Biens biens={data.biens} />}
          {onglet === "releve" && <Releve />}
          {onglet === "versements" && (
            <Tableau
              lignes={data.versements}
              vide={<Vide titre="Aucun versement" />}
              colonnes={[
                { libelle: "Date", rendu: (v) => dateFr(v.date) },
                { libelle: "Mode", rendu: (v) => v.mode || "—" },
                { libelle: "Détail", rendu: (v) => v.commentaire || "Versement au bailleur" },
                { libelle: "Montant", cle: "montant", type: "montant" },
              ]}
            />
          )}
          {onglet === "quittances" && (
            data.quittances.length === 0 ? <Vide titre="Aucun loyer encaissé" /> : (
              <div className="liste-mobile">
                {data.quittances.map((q) => (
                  <div key={q.id} className="ligne">
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ligne-flex" style={{ justifyContent: "space-between" }}><b>{q.maison} — {q.periode}</b><b>{fcfa(q.montant)}</b></div>
                      <div className="petit-texte discret">{q.locataire} — payé le {dateFr(q.date)} — {q.reference}</div>
                    </div>
                    <Link className="btn petit icone" to={`/espace-bailleur/quittance/${q.id}`} aria-label="Quittance" title="Quittance"><FileText size={15} /></Link>
                  </div>
                ))}
              </div>
            )
          )}
          {onglet === "interventions" && (
            <Tableau
              lignes={data.interventions}
              vide={<Vide titre="Aucuns travaux" texte="Les pannes et réparations signalées sur vos biens apparaîtront ici." />}
              colonnes={[
                { libelle: "Demande", rendu: (d) => <><div className="principal-cellule">{d.titre}</div><div className="sous-cellule">{d.reference} — {d.maison} — le {dateFr(d.date)}</div></> },
                { libelle: "Statut", rendu: (d) => <Badge ton={TONS_INTERVENTION[d.statut]}>{d.statut_libelle}</Badge> },
                { libelle: "À la charge de", rendu: (d) => d.imputation_libelle || "—" },
                { libelle: "Coût", rendu: (d) => (Number(d.cout) ? fcfa(d.cout) : "—"), droite: true },
              ]}
            />
          )}
        </Panneau>
        <p className="discret petit-texte centre">{data.entreprise.nom}{data.entreprise.telephone && ` — ${data.entreprise.telephone}`}{data.entreprise.email && ` — ${data.entreprise.email}`}</p>
      </main>
    </div>
  );
}
