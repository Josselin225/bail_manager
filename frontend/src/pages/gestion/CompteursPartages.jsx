import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Droplets, History, Pencil, Plus, Receipt, Zap } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr, fcfa, isoDate } from "../../lib/format";
import { Badge, Bouton, Chargement, Encart, EntetePage, Fenetre, FenetreFormulaire, Liste, Panneau, Saisie, SaisieMontant, Vide } from "../../components/ui";

const MODES = [{ valeur: "index", libelle: "Au prorata des sous-compteurs (index)" }, { valeur: "egal", libelle: "À parts égales entre logements occupés" }];
const nombre = (v) => (v === "" || v == null ? null : Number(String(v).replace(",", ".")));

function FormCompteur({ ouvert, onFermer, compteur }) {
  const vide = { nom: "", type_charge: "Eau", numero: "", mode: "index", maisons: [] };
  const [f, setF] = useState(vide);
  const [filtre, setFiltre] = useState("");
  useEffect(() => { ouvert && setF(compteur ? { ...compteur } : vide); }, [ouvert]); // eslint-disable-line
  const { data: maisons } = useQuery({ queryKey: ["maisons", { taille: 500 }], queryFn: () => api.get("/maisons/", { taille: 500 }), enabled: ouvert });
  const liste = (maisons?.results || []).filter((m) => `${m.designation} ${m.bailleur_nom} ${m.adresse}`.toLowerCase().includes(filtre.toLowerCase()));
  const basculer = (id) => setF((x) => ({ ...x, maisons: x.maisons.includes(id) ? x.maisons.filter((i) => i !== id) : [...x.maisons, id] }));
  return (
    <FenetreFormulaire titre={compteur ? "Modifier le compteur" : "Nouveau compteur partagé"} ouvert={ouvert} onFermer={onFermer} taille="large"
      onValider={async () => {
        const d = { nom: f.nom, type_charge: f.type_charge, numero: f.numero, mode: f.mode, maisons: f.maisons };
        await (compteur ? api.patch(`/compteurs/${compteur.id}/`, d) : api.post("/compteurs/", d));
        return "Compteur enregistré.";
      }}>
      <div className="formulaire">
        <Saisie libelle="Nom" requis valeur={f.nom} onChange={(nom) => setF({ ...f, nom })} placeholder="Ex. : Cour Habitat — compteur SODECI" />
        <Liste libelle="Type" valeur={f.type_charge} onChange={(type_charge) => setF({ ...f, type_charge })} options={[{ valeur: "Eau", libelle: "Eau" }, { valeur: "Électricité", libelle: "Électricité" }]} />
        <Saisie libelle="N° de compteur ou de contrat" valeur={f.numero} onChange={(numero) => setF({ ...f, numero })} />
        <Liste libelle="Répartition" valeur={f.mode} onChange={(mode) => setF({ ...f, mode })} options={MODES} />
      </div>
      <div className="champ plein" style={{ marginTop: 12 }}>
        <label>Logements desservis ({f.maisons.length}) <span className="requis">*</span></label>
        <input className="saisie" placeholder="Filtrer les biens…" value={filtre} onChange={(e) => setFiltre(e.target.value)} />
        <div className="choix-maisons">
          {liste.map((m) => (
            <label key={m.id} className={`case-maison ${f.maisons.includes(m.id) ? "choisie" : ""}`}>
              <input type="checkbox" checked={f.maisons.includes(m.id)} onChange={() => basculer(m.id)} />
              <span><b>{m.designation}</b><span className="petit-texte discret">{m.bailleur_nom} — {m.adresse}</span></span>
            </label>
          ))}
        </div>
        <div className="aide">Au moins deux logements.</div>
      </div>
    </FenetreFormulaire>
  );
}

/** Saisie d'une facture : index des sous-compteurs, aperçu de la répartition, puis charges créées sur les baux. */
function FormFacture({ ouvert, onFermer, compteur }) {
  const [f, setF] = useState({ numero: "", date_facture: isoDate(), periode: "", montant: "" });
  const [index, setIndex] = useState({});
  useEffect(() => { ouvert && (setF({ numero: "", date_facture: isoDate(), periode: "", montant: "" }), setIndex({})); }, [ouvert]);
  const { data: prep } = useQuery({ queryKey: ["compteur-preparation", compteur?.id, f.date_facture], queryFn: () => api.get(`/compteurs/${compteur.id}/preparation/`, { date: f.date_facture }), enabled: ouvert && !!compteur });
  const parIndex = compteur?.mode === "index";
  const apercu = useMemo(() => {
    if (!prep) return null;
    const montant = Number(f.montant) || 0;
    const lignes = prep.map((l) => {
      const precedent = nombre(index[l.maison]?.precedent ?? l.index_precedent);
      const actuel = nombre(index[l.maison]?.actuel);
      return { ...l, precedent, actuel, conso: parIndex && precedent != null && actuel != null ? actuel - precedent : null };
    });
    if (parIndex) {
      const total = lignes.reduce((s, l) => s + (l.conso > 0 ? l.conso : 0), 0);
      lignes.forEach((l) => { l.part = total > 0 && l.conso > 0 ? Math.round((montant * l.conso) / total) : 0; });
    } else {
      const occ = lignes.filter((l) => l.contrat).length;
      lignes.forEach((l) => { l.part = occ && l.contrat ? Math.round(montant / occ) : 0; });
    }
    return lignes;
  }, [prep, index, f.montant, parIndex]);
  return (
    <FenetreFormulaire titre={`Facture — ${compteur?.nom || ""}`} ouvert={ouvert} onFermer={onFermer} taille="large" libelleValider="Répartir la facture" icone={Receipt}
      onValider={async () => {
        await api.post(`/compteurs/${compteur.id}/factures/`, { ...f, lignes: (apercu || []).map((l) => ({ maison: l.maison, index_precedent: l.precedent, index_actuel: l.actuel })) });
        return "Facture répartie : les charges sont ajoutées aux baux.";
      }}>
      <div className="formulaire">
        <SaisieMontant libelle="Montant de la facture" requis valeur={f.montant} onChange={(montant) => setF({ ...f, montant })} />
        <Saisie libelle="Date de la facture" type="date" requis valeur={f.date_facture} onChange={(date_facture) => setF({ ...f, date_facture })} />
        <Saisie libelle="N° de facture" valeur={f.numero} onChange={(numero) => setF({ ...f, numero })} />
        <Saisie libelle="Période" valeur={f.periode} onChange={(periode) => setF({ ...f, periode })} placeholder="Ex. : août-septembre 2026" />
      </div>
      {!apercu ? <Chargement /> : (
        <div className="tableau-conteneur" style={{ marginTop: 12 }}>
          <table className="tableau tableau-repartition">
            <thead><tr><th>Logement</th>{parIndex && <><th>Index précédent</th><th>Index actuel</th><th className="droite">Conso.</th></>}<th className="droite">Part</th></tr></thead>
            <tbody>
              {apercu.map((l) => (
                <tr key={l.maison}>
                  <td data-libelle="Logement"><div className="principal-cellule">{l.designation}</div><div className="sous-cellule">{l.locataire || <span className="texte-attention">Vide — part au propriétaire ({l.bailleur})</span>}</div></td>
                  {parIndex && (
                    <>
                      <td data-libelle="Index précédent"><input className="saisie" inputMode="decimal" required value={index[l.maison]?.precedent ?? (l.index_precedent ?? "")} onChange={(e) => setIndex({ ...index, [l.maison]: { ...index[l.maison], precedent: e.target.value } })} /></td>
                      <td data-libelle="Index actuel"><input className="saisie" inputMode="decimal" required value={index[l.maison]?.actuel ?? ""} onChange={(e) => setIndex({ ...index, [l.maison]: { ...index[l.maison], actuel: e.target.value } })} /></td>
                      <td data-libelle="Consommation" className="droite">{l.conso == null ? "—" : l.conso < 0 ? <span className="texte-alerte">négative</span> : l.conso}</td>
                    </>
                  )}
                  <td data-libelle="Part" className="droite"><b>{fcfa(l.part)}</b></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <p className="petit-texte discret" style={{ margin: 0 }}>La part de chaque logement occupé devient une charge sur son bail (visible dans l'espace locataire) ; celle d'un logement vide est débitée du compte du propriétaire. L'écart d'arrondi va à la plus grosse part.</p>
    </FenetreFormulaire>
  );
}

function Historique({ compteur, onFermer }) {
  const { data } = useQuery({ queryKey: ["compteur-factures", compteur?.id], queryFn: () => api.get(`/compteurs/${compteur.id}/factures/`), enabled: !!compteur });
  return (
    <Fenetre titre={`Factures — ${compteur?.nom || ""}`} ouvert={!!compteur} onFermer={onFermer} taille="large">
      {!data ? <Chargement /> : data.length === 0 ? <Vide titre="Aucune facture répartie" /> : (
        <div className="pile">
          {data.map((fa) => (
            <div key={fa.id} className="facture-partagee">
              <div className="ligne-flex" style={{ justifyContent: "space-between" }}>
                <b>{dateFr(fa.date_facture)}{fa.periode && ` — ${fa.periode}`}{fa.numero && ` — n° ${fa.numero}`}</b><b>{fcfa(fa.montant)}</b>
              </div>
              <table className="tableau compact">
                <tbody>
                  {fa.lignes.map((l, i) => (
                    <tr key={i}><td>{l.maison}</td><td>{l.locataire || (l.proprietaire ? "Propriétaire (logement vide)" : "—")}</td><td className="droite">{l.consommation != null ? `${Number(l.consommation)} (${Number(l.index_precedent)} → ${Number(l.index_actuel)})` : "part égale"}</td><td className="droite">{fcfa(l.montant)}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
          ))}
        </div>
      )}
    </Fenetre>
  );
}

/** Compteurs d'eau et d'électricité partagés entre plusieurs logements d'une cour. */
export default function CompteursPartages() {
  const { data: compteurs, isLoading, refetch } = useQuery({ queryKey: ["compteurs-partages"], queryFn: () => api.get("/compteurs/") });
  const [modal, setModal] = useState(null);
  const fermer = () => (setModal(null), refetch());
  if (isLoading) return <Chargement />;
  return (
    <>
      <EntetePage titre="Compteurs partagés" soustitre="Une facture SODECI ou CIE pour plusieurs logements : répartissez-la selon les sous-compteurs ou à parts égales.">
        <Bouton variante="primaire" icone={Plus} onClick={() => setModal({ type: "compteur" })}>Nouveau compteur</Bouton>
      </EntetePage>
      {compteurs.length === 0 ? (
        <Panneau><Vide icone={Droplets} titre="Aucun compteur partagé" texte="Déclarez le compteur commun d'une cour et les logements qu'il dessert." /></Panneau>
      ) : (
        <div className="grille-compteurs">
          {compteurs.map((c) => (
            <Panneau key={c.id} titre={c.nom} icone={c.type_charge === "Eau" ? Droplets : Zap}
              actions={<Bouton taille="petit" variante="fantome" icone={Pencil} onClick={() => setModal({ type: "compteur", compteur: c })} aria-label="Modifier" />}>
              <div className="pile" style={{ gap: 10 }}>
                <div className="ligne-flex"><Badge ton="info">{c.type_charge}</Badge><span className="petit-texte discret">{c.mode_libelle}{c.numero && ` — n° ${c.numero}`}</span></div>
                <div className="petit-texte">{c.maisons_detail.map((m) => m.designation).join(", ")}</div>
                <div className="petit-texte discret">{c.derniere_facture ? `Dernière facture : ${fcfa(c.derniere_facture.montant)} le ${dateFr(c.derniere_facture.date)}` : "Aucune facture répartie"}</div>
                <div className="ligne-flex">
                  <Bouton taille="petit" variante="primaire" icone={Receipt} onClick={() => setModal({ type: "facture", compteur: c })}>Saisir une facture</Bouton>
                  <Bouton taille="petit" icone={History} onClick={() => setModal({ type: "historique", compteur: c })}>Factures</Bouton>
                </div>
              </div>
            </Panneau>
          ))}
        </div>
      )}
      <Encart ton="info">Les index précédents sont repris de la dernière facture, ou à défaut de l'état des lieux d'entrée du locataire.</Encart>
      <FormCompteur ouvert={modal?.type === "compteur"} compteur={modal?.compteur} onFermer={fermer} />
      <FormFacture ouvert={modal?.type === "facture"} compteur={modal?.compteur} onFermer={fermer} />
      <Historique compteur={modal?.type === "historique" ? modal.compteur : null} onFermer={() => setModal(null)} />
    </>
  );
}
