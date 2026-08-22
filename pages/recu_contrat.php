<?php
session_start();
require_once('../config/db.php');

$id = $_GET['id'] ?? 0;

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

// 2. Requête du contrat avec les détails
$sql = "SELECT c.*, 
               l.nom AS locataire_nom, l.telephone1 AS locataire_tel,
               m.designation AS maison_nom, m.adresse AS maison_adr, m.loyer AS loyer_base,
               b.nom AS bailleur_nom
        FROM contrats c
        JOIN locataires l ON c.locataire_id = l.id
        JOIN maisons m ON c.maison_id = m.id
        JOIN bailleurs b ON m.bailleur_id = b.id
        WHERE c.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$contrat = $stmt->fetch();

if (!$contrat) { die("Contrat introuvable"); }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat de Bail - <?= htmlspecialchars($contrat['locataire_nom']) ?></title>
    <style>
        @page { 
            size: A4; 
            margin: 10mm 15mm; 
        }
        
        body { 
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif; 
            font-size: 11pt; 
            line-height: 1.4; 
            color: #222; 
            margin: 0; 
        }

        /* Filigrane */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80pt;
            color: rgba(200, 200, 200, 0.15);
            z-index: -1000;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* En-tête Dynamique */
        .header-agency {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .agency-info h2 { margin: 0; font-size: 16pt; color: #000080; text-transform: uppercase; }
        .agency-info p { margin: 0; font-size: 9pt; color: #555; }
        .agency-logo { max-height: 60px; }

        .contract-title { text-align: center; margin-bottom: 20px; }
        .contract-title h1 { font-size: 14pt; text-decoration: underline; text-transform: uppercase; margin: 0; }
        
        .section { margin-bottom: 15px; }
        .section h3 { 
            font-size: 11pt; 
            margin: 10px 0 5px 0; 
            background: #f4f4f4; 
            padding: 5px 10px; 
            border-left: 5px solid #000080;
            text-transform: uppercase;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        
        .financial-table td { border: 1px solid #333; padding: 8px; text-align: center; }

        .signature-table { margin-top: 30px; }
        .signature-cell { 
            border: 1px solid #333; 
            height: 120px; 
            padding: 10px; 
            vertical-align: top; 
            width: 50%;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body>

    <div class="watermark">ORIGINAL</div>

    <div class="no-print" style="padding: 15px; background: #333; text-align: center; color: white;">
        <strong>Mode Aperçu du Contrat</strong>
        <button onclick="window.print()" style="margin-left:20px; padding: 8px 20px; cursor:pointer; background:#28a745; color:white; border:none; border-radius:3px; font-weight:bold;">Imprimer le contrat</button>
        <a href="contrats.php" style="margin-left:15px; text-decoration:none; color:#bbb;">Fermer</a>
    </div>

    <div class="header-agency">
        <div class="agency-info">
            <h2><?= htmlspecialchars($entreprise['nom_entreprise']) ?></h2>
            <p><?= nl2br(htmlspecialchars($entreprise['adresse_siege'])) ?></p>
            <p>Tél : <?= htmlspecialchars($entreprise['contact_telephone']) ?> | Email : <?= htmlspecialchars($entreprise['contact_email']) ?></p>
        </div>
        <?php if(!empty($entreprise['logo_url']) && file_exists("../uploads/" . $entreprise['logo_url'])): ?>
            <img src="../uploads/<?= htmlspecialchars($entreprise['logo_url']) ?>" class="agency-logo" alt="Logo">
        <?php endif; ?>
    </div>

    <div class="contract-title">
        <h1>Contrat de Bail à Usage d'Habitation</h1>
        <p style="margin-top: 5px; font-weight: bold;">N° REF : BAIL-<?= date('Y') ?>-<?= str_pad($contrat['id'], 4, '0', STR_PAD_LEFT) ?></p>
    </div>

    <div class="section">
        <h3>1. LES PARTIES</h3>
        <table class="info-table">
            <tr>
                <td width="20%"><strong>LE BAILLEUR :</strong></td>
                <td>
                    <strong><?= htmlspecialchars($contrat['bailleur_nom']) ?></strong>, 
                    domicilié pour les présentes à l'agence <strong><?= htmlspecialchars($entreprise['nom_entreprise']) ?></strong>.
                </td>
            </tr>
            <tr>
                <td><strong>LE PRENEUR :</strong></td>
                <td>
                    <strong><?= htmlspecialchars($contrat['locataire_nom']) ?></strong><br>
                    Téléphone : <?= htmlspecialchars($contrat['locataire_tel']) ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>2. DÉSIGNATION DU BIEN</h3>
        <p>Le bailleur donne en location au preneur qui accepte le bien immobilier suivant :</p>
        <p style="padding-left: 20px;"><strong>Désignation :</strong> <?= htmlspecialchars($contrat['maison_nom']) ?><br>
        <strong>Localisation :</strong> <?= htmlspecialchars($contrat['maison_adr']) ?></p>
    </div>

    <div class="section">
        <h3>3. CONDITIONS FINANCIÈRES</h3>
        <table class="financial-table">
            <tr style="background:#f8f8f8; font-weight: bold;">
                <td>Loyer Mensuel (Net)</td>
                <td>Dépôt de Garantie</td>
                <td>Date de Prise d'Effet</td>
            </tr>
            <tr>
                <td><strong><?= number_format($contrat['loyer_mensuel'], 0, ',', ' ') ?> FCFA</strong></td>
                <td><strong><?= number_format($contrat['depot_garantie'], 0, ',', ' ') ?> FCFA</strong></td>
                <td><?= date('d/m/Y', strtotime($contrat['date_debut'])) ?></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>4. PRINCIPALES CLAUSES</h3>
        <p style="font-size: 10pt; text-align: justify;">
            - Le présent bail est régi par les lois en vigueur relatives aux baux à usage d'habitation.<br>
            - Le <strong>préavis de résiliation est fixé à trois (03) mois</strong> francs, notifié par lettre recommandée ou acte d'huissier.<br>
            - Le preneur s'engage à payer le loyer au plus tard le 05 de chaque mois.<br>
            - Les charges d'abonnement et de consommation d'eau et d'électricité sont à la charge exclusive du preneur.
        </p>
    </div>

    <p style="margin-top: 20px;">
        Fait à <?= explode(',', $entreprise['adresse_siege'])[0] ?>, le <strong><?= date('d/m/Y', strtotime($contrat['date_contrat'])) ?></strong>.
    </p>

    <div class="signature-table">
        <table>
            <tr>
                <td class="signature-cell">
                    <strong>LE PRENEUR (LOCATAIRE)</strong><br>
                    <em style="font-size: 9pt;">(Précéder de la mention "Lu et approuvé")</em>
                </td>
                <td class="signature-cell" style="text-align: right;">
                    <strong>POUR L'AGENCE (LE MANDATAIRE)</strong><br>
                    <em style="font-size: 9pt;">(Signature et Cachet)</em>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #ddd; padding-top: 10px;">
        Contrat généré par <?= htmlspecialchars($entreprise['nom_entreprise']) ?> - Logiciel BailManager
    </div>

</body>
</html>