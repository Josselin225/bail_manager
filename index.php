<?php
require_once('config/db.php'); // Vérifiez le chemin vers votre fichier de connexion

// Récupération des paramètres de l'entreprise
$query_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
$entreprise = $query_settings->fetch();

// Valeurs par défaut si la table est vide
if (!$entreprise) {
    $entreprise = [
        'nom_entreprise' => 'BailManager',
        'contact_email' => 'contact@bailmanager.com',
        'contact_telephone' => '+225 XX XX XX XX',
        'adresse_siege' => 'Côte d’Ivoire'
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BailManager - Votre partenaire immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --marine: #000080;
            --rouge: #FF0000;
        }
        .bg-marine { background-color: var(--marine) !important; }
        .text-rouge { color: var(--rouge) !important; }
        .btn-rouge { background-color: var(--rouge); color: white; border: none; }
        .btn-rouge:hover { background-color: #cc0000; color: white; }
        
        /* Ajustement du Carrousel pour qu'il soit élégant */
        .carousel-item img {
            height: 550px;
            object-fit: cover;
            filter: brightness(0.7);
        }
        .carousel-caption {
            bottom: 20%;
            background: rgba(0, 0, 128, 0.6);
            padding: 20px;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-marine sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">BAIL<span class="text-rouge">MANAGER</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="#">Accueil</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropdownServices" role="button" data-bs-toggle="dropdown">
                        Nos Services
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="pages/services.php#gestion-locative">Gestion Locative</a></li>
                        <li><a class="dropdown-item" href="pages/services.php#conseil-bailleurs">Conseil aux Bailleurs</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="pages/services.php#assistance-juridique">Assistance Juridique</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="pages/nos_maisons.php">Nos Maisons</a></li>
                <li class="nav-item"><a class="nav-link" href="pages/contact.php">Contact</a></li>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-rouge" href="pages/login.php"><i class="fa fa-user-lock"></i> Se connecter</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div id="homeCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="0" class="active"></button>
        <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="1"></button>
        <button type="button" data-bs-target="#homeCarousel" data-bs-slide-to="2"></button>
    </div>
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1350&q=80" class="d-block w-100" alt="Villa de luxe">
            <div class="carousel-caption">
                <h2>Villas de Prestige</h2>
                <p>Confiez-nous vos biens les plus précieux, nous gérons tout.</p>
            </div>
        </div>
        <div class="carousel-item">
            <img src="https://images.unsplash.com/photo-1568605114967-8130f3a36994?auto=format&fit=crop&w=1350&q=80" class="d-block w-100" alt="Maison moderne">
            <div class="carousel-caption">
                <h2>Gestion Transparente</h2>
                <p>Un suivi rigoureux des loyers et des commissions en temps réel.</p>
            </div>
        </div>
        <div class="carousel-item">
            <img src="https://images.unsplash.com/photo-1449844908441-8829872d2607?auto=format&fit=crop&w=1350&q=80" class="d-block w-100" alt="Appartement">
            <div class="carousel-caption">
                <h2>Sérénité pour les Bailleurs</h2>
                <p>Recevez vos paiements ponctuellement, sans stress.</p>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#homeCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#homeCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>

<footer class="bg-marine text-white pt-5 pb-3 mt-5" style="background-color: #000080;">
    <div class="container text-center text-md-start">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="d-flex align-items-center mb-3">
                    <?php if(!empty($entreprise['logo_url']) && file_exists("uploads/" . $entreprise['logo_url'])): ?>
                        <img src="uploads/<?= htmlspecialchars($entreprise['logo_url']) ?>" alt="Logo" class="me-3 rounded bg-white p-1" style="height: 50px; width: 50px; object-fit: contain;">
                    <?php else: ?>
                        <div class="bg-white text-primary rounded d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fa fa-building fa-lg"></i>
                        </div>
                    <?php endif; ?>
                    <h5 class="text-uppercase fw-bold border-bottom border-danger d-inline-block mb-0">
                        <?= htmlspecialchars($entreprise['nom_entreprise']) ?>
                    </h5>
                </div>
                <p>Spécialiste de la gestion immobilière. Nous faisons le pont entre propriétaires exigeants et locataires sérieux.</p>
                <?php if(!empty($entreprise['adresse_siege'])): ?>
                    <p class="small opacity-75"><i class="fa fa-map-marker-alt me-2 text-danger"></i> <?= nl2br(htmlspecialchars($entreprise['adresse_siege'])) ?></p>
                <?php endif; ?>
            </div>

            <div class="col-md-4 mb-4">
                <h5 class="text-uppercase fw-bold border-bottom border-danger d-inline-block">Liens Rapides</h5>
                <ul class="list-unstyled mt-3">
                    <li><a href="#" class="text-white text-decoration-none opacity-75">Comment ça marche ?</a></li>
                    <li><a href="#" class="text-white text-decoration-none opacity-75">Devenir partenaire</a></li>
                    <li><a href="#" class="text-white text-decoration-none opacity-75">Mentions légales</a></li>
                </ul>
            </div>

            <div class="col-md-4 mb-4">
                <h5 class="text-uppercase fw-bold border-bottom border-danger d-inline-block">Contact</h5>
                <p class="mt-3">
                    <i class="fa fa-envelope me-2 text-danger"></i> 
                    <a href="mailto:<?= $entreprise['contact_email'] ?>" class="text-white text-decoration-none">
                        <?= htmlspecialchars($entreprise['contact_email']) ?>
                    </a>
                </p>
                <p>
                    <i class="fa fa-phone me-2 text-danger"></i> 
                    <?= htmlspecialchars($entreprise['contact_telephone'] ?: '+225 XX XX XX XX') ?>
                </p>
            </div>
        </div>
        <hr class="bg-white">
        <div class="text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($entreprise['nom_entreprise']) ?> - Tous droits réservés.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>