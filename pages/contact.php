<?php
    session_start();
    require_once('../config/db.php'); // Vérifiez le chemin vers votre fichier de connexion

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
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Contactez-nous - BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        .house-card { transition: transform 0.3s; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .house-card:hover { transform: translateY(-5px); }
        .badge-status { position: absolute; top: 10px; right: 10px; z-index: 10; }
        .carousel-item img { height: 200px; object-fit: cover; }

         :root {
            --marine: #000080;
            --rouge: #FF0000;
        }
        .bg-marine { background-color: var(--marine) !important; }
        .text-rouge { color: var(--rouge) !important; }
        .btn-rouge { background-color: var(--rouge); color: white; border: none; }
        .btn-rouge:hover { background-color: #cc0000; color: white; }

        .carousel-item img {
    height: 300px; /* Force une hauteur identique pour toutes les cartes */
    object-fit: cover; /* Recadre proprement l'image sans l'écraser */
    width: 100%;
}
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-marine sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">BAIL<span class="text-rouge">MANAGER</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="../index.php">Accueil</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropdownServices" role="button" data-bs-toggle="dropdown">
                        Nos Services
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="services.php#gestion-locative">Gestion Locative</a></li>
                        <li><a class="dropdown-item" href="services.php#conseil-bailleurs">Conseil aux Bailleurs</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="services.php#assistance-juridique">Assistance Juridique</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="nos_maisons.php">Nos Maisons</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-rouge" href="login.php"><i class="fa fa-user-lock"></i> Se connecter</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow border-0">
                <div class="row g-0">
                    <div class="col-md-5 bg-danger text-white p-4">
                        <h3 class="fw-bold mb-4">Infos Contact</h3>
                        
                        <p>
                            <i class="fa fa-map-marker-alt me-2"></i> 
                            <?= nl2br(htmlspecialchars($entreprise['adresse_siege'])) ?>
                        </p>
                        
                        <p>
                            <i class="fa fa-phone me-2"></i> 
                            <?= htmlspecialchars($entreprise['contact_telephone']) ?>
                        </p>
                        
                        <p>
                            <i class="fa fa-envelope me-2"></i> 
                            <?= htmlspecialchars($entreprise['contact_email']) ?>
                        </p>
                        
                        <hr>
                        <div class="mt-4">
                            <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                            <a href="https://wa.me/<?= htmlspecialchars(str_replace(' ', '', $entreprise['contact_telephone'])) ?>" class="text-white">
                                <i class="fab fa-whatsapp fa-lg"></i>
                            </a>
                        </div>
                    </div>

                    <div class="col-md-7 p-4">
                        <h3 class="text-dark fw-bold mb-4">Laissez un message</h3>
                        
                        <?php if(isset($_GET['sent'])): ?>
                            <div class="alert alert-success">Votre message a été envoyé avec succès !</div>
                        <?php endif; ?>

                        <form action="../php/send_message.php" method="POST">
                            <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                            <div class="mb-3">
                                <label class="form-label">Nom complet</label>
                                <input type="text" name="nom" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sujet</label>
                                <input type="text" name="sujet" class="form-control" placeholder="Ex: Info sur une maison">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea name="message" class="form-control" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 fw-bold">Envoyer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-marine text-white pt-5 pb-3 mt-5" style="background-color: #000080;">
    <div class="container text-center text-md-start">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="d-flex align-items-center mb-3">
                   <?php if(!empty($entreprise['logo_url']) && file_exists("../uploads/" . $entreprise['logo_url'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($entreprise['logo_url']) ?>" 
                            alt="Logo" 
                            class="me-3 rounded bg-white p-1" 
                            style="height: 50px; width: 50px; object-fit: contain;">
                    <?php else: ?>
                        <div class="bg-white text-primary rounded d-flex align-items-center justify-content-center me-3" 
                            style="width: 50px; height: 50px;">
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
                    <a href="mailto:<?= htmlspecialchars($entreprise['contact_email']) ?>" class="text-white text-decoration-none">
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

</body>
</html>
