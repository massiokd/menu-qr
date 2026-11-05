<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: login.php'); exit; }

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
        
        <!-- Ajout de catégorie -->
        <div class="admin-card">
            <div class="qr-info">
                <h2>Ajouter une catégorie</h2>
                <form action="add_category.php" method="POST" class="add-category-form">
                    <input type="text" name="nom" placeholder="Ex: Boissons, Desserts..." required>
                    <button type="submit" class="btn-primary">Ajouter</button>
                </form>
            </div>
        </div>

        <?php foreach ($categories as $cat): ?>
            <div class="category-block">
                <div class="category-header-container">
    <h2 class="category-header"><?= htmlspecialchars($cat['nom']) ?></h2>
    
    <!-- Bouton Supprimer Catégorie (SVG) -->
    <a href="delete_category.php?id=<?= $cat['id'] ?>" 
       onclick="return confirm('⚠️ ATTENTION : Supprimer cette catégorie effacera DÉFINITIVEMENT tous les plats qu\'elle contient.\n\nVoulez-vous vraiment continuer ?')" 
       class="icon-btn delete" title="Supprimer la catégorie">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
    </a>
</div>
                
                <?php
                $stmtItems = $pdo->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY ordre ASC");
                $stmtItems->execute([$cat['id']]);
                $items = $stmtItems->fetchAll();
                ?>

                <?php foreach ($items as $item): ?>
                    <div class="item-row">
                        <div class="item-row-header">
                            <span class="item-row-name"><?= htmlspecialchars($item['nom']) ?></span>
                            <div class="item-actions">
                                <!-- Icône Modifier -->
                                <a href="item_form.php?id=<?= $item['id'] ?>" class="icon-btn edit">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                </a>
                                <!-- Icône Supprimer -->
                                <a href="delete_item.php?id=<?= $item['id'] ?>" onclick="return confirm('Supprimer ce plat ?')" class="icon-btn delete">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </a>
                            </div>
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

                <a href="item_form.php?cat_id=<?= $cat['id'] ?>" class="btn-add-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Ajouter un plat
                </a>
            </div>
        <?php endforeach; ?>
    </main>

    <div id="toast" class="toast">Prix mis à jour !</div>

    <script>
        function showToast(message) { const toast = document.getElementById('toast'); toast.textContent = message; toast.classList.add('show'); setTimeout(() => { toast.classList.remove('show'); }, 3000); }
        async function updatePrix(itemId) {
            const newPrix = document.getElementById('prix-' + itemId).value;
            const formData = new FormData(); formData.append('action', 'update_prix'); formData.append('item_id', itemId); formData.append('prix', newPrix);
            try { const response = await fetch('update_item.php', { method: 'POST', body: formData }); const result = await response.json(); if(result.success) showToast('Prix mis à jour !'); } catch (error) { showToast('Erreur.'); }
        }
        async function toggleDispo(itemId, btn) {
            const formData = new FormData(); formData.append('action', 'toggle_dispo'); formData.append('item_id', itemId);
            try { const response = await fetch('update_item.php', { method: 'POST', body: formData }); const result = await response.json();
                if(result.success) { if(result.new_status) { btn.textContent = 'Disponible'; btn.classList.replace('unavailable', 'available'); showToast('Plat disponible.'); } else { btn.textContent = 'Épuisé'; btn.classList.replace('available', 'unavailable'); showToast('Plat épuisé.'); } }
            } catch (error) { showToast('Erreur.'); }
        }
    </script>
</body>
</html>