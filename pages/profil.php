<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) { header('Location: login.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <title>Mon Profil — BailManager</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/fontawesome/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --marine:#002147; }
        .main-content { background:#f4f7fe; min-height:100vh; padding:28px; }
        .profil-card { background:#fff; border-radius:14px; border:1px solid #e8ecf4; box-shadow:0 2px 10px rgba(0,0,0,.06); padding:24px; margin-bottom:20px; }
        .profil-avatar {
            width:110px; height:110px; border-radius:50%; object-fit:cover;
            border:3px solid #eef2fb; background:#eef2fb; display:flex; align-items:center; justify-content:center;
            font-size:34px; color:var(--marine); font-weight:700;
        }
        .section-title { font-size:14px; font-weight:700; color:#2d3a55; margin-bottom:16px; display:flex; align-items:center; gap:8px; }

        html[data-theme="dark"] .profil-card { background:#1e222b !important; border-color:#2e333d !important; }
        html[data-theme="dark"] .profil-avatar { background:#262b35 !important; border-color:#2e333d !important; color:#e4e6eb !important; }
        html[data-theme="dark"] .section-title { color:#e4e6eb !important; }
    </style>
</head>
<body>
<?php include('../includes/sidebar.php'); ?>

<div class="main-content">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <!-- Photo de profil -->
            <div class="profil-card text-center">
                <div class="section-title justify-content-center"><i class="fa fa-camera" style="color:var(--marine);"></i>Photo de profil</div>
                <?php if (!empty($user['photo_profil']) && file_exists('../uploads/users/' . $user['photo_profil'])): ?>
                <img src="../uploads/users/<?= htmlspecialchars($user['photo_profil']) ?>" class="profil-avatar mb-3" id="avatarPreview">
                <?php else: ?>
                <div class="profil-avatar mb-3 mx-auto" id="avatarPreview"><?= htmlspecialchars(mb_strtoupper(mb_substr($user['nom_complet'], 0, 1))) ?></div>
                <?php endif; ?>
                <form action="../php/update_photo_profil.php" method="POST" enctype="multipart/form-data" class="d-flex flex-column align-items-center gap-2">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <input type="file" name="photo" id="photoInput" accept="image/*" class="form-control" style="max-width:320px;">
                    <button type="submit" class="btn btn-sm px-4" style="background:var(--marine);color:#fff;border-radius:8px;">
                        <i class="fa fa-upload me-1"></i>Mettre à jour la photo
                    </button>
                </form>
            </div>

            <!-- Informations personnelles -->
            <div class="profil-card">
                <div class="section-title"><i class="fa fa-user" style="color:var(--marine);"></i>Informations personnelles</div>
                <form action="../php/update_profil.php" method="POST">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Nom complet</label>
                            <input type="text" name="nom_complet" class="form-control" value="<?= htmlspecialchars($user['nom_complet']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Rôle</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Membre depuis</label>
                            <input type="text" class="form-control bg-light" value="<?= !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : '—' ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Déconnexion automatique après (minutes)</label>
                            <input type="number" name="session_timeout_minutes" class="form-control" min="5" max="120" value="<?= (int)($user['session_timeout_minutes'] ?? 10) ?>" required>
                            <small class="text-muted" style="font-size:10.5px;">Durée d'inactivité avant déconnexion automatique de votre session (entre 5 et 120 minutes).</small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn px-4" style="background:var(--marine);color:#fff;border-radius:8px;">
                            <i class="fa fa-save me-1"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Changer le mot de passe -->
            <div class="profil-card">
                <div class="section-title"><i class="fa fa-key" style="color:var(--marine);"></i>Changer le mot de passe</div>
                <form action="../php/update_password.php" method="POST">
                    <input type="hidden" name="token" value="<?= csrf_generate() ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="form-control" placeholder="••••••••" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="6">
                        </div>
                    </div>
                    <p class="text-muted small mt-2 mb-0"><i class="fa fa-info-circle me-1"></i>Vous serez déconnecté après le changement, pour vous reconnecter avec le nouveau mot de passe.</p>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-danger px-4" style="border-radius:8px;">
                            <i class="fa fa-check me-1"></i>Confirmer le changement
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('photoInput').addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var preview = document.getElementById('avatarPreview');
        var img = document.createElement('img');
        img.src = e.target.result;
        img.className = 'profil-avatar mb-3';
        img.id = 'avatarPreview';
        preview.replaceWith(img);
    };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>
