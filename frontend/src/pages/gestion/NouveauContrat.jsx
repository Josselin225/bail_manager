import { useEffect, useMemo, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { FileSignature, UserPlus } from "lucide-react";
import { api } from "../../lib/api";
import { MODES, MOIS, dureeMandat, fcfa, isoDate, joursAvantFinMandat } from "../../lib/format";
import { Bouton, Combo, Encart, EntetePage, Liste, Panneau, Saisie, SaisieMontant, useToast } from "../../components/ui";
import { FormLocataire } from "../../components/formulaires";

export default function NouveauContrat() {
  const [params] = useSearchParams();
  const nav = useNavigate();
  const toast = useToast();
  const [nouveauLoc, setNouveauLoc] = useState(false);
  const { data: maisons } = useQuery({ queryKey: ["maisons-libres"], queryFn: () => api.get("/maisons/", { statut: "disponible", taille: 500 }) });
  const { data: locataires = [], refetch: rechargerLoc } = useQuery({ queryKey: ["locataires-libres"], queryFn: () => api.get("/locataires/options/", { sans_contrat: 1 }) });
  const { data: bailleurs = [] } = useQuery({ queryKey: ["bailleurs-options"], queryFn: () => api.get("/bailleurs/options/") });
  const [f, setF] = useState({ maison: params.get("maison") || "", locataire: params.get("locataire") || "", loyer_mensuel: "", date_debut: isoDate(), date_fin: "", mois_avance: 2, droit_agence: "", mode_paiement: "especes" });
  const [envoi, setEnvoi] = useState(false);
  const [erreur, setErreur] = useState("");
  const maj = (k) => (v) => setF((x) => ({ ...x, [k]: v }));
  const liste = maisons?.results || [];
  const maison = liste.find((m) => String(m.id) === String(f.maison));
  const mandatOk = maison ? bailleurs.find((b) => b.id === maison.bailleur)?.a_mandat : true;
  // Mandat à durée déterminée : le locataire doit savoir jusqu'à quand l'agence gère le bien
  const joursMandat = joursAvantFinMandat(maison?.mandat_bailleur);
  const finMandat = maison?.mandat_bailleur?.echeance;
  const bailApresMandat = joursMandat !== null && (!f.date_fin || (finMandat && f.date_fin > finMandat));
  const alerteMandat = joursMandat !== null && (joursMandat <= 90 || bailApresMandat);

  useEffect(() => {
    if (maison) setF((x) => ({ ...x, loyer_mensuel: maison.loyer, droit_agence: x.droit_agence === "" ? maison.loyer : x.droit_agence }));
  }, [maison?.id]); // eslint-disable-line

  const loyer = Number(f.loyer_mensuel) || 0;
  const caution = loyer * 2;
  const avance = loyer * Number(f.mois_avance || 0);
  const frais = Number(f.droit_agence) || 0;
  const moisCouverts = useMemo(() => {
    if (!f.date_debut || !f.mois_avance) return "";
    const d = new Date(f.date_debut);
    return Array.from({ length: Number(f.mois_avance) }, (_, k) => {
      const x = new Date(d.getFullYear(), d.getMonth() + k, 1);
      return `${MOIS[x.getMonth()]} ${x.getFullYear()}`;
    }).join(" et ");
  }, [f.date_debut, f.mois_avance]);

  const soumettre = async (e) => {
    e.preventDefault();
    setEnvoi(true);
    setErreur("");
    try {
      const { loyer_mensuel, ...reste } = f; // eslint-disable-line no-unused-vars
      const c = await api.post("/contrats/", { ...reste, date_fin: f.date_fin || null, droit_agence: frais });
      toast.succes(`Contrat ${c.reference} créé.`);
      window.open(`/impression/recu-contrat/${c.id}`, "_blank");
      nav(`/contrats/${c.id}`);
    } catch (err) {
      setErreur(err.message);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
      setEnvoi(false);
    }
  };

  return (
    <form onSubmit={soumettre}>
      <EntetePage titre="Nouveau contrat de bail" fil={[{ libelle: "Contrats", lien: "/contrats" }]} />
      <div className="grille g-2-1">
        <div className="pile">
          {erreur && <Encart ton="alerte">{erreur}</Encart>}
          <Panneau titre="Bien et locataire">
            <div className="formulaire">
              <Combo libelle="Maison" requis plein aide="Seules les maisons libres sont proposées." valeur={f.maison} onChange={maj("maison")} options={liste.map((m) => ({ valeur: m.id, libelle: m.designation, sous: `${m.type_maison || "—"} — ${m.bailleur_nom} — ${fcfa(m.loyer)}` }))} placeholder={liste.length ? "Rechercher une maison libre…" : "Aucune maison libre"} />
              {maison && !mandatOk && <div className="plein"><Encart ton="alerte">Le bailleur {maison.bailleur_nom} n'a pas de mandat en vigueur : le bail ne pourra pas être enregistré.</Encart></div>}
              {maison && mandatOk && maison.mandat_bailleur && (
                <div className="plein">
                  <Encart ton={alerteMandat ? "attention" : ""} titre={`Mandat ${maison.mandat_bailleur.reference} — ${maison.bailleur_nom}`}>
                    {dureeMandat(maison.mandat_bailleur)}.
                    {joursMandat !== null && joursMandat <= 90 && <> Il se termine dans <b>{joursMandat} jour{joursMandat > 1 ? "s" : ""}</b> : pensez à le faire renouveler.</>}
                    {bailApresMandat && <> Le bail court au-delà de la fin du mandat.</>}
                    {" "}Le locataire en est informé dans le bail.
                  </Encart>
                </div>
              )}
              <Combo libelle="Locataire" requis valeur={f.locataire} onChange={maj("locataire")} options={locataires.map((l) => ({ valeur: l.id, libelle: l.nom, sous: l.telephone1 }))} placeholder="Rechercher un locataire sans bail…" />
              <div className="champ champ-action">
                <Bouton icone={UserPlus} onClick={() => setNouveauLoc(true)}>Nouveau locataire</Bouton>
              </div>
            </div>
          </Panneau>
          <Panneau titre="Conditions du bail">
            <div className="formulaire">
              <SaisieMontant
                libelle="Loyer mensuel"
                valeur={f.loyer_mensuel}
                readOnly
                tabIndex={-1}
                placeholder="Choisissez d'abord la maison"
                aide="Fixé sur la fiche du bien. Modifiable ensuite par une révision du loyer, à la demande du bailleur."
              />
              <Saisie libelle="Date de début" aide="Date d'entrée du locataire." type="date" valeur={f.date_debut} onChange={maj("date_debut")} requis />
              <Liste libelle="Avance de loyer" valeur={f.mois_avance} onChange={(v) => maj("mois_avance")(Number(v))} options={[{ valeur: 0, libelle: "Aucune" }, { valeur: 1, libelle: "1 mois" }, { valeur: 2, libelle: "2 mois (maximum légal)" }]} aide={moisCouverts ? `Couvre ${moisCouverts}.` : "Art. 415 : 2 mois maximum."} />
              <SaisieMontant libelle="Frais d'agence" valeur={f.droit_agence} onChange={maj("droit_agence")} aide="Non remboursables, versés en caisse agence." />
              <Saisie libelle="Date de fin" type="date" valeur={f.date_fin} onChange={maj("date_fin")} aide="Vide = tacite reconduction." />
              <Liste libelle="Mode de paiement" aide="Pour les sommes versées à la signature." valeur={f.mode_paiement} onChange={maj("mode_paiement")} options={MODES} />
            </div>
          </Panneau>
        </div>
        <div>
          <Panneau titre="À verser à la signature" icone={FileSignature} className="collant">
            <dl className="definitions" style={{ gridTemplateColumns: "1fr auto" }}>
              <dt>Caution (2 mois, Art. 416)</dt><dd className="droite">{fcfa(caution)}</dd>
              <dt>Avance ({f.mois_avance} mois)</dt><dd className="droite">{fcfa(avance)}</dd>
              <dt>Frais d'agence</dt><dd className="droite">{fcfa(frais)}</dd>
            </dl>
            <hr />
            <div className="ligne-flex"><b>Total</b><span className="espace" /><span style={{ fontSize: 22, fontWeight: 800 }}>{fcfa(caution + avance + frais)}</span></div>
            <p className="discret petit-texte" style={{ marginTop: 12 }}>
              La caution reste détenue par l'agence jusqu'à la sortie. L'avance génère les quittances des premiers mois et revient au bailleur, commission déduite.
            </p>
            <Bouton type="submit" variante="primaire" icone={FileSignature} className="bloc grand" chargement={envoi} disabled={!f.maison || !f.locataire || !mandatOk}>
              Créer le contrat
            </Bouton>
          </Panneau>
        </div>
      </div>
      <FormLocataire ouvert={nouveauLoc} onFermer={() => setNouveauLoc(false)} onEnregistre={(l) => (rechargerLoc(), maj("locataire")(l.id))} />
    </form>
  );
}
