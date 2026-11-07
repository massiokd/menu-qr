<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->execute([$resto_id]);
$resto = $stmt->fetch();

// Récupérer le dernier paiement en attente
$stmtP = $pdo->prepare("SELECT * FROM payments WHERE restaurant_id = ? ORDER BY created_at DESC LIMIT 1");
$stmtP->execute([$resto_id]);
$dernier_paiement = $stmtP->fetch();
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
        <div class="theme-selector-card">
            <h2>Statut de votre compte</h2>
            <div style="margin-top:1rem; background:rgba(0,0,0,0.03); padding:1.5rem; border-radius:1rem;">
                <p style="margin-bottom:0.5rem; font-size:1rem;">
                    <strong>Formule :</strong> 
                    <span style="text-transform:capitalize; color:var(--brand-color); font-weight:800; font-size:1.2rem;">
                        <?= htmlspecialchars($resto['plan']) ?>
                    </span>
                </p>
                <?php if($resto['plan'] !== 'lifetime'): ?>
                    <p style="font-size:1rem;">
                        <strong>Expire le :</strong> <?= date('d/m/Y', strtotime($resto['expires_at'])) ?>
                    </p>
                <?php else: ?>
                    <p style="font-size:1rem; color:var(--success);"><strong>Abonnement à vie</strong></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Renouvellement / Paiement -->
        <div class="theme-selector-card">
            <h2>Renouveler ou Changer de formule</h2>
            <p>Choisissez la formule qui vous convient. Le paiement se fait via <strong>Baridimob</strong>.</p>

            <?php if($dernier_paiement && $dernier_paiement['statut'] === 'en_attente'): ?>
                <div style="background:#fef3c7; color:#92400e; padding:12px; border-radius:8px; margin-bottom:1rem; font-size:0.9rem;">
                    ⏳ Votre demande de paiement pour <strong><?= htmlspecialchars($dernier_paiement['plan']) ?></strong> est en attente de validation. Nous vérifions votre reçu.
                </div>
            <?php endif; ?>

            <form action="../actions/request_payment.php" method="POST" style="display:flex; flex-direction:column; gap:1rem; margin-top:1rem;">
                <select name="plan" class="form-control" required>
                    <option value="monthly">Mensuel - 800 DZD</option>
                    <option value="yearly">Annuel - 6 900 DZD</option>
                    <option value="lifetime">À vie - 14 900 DZD</option>
                </select>
                
                <div style="background:#f3f4f6; padding:1rem; border-radius:0.5rem; text-align:center;">
                    <p style="font-size:0.85rem; color:var(--text-gray); margin-bottom:0.5rem;">Scannez ce QR Code avec votre application Baridimob</p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=VOTRE_LIEN_CCP_OU_QR" alt="QR Baridimob" style="width:150px; height:150px; border-radius:0.5rem; margin:0 auto;">
                    <p style="font-size:0.8rem; margin-top:0.5rem; font-weight:bold;">Ou envoyez au CCP : 00 123 456 78</p>
                </div>

                <div>
                    <label style="font-size:0.85rem; font-weight:700;">Référence de la transaction (Reçu)</label>
                    <input type="text" name="reference" class="form-control" placeholder="Ex: Numéro de transaction" required>
                </div>

                <button type="submit" class="btn-primary">J'ai payé, envoyer pour validation</button>
            </form>
        </div>
    </main>

</body>
</html>