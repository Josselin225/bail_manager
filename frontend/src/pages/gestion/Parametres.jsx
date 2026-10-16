import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, BookMarked, DatabaseBackup, Pencil, Plus, Save, Trash2 } from "lucide-react";
import { api, telecharger } from "../../lib/api";
import { appliquerCouleur, useSession } from "../../lib/session";
import { Badge, Bouton, Case, Encart, EntetePage, Fenetre, FenetreFormulaire, Fichier, Onglets, Panneau, Saisie, Tableau, Zone, useConfirmer, useToast } from "../../components/ui";

const COULEURS = ["#0e2a47", "#1f3b73", "#0b4f4a", "#3d2a6b", "#5a1f2a", "#26323f"];

function Entreprise() {
  const toast = useToast();
  const { rafraichir } = useSession();
  const { data, refetch } = useQuery({ queryKey: ["parametres"], queryFn: () => api.get("/parametres/") });
  const [f, setF] = useState(null);
  const [envoi, setEnvoi] = useState(false);
  useEffect(() => { data && setF({ ...data, logo: null }); }, [data]);
  if (!f) return null;
  const maj = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  const enregistrer = async (e) => {
    e.preventDefault();
    setEnvoi(true);
    try {
      const { pied_de_page, updated_at, logo, ...reste } = f;
      await api.patch("/parametres/", logo instanceof File ? { ...reste, logo } : reste);
      toast.succes("Paramètres enregistrés.");
      refetch();
      rafraichir();
    } catch (err) {
      toast.erreur(err.message);
    } finally {
      setEnvoi(false);
    }
  };
  return (
    <form onSubmit={enregistrer} className="pile">
      <Panneau titre="Identité de l'agence">
        <div className="formulaire">
          <Saisie libelle="Nom de l'entreprise" requis valeur={f.nom_entreprise} onChange={maj("nom_entreprise")} />
          <Saisie libelle="Site web" type="url" placeholder="https://" valeur={f.site_web} onChange={maj("site_web")} />
          <Saisie libelle="Téléphone" type="tel" placeholder="07 00 00 00 00" valeur={f.contact_telephone} onChange={maj("contact_telephone")} />
          <Saisie libelle="E-mail" type="email" valeur={f.contact_email} onChange={maj("contact_email")} aide="Reçoit la synthèse quotidienne et une copie des quittances." />
          <Zone libelle="Adresse du siège" valeur={f.adresse_siege} onChange={maj("adresse_siege")} rows={2} />
          <Zone libelle="Activités" aide="Affichées sous le nom de l'agence sur les documents." valeur={f.activites} onChange={maj("activites")} rows={2} />
          <Fichier libelle="Logo" valeur={f.logo} actuel={data.logo} onChange={maj("logo")} />
          <div className="champ">
            <label>Couleur du menu</label>
            <div className="ligne-flex">
              {COULEURS.map((c) => (
                <button key={c} type="button" onClick={() => (maj("couleur_principale")(c), appliquerCouleur(c))} aria-label={c} style={{ width: 30, height: 30, borderRadius: 8, background: c, border: f.couleur_principale === c ? "3px solid var(--focus)" : "1px solid var(--ligne)", cursor: "pointer" }} />
              ))}
              <input type="color" value={f.couleur_principale || "#0e2a47"} onChange={(e) => (maj("couleur_principale")(e.target.value), appliquerCouleur(e.target.value))} aria-label="Couleur personnalisée" />
            </div>
          </div>
        </div>
      </Panneau>
      <Panneau titre="Signataire des contrats">
        <p className="discret" style={{ marginTop: 0 }}>Personne qui signe pour l'agence les mandats de gestion (avec les bailleurs) et les contrats de bail (avec les locataires).</p>
        <div className="formulaire">
          <Saisie libelle="Nom du représentant" valeur={f.representant_nom} onChange={maj("representant_nom")} placeholder="Ex. : M. Kouassi Jean" />
          <Saisie libelle="Qualité" valeur={f.representant_qualite} onChange={maj("representant_qualite")} placeholder="Ex. : Gérant, Directeur" />
          <Saisie libelle="Ville de signature" valeur={f.ville_signature} onChange={maj("ville_signature")} placeholder="Ex. : Yamoussoukro" aide="Apparaît dans « Fait à …, le … »." />
        </div>
      </Panneau>
      <Panneau titre="Mentions légales et bancaires (pied des documents)">
        <div className="formulaire">
          <Saisie libelle="N° compte contribuable (CC)" valeur={f.cc_numero} onChange={maj("cc_numero")} />
          <Saisie libelle="Régime d'imposition" valeur={f.regime_imposition} onChange={maj("regime_imposition")} />
          <Saisie libelle="N° RCCM" valeur={f.rccm_numero} onChange={maj("rccm_numero")} />
          <Saisie libelle="Compte bancaire" valeur={f.compte_bancaire} onChange={maj("compte_bancaire")} />
          <Saisie libelle="IBAN" valeur={f.iban} onChange={maj("iban")} />
          <Saisie libelle="SWIFT" valeur={f.swift} onChange={maj("swift")} />
        </div>
        {data.pied_de_page.length > 0 && (
          <div className="petit-texte discret centre" style={{ marginTop: 14, borderTop: "1px solid var(--ligne)", paddingTop: 10 }}>
            {data.pied_de_page.map((l) => <div key={l}>{l}</div>)}
          </div>
        )}
      </Panneau>
      <Panneau titre="Gestion">
        <div className="formulaire">
          <Saisie libelle="Taux de commission par défaut" type="number" step="0.5" min="0" max="100" suffixe="%" valeur={f.taux_commission} onChange={maj("taux_commission")} aide="Appliqué si un mandat ne précise pas son taux ; pré-rempli à la saisie d'un mandat." />
          <Case libelle="Rappels automatiques par e-mail" aide="Retards de loyer, échéances et fins de bail." plein={false} valeur={f.rappels_actifs} onChange={maj("rappels_actifs")} />
        </div>
      </Panneau>
      <div><Bouton type="submit" variante="primaire" icone={Save} chargement={envoi}>Enregistrer les paramètres</Bouton></div>
    </form>
  );
}

function Clauses({ url, titre, bibliotheque }) {
  const confirmer = useConfirmer();
  const [edit, setEdit] = useState(null);
  const [biblio, setBiblio] = useState(false);
  const [f, setF] = useState({});
  const { data = [], refetch } = useQuery({ queryKey: [url], queryFn: () => api.get(url) });
  const { data: articles = [] } = useQuery({ queryKey: ["bibliotheque"], queryFn: () => api.get("/clauses-contrat/bibliotheque/"), enabled: biblio });
  useEffect(() => { edit && setF(edit === "nouveau" ? { titre: "", contenu: "", actif: true, ordre_affichage: (data.length + 1) * 10 } : edit); }, [edit]); // eslint-disable-line
  const deplacer = async (i, d) => {
    const ids = data.map((c) => c.id);
    [ids[i], ids[i + d]] = [ids[i + d], ids[i]];
    await api.post(`${url}ordonner/`, { ordre: ids });
    refetch();
  };
  const ajouterArticle = async (a) => {
    await api.post(url, { titre: `${a.theme} (${a.article})`, contenu: a.texte, actif: true, ordre_affichage: (data.length + 1) * 10 });
    refetch();
  };
  return (
    <Panneau titre={titre} actions={<>{bibliotheque && <Bouton taille="petit" icone={BookMarked} onClick={() => setBiblio(true)}>Articles de loi</Bouton>}<Bouton taille="petit" variante="primaire" icone={Plus} onClick={() => setEdit("nouveau")}>Ajouter une clause</Bouton></>} serre>
      <Tableau
        lignes={data}
        colonnes={[
          { libelle: "", largeur: 70, rendu: (c) => { const i = data.indexOf(c); return <div className="ligne-flex" style={{ gap: 2 }}><Bouton taille="petit" variante="fantome" icone={ArrowUp} disabled={i === 0} onClick={() => deplacer(i, -1)} aria-label="Monter" /><Bouton taille="petit" variante="fantome" icone={ArrowDown} disabled={i === data.length - 1} onClick={() => deplacer(i, 1)} aria-label="Descendre" /></div>; } },
          { libelle: "Clause", rendu: (c) => <><div className="principal-cellule">{c.titre}</div><div className="sous-cellule" style={{ maxWidth: 720 }}>{c.contenu}</div></> },
          { libelle: "État", rendu: (c) => (c.actif ? <Badge ton="ok">Imprimée</Badge> : <Badge>Masquée</Badge>) },
          { libelle: "", rendu: (c) => <div className="actions-cellule"><Bouton taille="petit" variante="fantome" icone={Pencil} onClick={() => setEdit(c)} aria-label="Modifier" /><Bouton taille="petit" variante="fantome" icone={Trash2} aria-label="Supprimer" onClick={async () => (await confirmer({ message: `Supprimer la clause « ${c.titre} » ?`, danger: true })) && api.delete(`${url}${c.id}/`).then(refetch)} /></div> },
        ]}
      />
      <FenetreFormulaire titre={edit === "nouveau" ? "Nouvelle clause" : "Modifier la clause"} ouvert={!!edit} taille="large" onFermer={() => (setEdit(null), refetch())} onValider={async () => (edit === "nouveau" ? await api.post(url, f) : await api.patch(`${url}${edit.id}/`, f), "Clause enregistrée.")}>
        <div className="formulaire">
          <Saisie libelle="Titre" requis plein valeur={f.titre} onChange={(titre) => setF({ ...f, titre })} placeholder="Ex. : Dépôt de garantie" />
          <Zone libelle="Texte de la clause" requis valeur={f.contenu} onChange={(contenu) => setF({ ...f, contenu })} rows={7} />
          <Case libelle="Imprimer cette clause sur les documents" valeur={f.actif} onChange={(actif) => setF({ ...f, actif })} />
        </div>
      </FenetreFormulaire>
      <Fenetre titre="Loi n° 2019-576 — bail à usage d'habitation" ouvert={biblio} onFermer={() => setBiblio(false)} taille="large">
        <Encart>Reformulations prêtes à insérer. Le texte de loi reste la référence en cas de litige.</Encart>
        {articles.map((a) => (
          <div key={a.article} style={{ borderBottom: "1px solid var(--ligne)", padding: "12px 0" }}>
            <div className="ligne-flex"><b>{a.theme}</b><span className="discret">{a.article}</span><span className="espace" /><Bouton taille="petit" icone={Plus} onClick={() => ajouterArticle(a)}>Ajouter</Bouton></div>
            <div className="discret" style={{ marginTop: 4 }}>{a.texte}</div>
          </div>
        ))}
      </Fenetre>
    </Panneau>
  );
}

function Sauvegarde() {
  const toast = useToast();
  const [envoi, setEnvoi] = useState(false);
  return (
    <Panneau titre="Sauvegarde des données" icone={DatabaseBackup}>
      <p>Télécharge une archive ZIP contenant toutes les données (bailleurs, contrats, encaissements, comptabilité, journal…) et les fichiers envoyés (photos, scans).</p>
      <Encart ton="attention">Conservez l'archive hors du serveur (clé USB, disque externe, cloud). Une sauvegarde automatique quotidienne peut aussi être planifiée : voir le guide d'installation.</Encart>
      <div style={{ marginTop: 14 }}>
        <Bouton variante="primaire" icone={DatabaseBackup} chargement={envoi} onClick={async () => { setEnvoi(true); try { await telecharger("/sauvegarde/", {}, "sauvegarde.zip"); toast.succes("Sauvegarde téléchargée."); } catch (e) { toast.erreur(e.message); } finally { setEnvoi(false); } }}>
          Télécharger une sauvegarde
        </Bouton>
      </div>
    </Panneau>
  );
}

export default function Parametres() {
  const [onglet, setOnglet] = useState("entreprise");
  return (
    <>
      <EntetePage titre="Paramètres" soustitre="Identité de l'agence, mentions des documents, clauses types et sauvegardes." />
      <Onglets actif={onglet} onChange={setOnglet} onglets={[{ cle: "entreprise", libelle: "Entreprise" }, { cle: "bail", libelle: "Clauses du bail" }, { cle: "mandat", libelle: "Clauses du mandat" }, { cle: "sauvegarde", libelle: "Sauvegarde" }]} />
      {onglet === "entreprise" && <Entreprise />}
      {onglet === "bail" && <Clauses url="/clauses-contrat/" titre="Clauses imprimées sur les contrats de bail" bibliotheque />}
      {onglet === "mandat" && <Clauses url="/clauses-mandat/" titre="Clauses imprimées sur les mandats de gestion" />}
      {onglet === "sauvegarde" && <Sauvegarde />}
    </>
  );
}
