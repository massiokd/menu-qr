<?php
require 'config/db.php';

$token = $_GET['token'] ?? '';
$message = '';
$isSuccess = false;

if (!empty($token)) {
    $pdo = getDB();
    
    // On cherche un utilisateur avec ce token
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email_token = ? AND email_verified = false");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // On active le compte
        $stmtUpdate = $pdo->prepare("UPDATE users SET email_verified = true, is_active = true, email_token = NULL WHERE id = ?");
        $stmtUpdate->execute([$user['id']]);
        
        $message = "Votre compte a été activé avec succès ! Vous pouvez maintenant vous connecter.";
        $isSuccess = true;
    } else {
        $message = "Ce lien d'activation est invalide ou a déjà été utilisé.";
    }
} else {
    $message = "Lien d'activation manquant.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activation du compte - FenneTech</title>
    <!-- FAVICON QR CODE -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-card" style="text-align: center;">
        <div style="font-size: 4rem; margin-bottom: 1rem;">
            <?= $isSuccess ? '🎉' : '⚠️' ?>
        </div>
        <h1 style="font-size: 1.5rem; margin-bottom: 1rem;"><?= $isSuccess ? 'Compte activé !' : 'Erreur' ?></h1>
        
        <p style="font-size: 0.95rem; color: #6b7280; margin-bottom: 2rem;">
            <?= $message ?>
        </p>
        
        <?php if($isSuccess): ?>
            <a href="login.php" class="btn-submit" style="display:block; text-align:center; text-decoration:none;">Se connecter</a>
        <?php else: ?>
            <a href="register.php" style="color: var(--brand); font-weight: bold; text-decoration: none;">Retour à l'inscription</a>
        <?php endif; ?>
    </div>
</body>
</html>