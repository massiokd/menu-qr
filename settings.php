<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmtResto = $pdo->prepare("SELECT nom, slug, theme FROM restaurants WHERE id = ?");
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
    <link rel="stylesheet" href="admin.css">
</head>
<body>

    <!-- Navigation Admin -->
    <header class="admin-header">
        <h1>Paramètres</h1>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php">Gérer le Menu</a>
        <a href="settings.php" class="active">Paramètres</a>
       
    </div>

    <main class="container">
        <div class="theme-selector-card">
            <h2>🎨 Thème de votre Menu</h2>
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
                await fetch('update_theme.php', { method: 'POST', body: formData });
                showToast('Thème mis à jour avec succès !');
            } catch (e) {
                showToast('Erreur lors de la sauvegarde.');
            }
        }
    </script>
</body>
</html>