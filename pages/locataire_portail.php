<?php
session_start();
require_once('../config/db.php');

// Si déjà connecté en mode locataire, rediriger
if (!empty($_SESSION['locataire_mode'])) {
    header('Location: locataire_espace.php');
    exit();
}

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$nomEntreprise = htmlspecialchars($settings['nom_entreprise'] ?? 'BailManager');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Espace Locataire — <?= $nomEntreprise ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/toast.css">
    <style>
        :root { --marine: #002147; --rouge: #FF0000; }
        body { background: linear-gradient(135deg, var(--marine) 0%, #004080 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,.4); }
        .btn-login { background: var(--rouge); color: white; border: none; width: 100%; padding: 12px; font-weight: bold; border-radius: 8px; }
        .btn-login:hover { background: #cc0000; color: white; }
        .form-control:focus { border-color: var(--rouge); box-shadow: 0 0 0 .2rem rgba(255,0,0,.2); }
        .logo-circle { width: 70px; height: 70px; background: var(--marine); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/page_loader.php'; ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4 col-sm-10">
            <div class="card login-card p-4">
                <div class="text-center mb-4">
                    <div class="logo-circle">
                        <i class="fa fa-user-circle fa-2x text-white"></i>
                    </div>
                    <h4 class="fw-bold" style="color: var(--marine);">Espace <span style="color: var(--rouge);">Locataire</span></h4>
                    <p class="text-muted small"><?= $nomEntreprise ?></p>
                </div>

                <form action="../php/locataire_auth.php" method="POST">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Numéro de téléphone</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-phone"></i></span>
                            <input type="text" name="telephone" class="form-control" placeholder="07 XX XX XX XX" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small">Code d'accès</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-key"></i></span>
                            <input type="text" name="code_acces" class="form-control text-uppercase" placeholder="Code fourni par l'agence" maxlength="8" required>
                        </div>
                        <small class="text-muted">Contactez l'agence si vous n'avez pas de code.</small>
                    </div>
                    <button type="submit" class="btn btn-login">SE CONNECTER</button>
                </form>

                <div class="text-center mt-3">
                    <a href="../index.php" class="text-muted small text-decoration-none"><i class="fa fa-arrow-left me-1"></i>Retour au site</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../js/bootstrap.bundle.min.js"></script>
<script src="../js/toast.js"></script>
<?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
<script>showToast("Numéro ou code incorrect.", 'error');</script>
<?php elseif (isset($_GET['error']) && $_GET['error'] === 'timeout'): ?>
<script>showToast("Session expirée, veuillez vous reconnecter.", 'warning');</script>
<?php elseif (isset($_GET['error'])): ?>
<script>showToast("Veuillez remplir tous les champs.", 'error');</script>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deconnecte'): ?>
<script>showToast("Vous êtes déconnecté.", 'info');</script>
<?php endif; ?>
</body>
</html>
