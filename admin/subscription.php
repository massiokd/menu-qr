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

                    <div style="background:#f9fafb; padding:1.5rem; border-radius:0.5rem; border:1px solid #e5e7eb; text-align:center;">
                        <label style="font-weight:700; margin-bottom:1rem; display:block; color:var(--text-dark);">2. Effectuez le virement</label>
                        
                        <div style="background:white; padding:1rem; border-radius:0.5rem; border:1px dashed #d1d5db; margin-bottom:1rem; text-align:left;">
                            <p style="font-size:0.9rem; color:var(--text-dark); margin-bottom:1rem; text-align:center;">
                                Titulaire : <strong>Ouaked Massinissa</strong>
                            </p>
                            
                            <!-- CCP Classique -->
                            <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #f3f4f6;">
                                <p style="font-size:0.8rem; color:var(--text-gray); margin-bottom:0.5rem;">Compte CCP (pour virement bancaire) :</p>
                                <div style="background:#f9fafb; padding:0.75rem; border-radius:0.5rem; display:flex; flex-direction:column; gap:0.5rem;">
                                    <span style="font-size:1.1rem; font-weight:800; font-family:monospace; letter-spacing:1px; word-break: break-all;">00413557550 Clé 62</span>
                                    <button type="button" onclick="copyText('CCP : 00413557550 Clé 62', this)" style="background:var(--brand-color); color:white; border:none; padding:0.5rem; border-radius:0.3rem; cursor:pointer; font-size:0.8rem; font-weight:bold; width:100%;">Copier le numéro CCP</button>
                                </div>
                            </div>

                            <!-- RIP Baridimob -->
                            <div>
                                <p style="font-size:0.8rem; color:var(--text-gray); margin-bottom:0.5rem;">Numéro Baridimob (RIP) :</p>
                                <div style="background:#f9fafb; padding:0.75rem; border-radius:0.5rem; display:flex; flex-direction:column; gap:0.5rem;">
                                    <span style="font-size:1rem; font-weight:800; font-family:monospace; letter-spacing:1px; word-break: break-all;">00799999004135755062</span>
                                    <button type="button" onclick="copyText('00799999004135755062', this)" style="background:var(--brand-color); color:white; border:none; padding:0.5rem; border-radius:0.3rem; cursor:pointer; font-size:0.8rem; font-weight:bold; width:100%;">Copier le numéro RIP</button>
                                </div>
                            </div>
                        </div>

                        <a href="baridimob://" style="display:inline-flex; align-items:center; gap:0.5rem; background:#10b981; color:white; padding:0.6rem 1rem; border-radius:0.5rem; text-decoration:none; font-weight:600; font-size:0.9rem; margin-bottom:0.5rem; width:100%; justify-content:center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                            Ouvrir l'application Baridimob
                        </a>
                        
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

    <script>
        function copyText(textToCopy, btnElement) {
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = btnElement.innerText;
                btnElement.innerText = "✅ Copié !";
                btnElement.style.background = "#10b981"; 
                setTimeout(() => {
                    btnElement.innerText = originalText;
                    btnElement.style.background = "var(--brand-color)";
                }, 2000);
            }).catch(err => {
                alert("Erreur lors de la copie.");
            });
        }
    </script>

</body>
</html>