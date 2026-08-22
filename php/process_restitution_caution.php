<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_validate();
    $locataire_id = $_POST['locataire_id'];
    $retenue_reparation = (float)($_POST['retenue_reparation'] ?? 0);
    $retenue_loyer = (float)($_POST['retenue_loyer'] ?? 0);

    // On détermine si c'est une restitution finale ou une simple retenue
    $est_restitution_finale = isset($_POST['cloturer_contrat']);

    $total_retenues = $retenue_reparation + $retenue_loyer;

    // Le contrat actif de ce locataire fait foi pour le contrôle d'accès et le
    // montant réellement disponible — on ignore le montant envoyé par le
    // navigateur (champ readonly mais modifiable côté client).
    $stmtContrat = $pdo->prepare(
        "SELECT id, depot_garantie, maison_id FROM contrats WHERE locataire_id = ? AND statut_contrat = 'actif' LIMIT 1"
    );
    $stmtContrat->execute([$locataire_id]);
    $contratActif = $stmtContrat->fetch();
    if (!$contratActif) {
        flash('error', "Ce locataire n'a plus de contrat actif (déjà résilié ?).");
        header('Location: ../pages/gestion_cautions.php');
        exit();
    }

    $stmtMvt = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM mouvements_caution WHERE locataire_id = ?");
    $stmtMvt->execute([$locataire_id]);
    $montant_disponible = floatval($contratActif['depot_garantie']) + floatval($stmtMvt->fetchColumn());

    if ($total_retenues > $montant_disponible) {
        flash('error', "Le montant des retenues dépasse la caution disponible.");
        header('Location: ../pages/gestion_cautions.php');
        exit();
    }

    try {
        $pdo->beginTransaction();
        $user = $_SESSION['nom_complet'] ?? 'Administrateur';

        // --- LOGIQUE DE DÉBIT DE LA CAUTION ---
        if ($est_restitution_finale) {
            // CAS 1 : On vide tout car le locataire part
            $montant_debit_caution = -$montant_disponible;
            $commentaire_caution = "Solde de tout compte (Restitution finale)";
        } else {
            // CAS 2 : Le contrat continue, on ne retire que les retenues
            $montant_debit_caution = -$total_retenues;
            $commentaire_caution = "Retenue sur caution (Contrat actif)";
        }

        // Enregistrement du mouvement de caution
        $stmt = $pdo->prepare("INSERT INTO mouvements_caution (locataire_id, type_mouvement, montant, commentaire, effectue_par) VALUES (?, 'retenue', ?, ?, ?)");
        $stmt->execute([$locataire_id, $montant_debit_caution, $commentaire_caution, $user]);

        // --- TRANSFERTS VERS AGENCE OU BAILLEUR ---
        if ($retenue_reparation > 0) {
            $stmtAgence = $pdo->prepare("INSERT INTO mouvements_caisse_entreprise (type_mouvement, montant, commentaire, effectue_par) VALUES ('Entrée (Réparation)', ?, ?, ?)");
            $stmtAgence->execute([$retenue_reparation, "Réparation locataire ID: $locataire_id", $user]);
        }

        if ($retenue_loyer > 0) {
            $stmtBailleurId = $pdo->prepare("SELECT bailleur_id FROM maisons WHERE id = ?");
            $stmtBailleurId->execute([$contratActif['maison_id']]);
            $bailleur_id = $stmtBailleurId->fetchColumn();

            if ($bailleur_id) {
                $pdo->prepare(
                    "INSERT INTO compte_courant_bailleur (bailleur_id, montant, type_operation, commentaire)
                     VALUES (?, ?, 'loyer_encaisse', ?)"
                )->execute([$bailleur_id, $retenue_loyer, "Loyer récupéré sur caution ID: $locataire_id"]);

                recalculerSoldeBailleur($pdo, $bailleur_id);
            }
        }

        // Si on clôture le contrat depuis ce circuit, on le synchronise avec
        // resilier_contrat.php (statut contrat + disponibilité du bien).
        if ($est_restitution_finale) {
            $pdo->prepare("UPDATE contrats SET statut_contrat = 'termine', date_fin = CURDATE(), depot_garantie_actuel = 0 WHERE id = ?")
                ->execute([$contratActif['id']]);
            $pdo->prepare("UPDATE maisons SET statut = 'disponible' WHERE id = ?")
                ->execute([$contratActif['maison_id']]);
        }

        $pdo->commit();
        insertLog($pdo, "Caution", "$commentaire_caution — Locataire #$locataire_id — Retenues : $total_retenues FCFA");
        flash('success', "Caution traitée avec succès.");
        header("Location: ../pages/gestion_cautions.php");

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        flash('error', "Une erreur est survenue lors du traitement de la caution.");
        header('Location: ../pages/gestion_cautions.php');
        exit();
    }
}