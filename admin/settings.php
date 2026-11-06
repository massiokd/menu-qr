<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') {
    header('Location: ../login.php'); exit;
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmtResto = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

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
    </div>

    <main class="container">

        <?php if(isset($_GET['success'])): ?>
            <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem; text-align:center; font-weight:bold;">
                Modifications enregistrées avec succès !
            </div>
        <?php endif; ?>
        
        <!-- 1. PROFIL DU RESTAURANT -->
        <div class="theme-selector-card">
            <h2>Profil du Restaurant</h2>
            <p>Ces informations apparaîtront sur votre menu.</p>
            
            <form action="../actions/update_profile.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                <div>
                    <label style="font-size:0.85rem; font-weight:700; margin-bottom:0.3rem; display:block;">Nom du restaurant</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($resto['nom']) ?>" class="form-control" required>
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:700; margin-bottom:0.3rem; display:block;">Slogan</label>
                    <input type="text" name="slogan" value="<?= htmlspecialchars($resto['slogan'] ?? 'Découvrez notre carte') ?>" class="form-control" required>
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:700; margin-bottom:0.3rem; display:block;">Numéro de téléphone (Privé)</label>
                    <input type="text" name="telephone" value="<?= htmlspecialchars($resto['telephone'] ?? '') ?>" class="form-control" placeholder="Ex: 0550 12 34 56">
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:700; margin-bottom:0.3rem; display:block;">Lien Instagram</label>
                    <input type="url" name="instagram_url" value="<?= htmlspecialchars($resto['instagram_url'] ?? '') ?>" class="form-control" placeholder="https://instagram.com/votre-resto">
                </div>
                <div>
                    <label style="font-size:0.85rem; font-weight:700; margin-bottom:0.3rem; display:block;">Lien Facebook</label>
                    <input type="url" name="facebook_url" value="<?= htmlspecialchars($resto['facebook_url'] ?? '') ?>" class="form-control" placeholder="https://facebook.com/votre-resto">
                </div>
                <button type="submit" class="btn-primary" style="margin-top:0.5rem;">Enregistrer le profil</button>
            </form>
        </div>

        <!-- 2. LOGO -->
        <div class="theme-selector-card" style="display:flex; gap:1.5rem; align-items:center;">
            <div style="flex:1;">
                <h2>Logo du Restaurant</h2>
                <p>Ce logo apparaîtra en haut de votre menu.</p>
                <form action="../actions/update_logo.php" method="POST" enctype="multipart/form-data">
                    <label class="btn-upload" style="display:inline-flex; width:auto; padding:0.6rem 1rem;">
                        Changer le logo
                        <input type="file" name="logo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    </label>
                </form>
            </div>
            <?php if($resto['logo_url']): ?>
                <img src="<?= htmlspecialchars($resto['logo_url']) ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid white; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
            <?php endif; ?>
        </div>

        <!-- 3. THEMES -->
        <div class="theme-selector-card">
            <h2>Thème de votre Menu</h2>
            <p>Choisissez l'ambiance visuelle de votre carte.</p>
            
            <div class="theme-grid">
                <?php foreach ($themes as $key => $t): 
                    $isActive = ($resto['theme'] ?? 'classique') === $key;
                ?>
                    <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectTheme('<?= $key ?>', this)">
                        <div class="color-circle" style="background: <?= $t['couleur'] ?>;"></div>
                        <span><?= $t['nom'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 4. ABONNEMENT -->
        <div class="theme-selector-card">
            <h2>Mon Abonnement</h2>
            <div style="margin-top:1rem; background:rgba(0,0,0,0.03); padding:1rem; border-radius:0.5rem;">
                <p style="margin-bottom:0.5rem; font-size:0.9rem;">
                    <strong>Statut :</strong> 
                    <span style="text-transform:capitalize; color:var(--brand-color); font-weight:bold;"><?= htmlspecialchars($resto['plan']) ?></span>
                </p>
                <?php if($resto['plan'] !== 'lifetime'): ?>
                    <p style="font-size:0.9rem;">
                        <strong>Expire le :</strong> <?= date('d/m/Y', strtotime($resto['expires_at'])) ?>
                    </p>
                <?php else: ?>
                    <p style="font-size:0.9rem; color:var(--success);"><strong>Abonnement à vie</strong></p>
                <?php endif; ?>
            </div>
            
            <a href="https://wa.me/213559322518?text=Bonjour,%20je%20souhaite%20renouveler%20mon%20abonnement%20pour%20le%20restaurant%20<?= urlencode($resto['nom']) ?>" target="_blank" style="display:flex; align-items:center; justify-content:center; gap:0.5rem; width:100%; padding:0.75rem; background:#25D366; color:white; text-decoration:none; border-radius:0.5rem; font-weight:bold; margin-top:1rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                Contacter Fennec Tech
            </a>
        </div>

        <!-- 5. SECURITE -->
        <div class="theme-selector-card">
            <h2>Sécurité</h2>
            <p>Modifiez votre mot de passe.</p>
            <form action="../actions/update_password.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                <input type="password" name="new_password" placeholder="Nouveau mot de passe (min. 6 car.)" required class="form-control">
                <button type="submit" class="btn-primary" style="align-self:flex-start;">Mettre à jour</button>
            </form>
        </div>

    </main>

    <div id="toast" class="toast">Thème mis à jour !</div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }

        async function selectTheme(theme, element) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');

            const formData = new FormData();
            formData.append('theme', theme);

            try {
                await fetch('../actions/update_theme.php', { method: 'POST', body: formData });
                showToast('Thème mis à jour !');
            } catch (e) {
                showToast('Erreur.');
            }
        }
    </script>
</body>
</html>