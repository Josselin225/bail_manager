<?php
    require_once('../config/db.php');

    $query_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
    $entreprise = $query_settings->fetch();

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
    <title>Nos Services - BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <style>
        :root {
            --marine: #000080;
            --rouge: #FF0000;
        }
        .bg-marine { background-color: var(--marine) !important; }
        .text-rouge { color: var(--rouge) !important; }
        .btn-rouge { background-color: var(--rouge); color: white; border: none; }
        .btn-rouge:hover { background-color: #cc0000; color: white; }

        .service-hero {
            background: linear-gradient(135deg, var(--marine), #000050);
            color: #fff;
            padding: 70px 0 60px;
        }
        .service-section { padding: 60px 0; scroll-margin-top: 90px; }
        .service-section:nth-of-type(even) { background: #f4f7fe; }
        .service-icon {
            width: 64px; height: 64px; border-radius: 16px;
            background: rgba(0,0,128,.08);
            color: var(--marine);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; flex-shrink: 0;
        }
        .service-list { list-style: none; padding-left: 0; margin-top: 20px; }
        .service-list li { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
        .service-list i { color: var(--rouge); margin-top: 4px; flex-shrink: 0; }
    </style>
</head>
<body class="bg-light">
<?php include __DIR__ . '/../includes/page_loader.php'; ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-marine sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">BAIL<span class="text-rouge">MANAGER</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="../index.php">Accueil</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="dropdownServices" role="button" data-bs-toggle="dropdown">
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

<div class="service-hero text-center">
    <div class="container">
        <h1 class="fw-bold">Nos Services</h1>
        <p class="mb-0 opacity-75">Un accompagnement complet pour les propriétaires, de la mise en location à la gestion juridique.</p>
    </div>
</div>

<section class="service-section" id="gestion-locative">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="service-icon"><i class="fa fa-house-chimney-user"></i></div>
                    <h2 class="fw-bold mb-0">Gestion Locative</h2>
                </div>
                <p class="text-muted">Nous prenons en charge l'intégralité du quotidien locatif de votre bien, pour que vous n'ayez plus rien à gérer.</p>
                <ul class="service-list">
                    <li><i class="fa fa-check-circle"></i><span>Recherche et sélection de locataires sérieux</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Rédaction et signature des contrats de bail</span></li>
                    <li><i class="fa fa-check-circle"></i><span>État des lieux d'entrée et de sortie</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Encaissement des loyers et suivi des paiements</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Relances automatiques en cas d'impayés</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Coordination de l'entretien courant et des réparations</span></li>
                </ul>
            </div>
            <div class="col-lg-4 text-center">
                <i class="fa fa-house-chimney-user" style="font-size:140px;color:var(--marine);opacity:.12;"></i>
            </div>
        </div>
    </div>
</section>

<section class="service-section" id="conseil-bailleurs">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-4 text-center order-lg-1 order-2">
                <i class="fa fa-handshake" style="font-size:140px;color:var(--marine);opacity:.12;"></i>
            </div>
            <div class="col-lg-8 order-lg-2 order-1">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="service-icon"><i class="fa fa-handshake"></i></div>
                    <h2 class="fw-bold mb-0">Conseil aux Bailleurs</h2>
                </div>
                <p class="text-muted">Un accompagnement personnalisé pour vous aider à tirer le meilleur parti de votre patrimoine immobilier.</p>
                <ul class="service-list">
                    <li><i class="fa fa-check-circle"></i><span>Estimation du loyer au juste prix selon le marché</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Conseils pour valoriser et optimiser votre bien</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Accompagnement administratif et fiscal</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Stratégie de mise en location adaptée à votre bien</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Suivi personnalisé et reporting régulier</span></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="service-section" id="assistance-juridique">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="service-icon"><i class="fa fa-scale-balanced"></i></div>
                    <h2 class="fw-bold mb-0">Assistance Juridique</h2>
                </div>
                <p class="text-muted">Sécurisez chaque étape de la location grâce à un accompagnement conforme à la réglementation en vigueur.</p>
                <ul class="service-list">
                    <li><i class="fa fa-check-circle"></i><span>Rédaction de baux conformes à la réglementation</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Accompagnement en cas de litige locataire / bailleur</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Procédures de recouvrement des loyers impayés</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Conseil sur les obligations légales du bailleur</span></li>
                    <li><i class="fa fa-check-circle"></i><span>Gestion des préavis et procédures de résiliation</span></li>
                </ul>
            </div>
            <div class="col-lg-4 text-center">
                <i class="fa fa-scale-balanced" style="font-size:140px;color:var(--marine);opacity:.12;"></i>
            </div>
        </div>
    </div>
</section>

<div class="container text-center pb-5">
    <a href="contact.php" class="btn btn-rouge btn-lg px-5"><i class="fa fa-paper-plane me-2"></i>Discuter de votre projet</a>
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
                <h5 class="text-uppercase fw-bold border-bottom border-danger d-inline-block">Nos Services</h5>
                <ul class="list-unstyled mt-3">
                    <li><a href="services.php#gestion-locative" class="text-white text-decoration-none opacity-75">Gestion Locative</a></li>
                    <li><a href="services.php#conseil-bailleurs" class="text-white text-decoration-none opacity-75">Conseil aux Bailleurs</a></li>
                    <li><a href="services.php#assistance-juridique" class="text-white text-decoration-none opacity-75">Assistance Juridique</a></li>
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

<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
