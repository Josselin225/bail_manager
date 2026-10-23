import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { CheckCircle2, FlaskConical, XCircle } from "lucide-react";
import { api } from "../../lib/api";
import { fcfa } from "../../lib/format";
import { Bouton, Chargement, Encart, Panneau } from "../../components/ui";

/** Page de paiement simulée (fournisseur « test ») : remplace la page de l'agrégateur, aucun argent ne circule. */
export default function PaiementTest() {
  const { reference } = useParams();
  const nav = useNavigate();
  const [envoi, setEnvoi] = useState(null);
  const [erreur, setErreur] = useState("");
  const { data: p, error } = useQuery({ queryKey: ["paiement", reference], queryFn: () => api.get(`/portail/paiements/${reference}/`), retry: false });
  useEffect(() => { document.title = "Paiement (test)"; }, []);
  if (error) return <div className="paiement-test"><Encart ton="alerte">{error.message}</Encart></div>;
  if (!p) return <Chargement plein />;
  const simuler = async (resultat) => {
    setEnvoi(resultat);
    try {
      await api.post(`/portail/paiements/${reference}/simuler/`, { resultat });
      nav(`/espace-locataire?paiement=${reference}`);
    } catch (e) {
      setErreur(e.message);
      setEnvoi(null);
    }
  };
  return (
    <div className="paiement-test">
      <Panneau titre="Paiement Mobile Money" icone={FlaskConical}>
        <div className="pile">
          <Encart ton="info" titre="Simulation (mode test)">Cette page remplace celle de l'agrégateur tant que l'agence est en mode test. Aucun montant n'est débité et aucun loyer n'est encaissé.</Encart>
          {erreur && <Encart ton="alerte">{erreur}</Encart>}
          <div className="recap">
            <div><span className="discret">Référence</span><b>{p.reference}</b></div>
            <div><span className="discret">Opérateur</span><b>{p.operateur}</b></div>
            <div><span className="discret">Numéro</span><b>{p.telephone}</b></div>
            <div><span className="discret">Montant</span><b>{fcfa(p.montant)}</b></div>
          </div>
          {p.statut === "en_attente" ? (
            <div className="ligne-flex">
              <Bouton variante="succes" icone={CheckCircle2} chargement={envoi === "reussi"} onClick={() => simuler("reussi")}>Simuler un paiement réussi</Bouton>
              <Bouton icone={XCircle} chargement={envoi === "echoue"} onClick={() => simuler("echoue")}>Simuler un échec</Bouton>
            </div>
          ) : (
            <Bouton variante="primaire" onClick={() => nav(`/espace-locataire?paiement=${reference}`)}>Retour à mon espace</Bouton>
          )}
        </div>
      </Panneau>
    </div>
  );
}
