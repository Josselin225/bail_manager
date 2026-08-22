<?php
session_start();
require_once('../config/db.php');

// Vérification de sécurité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    csrf_validate();
    $contrat_id      = $_POST['contrat_id'];
    $caution_retenue = max(0, floatval($_POST['caution_retenue'] ?? 0));
    $date_fin        = date('Y-m-d');
    $user            = $_SESSION['nom_complet'] ?? 'Administrateur';

    try {
        $pdo->beginTransaction();

        // 1. Récupérer maison + locataire + dépôt de garantie initial du contrat
        $stmtC = $pdo->prepare("SELECT maison_id, locataire_id, depot_garantie FROM contrats WHERE id = ?");
        $stmtC->execute([$contrat_id]);
        $c = $stmtC->fetch();
        if (!$c) { throw new Exception("Contrat introuvable."); }
        $maison_id      = $c['maison_id'];
        $locataire_id   = $c['locataire_id'];
        $depot_garantie = floatval($c['depot_garantie']);

        // 2. Empêcher une double restitution si la caution a déjà été soldée
        //    via l'autre circuit (gestion_cautions.php / process_restitution_caution.php)
        $stmtDeja = $pdo->prepare(
            "SELECT COUNT(*) FROM mouvements_caution
             WHERE locataire_id = ? AND commentaire LIKE 'Solde de tout compte%'"
        );
        $stmtDeja->execute([$locataire_id]);
        if ($stmtDeja->fetchColumn() > 0) {
            $pdo->rollBack();
            flash('error', "La caution de ce locataire a déjà été soldée via Gestion des Cautions.");
            header('Location: ../pages/contrats.php');
            exit();
        }

        $depot_garantie_actuel = max(0, $depot_garantie - $caution_retenue);

        // 3. Mettre à jour le contrat (Statut, Date de fin, Caution restante)
        $sqlContrat = "UPDATE contrats SET
                        statut_contrat = 'termine',
                        date_fin = ?,
                        depot_garantie_actuel = ?
                       WHERE id = ?";
        $stmt1 = $pdo->prepare($sqlContrat);
        $stmt1->execute([$date_fin, $depot_garantie_actuel, $contrat_id]);

        // 4. Remettre la maison en 'disponible'
        $sqlMaison = "UPDATE maisons SET statut = 'disponible' WHERE id = ?";
        $stmt2 = $pdo->prepare($sqlMaison);
        $stmt2->execute([$maison_id]);

        // 5. Tracer la retenue dans le registre des mouvements de caution
        //    (source de vérité partagée avec gestion_cautions.php)
        if ($caution_retenue > 0) {
            $pdo->prepare(
                "INSERT INTO mouvements_caution (locataire_id, type_mouvement, montant, commentaire, effectue_par)
                 VALUES (?, 'retenue', ?, ?, ?)"
            )->execute([$locataire_id, -$caution_retenue, "Retenue à la résiliation du contrat #$contrat_id", $user]);
        }

        $pdo->commit();

        insertLog($pdo, "Résiliation Contrat", "Contrat #$contrat_id résilié — Retenue caution : $caution_retenue FCFA");
        flash('success', "Contrat résilié avec succès.");
        header("Location: ../pages/contrats.php");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        flash('error', "Erreur lors de la résiliation du contrat.");
        header('Location: ../pages/contrats.php');
        exit();
    }
} else {
    flash('error', "Droits insuffisants ou requête invalide.");
    header("Location: ../pages/contrats.php");
}