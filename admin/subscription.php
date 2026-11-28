<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->execute([$resto_id]);
$resto = $stmt->fetch();

$stmtP = $pdo->prepare("SELECT * FROM payments WHERE restaurant_id = ? ORDER BY created_at DESC LIMIT 1");
$stmtP->execute([$resto_id]);
$last_payment = $stmtP->fetch();

$pending_payment = ($last_payment && $last_payment['statut'] === 'en_attente') ? $last_payment : null;

$plans_fr = [
    'trial'    => 'Essai gratuit',
    'monthly'  => 'Mensuel',
    '3_months' => '3 Mois',
    'yearly'   => 'Annuel',
    'lifetime' => 'À vie'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Abonnement — <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/1apple-touch-icon.png">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js" data-turbo-track="reload"></script>
</head>
<body data-turbo="true">

    <header class="admin-header">
        <div class="header-brand">
            <span class="header-eyebrow"><?= htmlspecialchars($resto['nom']) ?></span>
            <h1>Abonnement</h1>
        </div>
        <div class="header-actions">
            <a href="../logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </header>

    <nav class="admin-tabs" aria-label="Navigation principale">
        <a href="admin.php">Gérer le Menu</a>
        <a href="stats.php">Statistiques</a>
        <a href="settings.php">Paramètres</a>
        <a href="subscription.php" class="active">Abonnement</a>
    </nav>

    <main class="container">

        <div class="plan-hero-card">
            <p class="plan-hero-eyebrow">Formule actuelle</p>
            <p class="plan-hero-title"><?= htmlspecialchars($plans_fr[$resto['plan']] ?? $resto['plan']) ?></p>
            <?php if ($resto['plan'] !== 'lifetime'): ?>
                <p class="plan-hero-meta">Expire le <strong><?= date('d/m/Y', strtotime($resto['expires_at'])) ?></strong></p>
            <?php else: ?>
                <p class="plan-hero-meta" style="color:#34d399; font-weight:700;">Accès illimité actif</p>
            <?php endif; ?>
        </div>

        <?php if ($resto['plan'] === 'lifetime'): ?>
            <div class="app-card" style="text-align:center;">
                <div class="app-card-header" style="margin-bottom:0;">
                    <h2>Compte à vie actif</h2>
                    <p>Vous disposez de toutes les fonctionnalités FenneTech sans renouvellement requis.</p>
                </div>
            </div>
        <?php else: ?>

            <?php if ($last_payment && $last_payment['statut'] === 'refuse' && !$pending_payment): ?>
                <div class="feedback-banner feedback-danger">
                    Paiement refusé : <?= htmlspecialchars($last_payment['admin_note'] ?? 'Aucune raison précisée.') ?>
                </div>
            <?php endif; ?>

            <div class="app-card">
                <div class="app-card-header">
                    <h2>Renouveler mon abonnement</h2>
                    <p>Sélectionnez une durée, effectuez le virement puis envoyez votre justificatif.</p>
                </div>

                <?php if ($pending_payment): ?>
                    <div class="feedback-banner feedback-warning" style="margin-bottom:0;">
                        Vérification en cours pour la formule <strong><?= htmlspecialchars($pending_payment['plan']) ?></strong>. Votre menu reste actif.
                    </div>
                <?php else: ?>
                    <form action="../actions/request_payment.php" method="POST" enctype="multipart/form-data" class="stack-form">
                        <div class="field-group">
                            <label class="field-label">1. Durée de renouvellement</label>
                            <select name="plan" class="form-control" required>
                                <option value="monthly">Mensuel — 900 DZD</option>
                                <option value="3_months">3 Mois — 2 400 DZD</option>
                                <option value="yearly">Annuel — 8 400 DZD</option>
                            </select>
                        </div>

                        <div class="payment-box">
                            <p class="field-label">2. Coordonnées de virement (Ouaked Massinissa)</p>

                            <div class="payment-account-card">
                                <span class="payment-account-label">Compte CCP (Algérie Poste)</span>
                                <span class="payment-account-value">00413557550 — Clé 62</span>
                                <button type="button" class="btn-copy-full" onclick="copyText('00413557550 Clé 62', this)">Copier le CCP</button>
                            </div>

                            <div class="payment-account-card">
                                <span class="payment-account-label">Baridimob (RIP)</span>
                                <span class="payment-account-value">00799999004135755062</span>
                                <button type="button" class="btn-copy-full" onclick="copyText('00799999004135755062', this)">Copier le RIP</button>
                            </div>

                            <a href="intent://#Intent;package=ru.bpc.mobilebank.bpc;S.browser_fallback_url=https%3A%2F%2Fplay.google.com%2Fstore%2Fapps%2Fdetails%3Fid%3Dru.bpc.mobilebank.bpc;end" class="btn-baridimob">
                                Ouvrir Baridimob
                            </a>
                        </div>

                        <div class="field-group">
                            <label class="field-label">3. Preuve de paiement</label>
                            <div class="feedback-banner feedback-info" style="margin-bottom:0.4rem; font-weight:500;">
                                <strong>Baridimob :</strong> capture de l'email de confirmation.<br>
                                <strong>Guichet CCP :</strong> photo du reçu cacheté.
                            </div>
                            <div class="upload-dropzone" onclick="document.getElementById('receipt-upload').click()">
                                <p style="font-weight:700; margin-bottom:0.2rem;" id="file-name-display">Ajouter une capture ou photo</p>
                                <p style="font-size:0.78rem; color:var(--text-gray);">Appuyez ici pour sélectionner l'image</p>
                                <input type="file" id="receipt-upload" name="receipt" accept="image/*" required style="display:none;" onchange="if(this.files[0]) document.getElementById('file-name-display').innerText = this.files[0].name;">
                            </div>
                        </div>

                        <div class="field-group">
                            <label class="field-label">4. Référence (optionnel)</label>
                            <input type="text" name="reference" class="form-control" placeholder="Numéro de transaction ou versement">
                        </div>

                        <button type="submit" class="btn-primary">Envoyer pour validation</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function copyText(textToCopy, btnElement) {
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = btnElement.innerText;
                btnElement.innerText = "Copié";
                btnElement.style.background = "#10b981";
                setTimeout(() => {
                    btnElement.innerText = originalText;
                    btnElement.style.background = "var(--brand-color)";
                }, 1800);
            }).catch(() => alert("Erreur lors de la copie."));
        }
    </script>
</body>
</html>