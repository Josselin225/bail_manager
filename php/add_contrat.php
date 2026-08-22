<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $maison_id      = $_POST['maison_id'];
    $locataire_id   = $_POST['locataire_id'];
    $loyer          = $_POST['loyer_mensuel'];
    $debut          = $_POST['date_debut']; // C'est ta variable $date_debut
    $date_signature = date('Y-m-d');

    try {
        $pdo->beginTransaction();

        // 1. On récupère la condition de la maison pour le calcul
        // Note: Utilisation des backticks `condition` car c'est un mot réservé MySQL
        $stmtMaison = $pdo->prepare("SELECT `condition` FROM maisons WHERE id = ?");
        $stmtMaison->execute([$maison_id]);
        $maison = $stmtMaison->fetch();
        $conditionTotal = (int)$maison['condition'];

        // 2. Répartition : 50% Caution, 50% Avance
        $nbMoisAvance = $conditionTotal / 2;
        $montantCaution = $loyer * ($conditionTotal / 2);

        // 3. Calcul de la date du prochain loyer
        $date_prochain_loyer = date('Y-m-d', strtotime("+$nbMoisAvance month", strtotime($debut)));

        // 4. INSERTION UNIQUE DU CONTRAT (Ordre des colonnes = Ordre des variables)
        $sql = "INSERT INTO contrats (
                    maison_id, 
                    locataire_id, 
                    loyer_mensuel, 
                    depot_garantie, 
                    date_contrat, 
                    date_debut, 
                    date_prochain_loyer, 
                    statut_contrat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'actif')";
        
        $stmt = $pdo->prepare($sql);
        
        // CORRECTION DE L'ORDRE ICI :
        $stmt->execute([
            $maison_id,           // 1. maison_id
            $locataire_id,        // 2. locataire_id
            $loyer,               // 3. loyer_mensuel
            $montantCaution,      // 4. depot_garantie (Était inversé avec $debut)
            $date_signature,      // 5. date_contrat
            $debut,               // 6. date_debut
            $date_prochain_loyer  // 7. date_prochain_loyer
        ]);

        // 5. Mise à jour du statut de la maison
        // Utilisation de 'occupe' (ou 'Occupée' selon ta préférence, mais 'occupe' est plus standard en BDD)
        $updateMaison = $pdo->prepare("UPDATE maisons SET statut = 'occupe' WHERE id = ?");
        $updateMaison->execute([$maison_id]);

        $pdo->commit();
        // Au lieu de simplement rediriger vers contrats.php
        $lastId = $pdo->lastInsertId();
        insertLog($pdo, "Création Contrat", "Contrat #$lastId créé — Maison #$maison_id / Locataire #$locataire_id — Loyer $loyer FCFA");
        header("Location: ../pages/recu_contrat.php?id=" . $lastId);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        flash('error', "Erreur lors de la création du contrat.");
        header('Location: ../pages/contrats.php');
        exit();
    }
}