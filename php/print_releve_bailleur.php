<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}

// 1. Récupération des paramètres de l'entreprise (Settings)
$query_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
$entreprise = $query_settings->fetch();

if (!$entreprise) {
    $entreprise = [
        'nom_entreprise' => 'BailManager',
        'logo_url' => '',
        'contact_email' => 'contact@bailmanager.com',
        'contact_telephone' => '+225 00 00 00 00',
        'adresse_siege' => 'Yamoussoukro, Côte d\'Ivoire'
    ];
}

// Préparation du logo en Base64
$logoPath = '../uploads/' . $entreprise['logo_url'];
$logoBase64 = '';
if (!empty($entreprise['logo_url']) && file_exists($logoPath)) {
    $logoData = base64_encode(file_get_contents($logoPath));
    $logoBase64 = 'data:image/' . pathinfo($logoPath, PATHINFO_EXTENSION) . ';base64,' . $logoData;
}

// 2. Données du Bailleur
$bailleur_id = $_GET['bailleur_id'] ?? null;
if (!$bailleur_id) { header('Location: ../pages/bailleurs.php?msg=missing_id'); exit(); }

$stmtB = $pdo->prepare("SELECT nom FROM bailleurs WHERE id = ?");
$stmtB->execute([$bailleur_id]);
$bailleur = $stmtB->fetch();

$stmtM = $pdo->prepare("SELECT * FROM compte_courant_bailleur WHERE bailleur_id = ? ORDER BY date_operation ASC");
$stmtM->execute([$bailleur_id]);
$mouvements = $stmtM->fetchAll();

// 3. Calcul du solde
$solde = 0;
foreach($mouvements as $m) { $solde += $m['montant']; }

// 4. QR CODE DE SÉCURITÉ
$qr_data = "RELEVE BAILLEUR: " . $bailleur['nom'] . " | SOLDE: " . number_format($solde, 0, ',', ' ') . " FCFA | DATE: " . date('d/m/Y');
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($qr_data);

// 5. HTML pour Dompdf
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "Helvetica", sans-serif; font-size: 10pt; color: #333; margin: 0; }
        .header-table { width: 100%; border-bottom: 2px solid #002d72; padding-bottom: 10px; margin-bottom: 20px; }
        .agency-name { color: #002d72; font-size: 16pt; font-weight: bold; text-transform: uppercase; }
        .logo { max-height: 60px; }
        
        .title-box { text-align: center; margin-bottom: 20px; background: #f0f4f8; padding: 10px; position: relative; }
        .title { color: #002d72; text-transform: uppercase; font-size: 14pt; margin: 0; }
        
        table.data-table { width: 100%; border-collapse: collapse; }
        th { background-color: #002d72; color: white; padding: 8px; font-size: 9pt; }
        td { padding: 8px; border-bottom: 1px solid #eee; font-size: 9pt; }
        
        .montant { text-align: right; }
        .total-row { background-color: #eee; font-weight: bold; }
        
        .qr-container { text-align: right; margin-top: 20px; }
        .qr-container img { width: 80px; }
        .qr-text { font-size: 7pt; color: #999; }

        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8pt; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="border:none;">
                <div class="agency-name">' . htmlspecialchars($entreprise['nom_entreprise']) . '</div>
                <div style="font-size:8pt;">' . nl2br(htmlspecialchars($entreprise['adresse_siege'])) . '</div>
            </td>
            <td style="border:none; text-align:right;">
                ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" class="logo">' : '') . '
            </td>
        </tr>
    </table>

    <div class="title-box">
        <h1 class="title">Relevé de Compte Bailleur</h1>
        <div style="font-size: 10pt; margin-top:5px;">
            Bailleur : <strong>' . htmlspecialchars($bailleur['nom']) . '</strong> | Situation au ' . date('d/m/Y H:i') . '
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Désignation</th>
                <th class="montant">Crédit</th>
                <th class="montant">Débit</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($mouvements as $m) {
            $html .= '<tr>
                <td>' . date('d/m/Y', strtotime($m['date_operation'])) . '</td>
                <td>' . htmlspecialchars($m['type_operation']) . '</td>
                <td class="montant">' . ($m['montant'] > 0 ? number_format($m['montant'], 0, ',', ' ') : '-') . '</td>
                <td class="montant">' . ($m['montant'] < 0 ? number_format(abs($m['montant']), 0, ',', ' ') : '-') . '</td>
            </tr>';
        }

        $html .= '
            <tr class="total-row">
                <td colspan="2" style="text-align: right;">SOLDE NET À REVERSER</td>
                <td colspan="2" class="montant" style="font-size:12pt; color:#002d72;">' . number_format($solde, 0, ',', ' ') . ' FCFA</td>
            </tr>
        </tbody>
    </table>

    <div class="qr-container">
        <img src="' . $qr_url . '"><br>
        <span class="qr-text">Document certifié conforme</span>
    </div>

    <div class="footer">
        ' . htmlspecialchars($entreprise['nom_entreprise']) . ' - Logiciel BailManager
    </div>

</body>
</html>';

// 6. Génération
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Releve_" . str_replace(' ', '_', $bailleur['nom']) . ".pdf", ["Attachment" => false]);