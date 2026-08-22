<?php
session_start();
require_once('../config/db.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récupération - BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
        .card { border: none; border-top: 4px solid #000080; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow p-4">
                <h4 class="text-center fw-bold mb-4">Récupération</h4>
                <p class="text-muted small">Saisissez votre email pour recevoir un lien de réinitialisation.</p>
                
                <form action="../php/process_forgot.php" method="POST">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <div class="mb-3">
                        <label class="form-label">Email professionnel</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" style="background-color: #000080;">Envoyer le lien</button>
                    <a href="login.php" class="btn btn-link w-100 mt-2 text-decoration-none small text-muted">Retour à la connexion</a>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
