import { useState } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, CalendarClock, ChevronLeft, ChevronRight, FilePlus2, HandCoins, Mail, MessagesSquare, CalendarCheck } from "lucide-react";
import { api } from "../../lib/api";
import { MOIS, MOIS_COURTS, compact, fcfa, nombre } from "../../lib/format";
import { useSession } from "../../lib/session";
import { Chargement, EntetePage, LienBouton, Montant, Panneau, Vide } from "../../components/ui";
import {
  GrapheClassement, GrapheCommissions, GrapheEncaissementsAnnee, GrapheImpayes, GrapheOccupation, GrapheRecouvrement,
} from "../../components/Graphes";

const COULEURS = { paye: "var(--ok)", partiel: "var(--ambre)", partiel_retard: "var(--signal)", en_retard: "var(--signal)", a_venir: "var(--ligne-forte)" };

function Echeancier({ initial }) {
  const auj = new Date();
  const [periode, setPeriode] = useState({ mois: auj.getMonth() + 1, annee: auj.getFullYear() });
  const courant = periode.mois === auj.getMonth() + 1 && periode.annee === auj.getFullYear();
  const { data, isFetching } = useQuery({
    queryKey: ["mois-paiements", periode],
    queryFn: () => api.get("/rapports/mois-paiements/", periode),
    initialData: courant ? initial : undefined,
    enabled: !courant,
  });
  const lignes = (courant ? initial : data)?.lignes || [];
  const total = lignes.reduce((s, l) => s + Number(l.loyer), 0);
  const parts = ["paye", "partiel", "partiel_retard", "en_retard", "a_venir"].map((st) => ({
    st,
    v: lignes.filter((l) => l.statut === st).reduce((s, l) => s + (st === "paye" ? Number(l.loyer) : Number(l.paye)), 0),
    reste: lignes.filter((l) => l.statut === st).reduce((s, l) => s + Number(l.reste), 0),
  }));
  const collecte = lignes.reduce((s, l) => s + Math.min(Number(l.paye), Number(l.loyer)), 0);
  const enRetard = lignes.filter((l) => l.statut === "en_retard" || l.statut === "partiel_retard");
  const decaler = (n) =>
    setPeriode(({ mois, annee }) => {
      const i = annee * 12 + mois - 1 + n;
      return { mois: (i % 12) + 1, annee: Math.floor(i / 12) };
    });

  return (
    <Panneau
      titre={`Loyers de ${MOIS[periode.mois - 1].toLowerCase()} ${periode.annee}`}
      icone={CalendarClock}
      actions={
        <div className="ligne-flex" style={{ gap: 4 }}>
          <button className="btn petit icone" onClick={() => decaler(-1)} aria-label="Mois précédent"><ChevronLeft size={16} /></button>
          <button className="btn petit icone" onClick={() => decaler(1)} aria-label="Mois suivant"><ChevronRight size={16} /></button>
        </div>
      }
    >
      {lignes.length === 0 ? (
        <Vide titre="Aucun loyer attendu ce mois-ci" texte="Les contrats actifs apparaîtront ici avec leur état de paiement." action={<LienBouton to="/contrats/nouveau" icone={FilePlus2}>Créer un contrat</LienBouton>} />
      ) : (
        <div className="pile" style={{ opacity: isFetching ? 0.6 : 1 }}>
          <div className="ligne-flex" style={{ alignItems: "baseline" }}>
            <span style={{ fontSize: 26, fontWeight: 800, letterSpacing: "-0.02em" }}>{nombre(collecte)}</span>
            <span className="discret">sur {fcfa(total)} attendus</span>
            <span className="espace" />
            <span className="gras">{total ? Math.round((collecte / total) * 100) : 0} % collectés</span>
          </div>
          <div className="echeancier-barre" aria-hidden>
            {parts.map((p) => {
              const largeur = p.st === "a_venir" ? p.reste : p.st === "paye" ? p.v : p.v;
              return largeur > 0 ? <span key={p.st} style={{ width: `${(largeur / total) * 100}%`, background: COULEURS[p.st] }} /> : null;
            })}
          </div>
          <div className="legende">
            <span><i style={{ background: "var(--ok)" }} />Payé</span>
            <span><i style={{ background: "var(--ambre)" }} />Partiel</span>
            <span><i style={{ background: "var(--signal)" }} />En retard ({enRetard.length})</span>
            <span><i style={{ background: "var(--ligne-forte)" }} />À venir</span>
          </div>
          <div className="echeancier">
            {lignes.map((l) => {
              const part = Number(l.loyer) ? Math.min(100, (Number(l.paye) / Number(l.loyer)) * 100) : 0;
              const jour = new Date(l.echeance).getDate();
              return (
                <Link key={l.contrat} to={`/contrats/${l.contrat}`} className={`echeance ${l.statut}`} style={{ "--part": `${part}%` }} title={`${l.locataire} — ${l.maison}`}>
                  <div className="jour"><span>le {jour}</span><span>{compact(l.loyer)}</span></div>
                  <div className="nom">{l.locataire}</div>
                  <div className="reste">{l.statut === "paye" ? "Payé" : `Reste ${nombre(l.reste)}`}</div>
                </Link>
              );
            })}
          </div>
        </div>
      )}
    </Panneau>
  );
}

export default function TableauDeBord() {
  const { utilisateur } = useSession();
  const { data, isLoading } = useQuery({ queryKey: ["tableau-de-bord"], queryFn: () => api.get("/tableau-de-bord/"), refetchInterval: 120000 });
  if (isLoading || !data) return <Chargement />;
  const k = data.kpi;
  const a = data.alertes;
  const variation = Number(k.encaisse_mois_precedent) ? Math.round(((k.encaisse_mois - k.encaisse_mois_precedent) / k.encaisse_mois_precedent) * 100) : null;
  const heure = new Date().getHours();
  const g = data.graphes;

  return (
    <div className="pile">
      <EntetePage photo="/vitrine/maison-2.jpg" titre={`${heure < 18 ? "Bonjour" : "Bonsoir"}, ${utilisateur?.nom_complet?.split(" ")[0] || ""}`} soustitre={new Date().toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric" })}>
        <LienBouton to="/encaissements" variante="primaire" icone={HandCoins}>Encaisser un loyer</LienBouton>
        <LienBouton to="/contrats/nouveau" icone={FilePlus2}>Nouveau contrat</LienBouton>
      </EntetePage>

      <div className="registre">
        <div>
          <div className="libelle">Encaissé ce mois</div>
          <div className="valeur">{nombre(k.encaisse_mois)}<small>FCFA</small></div>
          <div className="note">{variation === null ? "—" : `${variation >= 0 ? "+" : ""}${variation} % vs mois précédent`}</div>
        </div>
        <div className={k.contrats_en_retard ? "alerte" : "bon"}>
          <div className="libelle">Impayés</div>
          <div className="valeur">{nombre(k.impayes_total)}<small>FCFA</small></div>
          <div className="note">{k.contrats_en_retard} contrat(s) en retard</div>
        </div>
        <div>
          <div className="libelle">Taux d'occupation</div>
          <div className="valeur">{String(k.taux_occupation).replace(".", ",")} %</div>
          <div className="note">{k.maisons_occupees} occupée(s) sur {k.total_maisons}</div>
        </div>
        <div>
          <div className="libelle">Commissions {new Date().getFullYear()}</div>
          <div className="valeur">{nombre(k.commissions_annee)}<small>FCFA</small></div>
          <div className="note">Caisse : {fcfa(k.solde_caisse)}</div>
        </div>
        <div>
          <div className="libelle">Dû aux bailleurs</div>
          <div className="valeur">{nombre(k.solde_bailleurs)}<small>FCFA</small></div>
          <div className="note">{k.bailleurs} bailleur(s)</div>
        </div>
      </div>

      {(a.reservations > 0 || a.messages > 0 || a.messages_locataires > 0 || a.fins_contrat.length > 0 || a.mandats_expirant.length > 0 || a.sorties_prevues?.length > 0) && (
        <div className="ligne-flex">
          {a.reservations > 0 && <LienBouton to="/reservations" taille="petit" icone={CalendarCheck}>{a.reservations} visite(s) à confirmer</LienBouton>}
          {a.messages > 0 && <LienBouton to="/messages" taille="petit" icone={Mail}>{a.messages} message(s) non lu(s)</LienBouton>}
          {a.messages_locataires > 0 && <LienBouton to="/messagerie" taille="petit" icone={MessagesSquare}>{a.messages_locataires} message(s) de locataires</LienBouton>}
          {a.fins_contrat.map((f) => (
            <LienBouton key={f.contrat} to={`/contrats/${f.contrat}`} taille="petit" icone={AlertTriangle}>Bail de {f.locataire} : fin dans {f.jours} j</LienBouton>
          ))}
          {a.sorties_prevues?.map((s) => (
            <LienBouton key={`s${s.contrat}`} to={`/contrats/${s.contrat}`} taille="petit" icone={AlertTriangle}>Sortie de {s.locataire} {s.jours >= 0 ? `dans ${s.jours} j` : `prévue le ${s.date.split("-").reverse().join("/")}`}</LienBouton>
          ))}
          {a.mandats_expirant.map((m) => (
            <LienBouton key={m.bailleur_id} to={`/bailleurs/${m.bailleur_id}`} taille="petit" icone={AlertTriangle}>Mandat {m.bailleur} : fin dans {m.jours} j</LienBouton>
          ))}
        </div>
      )}

      <Echeancier initial={data.mois_courant} />

      <div className="grille g-2-1">
        <GrapheRecouvrement donnees={g.recouvrement} />
        <GrapheImpayes donnees={g.impayes_anciennete} />
      </div>

      <div className="grille g-2-1">
        <GrapheEncaissementsAnnee graphique={data.graphique} />
        <GrapheOccupation donnees={g.occupation} />
      </div>

      <div className="grille g-2-1">
        <GrapheCommissions donnees={g.commissions} annee={data.graphique.annee} />
        <GrapheClassement
          titre="Modes de paiement"
          sousTitre={`Encaissements ${data.graphique.annee}`}
          colonneLibelle="Mode"
          donnees={data.modes.map((m) => ({ libelle: m.libelle, total: m.total }))}
        />
      </div>

      <div className="grille g-2-1">
        <GrapheClassement
          titre="Maisons qui rapportent le plus"
          sousTitre={`Top 5 — encaissements ${data.graphique.annee}`}
          colonneLibelle="Maison"
          lien="/maisons"
          libelleLien="Maisons"
          donnees={data.top_maisons.map((t) => ({ libelle: t.maison, total: t.total }))}
        />
        <Panneau titre="Prochaines échéances" icone={CalendarClock} actions={<Link to="/calendrier" className="petit-texte">Calendrier</Link>}>
          {data.prochaines_echeances.length === 0 ? <Vide titre="Rien à venir" /> : (
            <div className="echeances-liste">
              {data.prochaines_echeances.map((l) => {
                const d = new Date(`${l.date}T00:00`);
                return (
                  <Link key={l.contrat} to={`/contrats/${l.contrat}`} className="echeance-ligne">
                    <span className="date-pastille"><b>{d.getDate()}</b><small>{MOIS_COURTS[d.getMonth()]}</small></span>
                    <span className="qui"><span className="gras">{l.locataire}</span><span className="discret petit-texte">{l.maison}</span></span>
                    <Montant v={l.loyer} />
                  </Link>
                );
              })}
            </div>
          )}
        </Panneau>
      </div>

      <div className="registre">
        <div>
          <div className="libelle">Cautions détenues</div>
          <div className="valeur">{nombre(k.cautions_detenues)}<small>FCFA</small></div>
          <div className="note">Restituables à la sortie</div>
        </div>
        <div>
          <div className="libelle">Revenu potentiel</div>
          <div className="valeur">{nombre(k.revenu_potentiel)}<small>FCFA</small></div>
          <div className="note">Loyers des biens libres</div>
        </div>
        <div>
          <div className="libelle">Charges {data.graphique.annee}</div>
          <div className="valeur">{nombre(k.charges_annee)}<small>FCFA</small></div>
          <div className="note">Refacturées aux locataires</div>
        </div>
        <div>
          <div className="libelle">Révisions {data.graphique.annee}</div>
          <div className="valeur">{k.revisions_annee}</div>
          <div className="note">Loyers révisés</div>
        </div>
      </div>
    </div>
  );
}
