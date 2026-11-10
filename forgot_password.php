<?php
session_start();
require 'config/db.php';
require 'actions/email_helper.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pdo = getDB();

    // On récupère aussi reset_last_request pour l'anti-spam
    $stmt = $pdo->prepare("SELECT u.id, u.email, u.reset_last_request, r.nom as resto_nom FROM users u JOIN restaurants r ON u.restaurant_id = r.id WHERE u.email = ? AND u.role = 'gerant'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 🛡️ SÉCURITÉ : On affiche TOUJOURS le même message final
    $message = "Si cet email est enregistré, un lien de réinitialisation vous a été envoyé.";
    $messageType = "success";

    if ($user) {
        // 🛡️ ANTI-SPAM : Vérifier si une demande a été faite il y a moins de 2 minutes
        $is_spammed = false;
        if (!empty($user['reset_last_request'])) {
            $timeSinceLast = time() - strtotime($user['reset_last_request']);
            if ($timeSinceLast < 120) {
                $is_spammed = true;
            }
        }

        if ($is_spammed) {
            $message = "Une demande a déjà été faite récemment. Veuillez patienter 2 minutes.";
            $messageType = "error";
        } else {
            // Générer le token et la date d'expiration
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $now = date('Y-m-d H:i:s');

            $stmtUpdate = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ?, reset_last_request = ? WHERE id = ?");
            $stmtUpdate->execute([$token, $expires, $now, $user['id']]);

            $resetLink = "https://menu-qr-ebke.onrender.com/reset_password.php?token=" . $token;

            // 📧 EMAIL 1 : Envoi du lien
            $reset_sent = sendResetEmail($user['email'], $user['resto_nom'], $resetLink);
            
            // 📧 EMAIL 2 : Envoi de l'alerte de sécurité (on ne bloque pas si ça échoue)
            sendSecurityAlert($user['email'], $user['resto_nom']);

            if (!$reset_sent) {
                // Si l'API Brevo plante, on affiche une erreur technique
                $message = "Erreur technique lors de l'envoi de l'email. Veuillez réessayer.";
                $messageType = "error";
            }
        }
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
            <div class="error-msg" style="background: <?= $messageType === 'success' ? '#dcfce7' : '#fee2e2' ?>; color: <?= $messageType === 'success' ? '#166534' : '#991b1b' ?>; border-color: <?= $messageType === 'success' ? '#bbf7d0' : '#fecaca' ?>;">
                <?= htmlspecialchars($message) ?>
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