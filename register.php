<?php
session_start();
require 'config/db.php';
require 'actions/email_helper.php';

if (isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_resto = trim($_POST['nom_resto'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    // On génère le slug à partir du nom (ex: "Le Comptoir" -> "le-comptoir")
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nom_resto)));
    $slug = trim($slug, '-');

    if (empty($nom_resto) || empty($email) || empty($password)) {
        $error = "Tous les champs sont obligatoires.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        $pdo = getDB();
        
        // Vérifier si l'email ou le slug existe déjà
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? UNION SELECT id FROM restaurants WHERE slug = ?");
        $stmtCheck->execute([$email, $slug]);
        if ($stmtCheck->fetch()) {
            $error = "Cet email est déjà utilisé, ou un restaurant avec ce nom existe déjà.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // 1. Créer le restaurant (Essai 14 jours)
                $expires_at = date('Y-m-d H:i:s', strtotime('+14 days'));
                $stmtResto = $pdo->prepare("INSERT INTO restaurants (nom, slug, email, plan, expires_at, is_active, subscription_status) VALUES (?, ?, ?, 'trial', ?, true, 'active')");
                $stmtResto->execute([$nom_resto, $slug, $email, $expires_at]);
                $resto_id = $pdo->lastInsertId();

                // 2. Créer l'utilisateur (inactif jusqu'à la vérification)
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $token = bin2hex(random_bytes(32));
                
                $stmtUser = $pdo->prepare("INSERT INTO users (restaurant_id, email, password_hash, role, is_active, email_verified, email_token) VALUES (?, ?, ?, 'gerant', false, false, ?)");
                $stmtUser->execute([$resto_id, $email, $hash, $token]);

                // 3. Envoyer l'email de vérification
                $verifyLink = "https://menu-qr-ebke.onrender.com/verify.php?token=" . $token;
                if (sendVerificationEmail($email, $nom_resto, $verifyLink)) {
                    $pdo->commit();
                    $success = "Votre compte a été créé ! Vérifiez votre boîte mail pour l'activer.";
                } else {
                    $pdo->rollBack();
                    $error = "Erreur lors de l'envoi de l'email. Veuillez réessayer.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Une erreur est survenue lors de la création du compte.";
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
    <title>Créer un compte - FenneTech</title>
    <!-- FAVICON QR CODE -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-card" style="max-width: 450px;">
        <h1 style="font-size: 1.5rem; text-align: center; margin-bottom: 0.5rem;">Créer un compte</h1>
        <p style="text-align: center; font-size: 0.85rem; color: #6b7280; margin-bottom: 1.5rem;">14 jours d'essai gratuit. Sans engagement.</p>
        
        <?php if($error): ?>
            <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div style="background: #dcfce7; color: #166534; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; text-align: center; margin-bottom: 1rem; border: 1px solid #bbf7d0;">
                <?= $success ?>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>Nom du restaurant</label>
                    <input type="text" name="nom_resto" placeholder="Ex: Pizzeria Chez Paul" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="votre@email.com" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" placeholder="Min. 6 caractères" required>
                </div>
                <button type="submit" class="btn-submit">Démarrer mon essai gratuit</button>
            </form>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="login.php" style="color: var(--text-gray); font-size: 0.85rem; text-decoration: none;">Déjà un compte ? Se connecter</a>
        </div>
    </div>
</body>
</html>