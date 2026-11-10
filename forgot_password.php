<?php
session_start();
require 'config/db.php';
require 'actions/email_helper.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT u.id, u.email, r.nom as resto_nom FROM users u JOIN restaurants r ON u.restaurant_id = r.id WHERE u.email = ? AND u.role = 'gerant'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Générer un token unique et une expiration (1 heure)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmtUpdate = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $stmtUpdate->execute([$token, $expires, $user['id']]);

        // Construire le lien
        $baseUrl = "https://menu-qr-ebke.onrender.com"; // Ton URL Render
        $resetLink = $baseUrl . "/reset_password.php?token=" . $token;

        // Envoyer l'email
        if (sendResetEmail($user['email'], $user['resto_nom'], $resetLink)) {
            $message = "Un email de réinitialisation vous a été envoyé.";
            $messageType = "success";
        } else {
            $message = "Erreur lors de l'envoi de l'email. Veuillez réessayer.";
            $messageType = "error";
        }
    } else {
        // Pour des raisons de sécurité, on affiche le même message même si l'email n'existe pas
        $message = "Si cet email existe, un lien de réinitialisation vous a été envoyé.";
        $messageType = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - FenneTech</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-card">
        <h1 style="font-size: 1.5rem;">Mot de passe oublié</h1>
        <p style="text-align:center; font-size:0.85rem; color:#6b7280; margin-bottom: 1.5rem;">Entrez votre email pour recevoir un lien de réinitialisation.</p>
        
        <?php if($message): ?>
            <div class="<?= $messageType === 'success' ? 'error-msg' : 'error-msg' ?>" style="background: <?= $messageType === 'success' ? '#dcfce7' : '#fee2e2' ?>; color: <?= $messageType === 'success' ? '#166534' : '#991b1b' ?>; border-color: <?= $messageType === 'success' ? '#bbf7d0' : '#fecaca' ?>;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="votre@email.com" required>
            </div>
            <button type="submit" class="btn-submit">Envoyer le lien</button>
        </form>
        
        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="login.php" style="color: var(--text-gray); font-size: 0.85rem; text-decoration: none;">Retour à la connexion</a>
        </div>
    </div>
</body>
</html>