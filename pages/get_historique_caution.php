<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    die("Accès refusé. Veuillez vous connecter.");
}

if (!isset($_GET['id'])) {
    die("ID du contrat manquant.");
}

$id_contrat = $_GET['id'];

// 0. Infos agence (en-tête du document)
$entreprise = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$entreprise) {
    $entreprise = ['nom_entreprise' => 'BailManager', 'contact_telephone' => '', 'contact_email' => ''];
}

// 1. Récupération des infos du contrat, de la maison et du locataire
$sqlContrat = "SELECT c.id as num_contrat, c.depot_garantie, c.date_debut, l.nom as nom_locataire, m.designation
               FROM contrats c
               JOIN locataires l ON c.locataire_id = l.id
               JOIN maisons m ON c.maison_id = m.id
               WHERE c.id = ?";
$stmtC = $pdo->prepare($sqlContrat);
$stmtC->execute([$id_contrat]);
$infosContrat = $stmtC->fetch();

$caution_initiale = (float)($infosContrat['depot_garantie'] ?? 0);

// 2. Récupération des mouvements financiers
$sqlMouv = "SELECT * FROM mouvements_caution
            WHERE locataire_id = (SELECT locataire_id FROM contrats WHERE id = ?)
            ORDER BY date_operation ASC";
$stmtM = $pdo->prepare($sqlMouv);
$stmtM->execute([$id_contrat]);
$mouvements = $stmtM->fetchAll();

// 3. Calcul du solde
$total_mouvements = 0;
foreach ($mouvements as $m) { $total_mouvements += $m['montant']; }
$solde_final = $caution_initiale + $total_mouvements;

// Définition de l'alerte de seuil (Alerte si moins de 30% du dépôt initial)
$seuil_alerte  = $caution_initiale * 0.3;
$solde_couleur = ($solde_final <= $seuil_alerte) ? '#dc2626' : '#059669';

// --- AFFICHAGE ---
echo '<div class="text-end mb-3">
        <a class="btn btn-sm btn-dark" href="releve_caution.php?id=' . (int)$id_contrat . '">
            <i class="fa fa-print me-2"></i>Imprimer le Relevé
        </a>
      </div>';

echo '<div id="printableArea">';

    // En-tête agence + titre du document
    echo '<div class="cr-header">
            <div>
                <div class="cr-agence">' . htmlspecialchars($entreprise['nom_entreprise']) . '</div>
                <div class="cr-agence-sub">' . htmlspecialchars($entreprise['contact_telephone'] ?? '') . (!empty($entreprise['contact_email']) ? ' &bull; ' . htmlspecialchars($entreprise['contact_email']) : '') . '</div>
            </div>
            <div class="text-end">
                <div class="cr-titre">Relevé de Compte Caution</div>
                <div class="cr-ref">Réf. CAUTION-' . str_pad($infosContrat['num_contrat'], 4, '0', STR_PAD_LEFT) . '</div>
            </div>
          </div>';

    // Bandeau identité locataire / bien
    echo '<div class="cr-identite">
            <div>
                <div class="cr-identite-lbl">Locataire</div>
                <div class="cr-identite-val">' . htmlspecialchars($infosContrat['nom_locataire']) . '</div>
            </div>
            <div>
                <div class="cr-identite-lbl">Bien loué</div>
                <div class="cr-identite-val">' . htmlspecialchars($infosContrat['designation']) . '</div>
            </div>
            <div>
                <div class="cr-identite-lbl">Contrat</div>
                <div class="cr-identite-val">N°' . (int)$infosContrat['num_contrat'] . '</div>
            </div>
          </div>';

    echo '<table class="cr-table">';
    echo '<thead>
            <tr>
                <th style="width:13%">Date</th>
                <th style="width:20%">Type</th>
                <th>Description / Commentaire</th>
                <th style="width:14%">Effectué par</th>
                <th style="width:16%" class="text-end">Montant</th>
            </tr>
          </thead>';
    echo '<tbody>';

    // 1ère Ligne : Le Dépôt de Garantie initial
    echo '<tr class="cr-row-initial">
            <td class="text-muted">Signature</td>
            <td><span class="cr-badge cr-badge-initial">Dépôt initial</span></td>
            <td>Versement de la caution à la signature du bail</td>
            <td class="text-muted">—</td>
            <td class="text-end fw-bold" style="color:#059669;">+' . number_format($caution_initiale, 0, ',', ' ') . ' FCFA</td>
          </tr>';

    // Lignes suivantes : Les retenues / ajustements
    foreach ($mouvements as $m) {
        $negatif = $m['montant'] < 0;
        $color   = $negatif ? '#dc2626' : '#059669';
        $prefix  = $negatif ? '' : '+';
        $badgeCl = $negatif ? 'cr-badge-retenue' : 'cr-badge-ajout';
        echo "<tr>
                <td class='text-muted'>" . date('d/m/Y', strtotime($m['date_operation'])) . "</td>
                <td><span class='cr-badge $badgeCl'>" . htmlspecialchars(ucfirst($m['type_mouvement'])) . "</span></td>
                <td class='text-muted'>" . htmlspecialchars($m['commentaire']) . "</td>
                <td class='text-muted'>" . htmlspecialchars($m['effectue_par'] ?? '—') . "</td>
                <td class='text-end fw-bold' style='color:$color;'>" . $prefix . number_format($m['montant'], 0, ',', ' ') . " FCFA</td>
              </tr>";
    }

    echo '</tbody></table>';

    // Solde final — carte mise en avant
    echo '<div class="cr-solde">
            <span class="cr-solde-lbl">Solde actuel net</span>
            <span class="cr-solde-val" style="color:' . $solde_couleur . ';">' . number_format($solde_final, 0, ',', ' ') . ' FCFA</span>
          </div>';

    // Signatures (visible uniquement à l'impression)
    echo '<div class="cr-signatures d-none d-print-flex">
            <div class="cr-signature-box">
                <strong>LE LOCATAIRE</strong>
                <div class="cr-signature-note">(Précédé de "Lu et approuvé")</div>
            </div>
            <div class="cr-signature-box text-end">
                <strong>L\'ADMINISTRATEUR</strong>
                <div class="cr-signature-note">(Cachet et signature)</div>
            </div>
          </div>';

    echo '<p class="cr-footer d-none d-print-block">
            Document édité le ' . date('d/m/Y à H:i') . ' — ' . htmlspecialchars($entreprise['nom_entreprise']) . ' — Logiciel BailManager
          </p>';

echo '</div>';
?>
