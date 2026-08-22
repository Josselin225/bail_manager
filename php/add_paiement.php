<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $contrat_id = intval($_POST['contrat_id']);
    $montant_verse = !empty($_POST['montant_paye']) ? floatval($_POST['montant_paye']) : 0;
    $mode = $_POST['mode_paiement'] ?? 'Espèces';
    // Normalisation vers les valeurs ENUM réelles de encaissements.mode_paiement —
    // le libellé affiché ("Mobile Money", "Chèque"...) ne correspond pas telle
    // quelle à la colonne (espaces/orthographe différents), ce qui faisait
    // échouer l'enregistrement pour ces deux modes.
    $modesValides = [
        'espèces' => 'especes', 'especes' => 'especes',
        'virement' => 'virement',
        'mobile money' => 'mobile_money', 'mobile_money' => 'mobile_money',
        'chèque' => 'cheque', 'cheque' => 'cheque',
    ];
    $mode_db = $modesValides[mb_strtolower(trim($mode))] ?? 'especes';
    $ref = "REC-" . strtoupper(substr(uniqid(), -6));
    
    $mois_nom = $_POST['periode_mois'] ?? '';
    $annee_nom = $_POST['periode_annee'] ?? '';
    $periode = $mois_nom . " " . $annee_nom;

    if ($montant_verse <= 0) {
        header("Location: ../pages/encaissements.php?error=Montant invalide");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // ---------------------------------------------------------
        // ÉTAPE 0 : VÉRIFICATION DES IMPAYÉS ANTÉRIEURS (SÉCURITÉ)
        // ---------------------------------------------------------
        $mois_liste = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        $index_selectionne = array_search($mois_nom, $mois_liste);

        // Récupérer le loyer théorique pour comparer
        $stmtCheck = $pdo->prepare("SELECT loyer_mensuel, m.bailleur_id FROM contrats c 
                                    JOIN maisons m ON c.maison_id = m.id 
                                    WHERE c.id = ?");
        $stmtCheck->execute([$contrat_id]);
        $contrat_info = $stmtCheck->fetch();
        $loyer_theorique = floatval($contrat_info['loyer_mensuel']);
        $bailleur_id = $contrat_info['bailleur_id'];

        // Vérifier chaque mois avant le mois choisi pour la même année
        for ($i = 0; $i < $index_selectionne; $i++) {
            $mois_prec = $mois_liste[$i] . " " . $annee_nom;
            
            $stmtV = $pdo->prepare("SELECT SUM(montant_recu) FROM encaissements 
                                    WHERE contrat_id = ? AND periode_concernee = ?");
            $stmtV->execute([$contrat_id, $mois_prec]);
            $deja_regle = floatval($stmtV->fetchColumn());

            if ($deja_regle < $loyer_theorique) {
                // Si un mois précédent n'est pas soldé, on annule tout
                $pdo->rollBack();
                header("Location: ../pages/encaissements.php?error=Dette détectée sur le mois de " . $mois_liste[$i]);
                exit();
            }
        }
        // ---------------------------------------------------------

        // 2. Calculer ce qui a déjà été payé pour CETTE période précise
        $stmtDejaPaye = $pdo->prepare("SELECT SUM(montant_recu) FROM encaissements 
                                       WHERE contrat_id = ? AND periode_concernee = ?");
        $stmtDejaPaye->execute([$contrat_id, $periode]);
        $total_deja_paye = floatval($stmtDejaPaye->fetchColumn());

        // 3. INSERTION DE L'ENCAISSEMENT ACTUEL
        $sqlEncaissement = "INSERT INTO encaissements (contrat_id, montant_recu, date_encaissement, mode_paiement, reference_recu, periode_concernee) 
                            VALUES (?, ?, NOW(), ?, ?, ?)";
        $pdo->prepare($sqlEncaissement)->execute([$contrat_id, $montant_verse, $mode_db, $ref, $periode]);
        $last_id = $pdo->lastInsertId();

        // 4. LOGIQUE D'ÉCHÉANCE INTELLIGENTE
        $nouveau_total_periode = $total_deja_paye + $montant_verse;

        if ($nouveau_total_periode >= $loyer_theorique) {
            $sqlUpdateContrat = "UPDATE contrats 
                                 SET date_prochain_loyer = DATE_ADD(date_prochain_loyer, INTERVAL 1 MONTH) 
                                 WHERE id = ?";
            $pdo->prepare($sqlUpdateContrat)->execute([$contrat_id]);
        }

        // 5. RÉPARTITION FINANCIÈRE
        if ($bailleur_id) {
            $stmtSet = $pdo->query("SELECT taux_commission FROM settings LIMIT 1");
            $settings = $stmtSet->fetch();
            $taux = ($settings && isset($settings['taux_commission'])) ? floatval($settings['taux_commission']) : 10;
            
            $commission_agence = $montant_verse * ($taux / 100); 
            $net_bailleur = $montant_verse - $commission_agence;

            $sqlCcb = "INSERT INTO compte_courant_bailleur (bailleur_id, type_operation, montant, commentaire, date_operation)
                    VALUES (?, 'loyer_encaisse', ?, ?, NOW())";
            $com_b = "Loyer $periode (Net commission $taux%) - Réf: $ref";
            $pdo->prepare($sqlCcb)->execute([$bailleur_id, $net_bailleur, $com_b]);

            recalculerSoldeBailleur($pdo, $bailleur_id);

            $sqlCaisse = "INSERT INTO mouvements_caisse_entreprise (type_mouvement, montant, commentaire, date_operation) 
                        VALUES ('Commission Gestion', ?, ?, NOW())";
            $com_a = "Commission $taux% sur $periode - Reçu $ref";
            $pdo->prepare($sqlCaisse)->execute([$commission_agence, $com_a]);
        }

        $pdo->commit();

        insertLog($pdo, "Encaissement Loyer", "Contrat #$contrat_id — $periode — $montant_verse FCFA — Réf: $ref");

        // ── Envoi email quittance (optionnel, ne bloque pas si SMTP non configuré) ──
        $stmtLoc = $pdo->prepare(
            "SELECT l.nom, l.telephone1, m.designation, m.adresse
             FROM contrats c
             JOIN locataires l ON c.locataire_id = l.id
             JOIN maisons m ON c.maison_id = m.id
             WHERE c.id = ?"
        );
        $stmtLoc->execute([$contrat_id]);
        $infoLoc = $stmtLoc->fetch();

        if (!empty($_ENV['MAIL_USER']) && $infoLoc) {
            require_once('../config/mailer.php');
            $stmtSettings = $pdo->query("SELECT * FROM settings LIMIT 1");
            $cfg = $stmtSettings->fetch();
            $nomEntreprise = htmlspecialchars($cfg['nom_entreprise'] ?? 'BailManager');

            $emailBody = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #ddd;border-radius:8px;overflow:hidden'>
              <div style='background:#002147;color:white;padding:20px;text-align:center'>
                <h2 style='margin:0'>$nomEntreprise</h2>
                <p style='margin:4px 0 0;opacity:.8;font-size:13px'>Quittance de loyer</p>
              </div>
              <div style='padding:24px'>
                <p>Bonjour <strong>" . htmlspecialchars($infoLoc['nom']) . "</strong>,</p>
                <p>Votre paiement de loyer a bien été enregistré. Voici le récapitulatif :</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                  <tr style='background:#f8f9fa'><td style='padding:8px 12px;font-weight:bold'>Référence</td><td style='padding:8px 12px'>$ref</td></tr>
                  <tr><td style='padding:8px 12px;font-weight:bold'>Bien loué</td><td style='padding:8px 12px'>" . htmlspecialchars($infoLoc['designation']) . "</td></tr>
                  <tr style='background:#f8f9fa'><td style='padding:8px 12px;font-weight:bold'>Période</td><td style='padding:8px 12px'>$periode</td></tr>
                  <tr><td style='padding:8px 12px;font-weight:bold'>Montant versé</td><td style='padding:8px 12px;color:#198754;font-weight:bold'>" . number_format($montant_verse, 0, ',', ' ') . " FCFA</td></tr>
                  <tr style='background:#f8f9fa'><td style='padding:8px 12px;font-weight:bold'>Mode de paiement</td><td style='padding:8px 12px'>$mode</td></tr>
                </table>
                <p style='font-size:12px;color:#666'>Merci pour votre règlement ponctuel.<br>$nomEntreprise</p>
              </div>
            </div>";

            // L'email du locataire n'est pas stocké — on envoie à l'agence en copie interne
            $emailAgence = $cfg['contact_email'] ?? $_ENV['MAIL_FROM'] ?? '';
            if ($emailAgence) {
                sendMail($emailAgence, $nomEntreprise, "Quittance $ref — " . $infoLoc['nom'], $emailBody);
            }
        }

        header("Location: ../pages/quittance.php?id=" . $last_id);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Erreur Encaissement : " . $e->getMessage());
        header("Location: ../pages/encaissements.php?error=Erreur système");
        exit();
    }
}