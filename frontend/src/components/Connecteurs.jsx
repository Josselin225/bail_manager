import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { CloudUpload, MessageCircle, MessageSquareText, PenLine, Save, Send, Smartphone } from "lucide-react";
import { api } from "../lib/api";
import { dateHeure, fcfa } from "../lib/format";
import { Badge, Bouton, Case, Encart, Liste, Onglets, Panneau, Saisie, Tableau, Vide, useToast } from "./ui";

const TONS = { simule: "info", envoye: "ok", echec: "alerte", reussi: "ok", echoue: "alerte", en_attente: "attention" };

/** Champ secret : jamais réaffiché ; laisser vide conserve la valeur enregistrée. */
function Secret({ libelle, renseigne, valeur, onChange, aide }) {
  return <Saisie libelle={libelle} type="password" autoComplete="new-password" valeur={valeur} onChange={onChange} placeholder={renseigne ? "•••••••• (enregistré)" : ""} aide={aide || (renseigne ? "Laissez vide pour conserver la valeur enregistrée." : undefined)} />;
}

function Reglages() {
  const toast = useToast();
  const { data, refetch } = useQuery({ queryKey: ["connecteurs"], queryFn: () => api.get("/connecteurs/") });
  const [f, setF] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  const [essai, setEssai] = useState({ telephone: "", canal: "sms", envoi: false });
  useEffect(() => { data && setF({ ...data, sms_secret: "", whatsapp_jeton: "", paiement_cle: "", paiement_secret: "" }); }, [data]);
  if (!f) return null;
  const maj = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  const opts = (liste) => liste.map(([valeur, libelle]) => ({ valeur, libelle }));
  const s = data.secrets_renseignes;
  const enregistrer = async (e) => {
    e.preventDefault();
    setEnvoi(true);
    try {
      const { choix, secrets_renseignes, url_notification, updated_at, ...reste } = f;
      await api.patch("/connecteurs/", reste);
      toast.succes("Connecteurs enregistrés.");
      refetch();
    } catch (err) {
      toast.erreur(err.message);
    } finally {
      setEnvoi(false);
    }
  };
  const tester = async () => {
    setEssai((x) => ({ ...x, envoi: true }));
    try {
      const r = await api.post("/connecteurs/tester/", { telephone: essai.telephone, canal: essai.canal });
      r.statut === "echec" ? toast.erreur(`Échec : ${r.erreur}`) : toast.succes(r.statut === "simule" ? "Message simulé (mode test) : voir le journal des envois." : "Message envoyé.");
    } catch (err) {
      toast.erreur(err.message);
    } finally {
      setEssai((x) => ({ ...x, envoi: false }));
    }
  };
  const enTest = [f.sms_fournisseur, f.whatsapp_fournisseur, f.paiement_fournisseur].includes("test");
  return (
    <form onSubmit={enregistrer} className="pile">
      {enTest && <Encart ton="info" titre="Mode test actif">Aucun SMS ni message WhatsApp n'est réellement envoyé et aucun paiement n'est débité : tout est enregistré dans le journal des envois et le journal d'activité. Un paiement simulé n'encaisse aucun loyer. Passez au vrai fournisseur une fois vos identifiants obtenus, ou désactivez le paiement en ligne.</Encart>}
      <Panneau titre="SMS" icone={MessageSquareText}>
        <div className="formulaire">
          <Liste libelle="Fournisseur" valeur={f.sms_fournisseur} onChange={maj("sms_fournisseur")} options={opts(data.choix.sms)} />
          <Saisie libelle="Expéditeur" valeur={f.sms_expediteur} onChange={maj("sms_expediteur")} placeholder="Ex. : +2250700000000" aide="Numéro (Orange, Twilio) validé chez le fournisseur." />
          {!["desactive", "test"].includes(f.sms_fournisseur) && (
            <>
              <Saisie libelle={f.sms_fournisseur === "twilio" ? "Account SID" : "Client ID"} valeur={f.sms_identifiant} onChange={maj("sms_identifiant")} />
              <Secret libelle={f.sms_fournisseur === "twilio" ? "Auth token" : "Client secret"} renseigne={s.sms_secret} valeur={f.sms_secret} onChange={maj("sms_secret")} />
            </>
          )}
        </div>
      </Panneau>
      <Panneau titre="WhatsApp" icone={MessageCircle}>
        <div className="formulaire">
          <Liste libelle="Fournisseur" valeur={f.whatsapp_fournisseur} onChange={maj("whatsapp_fournisseur")} options={opts(data.choix.whatsapp)} />
          {f.whatsapp_fournisseur === "meta" && (
            <>
              <Saisie libelle="Identifiant du numéro (Phone number ID)" valeur={f.whatsapp_numero_id} onChange={maj("whatsapp_numero_id")} />
              <Secret libelle="Jeton d'accès permanent" renseigne={s.whatsapp_jeton} valeur={f.whatsapp_jeton} onChange={maj("whatsapp_jeton")} />
            </>
          )}
          <Liste libelle="Canal privilégié" valeur={f.canal_prefere} onChange={maj("canal_prefere")} options={[{ valeur: "sms", libelle: "SMS" }, { valeur: "whatsapp", libelle: "WhatsApp" }]} aide="Utilisé s'il est branché ; sinon l'autre canal prend le relais." />
        </div>
      </Panneau>
      <Panneau titre="Messages automatiques aux locataires">
        <div className="formulaire">
          <Case libelle="Relances d'impayés" aide="Rappel amiable, relance et mise en demeure, à leur création." valeur={f.envoi_relances} onChange={maj("envoi_relances")} />
          <Case libelle="Confirmation de chaque paiement" aide="Montant, mois réglé et numéro de quittance." valeur={f.envoi_paiements} onChange={maj("envoi_paiements")} />
          <Case libelle="Rappels d'échéance et de retard" aide="Envoyés par la tâche quotidienne, un message au plus par jour et par locataire." valeur={f.envoi_rappels} onChange={maj("envoi_rappels")} />
        </div>
        <div className="essai-connecteur">
          <Saisie libelle="Envoyer un message d'essai au" type="tel" valeur={essai.telephone} onChange={(telephone) => setEssai({ ...essai, telephone })} placeholder="07 00 00 00 00" />
          <Liste libelle="Par" valeur={essai.canal} onChange={(canal) => setEssai({ ...essai, canal })} options={[{ valeur: "sms", libelle: "SMS" }, { valeur: "whatsapp", libelle: "WhatsApp" }]} />
          <Bouton icone={Send} chargement={essai.envoi} onClick={tester}>Essayer</Bouton>
        </div>
      </Panneau>
      <Panneau titre="Paiement Mobile Money" icone={Smartphone}>
        <p className="discret" style={{ marginTop: 0 }}>Wave, Orange Money, MTN MoMo et Moov Money via un agrégateur agréé. Le locataire paie depuis son espace ; à la confirmation, le loyer est encaissé et la quittance émise automatiquement.</p>
        <div className="formulaire">
          <Liste libelle="Agrégateur" valeur={f.paiement_fournisseur} onChange={maj("paiement_fournisseur")} options={opts(data.choix.paiement)} />
          {!["desactive", "test"].includes(f.paiement_fournisseur) && (
            <>
              <Saisie libelle={f.paiement_fournisseur === "paydunya" ? "Token" : "Site ID"} valeur={f.paiement_site_id} onChange={maj("paiement_site_id")} />
              <Secret libelle={f.paiement_fournisseur === "paydunya" ? "Master key" : "Clé API"} renseigne={s.paiement_cle} valeur={f.paiement_cle} onChange={maj("paiement_cle")} />
              {f.paiement_fournisseur === "paydunya" && <Secret libelle="Private key" renseigne={s.paiement_secret} valeur={f.paiement_secret} onChange={maj("paiement_secret")} />}
              <Saisie libelle="Adresse de notification à déclarer" plein valeur={data.url_notification} onChange={() => {}} readOnly aide="À renseigner chez l'agrégateur : il y signale chaque paiement, que le logiciel revérifie avant d'encaisser." />
            </>
          )}
        </div>
      </Panneau>
      <Panneau titre="Signature électronique des baux" icone={PenLine}>
        <Case libelle="Signature par code SMS" aide="Un code à 6 chiffres, valable 15 minutes, est envoyé au locataire ; sa saisie vaut signature et reste tracée (date, heure, numéro, adresse IP)." valeur={f.signature_sms} onChange={maj("signature_sms")} />
      </Panneau>
      <div><Bouton type="submit" variante="primaire" icone={Save} chargement={envoi}>Enregistrer les connecteurs</Bouton></div>
    </form>
  );
}

function JournalEnvois() {
  const { data = [], isLoading } = useQuery({ queryKey: ["connecteurs-envois"], queryFn: () => api.get("/connecteurs/envois/") });
  return (
    <Panneau serre>
      <Tableau
        lignes={data}
        chargement={isLoading}
        vide={<Vide titre="Aucun message" texte="Les SMS et messages WhatsApp (envoyés ou simulés) apparaîtront ici." />}
        colonnes={[
          { libelle: "Date", rendu: (m) => dateHeure(m.date) },
          { libelle: "Destinataire", rendu: (m) => <><div className="principal-cellule">{m.nom || m.destinataire}</div><div className="sous-cellule">{m.destinataire} — {m.canal === "sms" ? "SMS" : "WhatsApp"}</div></> },
          { libelle: "Message", rendu: (m) => <><div className="principal-cellule">{m.objet}</div><div className="sous-cellule texte-message">{m.texte}</div>{m.erreur && <div className="sous-cellule texte-alerte">{m.erreur}</div>}</> },
          { libelle: "Statut", rendu: (m) => <Badge ton={TONS[m.statut]}>{m.statut_libelle}</Badge> },
        ]}
      />
    </Panneau>
  );
}

function PaiementsEnLigne() {
  const { data = [], isLoading } = useQuery({ queryKey: ["connecteurs-paiements"], queryFn: () => api.get("/connecteurs/paiements/") });
  return (
    <Panneau serre>
      <Tableau
        lignes={data}
        cle="reference"
        chargement={isLoading}
        vide={<Vide titre="Aucun paiement en ligne" />}
        colonnes={[
          { libelle: "Date", rendu: (p) => dateHeure(p.date) },
          { libelle: "Locataire", rendu: (p) => <><div className="principal-cellule">{p.locataire}</div><div className="sous-cellule">{p.reference} — {p.operateur} {p.telephone}</div></> },
          { libelle: "Mois", cle: "periode" },
          { libelle: "Statut", rendu: (p) => <><Badge ton={TONS[p.statut]}>{p.statut_libelle}</Badge>{p.fournisseur === "test" && <div className="sous-cellule">simulation</div>}</> },
          { libelle: "Quittance", rendu: (p) => p.recu || "—" },
          { libelle: "Montant", rendu: (p) => fcfa(p.montant), droite: true },
        ]}
      />
    </Panneau>
  );
}

/** Onglet Paramètres > Connecteurs (administrateur). */
export default function Connecteurs() {
  const [vue, setVue] = useState("reglages");
  return (
    <div className="pile">
      <Onglets actif={vue} onChange={setVue} onglets={[{ cle: "reglages", libelle: "Réglages" }, { cle: "envois", libelle: "Journal des envois" }, { cle: "paiements", libelle: "Paiements en ligne" }]} />
      {vue === "reglages" && <Reglages />}
      {vue === "envois" && <JournalEnvois />}
      {vue === "paiements" && <PaiementsEnLigne />}
    </div>
  );
}

/** Copie chiffrée automatique hors du serveur (Paramètres > Sauvegarde). */
export function SauvegardeEnLigne() {
  const toast = useToast();
  const { data, refetch } = useQuery({ queryKey: ["connecteurs"], queryFn: () => api.get("/connecteurs/") });
  const [f, setF] = useState(null);
  const [etat, setEtat] = useState({});
  useEffect(() => { data && setF({ ...data, sauvegarde_secret: "", sauvegarde_phrase: "" }); }, [data]);
  if (!f) return null;
  const maj = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  const s = data.secrets_renseignes;
  const champs = ["sauvegarde_destination", "sauvegarde_dossier", "sauvegarde_url", "sauvegarde_bucket", "sauvegarde_region", "sauvegarde_identifiant", "sauvegarde_secret", "sauvegarde_phrase", "sauvegarde_garder"];
  const enregistrer = async (e) => {
    e.preventDefault();
    setEtat({ attente: "enregistrer" });
    try {
      await api.patch("/connecteurs/", Object.fromEntries(champs.map((k) => [k, f[k]])));
      toast.succes("Réglages de sauvegarde enregistrés.");
      refetch();
    } catch (err) {
      toast.erreur(err.message);
    } finally {
      setEtat({});
    }
  };
  const maintenant = async () => {
    setEtat({ attente: "maintenant" });
    try {
      const r = await api.post("/connecteurs/sauvegarde/");
      toast.succes(r.statut);
    } catch (err) {
      toast.erreur(err.message);
    } finally {
      setEtat({});
      refetch();
    }
  };
  const d = f.sauvegarde_destination;
  const echec = data.derniere_sauvegarde_statut?.startsWith("Échec");
  return (
    <form onSubmit={enregistrer}>
      <Panneau titre="Sauvegarde automatique hors du serveur" icone={CloudUpload}>
        <p className="discret" style={{ marginTop: 0 }}>Chaque nuit, la tâche de sauvegarde envoie une copie <b>chiffrée</b> des données et des fichiers vers la destination choisie. Sans la phrase de chiffrement, la copie est illisible : conservez-la en lieu sûr, elle sera demandée pour toute restauration.</p>
        {data.derniere_sauvegarde_le && (
          <Encart ton={echec ? "alerte" : "ok"} titre={`Dernière copie : ${dateHeure(data.derniere_sauvegarde_le)}`}>{data.derniere_sauvegarde_statut}</Encart>
        )}
        <div className="formulaire" style={{ marginTop: 12 }}>
          <Liste libelle="Destination" plein valeur={d} onChange={maj("sauvegarde_destination")} options={data.choix.sauvegarde.map(([valeur, libelle]) => ({ valeur, libelle }))} />
          {d === "dossier" && (
            <>
              <Saisie libelle="Dossier" plein valeur={f.sauvegarde_dossier} onChange={maj("sauvegarde_dossier")} placeholder="Ex. : E:\Sauvegardes ou C:\Users\agence\Google Drive\BailManager" aide="Disque externe, partage réseau, ou dossier synchronisé avec Google Drive / OneDrive / Dropbox." />
              <Saisie libelle="Copies conservées" type="number" min="1" max="365" valeur={f.sauvegarde_garder} onChange={maj("sauvegarde_garder")} />
            </>
          )}
          {d === "webdav" && (
            <>
              <Saisie libelle="Adresse WebDAV du dossier" plein valeur={f.sauvegarde_url} onChange={maj("sauvegarde_url")} placeholder="https://cloud.exemple.com/remote.php/dav/files/agence/Sauvegardes" />
              <Saisie libelle="Identifiant" valeur={f.sauvegarde_identifiant} onChange={maj("sauvegarde_identifiant")} />
              <Secret libelle="Mot de passe d'application" renseigne={s.sauvegarde_secret} valeur={f.sauvegarde_secret} onChange={maj("sauvegarde_secret")} />
            </>
          )}
          {d === "s3" && (
            <>
              <Saisie libelle="Point d'accès" valeur={f.sauvegarde_url} onChange={maj("sauvegarde_url")} placeholder="https://s3.eu-central-003.backblazeb2.com" />
              <Saisie libelle="Compartiment (bucket)" valeur={f.sauvegarde_bucket} onChange={maj("sauvegarde_bucket")} />
              <Saisie libelle="Région" valeur={f.sauvegarde_region} onChange={maj("sauvegarde_region")} placeholder="Ex. : eu-central-003" />
              <Saisie libelle="Access key" valeur={f.sauvegarde_identifiant} onChange={maj("sauvegarde_identifiant")} />
              <Secret libelle="Secret key" renseigne={s.sauvegarde_secret} valeur={f.sauvegarde_secret} onChange={maj("sauvegarde_secret")} />
            </>
          )}
          {d !== "desactive" && <Secret libelle="Phrase de chiffrement (12 caractères minimum)" renseigne={s.sauvegarde_phrase} valeur={f.sauvegarde_phrase} onChange={maj("sauvegarde_phrase")} aide={s.sauvegarde_phrase ? "Enregistrée. Laissez vide pour la conserver ; la changer ne rechiffre pas les anciennes copies." : "Une phrase longue et unique. Notez-la hors de l'ordinateur."} />}
        </div>
        <div className="ligne-flex" style={{ marginTop: 14 }}>
          <Bouton type="submit" variante="primaire" icone={Save} chargement={etat.attente === "enregistrer"}>Enregistrer</Bouton>
          {data.sauvegarde_destination !== "desactive" && <Bouton icone={CloudUpload} chargement={etat.attente === "maintenant"} onClick={maintenant}>Sauvegarder maintenant</Bouton>}
        </div>
        <p className="discret petit-texte" style={{ marginBottom: 0 }}>Restauration : <code>python manage.py dechiffrer_sauvegarde fichier.zip.chiffre</code> redonne l'archive ZIP.</p>
      </Panneau>
    </form>
  );
}
