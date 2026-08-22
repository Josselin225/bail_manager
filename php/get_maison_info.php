<?php
require_once('../config/db.php');

if(isset($_GET['id'])) {
    // On récupère la condition en plus des autres infos
    $stmt = $pdo->prepare("SELECT designation, loyer, adresse, image1, `condition` FROM maisons WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $maison = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $maison['image1'] = !empty($maison['image1']) ? $maison['image1'] : 'default_maison.jpg';
    
    echo json_encode($maison);
}