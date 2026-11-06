<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmtResto = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

// URL du menu pour le QR Code
$base_url = "https://menu-qr-ebke.onrender.com"; 
$menu_url = $base_url . "/menu.php?slug=" . $resto['slug'];

// Gestion des sous-onglets
$tab = $_GET['tab'] ?? 'profil';

$themes = [
    'classique' => ['nom' => 'Classique', 'couleur' => '#c2410c'],
    'minuit' => ['nom' => 'Minuit', 'couleur' => '#fbbf24'],
    'ocean' => ['nom' => 'Océan', 'couleur' => '#0284c7'],
    'jardin' => ['nom' => 'Jardin', 'couleur' => '#059669'],
    'tokyo' => ['nom' => 'Néo-Tokyo', 'couleur' => '#ef4444'],
    'rustique' => ['nom' => 'Rustique', 'couleur' => '#92400e'],
    'rose' => ['nom' => 'Rose Poudré', 'couleur' => '#db2777'],
    'fastfood' => ['nom' => 'Fast Food', 'couleur' => '#dc2626'],
    'minimaliste' => ['nom' => 'Minimaliste', 'couleur' => '#171717'],
    'royal' => ['nom' => 'Royal', 'couleur' => '#7e22ce'],
    'toscane' => ['nom' => 'Toscane', 'couleur' => '#b45309'],
    'hiver' => ['nom' => 'Hiver', 'couleur' => '#3b82f6']
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
        
        <!-- Sous-onglets (Sous-catégories) -->
        <div style="display:flex; gap:1rem; margin-bottom:2rem; border-bottom:1px solid #e5e7eb; padding-bottom:0.5rem; overflow-x:auto;">
            <a href="?tab=profil" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'profil' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'profil' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Profil</a>
            <a href="?tab=branding" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'branding' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'branding' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Apparence</a>
            <a href="?tab=qrcode" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'qrcode' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'qrcode' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">QR Code</a>
            <a href="?tab=securite" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'securite' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'securite' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Sécurité</a>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem; text-align:center; font-weight:bold;">Modifications enregistrées !</div>
        <?php endif; ?>

        <!-- CONTENU DES SOUS-ONGLETS -->
        
        <!-- ONGLET PROFIL -->
        <?php if($tab === 'profil'): ?>
            <div class="theme-selector-card">
                <h2>Profil du Restaurant</h2>
                <p>Ces informations apparaîtront sur votre menu.</p>
                <form action="../actions/update_profile.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Nom du restaurant</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($resto['nom']) ?>" class="form-control" required>
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Slogan</label>
                        <input type="text" name="slogan" value="<?= htmlspecialchars($resto['slogan'] ?? 'Découvrez notre carte') ?>" class="form-control" required>
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Téléphone (Privé)</label>
                        <input type="text" name="telephone" value="<?= htmlspecialchars($resto['telephone'] ?? '') ?>" class="form-control">
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Instagram</label>
                        <input type="url" name="instagram_url" value="<?= htmlspecialchars($resto['instagram_url'] ?? '') ?>" class="form-control">
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Facebook</label>
                        <input type="url" name="facebook_url" value="<?= htmlspecialchars($resto['facebook_url'] ?? '') ?>" class="form-control">
                    </div>
                    <button type="submit" class="btn-primary">Enregistrer</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ONGLET APPARENCE (Branding) -->
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
        <?php endif; ?>

        <!-- ONGLET QR CODE -->
        <?php if($tab === 'qrcode'): ?>
            <div class="theme-selector-card" style="text-align:center;">
                <h2>Votre QR Code</h2>
                <p>Imprimez ce code et collez-le sur vos tables. Vos clients le scanneront pour voir votre menu.</p>
                
                <div style="margin: 2rem 0;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($menu_url) ?>" alt="QR Code" style="border-radius:1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin:0 auto;">
                </div>

                <div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
                    <a href="<?= htmlspecialchars($menu_url) ?>" target="_blank" class="btn-primary" style="text-decoration:none; background:#f3f4f6; color:var(--text-dark);">
                        Voir le menu
                    </a>
                    
                    <!-- Bouton de téléchargement HD -->
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=1000x1000&download=1&data=<?= urlencode($menu_url) ?>" target="_blank" class="btn-primary" style="text-decoration:none; display:flex; align-items:center; gap:0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Télécharger en HD
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ONGLET SÉCURITÉ -->
        <?php if($tab === 'securite'): ?>
            <div class="theme-selector-card">
                <h2>Sécurité</h2>
                <p>Modifiez votre mot de passe.</p>
                <form action="../actions/update_password.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                    <input type="password" name="new_password" placeholder="Nouveau mot de passe (min. 6 car.)" required class="form-control">
                    <button type="submit" class="btn-primary" style="align-self:flex-start;">Mettre à jour</button>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <div id="toast" class="toast">Mis à jour !</div>

    <script>
        function showToast(message) { const toast = document.getElementById('toast'); toast.textContent = message; toast.classList.add('show'); setTimeout(() => { toast.classList.remove('show'); }, 3000); }
        
        async function selectTheme(theme, element) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            const formData = new FormData(); formData.append('theme', theme);
            await fetch('../actions/update_theme.php', { method: 'POST', body: formData });
            showToast('Thème mis à jour !');
        }
    </script>
</body>
</html>