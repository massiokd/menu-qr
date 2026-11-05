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

$base_url = "https://menu-qr-ebke.onrender.com"; 
$menu_url = $base_url . "/menu.php?slug=" . $resto['slug'];

$stmtCat = $pdo->prepare("SELECT * FROM categories WHERE restaurant_id = ? ORDER BY ordre ASC");
$stmtCat->execute([$resto_id]);
$categories = $stmtCat->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>

    <header class="admin-header">
        <h1>Gérer le Menu</h1>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php" class="active">Gérer le Menu</a>
        <a href="settings.php">Paramètres</a>
    </div>

    <main class="container">
        <!-- QR Code -->
        <div class="qr-card">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($menu_url) ?>" alt="QR Code">
            <div class="qr-info">
                <h2>Votre QR Code</h2>
                <p>Imprimez-le et placez-le sur vos tables.</p>
                <a href="<?= htmlspecialchars($menu_url) ?>" target="_blank">Voir le menu ↗</a>
            </div>
        </div>

        <!-- Plats -->
        <?php foreach ($categories as $cat): ?>
            <div class="category-block">
                <h2 class="category-header"><?= htmlspecialchars($cat['nom']) ?></h2>
                
                <?php
                $stmtItems = $pdo->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY ordre ASC");
                $stmtItems->execute([$cat['id']]);
                $items = $stmtItems->fetchAll();
                ?>

                <?php foreach ($items as $item): ?>
                    <div class="item-row">
                        <div class="item-row-header">
                            <span class="item-row-name"><?= htmlspecialchars($item['nom']) ?></span>
                        </div>
                        <div class="item-row-controls">
                            <div class="price-input-group">
                                <input type="number" id="prix-<?= $item['id'] ?>" value="<?= $item['prix'] ?>" step="50">
                                <span>DZD</span>
                                <button onclick="updatePrix(<?= $item['id'] ?>)" class="btn-save-price">OK</button>
                            </div>
                            <button onclick="toggleDispo(<?= $item['id'] ?>, this)" 
                                class="btn-status <?= $item['disponible'] ? 'available' : 'unavailable' ?>">
                                <?= $item['disponible'] ? 'Disponible' : 'Épuisé' ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <details class="add-item-details">
                    <summary>+ Ajouter un plat dans "<?= htmlspecialchars($cat['nom']) ?>"</summary>
                    <form action="add_item.php" method="POST" class="add-form">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                        <input type="text" name="nom" placeholder="Nom du plat" required>
                        <input type="text" name="description" placeholder="Description (optionnel)">
                        <input type="number" name="prix" placeholder="Prix en DZD" step="50" required>
                        <button type="submit">Ajouter au menu</button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </main>

    <div id="toast" class="toast">Prix mis à jour !</div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }
        async function updatePrix(itemId) {
            const newPrix = document.getElementById('prix-' + itemId).value;
            const formData = new FormData();
            formData.append('action', 'update_prix');
            formData.append('item_id', itemId);
            formData.append('prix', newPrix);
            try {
                const response = await fetch('update_item.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) showToast('Prix mis à jour !');
            } catch (error) { showToast('Erreur.'); }
        }
        async function toggleDispo(itemId, btn) {
            const formData = new FormData();
            formData.append('action', 'toggle_dispo');
            formData.append('item_id', itemId);
            try {
                const response = await fetch('update_item.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) {
                    if(result.new_status) {
                        btn.textContent = 'Disponible';
                        btn.classList.replace('unavailable', 'available');
                        showToast('Plat disponible.');
                    } else {
                        btn.textContent = 'Épuisé';
                        btn.classList.replace('available', 'unavailable');
                        showToast('Plat épuisé.');
                    }
                }
            } catch (error) { showToast('Erreur.'); }
        }
    </script>
</body>
</html>