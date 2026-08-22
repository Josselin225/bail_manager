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

// 1. Récupération des infos du contrat, de la maison et du locataire
$sqlContrat = "SELECT c.id as num_contrat, c.depot_garantie, l.nom as nom_locataire, m.designation 
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
            ORDER BY date_operation DESC";
$stmtM = $pdo->prepare($sqlMouv);
$stmtM->execute([$id_contrat]);
$mouvements = $stmtM->fetchAll();

// 3. Calcul du solde
$total_mouvements = 0;
foreach ($mouvements as $m) { $total_mouvements += $m['montant']; }
$solde_final = $caution_initiale + $total_mouvements;

// Définition de l'alerte de seuil (Alerte si moins de 30% du dépôt initial)
$seuil_alerte = $caution_initiale * 0.3;
$classe_alerte = ($solde_final <= $seuil_alerte) ? 'text-danger animate-pulse' : 'text-primary';

// --- AFFICHAGE ---
echo '<div class="text-end mb-3 d-print-none">
        <button class="btn btn-sm btn-dark" onclick="window.print()">
            <i class="fa fa-print me-2"></i>Imprimer le Relevé
        </button>
      </div>';

echo '<div id="printableArea" class="p-3">';
    // En-tête du document
    echo '<div class="text-center mb-4 border-bottom pb-3">
            <h4 class="fw-bold mb-1">RELEVÉ DE COMPTE CAUTION</h4>
            <p class="mb-0 text-muted">Contrat N°' . $infosContrat['num_contrat'] . ' | ' . htmlspecialchars($infosContrat['designation']) . '</p>
            <h5 class="mt-2 text-uppercase fw-bold">' . htmlspecialchars($infosContrat['nom_locataire']) . '</h5>
          </div>';

    echo '<table class="table table-bordered align-middle">';
    echo '<thead class="table-light text-uppercase small">
            <tr>
                <th style="width: 15%">Date</th>
                <th style="width: 20%">Type</th>
                <th>Description / Commentaire</th>
                <th style="width: 20%" class="text-end">Montant</th>
            </tr>
          </thead>';
    echo '<tbody>';

    // 1ère Ligne : Le Dépôt de Garantie initial
    echo '<tr class="table-info bg-opacity-10">
            <td class="text-muted italic">Signature</td>
            <td><span class="badge bg-info text-dark w-100">DÉPÔT INITIAL</span></td>
            <td>Versement de la caution à la signature du bail</td>
            <td class="text-end fw-bold text-success">+' . number_format($caution_initiale, 0, ',', ' ') . ' FCFA</td>
          </tr>';

    // Lignes suivantes : Les retenues
    foreach ($mouvements as $m) {
        $color = $m['montant'] < 0 ? 'text-danger' : 'text-success';
        $prefix = $m['montant'] > 0 ? '+' : '';
        echo "<tr>
                <td class='small'>" . date('d/m/Y', strtotime($m['date_operation'])) . "</td>
                <td><span class='badge border text-dark w-100'>" . strtoupper($m['type_mouvement']) . "</span></td>
                <td class='small text-muted'>" . htmlspecialchars($m['commentaire']) . "</td>
                <td class='fw-bold $color text-end'>" . $prefix . number_format($m['montant'], 0, ',', ' ') . " FCFA</td>
              </tr>";
    }
    
    // Pied du tableau : Le Solde Final
    echo '<tr class="table-dark shadow-sm">
            <td colspan="3" class="text-end fw-bold py-3">SOLDE ACTUEL NET :</td>
            <td class="text-end fw-bold fs-4 py-3 ' . $classe_alerte . '">
                ' . number_format($solde_final, 0, ',', ' ') . ' FCFA
            </td>
          </tr>';

    echo '</tbody></table>';

    // Signatures (Visible uniquement à l'impression)
    echo '<div class="d-none d-print-block mt-5 pt-4">
            <div class="row text-center">
                <div class="col-6">
                    <p class="fw-bold border-bottom d-inline-block px-4">Le Locataire</p>
                    <br><br><small class="text-muted">(Précédé de "Lu et approuvé")</small>
                </div>
                <div class="col-6">
                    <p class="fw-bold border-bottom d-inline-block px-4">L\'Administrateur</p>
                    <br><br><small class="text-muted">(Cachet et Signature)</small>
                </div>
            </div>
          </div>';

    echo '<p class="d-none d-print-block mt-5 small text-muted text-center italic border-top pt-2">
            Document édité le ' . date('d/m/Y à H:i') . ' - Logiciel BailManager
          </p>';
echo '</div>';
?>