<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $maison_id      = $_POST['maison_id'] ?? '';
    $locataire_id   = $_POST['locataire_id'] ?? '';
    $loyer          = $_POST['loyer_mensuel'] ?? '';
    $avance_loyer   = floatval($_POST['avance_loyer'] ?? 0);
    $droit_agence   = floatval($_POST['droit_agence'] ?? 0);
    $debut          = $_POST['date_debut'] ?? ''; // C'est ta variable $date_debut
    $date_signature = date('Y-m-d');

    if ($maison_id === '' || $locataire_id === '' || $loyer === '' || $debut === '') {
        flash('error', "Veuillez sélectionner une maison, un locataire et remplir toutes les dates avant d'enregistrer.");
        header('Location: ../pages/contrats.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. On récupère la condition de la maison pour le calcul
        // Note: Utilisation des backticks `condition` car c'est un mot réservé MySQL
        $stmtMaison = $pdo->prepare("SELECT `condition`, bailleur_id FROM maisons WHERE id = ?");
        $stmtMaison->execute([$maison_id]);
        $maison = $stmtMaison->fetch();

        if (!$maison) {
            throw new Exception("Maison #$maison_id introuvable.");
        }
        if (!bailleurAMandatActif($pdo, (int)$maison['bailleur_id'])) {
            throw new Exception("Le bailleur de cette maison n'a pas de mandat de gestion actif. Enregistrez d'abord un mandat pour ce bailleur.");
        }
        $conditionTotal = (int)$maison['condition'];

        // 2. Avance de loyer selon la condition de la maison ; caution fixée à 2 mois de loyer (règle obligatoire)
        $nbMoisAvance = $conditionTotal / 2;
        $montantCaution = $loyer * 2;

        // 3. Calcul de la date du prochain loyer
        $date_prochain_loyer = date('Y-m-d', strtotime("+$nbMoisAvance month", strtotime($debut)));

        // 4. INSERTION UNIQUE DU CONTRAT (Ordre des colonnes = Ordre des variables)
        $sql = "INSERT INTO contrats (
                    maison_id,
                    locataire_id,
                    loyer_mensuel,
                    depot_garantie,
                    avance_loyer,
                    droit_agence,
                    date_contrat,
                    date_debut,
                    date_prochain_loyer,
                    statut_contrat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')";

        $stmt = $pdo->prepare($sql);

        // CORRECTION DE L'ORDRE ICI :
        $stmt->execute([
            $maison_id,           // 1. maison_id
            $locataire_id,        // 2. locataire_id
            $loyer,               // 3. loyer_mensuel
            $montantCaution,      // 4. depot_garantie (Était inversé avec $debut)
            $avance_loyer,        // 5. avance_loyer
            $droit_agence,        // 6. droit_agence
            $date_signature,      // 7. date_contrat
            $debut,               // 8. date_debut
            $date_prochain_loyer  // 9. date_prochain_loyer
        ]);

        // 5. Mise à jour du statut de la maison
        // Utilisation de 'occupe' (ou 'Occupée' selon ta préférence, mais 'occupe' est plus standard en BDD)
        $updateMaison = $pdo->prepare("UPDATE maisons SET statut = 'occupe' WHERE id = ?");
        $updateMaison->execute([$maison_id]);

        $pdo->commit();
        // Au lieu de simplement rediriger vers contrats.php
        $lastId = $pdo->lastInsertId();
        insertLog($pdo, "Création Contrat", "Contrat #$lastId créé — Maison #$maison_id / Locataire #$locataire_id — Loyer $loyer FCFA");
        flash('success', "Contrat #$lastId créé avec succès.");
        header("Location: ../pages/recu_contrat.php?id=" . $lastId);
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        flash('error', "Erreur lors de la création du contrat.");
        header('Location: ../pages/contrats.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', $e->getMessage());
        header('Location: ../pages/contrats.php');
        exit();
    }
}