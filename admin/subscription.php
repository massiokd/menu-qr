<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->execute([$resto_id]);
$resto = $stmt->fetch();

// Récupérer le dernier paiement en attente
$stmtP = $pdo->prepare("SELECT * FROM payments WHERE restaurant_id = ? AND statut = 'en_attente' ORDER BY created_at DESC LIMIT 1");
$stmtP->execute([$resto_id]);
$pending_payment = $stmtP->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnement - <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <header class="admin-header">
        <h1>Abonnement</h1>
        <a href="../logout.php" class="btn-logout">Déconnexion</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>">Gérer le Menu</a>
        <a href="stats.php" class="<?= basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : '' ?>">Statistiques</a>
        <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">Paramètres</a>
        <a href="subscription.php" class="<?= basename($_SERVER['PHP_SELF']) == 'subscription.php' ? 'active' : '' ?>">Abonnement</a>
    </div>

    <main class="container">
        
        <!-- Statut Actuel -->
        <div class="theme-selector-card" style="background: linear-gradient(135deg, var(--text-dark) 0%, #000 100%); color: white; border: none;">
            <h2 style="color:white; margin-bottom:1rem;">Statut de votre compte</h2>
            <p style="font-size:1.2rem; margin-bottom:0.5rem;">
                Formule actuelle : <strong style="color:var(--brand-color); text-transform:uppercase;"><?= htmlspecialchars($resto['plan']) ?></strong>
            </p>
            <?php if($resto['plan'] !== 'lifetime'): ?>
                <p style="opacity:0.8;">Expire le : <strong><?= date('d/m/Y', strtotime($resto['expires_at'])) ?></strong></p>
            <?php else: ?>
                <p style="color:var(--success); font-weight:bold;">Accès à vie 🎉</p>
            <?php endif; ?>
        </div>

        <!-- Formulaire de renouvellement -->
        <div class="theme-selector-card">
            <h2>Renouveler ou Changer de formule</h2>
            <p>Choisissez la formule qui vous convient. Le paiement se fait par virement <strong>Baridimob</strong> ou <strong>CCP</strong>.</p>

            <?php if($pending_payment): ?>
                <div style="background:#fef3c7; color:#92400e; padding:1rem; border-radius:0.5rem; margin-bottom:1.5rem; border:1px solid #fcd34d;">
                    <strong>⏳ Paiement en attente de validation</strong><br>
                    Votre paiement pour la formule <strong><?= htmlspecialchars($pending_payment['plan']) ?></strong> (Réf: <?= htmlspecialchars($pending_payment['reference']) ?>) est en cours de vérification. Votre compte sera activé dès réception.
                </div>
            <?php else: ?>
                <form action="../actions/request_payment.php" method="POST" style="display:flex; flex-direction:column; gap:1.5rem; margin-top:1rem;">
                    
                    <div>
                        <label style="font-weight:700; margin-bottom:0.5rem; display:block;">1. Choisissez votre formule</label>
                        <select name="plan" class="form-control" required>
                            <option value="monthly">Mensuel - 800 DZD</option>
                            <option value="yearly">Annuel - 6 900 DZD</option>
                            <option value="lifetime">À vie - 14 900 DZD</option>
                        </select>
                    </div>

                    <div style="background:#f3f4f6; padding:1.5rem; border-radius:0.5rem; text-align:center;">
                        <label style="font-weight:700; margin-bottom:0.5rem; display:block;">2. Effectuez le virement</label>
                        <p style="font-size:0.85rem; color:var(--text-gray); margin-bottom:1rem;">Scannez ce QR Code avec votre application Baridimob</p>
                        
                        <!-- Remplace ceci par ton vrai QR code ou numéro CCP -->
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=VOTRE_LIEN_CCP_OU_NUMERO" alt="QR Baridimob" style="width:150px; height:150px; border-radius:0.5rem; margin:0 auto; display:block;">
                        
                        <p style="font-size:0.9rem; margin-top:1rem; font-weight:bold;">Ou envoyez au CCP : 00 123 456 78</p>
                        <p style="font-size:0.8rem; color:var(--text-gray);">(Fennec Tech)</p>
                    </div>

                    <div>
                        <label style="font-weight:700; margin-bottom:0.5rem; display:block;">3. Saisissez la référence du reçu</label>
                        <input type="text" name="reference" class="form-control" placeholder="Ex: Numéro de transaction Baridimob" required>
                    </div>

                    <button type="submit" class="btn-primary" style="padding:1rem; font-size:1rem;">J'ai payé, envoyer pour validation</button>
                </form>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>