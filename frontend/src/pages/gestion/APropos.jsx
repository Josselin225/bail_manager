import { Mail, Phone } from "lucide-react";
import { useSession } from "../../lib/session";
import { EntetePage, Panneau } from "../../components/ui";

export default function APropos() {
  const { version, entreprise } = useSession();
  return (
    <>
      <EntetePage titre="À propos" soustitre={`BailManager ${version} — gestion locative, financière et comptable immobilière`} />
      <div className="grille g2">
        <Panneau titre="Le logiciel">
          <p>BailManager accompagne les agences, gestionnaires et bailleurs de Côte d'Ivoire : patrimoine sous mandat, baux conformes à la loi n° 2019-576, encaissements et quittances sécurisées, comptes bailleurs, caisse, cautions, rapports et espace locataire en ligne.</p>
          <dl className="definitions">
            <dt>Version</dt><dd>{version}</dd>
            <dt>Licence</dt><dd>{entreprise?.nom}</dd>
            <dt>Technologies</dt><dd>Django (Python), React, base MySQL/MariaDB ou SQLite</dd>
            <dt>Installation</dt><dd>Serveur local (réseau d'agence) ou Cloud</dd>
          </dl>
        </Panneau>
        <Panneau titre="Développeur et support">
          <p>Une difficulté, une suggestion ? Contactez directement le développeur.</p>
          <p className="ligne-flex"><Phone size={16} /> <a href="tel:+2250749791287">(+225) 07 49 79 12 87</a> / <a href="tel:+2250100014330">01 00 01 43 30</a></p>
          <p className="ligne-flex"><Mail size={16} /> <a href="mailto:kkjoss01@gmail.com">kkjoss01@gmail.com</a></p>
          <p className="ligne-flex"><a href="https://www.bailmanager.com" target="_blank" rel="noreferrer">www.bailmanager.com</a></p>
        </Panneau>
      </div>
    </>
  );
}
