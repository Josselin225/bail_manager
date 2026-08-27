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

// Liste d'activités affichée dans l'en-tête (même contenu que includes/print_header.php)
$lhActivites = array_filter(array_map('trim', explode("\n", $entreprise['activites'] ?? '')));
$activitesHtml = implode('<br>', array_map('htmlspecialchars', $lhActivites));

// Ruban de couleur sous l'en-tête (image PNG : le SVG inline n'est pas rendu par Dompdf)
$wavePath = __DIR__ . '/../assets/img/print_wave.png';
$waveBase64 = file_exists($wavePath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($wavePath)) : '';

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

// 4bis. Pied de page complet (siège, CC, banque...) — répété sur chaque page (Dompdf gère
// nativement position:fixed sans les soucis de pagination de Chrome).
$footerLines = [];
if (!empty($entreprise['adresse_siege']) || !empty($entreprise['contact_telephone'])) {
    $footerLines[] = trim(
        (!empty($entreprise['adresse_siege']) ? 'Siège social : ' . $entreprise['adresse_siege'] : '') .
        (!empty($entreprise['contact_telephone']) ? ' - Tel : ' . $entreprise['contact_telephone'] : '')
    );
}
$ligneCC = array_filter([
    !empty($entreprise['cc_numero']) ? 'CC N° : ' . $entreprise['cc_numero'] : '',
    !empty($entreprise['regime_imposition']) ? 'Régime d\'Imposition : ' . $entreprise['regime_imposition'] : '',
    !empty($entreprise['rccm_numero']) ? 'N° RCCM : ' . $entreprise['rccm_numero'] : '',
    !empty($entreprise['contact_email']) ? 'E-mail : ' . $entreprise['contact_email'] : '',
]);
if ($ligneCC) $footerLines[] = implode(' - ', $ligneCC);
$ligneBanque = array_filter([
    !empty($entreprise['compte_bancaire']) ? 'Compte bancaire : ' . $entreprise['compte_bancaire'] : '',
    !empty($entreprise['iban']) ? 'IBAN ' . $entreprise['iban'] : '',
    !empty($entreprise['swift']) ? 'SWIFT: ' . $entreprise['swift'] : '',
]);
if ($ligneBanque) $footerLines[] = implode(' - ', $ligneBanque);

$footerHtml = '';
foreach ($footerLines as $i => $line) {
    $footerHtml .= '<div' . ($i === 0 ? ' class="footer-main"' : '') . '>' . htmlspecialchars($line) . '</div>';
}
if (!empty($entreprise['site_web'])) {
    $footerHtml .= '<div class="footer-site">' . htmlspecialchars($entreprise['site_web']) . '</div>';
}

// 5. HTML pour Dompdf
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 10mm 15mm 15mm 15mm; }
        body { font-family: "Helvetica", sans-serif; font-size: 10pt; color: #333; margin: 0; }
        .header-table { width: 100%; border-collapse: collapse; }
        .lh-activites { text-align: right; font-size: 7.5pt; font-weight: 700; color: #14305c; line-height: 1.5; text-transform: uppercase; }
        .lh-wave { width: 100%; height: 10px; display: block; margin-top: 6px; margin-bottom: 14px; border: none; }
        .logo { max-height: 52px; max-width: 130px; }

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

        .footer {
            position: fixed;
            bottom: -22px;
            left: 0;
            right: 0;
            width: 100%;
            text-align: center;
            font-size: 6.5pt;
            color: #444;
            border-top: 1.5px solid #14305c;
            padding-top: 3px;
            line-height: 1.35;
        }
        .footer .footer-site { color: #14305c; font-weight: 700; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="border:none; vertical-align:middle;">
                ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" class="logo">' : '') . '
            </td>
            <td style="border:none; vertical-align:middle;" class="lh-activites">
                ' . $activitesHtml . '
            </td>
        </tr>
    </table>
    ' . ($waveBase64 ? '<img src="' . $waveBase64 . '" class="lh-wave">' : '') . '

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
        ' . $footerHtml . '
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