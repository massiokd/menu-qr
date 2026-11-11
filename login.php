<?php
session_start(); // Session temporaire
require 'config/db.php';

// Si déjà connecté, on redirige
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'super_admin') { header('Location: super_admin.php'); }
    else { header('Location: admin/admin.php'); }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = true");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
            $error = "Compte temporairement bloqué suite à plusieurs échecs. Réessayez plus tard.";
        } 
        elseif (password_verify($password, $user['password_hash'])) {
            $stmtReset = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = ?");
            $stmtReset->execute([$user['id']]);

            // --- SÉPARATION DES SESSIONS ---
            session_destroy(); // On ferme la session temporaire
            
            if ($user['role'] === 'super_admin') {
                session_name('FENNEC_ADMIN');
            } else {
                session_name('FENNEC_GERANT');
            }
            session_start(); // On ouvre la bonne session

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['restaurant_id'] = $user['restaurant_id'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'super_admin') { header('Location: super_admin.php'); }
            else { header('Location: admin/admin.php'); }
            exit;
        } 
        else {
            $attempts = $user['failed_attempts'] + 1;
            if ($attempts >= 5) {
                $lockout = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $stmtLock = $pdo->prepare("UPDATE users SET failed_attempts = ?, lockout_until = ? WHERE id = ?");
                $stmtLock->execute([$attempts, $lockout, $user['id']]);
                $error = "Trop de tentatives échouées. Compte bloqué pendant 15 minutes.";
            } else {
                $stmtFail = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                $stmtFail->execute([$attempts, $user['id']]);
                $error = "Email ou mot de passe incorrect. (Tentative $attempts/5)";
            }
        }
    } else {
        $error = "Email ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connexion - FenneTech</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-card">
        <h1>Espace Gérant</h1>
        
        <?php if($error): ?>
            <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="votre@email.com" required autocomplete="off">
            </div>
                       <div class="form-group">
                <label>Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="login-pass" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('login-pass', this)">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>
            <div style="text-align: center; margin-top: 1rem;">
    <a href="forgot_password.php" style="color: var(--text-gray); font-size: 0.85rem; text-decoration: none;">Mot de passe oublié ?</a>
</div>
            <button type="submit" class="btn-submit">Se connecter</button>
        </form>
    </div>

    <script>
        function togglePassword(inputId, iconElement) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                iconElement.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                input.type = "password";
                iconElement.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        }
    </script>

</body>
</html>