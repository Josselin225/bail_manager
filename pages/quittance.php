<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) die("ID Paiement manquant");

$id = (int)$_GET['id'];

// --- 1. SETTINGS & DATA ---
$query_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
$entreprise = $query_settings->fetch() ?: [
    'nom_entreprise' => 'BailManager',
    'logo_url' => '',
    'contact_email' => 'contact@bailmanager.com',
    'contact_telephone' => '+225 00 00 00 00',
    'adresse_siege' => 'Yamoussoukro, Côte d\'Ivoire'
];

// Requête détaillée
$query = "SELECT e.*, c.loyer_mensuel, c.date_prochain_loyer, l.nom, m.designation, m.adresse, c.id as contrat_id
          FROM encaissements e
          JOIN contrats c ON e.contrat_id = c.id
          JOIN locataires l ON c.locataire_id = l.id
          JOIN maisons m ON c.maison_id = m.id
          WHERE e.id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die("Paiement introuvable");

// --- MOIS DE LOYER ENCORE IMPAYÉS (après ce paiement) ---
// date_prochain_loyer avance d'1 mois à chaque période soldée (voir add_paiement.php) :
// le nombre de mois encore en retard = le nombre de fois où on peut avancer cette date
// d'1 mois sans dépasser aujourd'hui.
$mois_impayes = 0;
if (!empty($data['date_prochain_loyer'])) {
    $echeance = new DateTime($data['date_prochain_loyer']);
    $aujourdhui = new DateTime('today');
    while ($echeance <= $aujourdhui) {
        $mois_impayes++;
        $echeance->modify('+1 month');
    }
}

// --- LOGIQUE DE CUMUL ET HISTORIQUE ---
$loyer_du = floatval($data['loyer_mensuel']);
$periode = $data['periode_concernee'];
$contrat_id = $data['contrat_id'];

// Récupérer l'historique de TOUS les versements pour cette période
$stmtHist = $pdo->prepare("SELECT date_encaissement, montant_recu, reference_recu, mode_paiement 
                           FROM encaissements 
                           WHERE contrat_id = ? AND periode_concernee = ? 
                           ORDER BY date_encaissement ASC");
$stmtHist->execute([$contrat_id, $periode]);
$historique = $stmtHist->fetchAll();

$total_deja_verse = 0;
foreach($historique as $v) { $total_deja_verse += floatval($v['montant_recu']); }

$reste_a_payer = $loyer_du - $total_deja_verse;
$is_echelonne = (count($historique) > 1);

// --- 2. FONCTIONS ET FORMATAGE ---
function int2str($a) {
    $fmt = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
    return ucfirst($fmt->format($a));
}
$montant_lettres = int2str($data['montant_recu']);
$date_paye = strtotime($data['date_encaissement']);

// --- 3. QR CODE ---
$qr_data = "Quittance No: " . $data['reference_recu'] . " | Client: " . $data['nom'] . " | Reçu ce jour: " . $data['montant_recu'] . " | Reste: " . $reste_a_payer;
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);

// Pied de page complet (siège, CC, banque...), injecté comme contenu CSS @page pour se répéter
// fiablement sur CHAQUE page imprimée (position:fixed casse sur les documents multi-pages sous Chrome).
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
if (!empty($entreprise['site_web'])) $footerLines[] = $entreprise['site_web'];

$footerCssParts = [];
foreach ($footerLines as $i => $line) {
    if ($i > 0) $footerCssParts[] = '"\A"';
    $footerCssParts[] = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $line) . '"';
}
$footerCssContent = $footerCssParts ? implode(' ', $footerCssParts) : '""';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Quittance - <?= htmlspecialchars($data['reference_recu']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 10mm 10mm 20mm 10mm;
            @bottom-center {
                content: <?= $footerCssContent ?>;
                white-space: pre-line;
                font-family: 'Segoe UI', Arial, sans-serif;
                font-size: 6.5pt;
                color: #444;
                text-align: center;
                border-top: 1.5px solid #14305c;
                padding-top: 3px;
                line-height: 1.4;
            }
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; background: #f8f9fa; color: #333; }
        .quittance-container { background: white; width: 190mm; margin: auto; padding: 30px; border: 1px solid #ddd; position: relative; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .agency-info h2 { margin: 0; color: #d32f2f; text-transform: uppercase; font-size: 18pt; }
        .logo-img { max-height: 70px; }
        .receipt-title { text-align: center; margin: 20px 0; }
        .receipt-title h1 { border: 2px solid #000; display: inline-block; padding: 5px 20px; text-transform: uppercase; font-size: 16pt; margin: 0; }
        .content { line-height: 1.6; font-size: 11pt; margin-top: 15px; }
        .amount-box { text-align: center; background: #f9f9f9; padding: 10px; margin: 10px 0; border: 1px dashed #000; font-size: 16pt; font-weight: bold; }
        .amount-letter { font-style: italic; text-decoration: underline; font-weight: bold; }
        .finance-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10pt; }
        .finance-table th, .finance-table td { border: 1px solid #333; padding: 8px; text-align: center; }
        .finance-table th { background: #eee; }
        .text-danger { color: #d32f2f; font-weight: bold; }
        .text-success { color: #2e7d32; font-weight: bold; }
        .qr-section { text-align: center; margin-top: 10px; padding: 10px; border: 1px solid #f0f0f0; background-color: #fafafa; width: fit-content; margin-left: auto; }
        .qr-section img { width: 85px; height: 85px; }
        .footer { margin-top: 30px; display: flex; justify-content: space-between; }
        .signature-box { width: 250px; text-align: center; }
        .stamp-area { border: 1px solid #eee; height: 80px; margin-top: 10px; display: flex; align-items: center; justify-content: center; color: #ccc; font-style: italic; }
        .highlight { background-color: #fffde7; font-weight: bold; }
        @media print { .no-print { display: none; } body { background: white; padding: 0; } .quittance-container { border: none; } }
    </style>
</head>
<body>

    <div class="no-print" style="text-align:center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background:#d32f2f; color:white; border:none; cursor:pointer; font-weight:bold;">IMPRIMER</button>
        <a href="encaissements.php" style="margin-left:15px; text-decoration:none; color:#666;">← Retour</a>
    </div>

    <div class="quittance-container">
        <?php include('../includes/print_header.php'); ?>

        <div class="receipt-title">
            <h1><?= ($reste_a_payer <= 0) ? "QUITTANCE DE SOLDE" : "QUITTANCE DE LOYER" ?></h1>
        </div>

        <div style="display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 10px;">
            <span>N° : <?= htmlspecialchars($data['reference_recu']) ?></span>
            <span>Date : <?= date('d/m/Y', $date_paye) ?></span>
        </div>

        <div class="content">
            Reçu de M./Mme <strong><?= htmlspecialchars($data['nom']) ?></strong>, la somme de :
            <div class="amount-box"><?= number_format($data['montant_recu'], 0, ',', ' ') ?> FCFA</div>
            Soit : <span class="amount-letter"><?= $montant_lettres ?> Francs CFA</span>.
            <br><br>
            Objet : Loyer du local <strong><?= htmlspecialchars($data['designation']) ?></strong> situé à <strong><?= htmlspecialchars($data['adresse']) ?></strong>.<br>
            Période : <strong><?= htmlspecialchars($periode) ?></strong>.

            <table class="finance-table">
                <thead>
                    <tr>
                        <th>Loyer mensuel</th>
                        <th>Total versé (Cumul)</th>
                        <th>Reste à payer</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= number_format($loyer_du, 0, ',', ' ') ?> FCFA</td>
                        <td class="fw-bold"><?= number_format($total_deja_verse, 0, ',', ' ') ?> FCFA</td>
                        <td>
                            <?php if ($reste_a_payer > 0): ?>
                                <span class="text-danger"><?= number_format($reste_a_payer, 0, ',', ' ') ?> FCFA</span>
                            <?php else: ?>
                                <span class="text-success">0 FCFA (SOLDÉ)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ($mois_impayes > 0): ?>
            <div style="margin-top: 15px; padding: 10px 14px; border: 1px solid #d32f2f; background: #fff5f5; border-radius: 4px; font-size: 10pt;">
                <span class="text-danger">⚠ Rappel :</span> il reste <strong><?= $mois_impayes ?></strong> mois de loyer impayé<?= $mois_impayes > 1 ? 's' : '' ?> sur ce contrat
                (échéance actuelle : <?= (new DateTime($data['date_prochain_loyer']))->format('d/m/Y') ?>).
            </div>
            <?php endif; ?>

            <?php if ($is_echelonne): ?>
                <div style="margin-top: 20px;">
                    <p style="font-size: 9pt; font-weight: bold; text-decoration: underline; margin-bottom: 5px;">Détails des versements échelonnés pour cette période :</p>
                    <table class="finance-table" style="font-size: 8.5pt; border-color: #ddd;">
                        <thead style="background: #f9f9f9;">
                            <tr>
                                <th>Date</th>
                                <th>Réf. Reçu</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $v): ?>
                                <tr class="<?= ($v['reference_recu'] == $data['reference_recu']) ? 'highlight' : '' ?>">
                                    <td><?= date('d/m/Y', strtotime($v['date_encaissement'])) ?></td>
                                    <td><?= htmlspecialchars($v['reference_recu']) ?></td>
                                    <td><?= number_format($v['montant_recu'], 0, ',', ' ') ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="qr-section">
            <img src="<?= $qr_url ?>" alt="QR Code">
            <p style="font-size: 6pt; color:#aaa;">Authenticité garantie</p>
        </div>

        <div class="footer">
            <div class="signature-box"><strong>Le Locataire</strong><br><small>(Bon pour acquit)</small></div>
            <div class="signature-box">
                <strong>L'Agence</strong><br><small>(Cachet et Signature)</small>
                <div class="stamp-area">Cachet Officiel</div>
            </div>
        </div>

    </div>

</body>
</html>