<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmtResto = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

$stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user_data = $stmtUser->fetch();
$user_email = $user_data['email'];

$tab = $_GET['tab'] ?? 'profil';

$themes = [
    'classique' => ['nom' => 'Classique', 'couleur' => '#c2410c'], 'minuit' => ['nom' => 'Minuit', 'couleur' => '#fbbf24'],
    'ocean' => ['nom' => 'Océan', 'couleur' => '#0284c7'], 'jardin' => ['nom' => 'Jardin', 'couleur' => '#059669'],
    'tokyo' => ['nom' => 'Néo-Tokyo', 'couleur' => '#ef4444'], 'rustique' => ['nom' => 'Rustique', 'couleur' => '#92400e'],
    'rose' => ['nom' => 'Rose Poudré', 'couleur' => '#db2777'], 'fastfood' => ['nom' => 'Fast Food', 'couleur' => '#dc2626'],
    'minimaliste' => ['nom' => 'Minimaliste', 'couleur' => '#171717'], 'royal' => ['nom' => 'Royal', 'couleur' => '#7e22ce'],
    'toscane' => ['nom' => 'Toscane', 'couleur' => '#b45309'], 'hiver' => ['nom' => 'Hiver', 'couleur' => '#3b82f6']
];

$fonts = [
    'Plus Jakarta Sans' => 'Moderne (Par défaut)',
    'Poppins' => 'Arrondi & Amical',
    'Montserrat' => 'Élégant & Classique',
    'Lora' => 'Traditionnel & Serif'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <header class="admin-header">
        <h1>Paramètres</h1>
        <a href="../logout.php" class="btn-logout">Déconnexion</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php">Gérer le Menu</a>
        <a href="settings.php" class="active">Paramètres</a>
        <a href="subscription.php">Abonnement</a>
    </div>

    <main class="container">
        
        <div style="display:flex; gap:1rem; margin-bottom:2rem; border-bottom:1px solid #e5e7eb; padding-bottom:0.5rem; overflow-x:auto;">
            <a href="?tab=profil" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'profil' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'profil' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Profil</a>
            <a href="?tab=branding" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'branding' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'branding' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Apparence</a>
            <a href="?tab=qrcode" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'qrcode' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'qrcode' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">QR Code</a>
            <a href="?tab=securite" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'securite' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'securite' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Sécurité</a>
        </div>

        <!-- ONGLET APPARENCE -->
        <?php if($tab === 'branding'): ?>
            <!-- Logo -->
            <div class="theme-selector-card" style="display:flex; gap:1.5rem; align-items:center;">
                <div style="flex:1;">
                    <h2>Logo</h2>
                    <p>Ce logo apparaîtra en haut de votre menu.</p>
                    <form action="../actions/update_logo.php" method="POST" enctype="multipart/form-data">
                        <label class="btn-upload" style="display:inline-flex; width:auto;">Changer le logo
                            <input type="file" name="logo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
                <?php if($resto['logo_url']): ?>
                    <img src="<?= htmlspecialchars($resto['logo_url']) ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid white; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                <?php endif; ?>
            </div>

            <!-- Thèmes de couleurs -->
            <div class="theme-selector-card">
                <h2>Thème de couleurs</h2>
                <p>Choisissez l'ambiance visuelle de votre carte.</p>
                <div class="theme-grid">
                    <?php foreach ($themes as $key => $t): $isActive = ($resto['theme'] ?? 'classique') === $key; ?>
                        <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectTheme('<?= $key ?>', this)">
                            <div class="color-circle" style="background: <?= $t['couleur'] ?>;"></div>
                            <span><?= $t['nom'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- POLICE D'ÉCRITURE (RÉINTÉGRÉ) -->
            <div class="theme-selector-card">
                <h2>Style d'écriture</h2>
                <p>Choisissez la police qui correspond à votre style.</p>
                <div class="theme-grid">
                    <?php foreach ($fonts as $key => $nom): $isActive = ($resto['font_family'] ?? 'Plus Jakarta Sans') === $key; ?>
                        <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectFont('<?= $key ?>', this)" style="font-family: '<?= $key ?>', sans-serif;">
                            <span style="font-size:1.2rem; font-weight:bold;">Aa</span>
                            <span><?= $nom ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ONGLET SÉCURITÉ -->
        <?php if($tab === 'securite'): ?>
            <!-- Formulaire Email -->
            <div class="theme-selector-card">
                <h2>Changer d'adresse email</h2>
                <form action="../actions/update_email.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                    <input type="email" name="new_email" placeholder="Nouvel email" required class="form-control" value="<?= htmlspecialchars($user_email) ?>">
                    <div class="password-wrapper">
                        <input type="password" name="current_password" id="pass-email" placeholder="Mot de passe actuel (pour confirmer)" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-email', this)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <button type="submit" class="btn-primary" style="align-self:flex-start;">Mettre à jour l'email</button>
                </form>
            </div>

            <!-- Formulaire Mot de passe -->
            <div class="theme-selector-card">
                <h2>Changer de mot de passe</h2>
                <form action="../actions/update_password.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                    <div class="password-wrapper">
                        <input type="password" name="current_password" id="pass-actuel" placeholder="Mot de passe actuel" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-actuel', this)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="pass-new" placeholder="Nouveau mot de passe" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-new', this)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="pass-conf" placeholder="Confirmer le nouveau mot de passe" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-conf', this)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <button type="submit" class="btn-primary" style="align-self:flex-start;">Mettre à jour le mot de passe</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ... (Le code du profil et du QR code reste inchangé) ... -->
        
    </main>

    <script>
        function togglePassword(inputId, iconElement) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                // Icône Œil barré
                iconElement.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                input.type = "password";
                // Icône Œil normal
                iconElement.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        }

        // ... (Le code AJAX pour changer de thème et de police reste inchangé) ...
        async function selectTheme(theme, element) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            const formData = new FormData(); formData.append('theme', theme);
            await fetch('../actions/update_theme.php', { method: 'POST', body: formData });
            showToast('Thème mis à jour !');
        }
        async function selectFont(font, element) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            const formData = new FormData(); formData.append('font_family', font);
            await fetch('../actions/update_font.php', { method: 'POST', body: formData });
            showToast('Police mise à jour !');
        }
    </script>
</body>
</html>