import { useEffect, useState } from "react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { AlarmClock, CalendarClock, CalendarRange, ClipboardCheck, PenLine, Send, FileX2, HandCoins, Pencil, Plus, Printer, ShieldMinus, TrendingUp, Trash2 } from "lucide-react";
import { api } from "../../lib/api";
import { MOIS_COURTS, dateFr, fcfa, isoDate } from "../../lib/format";
import { useAction } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import {
  Anciennete, Badge, Bouton, Chargement, Definitions, Encart, EntetePage, FenetreFormulaire, LienBouton, Liste, Montant, Onglets, Panneau, Saisie,
  Fenetre, SaisieMontant, Statut, Tableau, Vide, Zone, useConfirmer, useToast,
} from "../../components/ui";
import { Documents, FormEncaissement } from "../../components/formulaires";
import { BoutonRelancer, FormPlanApurement, LIBELLES_NIVEAU, SuiviPlan } from "../../components/Recouvrement";

export function FormCharge({ ouvert, onFermer, contratId, charge }) {
  const { data: types = [] } = useQuery({ queryKey: ["types-charges"], queryFn: () => api.get("/charges/types/"), enabled: ouvert });
  const { data: contrats } = useQuery({ queryKey: ["contrats-actifs-options"], queryFn: () => api.get("/contrats/", { statut_contrat: "actif", taille: 500 }), enabled: ouvert && !contratId });
  const initial = charge || { contrat: contratId || "", type_charge: "Eau", montant: "", date_charge: isoDate(), description: "" };
  const [f, setF] = useState(initial);
  useEffect(() => { ouvert && setF(charge || { ...initial, contrat: contratId || "" }); }, [ouvert]); // eslint-disable-line
  const maj = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  return (
    <FenetreFormulaire
      titre={charge ? "Modifier la charge" : "Nouvelle charge"}
      ouvert={ouvert}
      onFermer={onFermer}
      onValider={async () => {
        const d = { contrat: f.contrat, type_charge: f.type_charge, montant: f.montant, date_charge: f.date_charge, description: f.description };
        charge ? await api.patch(`/charges/${charge.id}/`, d) : await api.post("/charges/", d);
        return "Charge enregistrée.";
      }}
    >
      <div className="formulaire">
        {!contratId && (
          <Liste libelle="Contrat" plein requis valeur={f.contrat} onChange={maj("contrat")} vide="— Choisir —" options={(contrats?.results || []).map((c) => ({ valeur: c.id, libelle: `${c.locataire_nom} — ${c.maison_nom}` }))} />
        )}
        <Liste libelle="Type de charge" valeur={f.type_charge} onChange={maj("type_charge")} options={types} />
        <SaisieMontant libelle="Montant" valeur={f.montant} onChange={maj("montant")} requis />
        <Saisie libelle="Date de la charge" type="date" valeur={f.date_charge} onChange={maj("date_charge")} requis />
        <Zone libelle="Description" valeur={f.description} onChange={maj("description")} rows={2} />
      </div>
    </FenetreFormulaire>
  );
}

const LIBELLES_ETAT = { neuf: "Neuf", bon: "Bon", usage: "Usagé", mauvais: "Mauvais" };

function FormPreavis({ ouvert, onFermer, c }) {
  const [f, setF] = useState({ par: "locataire", date_notification: isoDate(), motif: "" });
  useEffect(() => { if (ouvert) setF({ par: "locataire", date_notification: isoDate(), motif: "" }); }, [ouvert]);
  const sortie = f.date_notification ? (() => { const d = new Date(`${f.date_notification}T00:00`); d.setMonth(d.getMonth() + 3); return d; })() : null;
  return (
    <FenetreFormulaire titre="Enregistrer un préavis de départ" ouvert={ouvert} onFermer={onFermer} libelleValider="Enregistrer le préavis" icone={CalendarClock}
      onValider={async () => (await api.post(`/contrats/${c.id}/preavis/`, f), "Préavis enregistré.")}>
      <div className="formulaire">
        <Liste libelle="Préavis donné par" valeur={f.par} onChange={(par) => setF({ ...f, par })} options={[{ valeur: "locataire", libelle: "Le locataire" }, { valeur: "bailleur", libelle: "Le bailleur" }]} />
        <Saisie libelle="Date de notification" type="date" requis valeur={f.date_notification} onChange={(date_notification) => setF({ ...f, date_notification })} aide={sortie ? `Sortie prévue le ${sortie.toLocaleDateString("fr-FR")} (préavis de 3 mois).` : undefined} />
        <Saisie libelle="Motif" plein valeur={f.motif} onChange={(motif) => setF({ ...f, motif })} placeholder="Ex. : mutation professionnelle, achat d'un logement" />
      </div>
    </FenetreFormulaire>
  );
}

function FormResiliation({ ouvert, onFermer, c, partiel }) {
  const nav = useNavigate();
  const [f, setF] = useState({});
  const [couts, setCouts] = useState({});
  const { data: sortie } = useQuery({ queryKey: ["etat-des-lieux", String(c.id), "sortie"], queryFn: () => api.get(`/contrats/${c.id}/etat-des-lieux/sortie/`), enabled: ouvert && !!c.etats_des_lieux?.sortie });
  const degradations = sortie?.degradations || [];
  useEffect(() => {
    if (ouvert) {
      setCouts({});
      setF({ date_effet: c.date_sortie_prevue && c.date_sortie_prevue <= isoDate() ? c.date_sortie_prevue : isoDate(), retenue_reparation: "", retenue_loyer: partiel ? "" : Math.min(Number(c.total_impaye), Number(c.caution_disponible)) || "", motif: c.preavis_motif || "" });
    }
  }, [ouvert]); // eslint-disable-line
  const majCout = (k, v) => {
    const n = { ...couts, [k]: v };
    setCouts(n);
    const somme = Object.values(n).reduce((t, x) => t + (Number(x) || 0), 0);
    setF((x) => ({ ...x, retenue_reparation: somme || "" }));
  };
  const maj = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  const retenues = (Number(f.retenue_reparation) || 0) + (Number(f.retenue_loyer) || 0);
  const restitue = Number(c.caution_disponible) - retenues;
  return (
    <FenetreFormulaire
      titre={partiel ? "Retenue sur caution" : "Résilier le contrat"}
      icone={partiel ? ShieldMinus : FileX2}
      ouvert={ouvert}
      onFermer={onFermer}
      variante={partiel ? "primaire" : "danger"}
      libelleValider={partiel ? "Enregistrer la retenue" : "Résilier et solder la caution"}
      desactive={restitue < 0}
      onValider={async () => {
        await api.post(`/contrats/${c.id}/${partiel ? "caution" : "resilier"}/`, f);
        if (!partiel) nav(`/impression/resiliation/${c.id}`);
        return partiel ? "Retenue enregistrée." : "Contrat résilié. Le bien est de nouveau disponible.";
      }}
    >
      {!partiel && <Encart ton="attention">Le bail passera au statut « Terminé », le bien redeviendra disponible et le solde de la caution sera restitué au locataire.</Encart>}
      {!partiel && !c.etats_des_lieux?.sortie && <Encart titre="État des lieux de sortie non établi">Établissez-le d'abord pour justifier les retenues pour réparations par comparaison avec l'état des lieux d'entrée.</Encart>}
      {degradations.length > 0 && (
        <div className="degradations">
          <div className="gras">Dégradations constatées à la sortie ({degradations.length})</div>
          {degradations.map((d, k) => (
            <div key={k} className="degradation">
              <div>
                <div className="gras">{d.piece} — {d.element}</div>
                <div className="petit-texte discret">Entrée : {LIBELLES_ETAT[d.etat_entree] || "—"} → Sortie : <b className="texte-alerte">{LIBELLES_ETAT[d.etat_sortie]}</b>{d.observation && ` — ${d.observation}`}</div>
              </div>
              <SaisieMontant valeur={couts[k] ?? ""} onChange={(v) => majCout(k, v)} placeholder="Coût" aria-label={`Coût de réparation — ${d.element}`} />
            </div>
          ))}
        </div>
      )}
      <div className="formulaire">
        {!partiel && <Saisie libelle="Date de sortie" type="date" valeur={f.date_effet} onChange={maj("date_effet")} requis />}
        <SaisieMontant libelle="Retenue pour réparations" valeur={f.retenue_reparation} onChange={maj("retenue_reparation")} aide={degradations.length ? "Total des coûts saisis ci-dessus, modifiable. Versée en caisse agence." : "Versée en caisse agence."} />
        <SaisieMontant libelle="Retenue pour loyers impayés" valeur={f.retenue_loyer} onChange={maj("retenue_loyer")} aide={`Impayés actuels : ${fcfa(c.total_impaye)}. Imputée sur les mois les plus anciens.`} />
        <Zone libelle="Motif" valeur={f.motif} onChange={maj("motif")} rows={2} />
      </div>
      <dl className="definitions" style={{ gridTemplateColumns: "1fr auto" }}>
        <dt>Caution disponible</dt><dd className="droite">{fcfa(c.caution_disponible)}</dd>
        <dt>Total des retenues</dt><dd className="droite">− {fcfa(retenues)}</dd>
        {!partiel && <><dt className="gras">À restituer au locataire</dt><dd className="droite gras" style={{ color: restitue < 0 ? "var(--signal)" : undefined }}>{fcfa(Math.max(0, restitue))}</dd></>}
      </dl>
      {restitue < 0 && <Encart ton="alerte">Les retenues dépassent la caution disponible.</Encart>}
    </FenetreFormulaire>
  );
}

/** Signature du bail par code SMS : envoi du code au locataire, saisie du code (par l'agent ou par le locataire dans son espace). */
function FormSignature({ ouvert, onFermer, c }) {
  const [envoi, setEnvoi] = useState(null);
  const [code, setCode] = useState("");
  const [etat, setEtat] = useState({});
  const toast = useToast();
  const fermer = () => (setEnvoi(null), setCode(""), setEtat({}), onFermer());
  const envoyer = async () => {
    setEtat({ attente: "envoi" });
    try {
      setEnvoi(await api.post(`/contrats/${c.id}/signature/envoyer/`));
      setCode("");
      setEtat({});
    } catch (e) {
      setEtat({ erreur: e.message });
    }
  };
  const valider = async (e) => {
    e.preventDefault();
    setEtat({ attente: "valider" });
    try {
      await api.post(`/contrats/${c.id}/signature/valider/`, { code });
      toast.succes("Bail signé électroniquement.");
      fermer();
    } catch (err) {
      setEtat({ erreur: err.message });
    }
  };
  return (
    <Fenetre titre="Signature électronique du bail" ouvert={ouvert} onFermer={fermer} taille="etroite"
      pied={envoi
        ? <><Bouton variante="fantome" chargement={etat.attente === "envoi"} onClick={envoyer}>Renvoyer un code</Bouton><Bouton type="submit" form="form-signature" variante="primaire" icone={PenLine} chargement={etat.attente === "valider"}>Valider la signature</Bouton></>
        : <><Bouton onClick={fermer}>Annuler</Bouton><Bouton variante="primaire" icone={Send} chargement={etat.attente === "envoi"} onClick={envoyer}>Envoyer le code</Bouton></>}>
      <form id="form-signature" className="pile" onSubmit={valider}>
        {etat.erreur && <Encart ton="alerte">{etat.erreur}</Encart>}
        {!envoi ? (
          <p style={{ margin: 0 }}>Un code à 6 chiffres, valable 15 minutes, va être envoyé à <b>{c.locataire_nom}</b>. Le locataire le saisit dans son espace en ligne, ou vous le communique pour que vous le saisissiez ici : cette saisie vaut signature du bail {c.reference}.</p>
        ) : (
          <>
            <Encart ton={envoi.code_test ? "info" : "ok"} titre={envoi.code_test ? "Mode test : aucun SMS n'a été envoyé" : `Code envoyé au ${envoi.telephone}`}>
              {envoi.code_test ? <>Code simulé : <b className="code-test">{envoi.code_test}</b> (affiché seulement en mode test).</> : `Valable ${envoi.expire_dans} minutes.`}
            </Encart>
            <Saisie libelle="Code reçu par le locataire" requis autoFocus valeur={code} onChange={(v) => setCode(v.replace(/\D/g, "").slice(0, 6))} inputMode="numeric" autoComplete="one-time-code" placeholder="000000" pattern="\d{6}" />
          </>
        )}
      </form>
    </Fenetre>
  );
}

/** Relances envoyées et plans d'apurement du bail ; masqué tant que le locataire est à jour et sans historique. */
function PanneauRecouvrement({ c }) {
  const [plan, setPlan] = useState(false);
  const { data: r, refetch } = useQuery({ queryKey: ["relances", String(c.id)], queryFn: () => api.get(`/contrats/${c.id}/relances/`) });
  if (!r) return null;
  const dette = Number(r.situation.montant);
  const actif = c.statut_contrat === "actif";
  if (!dette && !r.relances.length && !r.plans.length) return null;
  const derniere = r.relances[0];
  const planActif = r.plans.find((p) => p.actif);
  return (
    <Panneau titre="Recouvrement" icone={AlarmClock}>
      {dette > 0 && <p className="petit-texte" style={{ marginTop: 0 }}><b>{fcfa(dette)}</b> dus ({r.situation.periodes.join(", ")}).</p>}
      {r.relances.length > 0 && (
        <div className="liste-relances">
          {r.relances.map((x) => (
            <div key={x.id} className="ligne-relance">
              <Badge ton={x.niveau === 3 ? "alerte" : "attention"}>{LIBELLES_NIVEAU[x.niveau]}</Badge>
              <span className="petit-texte">le {dateFr(x.date_relance)} — {fcfa(x.montant_du)}</span>
              <span className="espace" />
              <LienBouton taille="petit" variante="fantome" icone={Printer} to={`/impression/relance/${c.id}/${x.id}`}>Lettre</LienBouton>
            </div>
          ))}
        </div>
      )}
      {planActif && <SuiviPlan plan={planActif} contratId={c.id} />}
      {actif && dette > 0 && (
        <div className="ligne-flex" style={{ marginTop: 12 }}>
          <BoutonRelancer contratId={c.id} niveauActuel={derniere?.niveau} />
          <Bouton taille="petit" icone={CalendarRange} onClick={() => setPlan(true)}>{planActif ? "Nouveau plan" : "Plan d'apurement"}</Bouton>
        </div>
      )}
      {plan && <FormPlanApurement ouvert contratId={c.id} dette={dette} onFermer={() => (setPlan(false), refetch())} />}
    </Panneau>
  );
}

export default function FicheContrat() {
  const { id } = useParams();
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const [onglet, setOnglet] = useState("paiements");
  const [modal, setModal] = useState(null);
  const [form, setForm] = useState({});
  const [params, setParams] = useSearchParams();
  const nav = useNavigate();
  const { data: c, refetch } = useQuery({ queryKey: ["contrat", id], queryFn: () => api.get(`/contrats/${id}/`) });
  // Arrivée depuis « Révisions > À réviser » : formulaire de révision triennale prêt, effet au plus tôt dans 3 mois
  useEffect(() => {
    if (!c || !params.get("reviser")) return;
    const effet = new Date();
    effet.setMonth(effet.getMonth() + 3, 1);
    setForm({ origine: params.get("reviser"), nouveau_loyer: "", motif: "Révision triennale", date_effet: isoDate(effet) });
    setModal("revision");
    setParams({}, { replace: true });
  }, [c]); // eslint-disable-line
  const supprimerCharge = useAction((ch) => api.delete(`/charges/${ch.id}/`), { succes: "Charge supprimée.", apres: () => refetch() });
  const annulerPreavis = useAction(() => api.post(`/contrats/${id}/annuler-preavis/`), { succes: "Préavis annulé.", apres: () => refetch() });
  if (!c) return <Chargement />;
  const actif = c.statut_contrat === "actif";
  const fermer = () => (setModal(null), refetch());
  const auj = new Date();
  const indexAuj = auj.getFullYear() * 12 + auj.getMonth();

  return (
    <div className="pile">
      <EntetePage
        titre={`${c.reference} — ${c.locataire_nom}`}
        soustitre={<span className="ligne-flex"><Statut s={c.statut_contrat} />{actif && <Statut s={c.statut_paiement} libelle={c.jours_retard ? `${c.jours_retard} jours de retard` : undefined} />}{c.mandat_echu && <Badge ton="attention">Mandat échu</Badge>}<span>{c.maison_nom}</span></span>}
        fil={[{ libelle: "Contrats", lien: "/contrats" }]}
      >
        <LienBouton icone={Printer} to={`/impression/contrat/${c.id}`}>Bail</LienBouton>
        <LienBouton icone={Printer} to={`/impression/recu-contrat/${c.id}`}>Reçu de signature</LienBouton>
        {!actif && <LienBouton icone={Printer} to={`/impression/resiliation/${c.id}`}>Reçu de résiliation</LienBouton>}
        {actif && <Bouton variante="succes" icone={HandCoins} onClick={() => setModal("encaisser")}>Encaisser</Bouton>}
      </EntetePage>

      {actif && c.preavis_le && (
        <Encart ton="attention" titre={`Préavis de départ — sortie prévue le ${dateFr(c.date_sortie_prevue)}`}>
          Notifié le {dateFr(c.preavis_le)} par {c.preavis_par === "bailleur" ? "le bailleur" : "le locataire"}{c.preavis_motif && ` (${c.preavis_motif})`}.
          Préparez l'état des lieux de sortie, puis résiliez le bail à la remise des clés.
          <div className="ligne-flex" style={{ marginTop: 8 }}>
            <LienBouton taille="petit" variante="primaire" icone={ClipboardCheck} to={`/contrats/${c.id}/etat-des-lieux/sortie`}>État des lieux de sortie</LienBouton>
            {estAdmin && <Bouton taille="petit" onClick={async () => (await confirmer({ message: "Annuler ce préavis ? Le bail continue normalement." })) && annulerPreavis.mutate()}>Annuler le préavis</Bouton>}
          </div>
        </Encart>
      )}

      <div className="registre">
        <div><div className="libelle">Loyer mensuel</div><div className="valeur">{Math.round(c.loyer_mensuel).toLocaleString("fr-FR")}<small>FCFA</small></div><div className="note">commission {c.taux_commission} %</div></div>
        <div className={Number(c.total_impaye) > 0 ? "alerte" : "bon"}><div className="libelle">Impayés</div><div className="valeur">{Math.round(c.total_impaye).toLocaleString("fr-FR")}<small>FCFA</small></div><div className="note">{c.impayes.length} mois</div></div>
        <div><div className="libelle">Prochaine échéance</div><div className="valeur" style={{ fontSize: 19 }}>{actif ? dateFr(c.date_prochain_loyer) : "—"}</div></div>
        <div><div className="libelle">Caution disponible</div><div className="valeur">{Math.round(c.caution_disponible).toLocaleString("fr-FR")}<small>FCFA</small></div><div className="note">sur {fcfa(c.depot_garantie)} déposés</div></div>
      </div>

      <div className="grille g-2-1">
        <Panneau titre="Échéancier" icone={CalendarClock}>
          <div className="mois-grille">
            {c.echeancier.map((p) => {
              const futur = p.annee * 12 + p.mois - 1 > indexAuj;
              return (
                <div key={`${p.annee}-${p.mois}`} className={`mois-case ${futur && p.statut !== "solde" ? "futur" : p.statut}`} title={`${p.libelle} : payé ${fcfa(p.paye)} sur ${fcfa(p.loyer)}`}>
                  <b>{MOIS_COURTS[p.mois - 1]} {p.annee}</b>
                  {p.statut === "solde" ? "Soldé" : p.paye > 0 ? `Reste ${Math.round(p.reste).toLocaleString("fr-FR")}` : futur ? "À venir" : "Impayé"}
                </div>
              );
            })}
          </div>
        </Panneau>
        <div className="pile">
        <PanneauRecouvrement c={c} />
        <Panneau titre="États des lieux" icone={ClipboardCheck} className="panneau-edl">
          {[["entree", "Entrée"], ["sortie", "Sortie"]].map(([t, l]) => {
            const fait = c.etats_des_lieux?.[t];
            if (t === "sortie" && actif && !fait && !c.preavis_le) return null;
            return (
              <div key={t} className="ligne-edl">
                <div>
                  <div className="gras">État des lieux d'{t === "entree" ? "entrée" : "sortie"}</div>
                  <div className="petit-texte">{fait ? <Badge ton="ok">Établi le {dateFr(fait)}</Badge> : <Badge ton="attention">À établir</Badge>}</div>
                </div>
                <span className="espace" />
                <LienBouton taille="petit" icone={Printer} to={`/impression/etat-des-lieux/${c.id}/${t}`}>{fait ? "Imprimer" : "Grille vierge"}</LienBouton>
                <LienBouton taille="petit" variante={fait ? "" : "primaire"} icone={fait ? Pencil : ClipboardCheck} to={`/contrats/${c.id}/etat-des-lieux/${t}`}>{fait ? "Modifier" : "Remplir"}</LienBouton>
              </div>
            );
          })}
          {!c.etats_des_lieux?.entree && actif && <p className="discret petit-texte" style={{ margin: "10px 0 0" }}>Après impression du bail, remplissez puis imprimez l'état des lieux d'entrée avec le locataire : il est annexé au contrat.</p>}
        </Panneau>
        <Panneau titre="Conditions">
          <Definitions
            items={[
              ["Locataire", <Link to={`/locataires/${c.locataire}`}>{c.locataire_nom}</Link>],
              ["Bien", <Link to={`/maisons/${c.maison}`}>{c.maison_nom}</Link>],
              ["Bailleur", <Link to={`/bailleurs/${c.bailleur_id}`}>{c.bailleur_nom}</Link>],
              ["Mandat de gestion", c.mandat ? <Link to={`/impression/mandat/${c.mandat}`}>{c.mandat_reference}</Link> : "—"],
              ["Signé le", dateFr(c.date_contrat)],
              ["Signature électronique", c.signature_electronique?.signe_le ? <Badge ton="ok">Signé par code SMS le {dateFr(c.signature_electronique.signe_le)}</Badge> : c.signature_electronique?.en_attente ? <Badge ton="attention">Code envoyé, en attente</Badge> : actif ? <Bouton taille="petit" icone={PenLine} onClick={() => setModal("signature")}>Faire signer par SMS</Bouton> : "—"],
              ["Ancienneté", <Anciennete a={c.anciennete} detail />],
              ["Début", dateFr(c.date_debut)],
              ["Échéance", c.date_fin ? dateFr(c.date_fin) : "Tacite reconduction"],
              ["Avance versée", `${fcfa(c.avance_loyer)} (${c.mois_avance} mois)`],
              ["Frais d'agence", fcfa(c.droit_agence)],
              ["Résilié le", c.date_resiliation && dateFr(c.date_resiliation)],
              ["Motif", c.motif_resiliation],
            ]}
          />
          {actif && (
            <div className="ligne-flex" style={{ marginTop: 16 }}>
              <Bouton taille="petit" icone={CalendarClock} onClick={() => (setForm({ date_fin: c.date_fin || "" }), setModal("echeance"))}>Échéance</Bouton>
              {estAdmin && <Bouton taille="petit" icone={TrendingUp} onClick={() => (setForm({ nouveau_loyer: "", motif: "", date_effet: isoDate() }), setModal("revision"))}>Réviser le loyer</Bouton>}
              {!c.preavis_le && <Bouton taille="petit" icone={CalendarClock} onClick={() => setModal("preavis")}>Préavis</Bouton>}
              <Bouton taille="petit" icone={ShieldMinus} onClick={() => setModal("retenue")}>Retenue caution</Bouton>
              {estAdmin && <Bouton taille="petit" variante="danger" icone={FileX2} onClick={() => setModal("resilier")}>Résilier</Bouton>}
            </div>
          )}
        </Panneau>
        </div>
      </div>

      <Panneau>
        <Onglets
          actif={onglet}
          onChange={setOnglet}
          onglets={[
            { cle: "paiements", libelle: "Paiements", compte: c.encaissements.length },
            { cle: "caution", libelle: "Caution", compte: c.mouvements_caution.length },
            { cle: "revisions", libelle: "Révisions", compte: c.revisions.length },
            { cle: "charges", libelle: "Charges", compte: c.charges.length },
            { cle: "documents", libelle: "Documents" },
          ]}
        />
        {onglet === "paiements" && (
          <Tableau
            lignes={c.encaissements}
            vide={<Vide titre="Aucun paiement" />}
            colonnes={[
              { libelle: "Reçu", rendu: (p) => <Link to={`/impression/quittance/${p.id}`}>{p.reference_recu}</Link> },
              { libelle: "Période", rendu: (p) => <>{p.periode_concernee}{p.origine !== "loyer" && <div className="sous-cellule">{p.origine_libelle}</div>}</> },
              { libelle: "Date", rendu: (p) => dateFr(p.date_encaissement) },
              { libelle: "Mode", cle: "mode_libelle" },
              { libelle: "Commission", cle: "commission", type: "montant" },
              { libelle: "Montant", cle: "montant_recu", type: "montant" },
            ]}
          />
        )}
        {onglet === "caution" && (
          <>
            <div className="ligne-flex" style={{ marginBottom: 10 }}>
              <span>Déposée : <b>{fcfa(c.depot_garantie)}</b> — disponible : <b>{fcfa(c.caution_disponible)}</b></span>
              <span className="espace" />
              <LienBouton taille="petit" icone={Printer} to={`/impression/releve-caution/${c.id}`}>Relevé de caution</LienBouton>
            </div>
            <Tableau
              lignes={c.mouvements_caution}
              vide={<Vide titre="Aucun mouvement" texte="La caution est intacte." />}
              colonnes={[
                { libelle: "Date", rendu: (m) => dateFr(m.date_operation) },
                { libelle: "Mouvement", rendu: (m) => <><div className="principal-cellule">{m.type_libelle}</div><div className="sous-cellule">{m.commentaire}</div></> },
                { libelle: "Par", cle: "effectue_par" },
                { libelle: "Montant", droite: true, rendu: (m) => <Montant v={m.montant} signe /> },
              ]}
            />
          </>
        )}
        {onglet === "revisions" && (
          <Tableau
            lignes={c.revisions}
            vide={<Vide titre="Aucune révision" texte="Art. 423 : révision possible tous les trois ans, notifiée trois mois avant." />}
            colonnes={[
              { libelle: "Effet", rendu: (r) => dateFr(r.date_effet) },
              { libelle: "Ancien", cle: "ancien_loyer", type: "montant" },
              { libelle: "Nouveau", cle: "nouveau_loyer", type: "montant" },
              { libelle: "Variation", rendu: (r) => `${r.variation > 0 ? "+" : ""}${r.variation} %` },
              { libelle: "Motif", cle: "motif" },
              { libelle: "Par", cle: "effectue_par_nom" },
            ]}
          />
        )}
        {onglet === "charges" && (
          <>
            <div className="ligne-flex" style={{ marginBottom: 10 }}>
              <span className="espace" />
              <Bouton taille="petit" icone={Plus} onClick={() => setModal("charge")}>Ajouter une charge</Bouton>
            </div>
            <Tableau
              lignes={c.charges}
              vide={<Vide titre="Aucune charge" />}
              colonnes={[
                { libelle: "Date", rendu: (x) => dateFr(x.date_charge) },
                { libelle: "Type", cle: "type_charge" },
                { libelle: "Description", cle: "description" },
                { libelle: "Montant", cle: "montant", type: "montant" },
                { libelle: "", rendu: (x) => estAdmin && <Bouton taille="petit" variante="fantome" icone={Trash2} aria-label="Supprimer" onClick={async () => (await confirmer({ message: "Supprimer cette charge ?", danger: true })) && supprimerCharge.mutate(x)} /> },
              ]}
            />
          </>
        )}
        {onglet === "documents" && <Documents type="contrat" id={c.id} />}
      </Panneau>

      <FormEncaissement ouvert={modal === "encaisser"} contrat={c} onFermer={fermer} />
      <FormResiliation ouvert={modal === "resilier"} c={c} onFermer={fermer} />
      <FormPreavis ouvert={modal === "preavis"} c={c} onFermer={fermer} />
      <FormSignature ouvert={modal === "signature"} c={c} onFermer={fermer} />
      <FormResiliation ouvert={modal === "retenue"} c={c} partiel onFermer={fermer} />
      <FormCharge ouvert={modal === "charge"} contratId={c.id} onFermer={fermer} />
      <FenetreFormulaire titre="Échéance du bail" taille="etroite" ouvert={modal === "echeance"} onFermer={fermer} onValider={async () => (await api.post(`/contrats/${c.id}/echeance/`, form), "Échéance mise à jour.")}>
        <div className="formulaire"><Saisie libelle="Date de fin" type="date" plein valeur={form.date_fin} onChange={(date_fin) => setForm({ date_fin })} aide="Vide = tacite reconduction." /></div>
      </FenetreFormulaire>
      <FenetreFormulaire titre="Réviser le loyer" ouvert={modal === "revision"} onFermer={fermer} libelleValider="Réviser le loyer" onValider={async () => { const r = await api.post(`/contrats/${c.id}/reviser/`, form); nav(`/impression/revision/${r.id}`); return "Révision enregistrée : remettez la lettre au locataire."; }}>
        <Encart>Loyer actuel : <b>{fcfa(c.loyer_mensuel)}</b>. Les mois antérieurs à la date d'effet gardent l'ancien montant ; le loyer du bien est mis à jour pour les prochains baux.</Encart>
        <div className="formulaire">
          <Liste libelle="Origine de la révision" plein valeur={form.origine || "bailleur"} onChange={(v) => setForm({ ...form, origine: v })} options={[{ valeur: "bailleur", libelle: "À la demande du bailleur" }, { valeur: "triennale", libelle: "Révision triennale" }, { valeur: "accord", libelle: "Accord entre les parties" }]} />
          <SaisieMontant libelle="Nouveau loyer" valeur={form.nouveau_loyer} onChange={(v) => setForm({ ...form, nouveau_loyer: v })} requis />
          <Saisie libelle="Date d'effet" type="date" requis valeur={form.date_effet} onChange={(v) => setForm({ ...form, date_effet: v })} aide="Le mois entier est concerné." />
          <Saisie libelle="Motif" plein valeur={form.motif} onChange={(v) => setForm({ ...form, motif: v })} placeholder="Ex. : travaux de rénovation, alignement sur le marché" />
        </div>
      </FenetreFormulaire>
    </div>
  );
}
