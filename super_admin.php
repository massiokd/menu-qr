<?php
session_name('FENNEC_ADMIN');
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

// Stats de base
$total_restos = count($restos);
$total_active = 0;
foreach ($restos as $r) {
    if ($r['is_active'] && ($r['plan'] === 'lifetime' || strtotime($r['expires_at']) > time())) {
        $total_active++;
    }
}

// Calculs des revenus
$stmtRev = $pdo->query("SELECT SUM(montant) as total FROM payments WHERE statut = 'valide'");
$total_rev = $stmtRev->fetch()['total'] ?? 0;

$stmtRevMonth = $pdo->query("SELECT SUM(montant) as total FROM payments WHERE statut = 'valide' AND DATE_TRUNC('month', created_at) = DATE_TRUNC('month', CURRENT_DATE)");
$rev_month = $stmtRevMonth->fetch()['total'] ?? 0;

$stmtCount = $pdo->query("SELECT COUNT(*) as total FROM restaurants WHERE is_active = true");
$active_clients = $stmtCount->fetch()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>FenneTech - Super Admin</title>
    <link rel="stylesheet" href="assets/css/super_admin.css">
</head>
<body>

    <header class="header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img src="assets/images/FenneTechLogo.png" alt="FenneTech" style="height: 50px; width: auto;">
            <span style="font-size: 1.5rem; font-weight: bold; border-left: 2px solid #e5e7eb; padding-left: 15px;">Admin</span>
        </div>
        <a href="logout.php" style="color: var(--danger); font-weight: bold; text-decoration: none;">Déconnexion</a>
    </header>

    <main class="container">

        <!-- STATISTIQUES FINANCIÈRES -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:1rem; margin-bottom:2rem;">
            <div class="card" style="text-align:center; padding:1.5rem;">
                <h3 style="font-size:0.85rem; color:var(--gray);">Revenu Total</h3>
                <p style="font-size:2rem; font-weight:800; color:var(--success);"><?= number_format($total_rev, 0, ',', ' ') ?> DZD</p>
            </div>
            <div class="card" style="text-align:center; padding:1.5rem;">
                <h3 style="font-size:0.85rem; color:var(--gray);">Revenu (Ce mois)</h3>
                <p style="font-size:2rem; font-weight:800; color:var(--brand-color);"><?= number_format($rev_month, 0, ',', ' ') ?> DZD</p>
            </div>
            <div class="card" style="text-align:center; padding:1.5rem;">
                <h3 style="font-size:0.85rem; color:var(--gray);">Clients Actifs</h3>
                <p style="font-size:2rem; font-weight:800; color:#3b82f6;"><?= $active_clients ?></p>
            </div>
        </div>
        
        <!-- STATISTIQUES GÉNÉRALES -->
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

        <!-- FORMULAIRE D'AJOUT DE RESTAURANT -->
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

        <!-- LISTE DES RESTAURANTS -->
        <h2 style="margin-top: 2rem; margin-bottom: 1rem;">Liste des Restaurants</h2>
        
        <?php foreach ($restos as $r): 
            // Calcul du statut
            $is_expired = $r['plan'] !== 'lifetime' && strtotime($r['expires_at'] ?? 'now') < time();
            $is_online = $r['is_active'] && !$is_expired;

            // Vérification du paiement en attente
            $stmtP = $pdo->prepare("SELECT * FROM payments WHERE restaurant_id = ? AND statut = 'en_attente' ORDER BY created_at DESC LIMIT 1");
            $stmtP->execute([$r['id']]);
            $pending = $stmtP->fetch();
        ?>
            <div class="card">
                
                <!-- ALERTE DE PAIEMENT EN ATTENTE -->
                <?php if($pending): ?>
                    <div style="background: #fef3c7; border: 1px solid #fcd34d; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                        <p style="font-weight: bold; color: #92400e; margin-bottom: 0.5rem;">⚠️ Demande de paiement en attente</p>
                        <p style="font-size: 0.85rem; color: #78350f;">Plan : <strong><?= htmlspecialchars($pending['plan']) ?></strong> (<?= $pending['montant'] ?> DZD)</p>
                        <p style="font-size: 0.85rem; color: #78350f;">Réf : <strong><?= htmlspecialchars($pending['reference']) ?></strong></p>
                        
                        <?php if(!empty($pending['receipt_url'])): ?>
                            <a href="<?= htmlspecialchars($pending['receipt_url']) ?>" target="_blank" style="display:inline-block; margin-bottom:0.5rem; color:#3b82f6; font-weight:bold; font-size:0.85rem;">📄 Voir le reçu</a>
                        <?php endif; ?>

                        <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                            <a href="actions/validate_payment.php?id=<?= $pending['id'] ?>" style="background: var(--success); color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: bold; font-size: 0.85rem;" onclick="return confirm('Valider ce paiement ?')">✅ Valider</a>
                            <button type="button" onclick="openRejectModal(<?= $pending['id'] ?>)" style="background: var(--danger); color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; border: none; text-decoration: none; font-weight: bold; font-size: 0.85rem; cursor: pointer;">❌ Refuser</button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- EN-TÊTE DU RESTAURANT -->
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
                        <span class="badge badge-plan">
    <?php 
        $plans_fr = ['trial' => 'Essai', 'monthly' => 'Mensuel', 'yearly' => 'Annuel', 'lifetime' => 'À vie'];
        echo $plans_fr[$r['plan']] ?? $r['plan']; 
    ?>
</span>
                    </div>
                </div>

                <!-- DATE D'EXPIRATION -->
                <div style="font-size: 0.875rem; margin-bottom: 1rem;">
                    <strong>Expire le :</strong> <?= $r['plan'] === 'lifetime' ? 'À vie' : ($r['expires_at'] ? date('d/m/Y', strtotime($r['expires_at'])) : 'Non défini') ?>
                </div>

                <!-- LOGIQUE : SI À VIE, ON AFFICHE LE BADGE VIP. SINON, LES FORMULAIRES. -->
                <?php if($r['plan'] === 'lifetime'): ?>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 1rem; border-radius: 0.5rem; display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:1rem;">
                        <p style="font-weight:bold; color:#166534; margin:0;">💎 Client Premium à vie (Aucun renouvellement requis)</p>
                        <form action="actions/super_admin_actions.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update_resto">
                            <input type="hidden" name="resto_id" value="<?= $r['id'] ?>">
                            <button type="submit" name="toggle_active" value="1" class="btn-outline" style="color: <?= $r['is_active'] ? 'var(--danger)' : 'var(--success)' ?>; border: 1px solid currentColor; padding: 0.4rem 0.8rem; border-radius: 0.3rem; background: transparent; cursor: pointer; font-weight:bold;">
                                <?= $r['is_active'] ? 'Suspendre' : 'Réactiver' ?>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    
                    <!-- ACTIONS STANDARD (Changer de plan / Suspendre) -->
                    <form action="actions/super_admin_actions.php" method="POST" class="form-row" style="margin-bottom: 1rem;">
                        <input type="hidden" name="action" value="update_resto">
                        <input type="hidden" name="resto_id" value="<?= $r['id'] ?>">
                        
                        <select name="plan">
                            <option value="trial" <?= $r['plan']=='trial'?'selected':'' ?>>Essai (14j)</option>
                            <option value="monthly" <?= $r['plan']=='monthly'?'selected':'' ?>>Mensuel (800 DZD)</option>
                            <option value="yearly" <?= $r['plan']=='yearly'?'selected':'' ?>>Annuel (6900 DZD)</option>
                            <option value="lifetime" <?= $r['plan']=='lifetime'?'selected':'' ?>>À vie (14900 DZD)</option>
                        </select>
                        
                        <button type="submit" class="btn-primary">Mettre à jour</button>
                        
                        <button type="submit" name="toggle_active" value="1" class="btn-outline" style="color: <?= $r['is_active'] ? 'var(--danger)' : 'var(--success)' ?>;">
                            <?= $r['is_active'] ? 'Suspendre' : 'Réactiver' ?>
                        </button>
                    </form>

                    <!-- PROLONGATION MANUELLE -->
                    <div style="background: #f9fafb; padding: 1rem; border-radius: 0.5rem; border: 1px dashed #d1d5db; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <p style="font-size: 0.85rem; font-weight: 700; margin: 0; color: var(--text-dark);">Ajouter du temps (Manuel) :</p>
                        <form action="actions/manual_extend.php" method="POST" style="display:flex; gap:0.5rem; align-items:center;">
                            <input type="hidden" name="resto_id" value="<?= $r['id'] ?>">
                            <select name="duration" style="padding: 0.4rem; border-radius: 0.3rem; border: 1px solid #ccc; font-size: 0.85rem;">
                                <option value="1_month">+1 Mois</option>
                                <option value="1_year">+1 An</option>
                            </select>
                            <button type="submit" style="background: var(--success); color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 0.3rem; font-size: 0.85rem; font-weight: bold; cursor: pointer;">Ajouter</button>
                        </form>
                    </div>
                    
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    </main>

    <!-- MODALE POUR REFUSER UN PAIEMENT -->
    <div id="reject-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 1rem; width: 90%; max-width: 400px; text-align: center;">
            <h2 style="font-size: 1.2rem; margin-bottom: 1rem; color: #991b1b;">Refuser le paiement</h2>
            <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 1rem;">Expliquez au gérant pourquoi le paiement est refusé.</p>
            
            <form action="actions/reject_payment.php" method="POST">
                <input type="hidden" name="payment_id" id="reject-payment-id">
                <textarea name="reason" required placeholder="Ex: La référence est introuvable sur mon compte Baridimob." style="width: 100%; height: 80px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; margin-bottom: 1rem; font-family: inherit;"></textarea>
                
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" onclick="document.getElementById('reject-modal').style.display='none'" style="flex: 1; padding: 0.75rem; border: 1px solid #d1d5db; background: white; border-radius: 0.5rem; cursor: pointer; font-weight: bold;">Annuler</button>
                    <button type="submit" style="flex: 1; padding: 0.75rem; border: none; background: var(--danger); color: white; border-radius: 0.5rem; cursor: pointer; font-weight: bold;">Confirmer le refus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(paymentId) {
            document.getElementById('reject-payment-id').value = paymentId;
            document.getElementById('reject-modal').style.display = 'flex';
        }
    </script>

</body>
</html>