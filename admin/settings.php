<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];
$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->execute([$resto_id]);
$resto = $stmt->fetch();

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
        
        <!-- Sous-onglets (Sous-catégories) -->
        <div style="display:flex; gap:1rem; margin-bottom:2rem; border-bottom:1px solid #e5e7eb; padding-bottom:0.5rem; overflow-x:auto;">
            <a href="?tab=profil" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'profil' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'profil' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Profil</a>
            <a href="?tab=branding" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'branding' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'branding' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Apparence</a>
            <a href="?tab=securite" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'securite' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'securite' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Sécurité</a>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem; text-align:center; font-weight:bold;">Modifications enregistrées !</div>
        <?php endif; ?>

        <!-- CONTENU DES SOUS-ONGLETS -->
        
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

        <?php if($tab === 'branding'): ?>
            <!-- Logo -->
            <div class="theme-selector-card" style="display:flex; gap:1.5rem; align-items:center;">
                <div style="flex:1;">
                    <h2>Logo</h2>
                    <form action="../actions/update_logo.php" method="POST" enctype="multipart/form-data">
                        <label class="btn-upload" style="display:inline-flex; width:auto;">Changer le logo
                            <input type="file" name="logo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
                <?php if($resto['logo_url']): ?>
                    <img src="<?= htmlspecialchars($resto['logo_url']) ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover;">
                <?php endif; ?>
            </div>

            <!-- Thèmes de couleurs -->
            <div class="theme-selector-card">
                <h2>Thème de couleurs</h2>
                <div class="theme-grid">
                    <?php foreach ($themes as $key => $t): $isActive = ($resto['theme'] ?? 'classique') === $key; ?>
                        <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectTheme('<?= $key ?>', this)">
                            <div class="color-circle" style="background: <?= $t['couleur'] ?>;"></div>
                            <span><?= $t['nom'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Police d'écriture -->
            <div class="theme-selector-card">
                <h2>Style d'écriture</h2>
                <div class="theme-grid">
                    <?php foreach ($fonts as $key => $nom): $isActive = ($resto['font_family'] ?? 'Plus Jakarta Sans') === $key; ?>
                        <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectFont('<?= $key ?>', this)" style="font-family: '<?= $key ?>', sans-serif;">
                            <span style="font-size:1rem;">Aa</span>
                            <span><?= $nom ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if($tab === 'securite'): ?>
            <div class="theme-selector-card">
                <h2>Sécurité</h2>
                <p>Modifiez votre mot de passe.</p>
                <form action="../actions/update_password.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                    <input type="password" name="new_password" placeholder="Nouveau mot de passe" required class="form-control">
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

        async function selectFont(font, element) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            const formData = new FormData(); formData.append('font_family', font);
            // On réutilise update_theme.php ou on crée un update_font.php
            await fetch('../actions/update_font.php', { method: 'POST', body: formData });
            showToast('Police mise à jour !');
        }
    </script>
</body>
</html>