<?php
require_once('../config/db.php');

$token = $_GET['token'] ?? '';
$isValid = false;

if ($token) {
    // On vérifie si le token existe et n'est pas expiré
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expire > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $isValid = true;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Nouveau mot de passe - BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .card { border: none; border-top: 4px solid #FF0000; } /* Rouge pour l'action critique */
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow p-4">
                <?php if ($isValid): ?>
                    <h4 class="text-center fw-bold mb-3">Réinitialisation</h4>
                    <p class="text-center text-muted small">Veuillez choisir un nouveau mot de passe sécurisé.</p>
                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger small py-2">
                        <?= $_GET['error'] === 'mismatch' ? "Les mots de passe ne correspondent pas (ou sont trop courts, 6 caractères minimum)." : "Une erreur est survenue, veuillez réessayer." ?>
                    </div>
                    <?php endif; ?>

                    <form action="../php/complete_reset.php" method="POST">
                        <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                        <input type="hidden" name="reset_token" value="<?= htmlspecialchars($token) ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nouveau mot de passe</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-danger w-100 shadow-sm">Mettre à jour le mot de passe</button>
                    </form>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fa fa-exclamation-circle text-danger fa-3x mb-3"></i>
                        <h4 class="fw-bold">Lien expiré ou invalide</h4>
                        <p class="text-muted">Désolé, ce lien n'est plus valable. Veuillez refaire une demande.</p>
                        <a href="forgot_password.php" class="btn btn-primary btn-sm">Recommencer</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
