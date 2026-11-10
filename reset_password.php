<?php
session_start();
require 'config/db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

$pdo = getDB();

// Vérifier si le token est valide
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("Ce lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmtUpdate->execute([$hash, $user['id']]);
        
        $success = "Votre mot de passe a été mis à jour avec succès !";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - FenneTech</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-card">
        <h1 style="font-size: 1.5rem;">Nouveau mot de passe</h1>
        
        <?php if($error): ?>
            <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; text-align: center; margin-bottom: 1rem; border: 1px solid #bbf7d0;">
                <?= $success ?>
            </div>
            <a href="login.php" class="btn-submit" style="display:block; text-align:center; text-decoration:none; margin-top:1rem;">Se connecter</a>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="password" required placeholder="Min. 6 caractères">
                </div>
                <div class="form-group">
                    <label>Confirmer le mot de passe</label>
                    <input type="password" name="confirm" required>
                </div>
                <button type="submit" class="btn-submit">Mettre à jour</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>