import { useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { FilePlus2, KeyRound, MessageSquare, Pencil, Printer, Trash2 } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr } from "../../lib/format";
import { useAction } from "../../lib/hooks";
import { useSession } from "../../lib/session";
import { Anciennete, Avatar, Badge, Bouton, Chargement, Definitions, Encart, EntetePage, Fenetre, LienBouton, Montant, Onglets, Panneau, Statut, Tableau, Vide, useConfirmer } from "../../components/ui";
import { Documents, FormLocataire } from "../../components/formulaires";

export default function FicheLocataire() {
  const { id } = useParams();
  const nav = useNavigate();
  const { estAdmin } = useSession();
  const confirmer = useConfirmer();
  const [onglet, setOnglet] = useState("paiements");
  const [modif, setModif] = useState(false);
  const [code, setCode] = useState(null);
  const { data: l, refetch } = useQuery({ queryKey: ["locataire", id], queryFn: () => api.get(`/locataires/${id}/`) });
  const { data: contrats } = useQuery({ queryKey: ["contrats", { locataire: id }], queryFn: () => api.get("/contrats/", { locataire: id, taille: 50 }) });
  const { data: paiements } = useQuery({ queryKey: ["encaissements", { locataire: id }], queryFn: () => api.get("/encaissements/", { locataire: id, taille: 100 }) });
  const generer = useAction(() => api.post(`/locataires/${id}/generer-code/`), { apres: (r) => (setCode(r), refetch()) });
  const desactiver = useAction(() => api.post(`/locataires/${id}/desactiver-acces/`), { succes: "Accès désactivé.", apres: () => refetch() });
  const supprimer = useAction(() => api.delete(`/locataires/${id}/`), { succes: "Locataire supprimé.", apres: () => nav("/locataires") });
  if (!l) return <Chargement />;

  return (
    <div className="pile">
      <EntetePage titre={l.nom} fil={[{ libelle: "Locataires", lien: "/locataires" }]}>
        <Bouton icone={Pencil} onClick={() => setModif(true)}>Modifier</Bouton>
        <LienBouton icone={MessageSquare} to={`/messagerie?locataire=${l.id}`}>Message</LienBouton>
        {!l.contrat_actif && <LienBouton variante="primaire" icone={FilePlus2} to={`/contrats/nouveau?locataire=${l.id}`}>Nouveau bail</LienBouton>}
      </EntetePage>
      <div className="grille g-1-2">
        <div className="pile">
          <Panneau>
            <div className="identite" style={{ marginBottom: 16 }}>
              <Avatar nom={l.nom} src={l.photo_locataire} grand />
              <div><h2>{l.nom}</h2><div className="discret">{l.profession}</div></div>
            </div>
            <Definitions items={[["Téléphone", [l.telephone1, l.telephone2].filter(Boolean).join(" / ")], ["E-mail", l.email], ["Pièce d'identité", l.piece_identite], ["Dossier créé le", dateFr(l.created_at)]]} />
            {(l.photo_cni_recto || l.photo_cni_verso) && (
              <div className="galerie" style={{ gridTemplateColumns: "1fr 1fr", marginTop: 14 }}>
                {[l.photo_cni_recto, l.photo_cni_verso].filter(Boolean).map((src) => <a key={src} href={src} target="_blank" rel="noreferrer"><img src={src} alt="Pièce d'identité" /></a>)}
              </div>
            )}
            {estAdmin && !contrats?.count && (
              <Bouton variante="fantome" taille="petit" icone={Trash2} style={{ color: "var(--signal)", marginTop: 10 }} onClick={async () => (await confirmer({ message: `Supprimer le dossier de ${l.nom} ?`, danger: true, libelle: "Supprimer" })) && supprimer.mutate()}>Supprimer</Bouton>
            )}
          </Panneau>
          <Panneau titre="Espace locataire" icone={KeyRound}>
            <p className="discret">Le locataire se connecte sur <b>/espace-locataire</b> avec son téléphone ({l.telephone1 || "non renseigné"}) et un code d'accès pour consulter ses paiements et télécharger ses quittances.</p>
            <div className="ligne-flex">
              {l.acces_portail ? <Badge ton="ok">Accès activé</Badge> : <Badge>Pas d'accès</Badge>}
              <span className="espace" />
              {estAdmin && (
                <>
                  {l.acces_portail && <Bouton taille="petit" onClick={() => desactiver.mutate()}>Désactiver</Bouton>}
                  <Bouton taille="petit" variante="primaire" chargement={generer.isPending} onClick={async () => (!l.acces_portail || (await confirmer("Générer un nouveau code ? L'ancien ne fonctionnera plus."))) && generer.mutate()}>
                    {l.acces_portail ? "Nouveau code" : "Générer un code"}
                  </Bouton>
                </>
              )}
            </div>
          </Panneau>
        </div>
        <div className="pile">
          {l.contrat_actif ? (
            <Panneau titre="Bail en cours" actions={<LienBouton taille="petit" to={`/contrats/${l.contrat_actif.id}`}>Ouvrir le contrat</LienBouton>}>
              <div className="ligne-flex">
                <b>{l.contrat_actif.maison}</b>
                <Statut s={l.contrat_actif.statut_paiement} />
                <span className="espace" />
                <span>Loyer <Montant v={l.contrat_actif.loyer} /></span>
                <span className="discret">Prochaine échéance : {dateFr(l.contrat_actif.date_prochain_loyer)}</span>
              </div>
            </Panneau>
          ) : (
            <Encart>Aucun bail en cours pour ce locataire.</Encart>
          )}
          <Panneau>
            <Onglets actif={onglet} onChange={setOnglet} onglets={[{ cle: "paiements", libelle: "Paiements", compte: paiements?.count }, { cle: "contrats", libelle: "Contrats", compte: contrats?.count }, { cle: "documents", libelle: "Documents" }]} />
            {onglet === "paiements" && (
              <Tableau
                lignes={paiements?.results || []}
                vide={<Vide titre="Aucun paiement" />}
                colonnes={[
                  { libelle: "Reçu", rendu: (p) => <Link to={`/impression/quittance/${p.id}`} target="_blank">{p.reference_recu}</Link> },
                  { libelle: "Période", cle: "periode_concernee" },
                  { libelle: "Date", rendu: (p) => dateFr(p.date_encaissement) },
                  { libelle: "Mode", cle: "mode_libelle" },
                  { libelle: "Montant", cle: "montant_recu", type: "montant" },
                ]}
              />
            )}
            {onglet === "contrats" && (
              <Tableau
                lignes={contrats?.results || []}
                surLigne={(c) => nav(`/contrats/${c.id}`)}
                colonnes={[
                  { libelle: "Contrat", cle: "reference" },
                  { libelle: "Bien", cle: "maison_nom" },
                  { libelle: "Début", rendu: (c) => dateFr(c.date_debut) },
                  { libelle: "Ancienneté", rendu: (c) => <Anciennete a={c.anciennete} /> },
                  { libelle: "Statut", rendu: (c) => <Statut s={c.statut_contrat} /> },
                  { libelle: "", rendu: (c) => <LienBouton taille="petit" variante="fantome" icone={Printer} to={`/impression/contrat/${c.id}`} target="_blank" /> },
                ]}
              />
            )}
            {onglet === "documents" && <Documents type="locataire" id={l.id} />}
          </Panneau>
        </div>
      </div>
      <FormLocataire ouvert={modif} locataire={l} onFermer={() => (setModif(false), refetch())} />
      <Fenetre titre="Code d'accès à l'espace locataire" ouvert={!!code} onFermer={() => setCode(null)} taille="etroite" pied={<Bouton variante="primaire" onClick={() => setCode(null)}>J'ai noté le code</Bouton>}>
        <p>Communiquez ces informations à <b>{code?.nom}</b>. Le code n'est affiché qu'une seule fois.</p>
        <div className="panneau" style={{ padding: 16, textAlign: "center", boxShadow: "none" }}>
          <div className="discret">Téléphone</div>
          <div className="gras" style={{ fontSize: 18 }}>{code?.telephone}</div>
          <div className="discret" style={{ marginTop: 10 }}>Code d'accès</div>
          <div style={{ fontSize: 30, fontWeight: 800, letterSpacing: "0.2em" }}>{code?.code}</div>
        </div>
      </Fenetre>
    </div>
  );
}
