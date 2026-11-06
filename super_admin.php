<?php
session_start();
require 'config/db.php';

// Sécurité : Seul le super admin peut passer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDB();

// Récupérer tous les restaurants
$stmt = $pdo->query("SELECT * FROM restaurants ORDER BY created_at DESC");
$restos = $stmt->fetchAll();

// Stats
$total_restos = count($restos);
$total_active = 0;
foreach ($restos as $r) {
    if ($r['is_active'] && ($r['plan'] === 'lifetime' || strtotime($r['expires_at']) > time())) {
        $total_active++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fennec Tech - Super Admin</title>
    <link rel="stylesheet" href="assets/css/super_admin.css">
</head>
<body>

    <header class="header">
        <h1>🦊 Fennec Tech Admin</h1>
        <a href="logout.php" style="color: var(--danger); font-weight: bold; text-decoration: none;">Déconnexion</a>
    </header>

    <main class="container">
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Clients</h3>
                <div class="value"><?= $total_restos ?></div>
            </div>
            <div class="stat-card">
                <h3>Actifs</h3>
                <div class="value" style="color: var(--success);"><?= $total_active ?></div>
            </div>
            <div class="stat-card">
                <h3>Inactifs/Expirés</h3>
                <div class="value" style="color: var(--danger);"><?= $total_restos - $total_active ?></div>
            </div>
        </div>

        <!-- Formulaire d'ajout de restaurant -->
        <div class="card">
            <h2 class="card-title">➕ Ajouter un nouveau restaurant</h2>
            <form action="actions/super_admin_actions.php" method="POST" class="form-row">
                <input type="hidden" name="action" value="add_resto">
                <input type="text" name="nom" placeholder="Nom du restaurant" required>
                <input type="text" name="slug" placeholder="Lien (ex: chez-paul)" required>
                <input type="email" name="email" placeholder="Email du gérant" required>
                <button type="submit" class="btn-primary">Créer le client</button>
            </form>
        </div>

        <!-- Liste des restaurants -->
        <h2 style="margin-top: 2rem; margin-bottom: 1rem;">Liste des Restaurants</h2>
        
        <?php foreach ($restos as $r): 
            $is_expired = $r['plan'] !== 'lifetime' && strtotime($r['expires_at'] ?? 'now') < time();
            $is_online = $r['is_active'] && !$is_expired;
        ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title"><?= htmlspecialchars($r['nom']) ?></div>
                        <div class="card-subtitle">Lien: /menu.php?slug=<?= htmlspecialchars($r['slug']) ?></div>
                    </div>
                    <div>
                        <?php if($is_online): ?>
                            <span class="badge badge-active">En ligne</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Hors ligne / Expiré</span>
                        <?php endif; ?>
                        <span class="badge badge-plan"><?= htmlspecialchars($r['plan']) ?></span>
                    </div>
                </div>

                <div style="font-size: 0.875rem; margin-bottom: 1rem;">
                    <strong>Expire le :</strong> <?= $r['plan'] === 'lifetime' ? 'À vie' : ($r['expires_at'] ? date('d/m/Y', strtotime($r['expires_at'])) : 'Non défini') ?>
                </div>

                <!-- Actions -->
                <form action="super_admin_actions.php" method="POST" class="form-row">
                    <input type="hidden" name="action" value="update_resto">
                    <input type="hidden" name="resto_id" value="<?= $r['id'] ?>">
                    
                    <select name="plan">
                        <option value="trial" <?= $r['plan']=='trial'?'selected':'' ?>>Essai (14j)</option>
                        <option value="monthly" <?= $r['plan']=='monthly'?'selected':'' ?>>Mensuel (800 DZD)</option>
                        <option value="yearly" <?= $r['plan']=='yearly'?'selected':'' ?>>Annuel (6900 DZD)</option>
                        <option value="lifetime" <?= $r['plan']=='lifetime'?'selected':'' ?>>À vie (14900 DZD)</option>
                    </select>
                    
                    <button type="submit" class="btn-primary">Mettre à jour</button>
                    
                    <!-- Bouton Suspendre / Réactiver -->
                    <button type="submit" name="toggle_active" value="1" class="btn-outline" style="color: <?= $r['is_active'] ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= $r['is_active'] ? 'Suspendre' : 'Réactiver' ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>

    </main>
</body>
</html>