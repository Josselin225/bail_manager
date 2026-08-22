<?php
session_start();
require_once('../config/db.php');

// 1. Récupération des paramètres de l'entreprise (Settings)
$query_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
$entreprise = $query_settings->fetch();

// 2. Récupération des maisons disponibles
// Remplacer l'ancienne ligne 10 par celle-ci :
$query_maisons = $pdo->query("SELECT * FROM maisons ORDER BY id DESC");
$maisons = $query_maisons->fetchAll();

// 3. Sécurité : Initialisation si les tables sont vides
if (!$entreprise) {
    $entreprise = [
        'nom_entreprise' => 'BailManager',
        'logo_url' => '',
        'contact_email' => 'contact@votreagence.com',
        'contact_telephone' => '+225 XX XX XX XX',
        'adresse_siege' => ''
    ];
}

// Si aucune maison n'est trouvée, on crée un tableau vide pour éviter l'erreur dans le foreach
if (!$maisons) {
    $maisons = [];
}
?>

<?php if(isset($_GET['res']) && $_GET['res'] === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show container mt-3" role="alert">
        <i class="fa fa-check-circle me-2"></i>
        <strong>Merci !</strong> Votre demande de visite a bien été envoyée. L'agence vous contactera très bientôt.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Maisons - BailManager</title>
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
    <h2 class="text-center fw-bold mb-5" style="color: #1a237e;">Découvrez Nos Biens Immobiliers</h2>

    <div class="row g-4">
        <?php foreach($maisons as $m): ?>
        <div class="col-md-4">
            <div class="card h-100 house-card">
                <span class="badge <?= $m['statut'] === 'disponible' ? 'bg-success' : 'bg-danger' ?> badge-status">
                    <?= ucfirst($m['statut']) ?>
                </span>

                <div id="carousel<?= $m['id'] ?>" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <?php 
                            // On ajoute 'maisons/' dans le chemin car vos photos y sont rangées
                            $imagePath = "../uploads/maisons/" . $m['image1']; 
                            
                            if(!empty($m['image1']) && file_exists($imagePath)): ?>
                                <img src="<?= $imagePath ?>" class="d-block w-100" style="height:250px; object-fit:cover;" alt="Photo">
                            <?php else: ?>
                                <img src="../images/default.jpg" class="d-block w-100" style="height:250px; object-fit:cover;" alt="Non disponible">
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($m['image2']) && file_exists("../uploads/".$m['image2'])): ?>
                        <div class="carousel-item">
                            <img src="../uploads/<?= htmlspecialchars($m['image2']) ?>" class="d-block w-100" alt="Maison">
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($m['image3']) && file_exists("../uploads/".$m['image3'])): ?>
                        <div class="carousel-item">
                            <img src="../uploads/<?= htmlspecialchars($m['image3']) ?>" class="d-block w-100" alt="Maison">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <h5 class="card-title fw-bold text-dark"><?= htmlspecialchars($m['designation']) ?></h5>
                    <p class="text-muted small mb-2"><i class="fa fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($m['adresse']) ?></p>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fs-5 fw-bold text-primary"><?= number_format($m['loyer'], 0, ',', ' ') ?> FCFA <small class="text-muted" style="font-size: 12px;">/mois</small></span>
                    </div>

                    <div class="bg-light p-2 rounded mb-3">
                        <small class="d-block text-secondary"><strong>Condition :</strong> <?= htmlspecialchars($m['condition']) ?> mois de caution</small>
                        <small class="text-secondary text-truncate d-block"><strong>Type :</strong> <?= htmlspecialchars($m['type_maison']) ?></small>
                    </div>

                    <?php if($m['statut'] === 'disponible'): ?>
                        <button class="btn btn-danger w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#modalReserve<?= $m['id'] ?>">
                            Réserver maintenant
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 fw-bold" disabled>Déjà Occupée</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalReserve<?= $m['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title">Réservation : <?= htmlspecialchars($m['designation']) ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="../php/add_reservation.php" method="POST">
                        <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                        <div class="modal-body">
                            <input type="hidden" name="maison_id" value="<?= $m['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Votre Nom Complet</label>
                                <input type="text" name="nom_visiteur" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" name="tel_visiteur" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date de visite souhaitée</label>
                                <input type="date" name="date_visite" class="form-control" required>
                            </div>
                            <p class="small text-muted">En cliquant sur valider, l'agence vous contactera pour confirmer la visite.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger w-100">Envoyer ma demande</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
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

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
