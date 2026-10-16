import { useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { FileSignature, Home, Pencil, Plus, Printer, Trash2, Wallet } from "lucide-react";
import { api } from "../../lib/api";
import { MODES, dateFr, dureeMandat, fcfa, joursAvantFinMandat } from "../../lib/format";
import { useAction } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import {
  Avatar, Badge, Bouton, Chargement, Definitions, Encart, EntetePage, FenetreFormulaire, LienBouton, Liste, Montant, Onglets,
  Panneau, Saisie, SaisieMontant, Statut, Tableau, Vide, useConfirmer,
} from "../../components/ui";
import { Documents, FormBailleur, FormMaison, FormMandat } from "../../components/formulaires";
import AccesBailleur from "../../components/AccesBailleur";

export function FormVersement({ ouvert, onFermer, bailleur, solde }) {
  const [f, setF] = useState({ montant: "", mode: "especes", commentaire: "" });
  return (
    <FenetreFormulaire
      titre={`Verser au bailleur — ${bailleur?.nom || ""}`}
      ouvert={ouvert}
      onFermer={onFermer}
      libelleValider="Enregistrer le versement"
      onValider={async () => {
        await api.post("/compte-bailleurs/verser/", { bailleur: bailleur.id, ...f });
        setF({ montant: "", mode: "especes", commentaire: "" });
        return "Versement enregistré.";
      }}
    >
      <Encart>Solde disponible : <b>{fcfa(solde)}</b></Encart>
      <div className="formulaire">
        <SaisieMontant libelle="Montant" valeur={f.montant} onChange={(montant) => setF({ ...f, montant })} requis max={solde} />
        <Liste libelle="Mode de paiement" valeur={f.mode} onChange={(mode) => setF({ ...f, mode })} options={MODES} />
        <Saisie libelle="Commentaire" valeur={f.commentaire} onChange={(commentaire) => setF({ ...f, commentaire })} plein placeholder="Ex. : versement loyers de mai" />
      </div>
    </FenetreFormulaire>
  );
}

export default function FicheBailleur() {
  const { id } = useParams();
  const nav = useNavigate();
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const [onglet, setOnglet] = useState("biens");
  const [modal, setModal] = useState(null);
  const { data: b, isLoading, refetch } = useQuery({ queryKey: ["bailleur", id], queryFn: () => api.get(`/bailleurs/${id}/`) });
  const { data: mandats = [] } = useQuery({ queryKey: ["mandats", id], queryFn: () => api.get("/mandats/", { bailleur: id }) });
  const { data: maisons } = useQuery({ queryKey: ["maisons", { bailleur: id }], queryFn: () => api.get("/maisons/", { bailleur: id, taille: 200 }) });
  const { data: ops } = useQuery({ queryKey: ["compte-bailleurs", { bailleur: id }], queryFn: () => api.get("/compte-bailleurs/", { bailleur: id, taille: 50 }), enabled: onglet === "compte" });
  const supprimer = useAction(() => api.delete(`/bailleurs/${id}/`), { succes: "Bailleur supprimé.", apres: () => nav("/bailleurs") });
  const resilier = useAction((m) => api.post(`/mandats/${m.id}/resilier/`, { forcer: true }), { succes: "Mandat résilié." });

  if (isLoading || !b) return <Chargement />;
  const fermer = () => (setModal(null), refetch());

  return (
    <div className="pile">
      <EntetePage titre={b.nom} fil={[{ libelle: "Bailleurs", lien: "/bailleurs" }]}>
        <Bouton icone={Pencil} onClick={() => setModal("modifier")}>Modifier</Bouton>
        <LienBouton to={`/impression/releve-bailleur/${b.id}`} icone={Printer}>Relevé de compte</LienBouton>
        <LienBouton to={`/impression/recap-fiscal/${b.id}?annee=${new Date().getFullYear()}`} icone={Printer}>Récapitulatif annuel</LienBouton>
        <Bouton variante="primaire" icone={Wallet} disabled={Number(b.solde) <= 0} onClick={() => setModal("versement")}>Verser au bailleur</Bouton>
      </EntetePage>

      <div className="grille g-1-2">
        <Panneau>
          <div className="identite" style={{ marginBottom: 16 }}>
            <Avatar nom={b.nom} src={b.photo} grand />
            <div>
              <h2>{b.nom}</h2>
              <div className="discret">{b.code_bailleur}</div>
            </div>
          </div>
          <Definitions items={[["Téléphone", [b.telephone1, b.telephone2].filter(Boolean).join(" / ")], ["E-mail", b.email], ["N° CNI", b.numero_cni], ["Adresse", b.adresse], ["Client depuis", dateFr(b.created_at)]]} />
          {estAdmin && (
            <Bouton variante="fantome" taille="petit" icone={Trash2} style={{ marginTop: 14, color: "var(--signal)" }} onClick={async () => (await confirmer({ message: `Supprimer définitivement ${b.nom} ? Possible uniquement s'il n'a ni bien ni historique financier.`, danger: true, libelle: "Supprimer" })) && supprimer.mutate()}>
              Supprimer
            </Bouton>
          )}
        </Panneau>
        <div className="pile">
          <div className="registre">
            <div><div className="libelle">Solde dû au bailleur</div><div className="valeur">{Math.round(b.solde).toLocaleString("fr-FR")}<small>FCFA</small></div></div>
            <div><div className="libelle">Commissions agence</div><div className="valeur">{Math.round(b.commissions).toLocaleString("fr-FR")}<small>FCFA</small></div></div>
            <div><div className="libelle">Biens</div><div className="valeur">{b.nb_maisons}</div><div className="note">{b.nb_occupees} occupé(s)</div></div>
          </div>
          <Panneau titre="Mandat de gestion" icone={FileSignature} actions={<Bouton taille="petit" icone={Plus} onClick={() => setModal("mandat")}>{b.mandat ? "Renouveler" : "Enregistrer le mandat"}</Bouton>}>
            {b.mandat ? (
              <div className="ligne-flex">
                <Badge ton="ok">En vigueur</Badge>
                {b.mandat.reference && <b>{b.mandat.reference}</b>}
                <span>Commission <b>{b.mandat.taux_commission} %</b></span>
                <span className="discret">depuis le {dateFr(b.mandat.date_debut)} — {dureeMandat(b.mandat)}</span>
                {joursAvantFinMandat(b.mandat) !== null && joursAvantFinMandat(b.mandat) <= 90 && <Badge ton="attention">Fin dans {joursAvantFinMandat(b.mandat)} j</Badge>}
                <span className="espace" />
                <LienBouton taille="petit" to={`/impression/mandat/${b.mandat.id}`} icone={Printer}>Imprimer</LienBouton>
              </div>
            ) : (
              <Encart ton="alerte" titre="Aucun mandat en vigueur">Sans mandat, impossible d'ajouter un bien ou de signer un bail pour ce bailleur.</Encart>
            )}
          </Panneau>
          <AccesBailleur b={b} onChange={refetch} />
        </div>
      </div>

      <Panneau>
        <Onglets
          actif={onglet}
          onChange={setOnglet}
          onglets={[
            { cle: "biens", libelle: "Biens", compte: maisons?.count },
            { cle: "mandats", libelle: "Historique des mandats", compte: mandats.length },
            { cle: "compte", libelle: "Compte courant" },
            { cle: "documents", libelle: "Documents" },
          ]}
        />
        {onglet === "biens" && (
          <>
            <div className="ligne-flex" style={{ marginBottom: 12 }}>
              <span className="espace" />
              <Bouton icone={Plus} disabled={!b.mandat} onClick={() => setModal("maison")}>Ajouter un bien</Bouton>
            </div>
            <Tableau
              lignes={maisons?.results || []}
              surLigne={(m) => nav(`/maisons/${m.id}`)}
              vide={<Vide icone={Home} titre="Aucun bien enregistré" />}
              colonnes={[
                { libelle: "Bien", rendu: (m) => <><div className="principal-cellule">{m.designation}</div><div className="sous-cellule">{m.type_maison}</div></> },
                { libelle: "Locataire", rendu: (m) => m.locataire_actuel?.nom || <span className="discret">—</span> },
                { libelle: "Statut", rendu: (m) => <Statut s={m.statut} /> },
                { libelle: "Loyer", cle: "loyer", type: "montant" },
              ]}
            />
          </>
        )}
        {onglet === "mandats" && (
          <Tableau
            lignes={mandats}
            colonnes={[
              { libelle: "Mandat", rendu: (m) => <b>{m.reference}</b> },
              { libelle: "Signé le", rendu: (m) => dateFr(m.date_signature) },
              { libelle: "Durée", rendu: (m) => `Depuis le ${dateFr(m.date_debut)} — ${dureeMandat(m)}` },
              { libelle: "Taux", rendu: (m) => `${m.taux_commission} %` },
              { libelle: "Baux signés", rendu: (m) => m.nb_baux },
              { libelle: "Statut", rendu: (m) => <Statut s={m.en_vigueur ? "actif" : m.statut} /> },
              { libelle: "Scan", rendu: (m) => (m.document_signe ? <a href={m.document_signe}>Voir</a> : "—") },
              {
                libelle: "",
                rendu: (m) => (
                  <div className="actions-cellule">
                    <LienBouton taille="petit" variante="fantome" icone={Printer} to={`/impression/mandat/${m.id}`} />
                    {estAdmin && m.statut === "actif" && (
                      <Bouton taille="petit" variante="fantome" onClick={async () => (await confirmer({ message: "Résilier ce mandat ? Aucun nouveau bien ni bail ne pourra être enregistré pour ce bailleur.", danger: true, libelle: "Résilier" })) && resilier.mutate(m, { onSuccess: () => refetch() })}>Résilier</Bouton>
                    )}
                  </div>
                ),
              },
            ]}
          />
        )}
        {onglet === "compte" && (
          <Tableau
            lignes={ops?.results || []}
            chargement={!ops}
            colonnes={[
              { libelle: "Date", rendu: (o) => dateFr(o.date_operation) },
              { libelle: "Opération", rendu: (o) => <><div className="principal-cellule">{o.type_libelle}</div><div className="sous-cellule">{o.commentaire}</div></> },
              { libelle: "Montant", droite: true, rendu: (o) => <Montant v={o.montant} signe /> },
            ]}
          />
        )}
        {onglet === "documents" && <Documents type="bailleur" id={b.id} />}
      </Panneau>

      <FormBailleur ouvert={modal === "modifier"} bailleur={b} onFermer={fermer} />
      <FormMandat ouvert={modal === "mandat"} bailleur={b} onFermer={fermer} />
      <FormMaison ouvert={modal === "maison"} bailleurParDefaut={b.id} onFermer={fermer} />
      <FormVersement ouvert={modal === "versement"} bailleur={b} solde={b.solde} onFermer={fermer} />
      <Link to="/bailleurs" hidden>Retour</Link>
    </div>
  );
}
