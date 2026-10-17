import { lazy, Suspense } from "react";
import { Navigate, Route, Routes, useLocation } from "react-router-dom";
import Coquille from "./components/Coquille";
import { BarreChargement, Chargement } from "./components/ui";
import { useSession } from "./lib/session";

const p = (f) => lazy(f);
// Site public & authentification
const Accueil = p(() => import("./pages/public/Accueil"));
const NosMaisons = p(() => import("./pages/public/NosMaisons"));
const MaisonPublique = p(() => import("./pages/public/MaisonPublique"));
const Services = p(() => import("./pages/public/Services"));
const Contact = p(() => import("./pages/public/Contact"));
const Connexion = p(() => import("./pages/public/Connexion"));
const MotDePasseOublie = p(() => import("./pages/public/MotDePasseOublie"));
const Reinitialiser = p(() => import("./pages/public/Reinitialiser"));
// Espace locataire
const EspaceLocataire = p(() => import("./pages/portail/EspaceLocataire"));
const QuittanceLocataire = p(() => import("./pages/portail/QuittanceLocataire"));
const PaiementTest = p(() => import("./pages/portail/PaiementTest"));
const EspaceBailleur = p(() => import("./pages/portail/EspaceBailleur"));
const QuittanceBailleur = p(() => import("./pages/portail/QuittanceBailleur"));
// Gestion
const TableauDeBord = p(() => import("./pages/gestion/TableauDeBord"));
const Bailleurs = p(() => import("./pages/gestion/Bailleurs"));
const FicheBailleur = p(() => import("./pages/gestion/FicheBailleur"));
const Maisons = p(() => import("./pages/gestion/Maisons"));
const FicheMaison = p(() => import("./pages/gestion/FicheMaison"));
const Locataires = p(() => import("./pages/gestion/Locataires"));
const FicheLocataire = p(() => import("./pages/gestion/FicheLocataire"));
const Contrats = p(() => import("./pages/gestion/Contrats"));
const NouveauContrat = p(() => import("./pages/gestion/NouveauContrat"));
const FicheContrat = p(() => import("./pages/gestion/FicheContrat"));
const Encaissements = p(() => import("./pages/gestion/Encaissements"));
const Calendrier = p(() => import("./pages/gestion/Calendrier"));
const Charges = p(() => import("./pages/gestion/Charges"));
const Revisions = p(() => import("./pages/gestion/Revisions"));
const Interventions = p(() => import("./pages/gestion/Interventions"));
const ComptesBailleurs = p(() => import("./pages/gestion/ComptesBailleurs"));
const Caisse = p(() => import("./pages/gestion/Caisse"));
const Cautions = p(() => import("./pages/gestion/Cautions"));
const Reservations = p(() => import("./pages/gestion/Reservations"));
const Messages = p(() => import("./pages/gestion/Messages"));
const Messagerie = p(() => import("./pages/gestion/Messagerie"));
const Rapports = p(() => import("./pages/gestion/Rapports"));
const Journal = p(() => import("./pages/gestion/Journal"));
const Utilisateurs = p(() => import("./pages/gestion/Utilisateurs"));
const Parametres = p(() => import("./pages/gestion/Parametres"));
const Profil = p(() => import("./pages/gestion/Profil"));
const Guide = p(() => import("./pages/gestion/Guide"));
const APropos = p(() => import("./pages/gestion/APropos"));
// Documents imprimables
const Quittance = p(() => import("./pages/impression/Quittance"));
const DocContrat = p(() => import("./pages/impression/DocContrat"));
const RecuContrat = p(() => import("./pages/impression/RecuContrat"));
const RecuResiliation = p(() => import("./pages/impression/RecuResiliation"));
const DocMandat = p(() => import("./pages/impression/DocMandat"));
const RecapFiscal = p(() => import("./pages/impression/RecapFiscal"));
const ReleveBailleur = p(() => import("./pages/impression/ReleveBailleur"));
const ReleveCaution = p(() => import("./pages/impression/ReleveCaution"));
const ImpressionRapport = p(() => import("./pages/impression/ImpressionRapport"));
const DocEtatDesLieux = p(() => import("./pages/impression/DocEtatDesLieux"));
const DocRevision = p(() => import("./pages/impression/DocRevision"));
const DocRelance = p(() => import("./pages/impression/DocRelance"));
const DocPlanApurement = p(() => import("./pages/impression/DocPlanApurement"));
const Equipe = p(() => import("./pages/gestion/Equipe"));
const CompteursPartages = p(() => import("./pages/gestion/CompteursPartages"));
const Rapprochement = p(() => import("./pages/gestion/Rapprochement"));
const Recouvrement = p(() => import("./pages/gestion/Recouvrement"));
const EtatDesLieux = p(() => import("./pages/gestion/EtatDesLieux"));

function Protege({ admin, children }) {
  const { pret, utilisateur, estAdmin, expire } = useSession();
  const loc = useLocation();
  if (!pret) return <Chargement plein />;
  if (!utilisateur) return <Navigate to="/connexion" replace state={{ retour: loc.pathname + loc.search, expire }} />;
  if (admin && !estAdmin) return <Navigate to="/tableau-de-bord" replace />;
  return children;
}

export default function App() {
  return (
    <Suspense fallback={<Chargement plein />}>
      <BarreChargement />
      <Routes>
        <Route path="/" element={<Accueil />} />
        <Route path="/nos-maisons" element={<NosMaisons />} />
        <Route path="/nos-maisons/:id" element={<MaisonPublique />} />
        <Route path="/services" element={<Services />} />
        <Route path="/contact" element={<Contact />} />
        <Route path="/connexion" element={<Connexion />} />
        <Route path="/mot-de-passe-oublie" element={<MotDePasseOublie />} />
        <Route path="/reinitialiser/:uid/:jeton" element={<Reinitialiser />} />
        <Route path="/espace-locataire" element={<EspaceLocataire />} />
        <Route path="/espace-locataire/quittance/:id" element={<QuittanceLocataire />} />
        <Route path="/paiement-test/:reference" element={<PaiementTest />} />
        <Route path="/espace-bailleur" element={<EspaceBailleur />} />
        <Route path="/espace-bailleur/quittance/:id" element={<QuittanceBailleur />} />
        <Route path="/espace-bailleur/releve" element={<ReleveBailleur portail />} />
        <Route path="/espace-bailleur/recap-fiscal" element={<RecapFiscal portail />} />

        <Route path="/impression" element={<Protege><Suspense fallback={<Chargement />}><ImpressionOutlet /></Suspense></Protege>}>
          <Route path="quittance/:id" element={<Quittance />} />
          <Route path="contrat/:id" element={<DocContrat />} />
          <Route path="recu-contrat/:id" element={<RecuContrat />} />
          <Route path="resiliation/:id" element={<RecuResiliation />} />
          <Route path="mandat/:id" element={<DocMandat />} />
          <Route path="releve-bailleur/:id" element={<ReleveBailleur />} />
          <Route path="recap-fiscal/:id" element={<RecapFiscal />} />
          <Route path="releve-caution/:id" element={<ReleveCaution />} />
          <Route path="rapport/:type" element={<ImpressionRapport />} />
          <Route path="etat-des-lieux/:id/:type" element={<DocEtatDesLieux />} />
          <Route path="relance/:contrat/:relance" element={<DocRelance />} />
          <Route path="revision/:id" element={<DocRevision />} />
          <Route path="plan-apurement/:contrat/:plan" element={<DocPlanApurement />} />
        </Route>

        <Route element={<Protege><Coquille /></Protege>}>
          <Route path="/tableau-de-bord" element={<TableauDeBord />} />
          <Route path="/bailleurs" element={<Bailleurs />} />
          <Route path="/bailleurs/:id" element={<FicheBailleur />} />
          <Route path="/maisons" element={<Maisons />} />
          <Route path="/maisons/:id" element={<FicheMaison />} />
          <Route path="/locataires" element={<Locataires />} />
          <Route path="/locataires/:id" element={<FicheLocataire />} />
          <Route path="/contrats" element={<Contrats />} />
          <Route path="/contrats/nouveau" element={<NouveauContrat />} />
          <Route path="/contrats/:id" element={<FicheContrat />} />
          <Route path="/contrats/:id/etat-des-lieux/:type" element={<EtatDesLieux />} />
          <Route path="/encaissements" element={<Encaissements />} />
          <Route path="/calendrier" element={<Calendrier />} />
          <Route path="/recouvrement" element={<Recouvrement />} />
          <Route path="/rapprochement" element={<Rapprochement />} />
          <Route path="/compteurs" element={<CompteursPartages />} />
          <Route path="/equipe" element={<Equipe />} />
          <Route path="/charges" element={<Charges />} />
          <Route path="/revisions" element={<Revisions />} />
          <Route path="/interventions" element={<Interventions />} />
          <Route path="/comptes-bailleurs" element={<ComptesBailleurs />} />
          <Route path="/caisse" element={<Caisse />} />
          <Route path="/cautions" element={<Cautions />} />
          <Route path="/reservations" element={<Reservations />} />
          <Route path="/messages" element={<Messages />} />
          <Route path="/messagerie" element={<Messagerie />} />
          <Route path="/rapports" element={<Rapports />} />
          <Route path="/journal" element={<Protege admin><Journal /></Protege>} />
          <Route path="/utilisateurs" element={<Protege admin><Utilisateurs /></Protege>} />
          <Route path="/parametres" element={<Protege admin><Parametres /></Protege>} />
          <Route path="/profil" element={<Profil />} />
          <Route path="/guide" element={<Guide />} />
          <Route path="/a-propos" element={<APropos />} />
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Suspense>
  );
}

import { Outlet } from "react-router-dom";
function ImpressionOutlet() {
  return <Outlet />;
}
