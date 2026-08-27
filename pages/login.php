<?php
session_start();
require_once('../config/db.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Connexion - BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/toast.css">
    <style>
        :root { --marine: #000080; --rouge: #FF0000; }
        body { background-color: var(--marine); height: 100vh; display: flex; align-items: center; }
        .login-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .btn-login { background-color: var(--rouge); color: white; border: none; width: 100%; padding: 12px; font-weight: bold; }
        .btn-login:hover { background-color: #cc0000; color: white; }
        .form-control:focus { border-color: var(--rouge); box-shadow: 0 0 0 0.2rem rgba(255, 0, 0, 0.25); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/page_loader.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card login-card p-4">
                <div class="text-center mb-4">
                    <h2 class="fw-bold" style="color: var(--marine);">BAIL<span style="color: var(--rouge);">MANAGER</span></h2>
                    <p class="text-muted">Accès Administration</p>
                </div>

                <form action="../php/auth_process.php" method="POST">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="admin@exemple.com" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login mb-3">SE CONNECTER</button>

                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="text-muted small text-decoration-none">
                            <i class="fa fa-question-circle me-1"></i> Mot de passe oublié ?
                        </div>
                    </div>

                    <div class="text-center">
                        <a href="../index.php" class="text-decoration-none text-muted small"><i class="fa fa-arrow-left"></i> Retour au site</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../js/toast.js"></script>
<?php if (isset($_GET['error']) && $_GET['error'] === 'timeout'): ?>
<script>showToast("Votre session a expiré après 10 minutes d'inactivité. Veuillez vous reconnecter.", 'warning');</script>
<?php elseif (isset($_GET['error'])): ?>
<script>showToast("Email ou mot de passe incorrect.", 'error');</script>
<?php endif; ?>
</body>
</html>
