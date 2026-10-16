import { Link } from "react-router-dom";
import { ArrowLeft } from "lucide-react";
import { useSession } from "../../lib/session";
import { Marque } from "./Site";

export default function AuthCadre({
  children,
  image = "/vitrine/maison-2.jpg",
  titre = "Le registre de votre agence, toujours à jour.",
  texte = "Bailleurs sous mandat, baux, encaissements, quittances, comptes et cautions — au même endroit, accessible depuis tout poste du réseau.",
}) {
  const { entreprise } = useSession();
  return (
    <div className="auth">
      <div className="auth-visuel">
        <img src={image} alt="" className="auth-photo" />
        <Link to="/" className="auth-marque"><Marque nom={entreprise?.nom} /></Link>
        <div className="auth-accroche">
          <h1>{titre}</h1>
          <p>{texte}</p>
        </div>
        <div className="auth-pied">© {new Date().getFullYear()} {entreprise?.nom || "BailManager"}</div>
      </div>
      <div className="auth-form">
        <Link to="/" className="auth-retour"><ArrowLeft size={15} /> Retour au site</Link>
        {children}
      </div>
    </div>
  );
}
