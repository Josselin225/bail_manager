import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { CheckCheck, FileSearch, GitCompareArrows } from "lucide-react";
import { api } from "../../lib/api";
import { dateFr, dateHeure, fcfa } from "../../lib/format";
import { Badge, Bouton, Combo, Encart, EntetePage, Fichier, Liste, Onglets, Panneau, Tableau, Vide, useToast } from "../../components/ui";

const SOURCES = ["Wave", "Orange Money", "MTN MoMo", "Moov Money", "Banque"].map((s) => ({ valeur: s, libelle: s }));
const CHAMPS = [["date", "Date"], ["montant", "Montant reçu"], ["telephone", "Téléphone de l'expéditeur"], ["libelle", "Libellé / nom"], ["reference", "Référence"]];
const ETATS = {
  certain: ["ok", "Reconnu"], probable: ["info", "Probable"], a_affecter: ["attention", "À affecter"],
  deja: ["", "Déjà encaissé"], ignoree: ["", "Ignorée"],
};

function Import() {
  const toast = useToast();
  const [fichier, setFichier] = useState(null);
  const [source, setSource] = useState("Wave");
  const [analyse, setAnalyse] = useState(null);
  const [choix, setChoix] = useState({});
  const [etat, setEtat] = useState({});
  const [resultats, setResultats] = useState(null);
  const { data: contrats } = useQuery({ queryKey: ["contrats-actifs-options"], queryFn: () => api.get("/contrats/", { statut_contrat: "actif", taille: 500 }), enabled: !!analyse });
  const options = useMemo(() => (contrats?.results || []).map((c) => ({ valeur: c.id, libelle: c.locataire_nom, sous: `${c.maison_nom} — ${c.reference}` })), [contrats]);

  const analyser = async (colonnes) => {
    setEtat({ attente: "analyse" });
    setResultats(null);
    try {
      const fd = new FormData();
      fd.append("fichier", fichier);
      if (colonnes) fd.append("colonnes", JSON.stringify(colonnes));
      const r = await api.post("/rapprochement/analyser/", fd);
      setAnalyse(r);
      setChoix(Object.fromEntries(r.lignes.filter((l) => l.contrat).map((l) => [l.n, { contrat: l.contrat, coche: l.etat === "certain" || l.etat === "probable" }])));
    } catch (e) {
      toast.erreur(e.message);
    } finally {
      setEtat({});
    }
  };
  const actives = analyse?.lignes.filter((l) => ["certain", "probable", "a_affecter"].includes(l.etat)) || [];
  const selection = actives.filter((l) => choix[l.n]?.coche && choix[l.n]?.contrat);
  const total = selection.reduce((s, l) => s + Number(l.montant), 0);
  const encaisser = async () => {
    setEtat({ attente: "encaisser" });
    try {
      const r = await api.post("/rapprochement/encaisser/", {
        source, mode: source === "Banque" ? "virement" : "mobile_money",
        lignes: selection.map((l) => ({ ...l, contrat: choix[l.n].contrat })),
      });
      setResultats(r.resultats);
      const ok = r.resultats.filter((x) => x.ok).length;
      toast.succes(`${ok} paiement${ok > 1 ? "s" : ""} encaissé${ok > 1 ? "s" : ""}.`);
      analyser(analyse.colonnes);
    } catch (e) {
      toast.erreur(e.message);
      setEtat({});
    }
  };

  return (
    <div className="pile">
      <Panneau titre="1. Relevé à rapprocher" icone={FileSearch}>
        <p className="discret" style={{ marginTop: 0 }}>Exportez le relevé depuis l'espace marchand de l'opérateur ou depuis la banque (CSV ou Excel), puis déposez-le ici. Chaque paiement reçu est comparé aux loyers attendus : téléphone du locataire, montant dû, nom dans le libellé.</p>
        <div className="formulaire">
          <Fichier libelle="Fichier du relevé" accept=".csv,.txt,.xlsx" valeur={fichier} onChange={(f) => (setFichier(f), setAnalyse(null), setResultats(null))} />
          <Liste libelle="Provenance" valeur={source} onChange={setSource} options={SOURCES} aide={source === "Banque" ? "Encaissé en « Virement »." : "Encaissé en « Mobile Money »."} />
        </div>
        <div style={{ marginTop: 12 }}><Bouton variante="primaire" icone={FileSearch} disabled={!fichier} chargement={etat.attente === "analyse"} onClick={() => analyser()}>Analyser le relevé</Bouton></div>
      </Panneau>

      {analyse && (
        <Panneau titre="2. Colonnes du relevé">
          <p className="discret" style={{ marginTop: 0 }}>Colonnes reconnues automatiquement ; corrigez-les si besoin, l'analyse est relancée.</p>
          <div className="colonnes-releve">
            {CHAMPS.map(([cle, libelle]) => (
              <Liste key={cle} libelle={libelle} valeur={analyse.colonnes[cle] ?? ""} onChange={(v) => analyser({ ...analyse.colonnes, [cle]: v === "" ? null : Number(v) })}
                options={[{ valeur: "", libelle: "— aucune —" }, ...analyse.entetes.map((e, i) => ({ valeur: i, libelle: e }))]} />
            ))}
          </div>
        </Panneau>
      )}

      {analyse && (
        <Panneau titre={`3. Paiements reçus (${actives.length} à traiter)`} icone={GitCompareArrows} serre
          actions={<span className="petit-texte discret">{analyse.lignes.filter((l) => l.etat === "deja").length} déjà encaissé(s), {analyse.lignes.filter((l) => l.etat === "ignoree").length} ignoré(s)</span>}>
          <Tableau
            lignes={analyse.lignes}
            cle="n"
            vide={<Vide titre="Aucune ligne lue" />}
            colonnes={[
              { libelle: "", rendu: (l) => (["certain", "probable", "a_affecter"].includes(l.etat)
                ? <input type="checkbox" aria-label="Encaisser cette ligne" checked={!!choix[l.n]?.coche} disabled={!choix[l.n]?.contrat} onChange={(e) => setChoix({ ...choix, [l.n]: { ...choix[l.n], coche: e.target.checked } })} />
                : null) },
              { libelle: "Date", rendu: (l) => (l.date ? dateFr(l.date) : "—") },
              { libelle: "Paiement", rendu: (l) => <><div className="principal-cellule">{l.telephone || l.reference || "—"}</div><div className="sous-cellule">{[l.libelle, l.reference].filter(Boolean).join(" — ")}</div></> },
              { libelle: "Montant", rendu: (l) => (l.montant ? <b>{fcfa(l.montant)}</b> : "—"), droite: true },
              { libelle: "Bail", rendu: (l) => (["certain", "probable", "a_affecter"].includes(l.etat) ? (
                <div className="combo-rapprochement">
                  <Combo options={options} valeur={choix[l.n]?.contrat || ""} placeholder="Choisir le locataire…" onChange={(contrat) => setChoix({ ...choix, [l.n]: { contrat, coche: true } })} />
                </div>
              ) : <span className="discret">{l.raison}</span>) },
              { libelle: "Correspondance", rendu: (l) => <><Badge ton={ETATS[l.etat][0]}>{ETATS[l.etat][1]}</Badge>{["certain", "probable"].includes(l.etat) && <div className="sous-cellule">par {l.raison}</div>}</> },
            ]}
          />
          <div className="pied-rapprochement">
            <span><b>{selection.length}</b> ligne{selection.length > 1 ? "s" : ""} sélectionnée{selection.length > 1 ? "s" : ""} — <b>{fcfa(total)}</b></span>
            <Bouton variante="succes" icone={CheckCheck} disabled={!selection.length} chargement={etat.attente === "encaisser"} onClick={encaisser}>Encaisser la sélection</Bouton>
          </div>
        </Panneau>
      )}

      {resultats && (
        <Panneau titre="Résultat">
          <div className="liste-mobile">
            {resultats.map((r) => (
              <div key={r.n} className="ligne">
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="ligne-flex" style={{ justifyContent: "space-between" }}><b>{r.locataire || `Ligne ${r.n + 1}`}</b><Badge ton={r.ok ? "ok" : "alerte"}>{r.ok ? "Encaissé" : "Non encaissé"}</Badge></div>
                  <div className="petit-texte discret">
                    {r.ok ? r.recus.map((x, i) => <span key={x.id}>{i > 0 && ", "}<Link to={`/impression/quittance/${x.id}`}>{x.reference}</Link> ({x.periode})</span>) : r.message}
                    {r.ok && r.message && <div className="texte-attention">{r.message}</div>}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </Panneau>
      )}
    </div>
  );
}

function Historique() {
  const { data = [], isLoading } = useQuery({ queryKey: ["rapprochement-historique"], queryFn: () => api.get("/rapprochement/historique/") });
  return (
    <Panneau serre>
      <Tableau
        lignes={data}
        chargement={isLoading}
        vide={<Vide titre="Aucun relevé rapproché" />}
        colonnes={[
          { libelle: "Paiement du", rendu: (x) => dateFr(x.date) },
          { libelle: "Provenance", rendu: (x) => <><div className="principal-cellule">{x.source}</div><div className="sous-cellule">{[x.telephone, x.reference].filter(Boolean).join(" — ")}</div></> },
          { libelle: "Locataire", rendu: (x) => <Link to={`/contrats/${x.contrat}`}>{x.locataire}</Link> },
          { libelle: "Quittance", rendu: (x) => x.recu || "—" },
          { libelle: "Importé le", rendu: (x) => dateHeure(x.importe_le) },
          { libelle: "Montant", rendu: (x) => <>{fcfa(x.montant)}{Number(x.reste) > 0 && <div className="sous-cellule texte-attention">trop-perçu {fcfa(x.reste)}</div>}</>, droite: true },
        ]}
      />
    </Panneau>
  );
}

/** Rapprochement des relevés Mobile Money et bancaires avec les loyers attendus. */
export default function Rapprochement() {
  const [onglet, setOnglet] = useState("import");
  return (
    <>
      <EntetePage titre="Rapprochement" soustitre="Importez un relevé Mobile Money ou bancaire : les paiements reçus sont reconnus et encaissés en un clic, sans double saisie." />
      <Encart ton="info">Un même paiement ne peut jamais être encaissé deux fois : chaque ligne déjà traitée est reconnue au prochain import.</Encart>
      <Onglets actif={onglet} onChange={setOnglet} onglets={[{ cle: "import", libelle: "Nouveau relevé" }, { cle: "historique", libelle: "Historique" }]} />
      {onglet === "import" ? <Import /> : <Historique />}
    </>
  );
}
