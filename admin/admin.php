<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

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
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <header class="admin-header">
        <h1>Gérer le Menu</h1>
        <a href="../logout.php" class="btn-logout">Déconnexion</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>">Gérer le Menu</a>
        <a href="stats.php" class="<?= basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : '' ?>">Statistiques</a>
        <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">Paramètres</a>
        <a href="subscription.php" class="<?= basename($_SERVER['PHP_SELF']) == 'subscription.php' ? 'active' : '' ?>">Abonnement</a>
    </div>


    <main class="container">
                <!-- Barre de recherche -->
        <div class="search-bar-container">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="searchInput" placeholder="Rechercher un plat..." onkeyup="filterItems()">
        </div>

        <!-- Message si aucun résultat -->
        <div id="noResultsMsg" class="no-results">Aucun plat ne correspond à votre recherche.</div>
        <!-- Ajout de catégorie -->
        <div class="admin-card">
            <div class="qr-info">
                <h2>Ajouter une catégorie</h2>
                <form action="../actions/add_category.php" method="POST" class="add-category-form">
                    <input type="text" name="nom" placeholder="Ex: Boissons, Desserts..." required>
                    <button type="submit" class="btn-primary">Ajouter</button>
                </form>
            </div>
        </div>

                <?php foreach ($categories as $cat): ?>
            <div class="category-block">
                
                <!-- En-tête de la catégorie -->
                <div class="category-header-container">
                    <h2 class="category-header"><?= htmlspecialchars($cat['nom']) ?></h2>
                    
                    <div class="category-actions">
                        <!-- Boutons de tri (Flèches) -->
                        <div class="sort-buttons">
                            <button onclick="moveItem(<?= $cat['id'] ?>, 'category', 'up')" title="Monter">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"></polyline></svg>
                            </button>
                            <button onclick="moveItem(<?= $cat['id'] ?>, 'category', 'down')" title="Descendre">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                        </div>

                        <!-- Bouton Modifier -->
                        <a href="#" data-id="<?= $cat['id'] ?>" data-nom="<?= htmlspecialchars($cat['nom']) ?>" onclick="openEditCategory(this); return false;" class="icon-btn edit" title="Modifier la catégorie">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        </a>

                        <!-- Bouton Supprimer -->
                        <a href="#" onclick="openDeleteModal('../actions/delete_category.php?id=<?= $cat['id'] ?>', 'Voulez-vous vraiment supprimer la catégorie <?= htmlspecialchars(addslashes($cat['nom'])) ?> ? Tous les plats qu\'elle contient seront définitivement effacés.'); return false;" class="icon-btn delete" title="Supprimer la catégorie">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </a>
                    </div>
                </div>
                
                <!-- Liste des plats -->
                <div class="items-list-container">
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
                                    <!-- Boutons de tri du plat -->
                                    <div class="sort-buttons">
                                        <button onclick="moveItem(<?= $item['id'] ?>, 'item', 'up')" title="Monter">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"></polyline></svg>
                                        </button>
                                        <button onclick="moveItem(<?= $item['id'] ?>, 'item', 'down')" title="Descendre">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                        </button>
                                    </div>

                                    <!-- Modifier Plat -->
                                    <a href="item_form.php?id=<?= $item['id'] ?>" class="icon-btn edit">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    </a>
                                    <!-- Supprimer Plat -->
                                    <a href="#" onclick="openDeleteModal('../actions/delete_item.php?id=<?= $item['id'] ?>', 'Voulez-vous vraiment supprimer le plat <?= htmlspecialchars(addslashes($item['nom'])) ?> ?'); return false;" class="icon-btn delete">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="item-row-controls">
                                <div class="price-input-group">
                                    <input type="number" id="prix-<?= $item['id'] ?>" value="<?= $item['prix'] ?>" step="50">
                                    <span>DZD</span>
                                    <button onclick="updatePrix(<?= $item['id'] ?>)" class="btn-save-price">OK</button>
                                </div>
                                <button onclick="toggleDispo(<?= $item['id'] ?>, this)" class="btn-status <?= $item['disponible'] ? 'available' : 'unavailable' ?>">
                                    <?= $item['disponible'] ? 'Disponible' : 'Épuisé' ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Bouton Ajouter un plat -->
                <a href="item_form.php?cat_id=<?= $cat['id'] ?>" class="btn-add-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Ajouter un plat
                </a>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- LA MODALE DE SUPPRESSION -->
    <div id="delete-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <h2 class="modal-title">Confirmer la suppression</h2>
            <p class="modal-message" id="modal-message">Êtes-vous sûr de vouloir supprimer cet élément ?</p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">Annuler</button>
                <a href="#" id="modal-confirm-btn" class="btn-confirm">Supprimer</a>
            </div>
        </div>
    </div>

    <div id="toast" class="toast">Prix mis à jour !</div>

    <script>
        // --- GESTION DE LA MODALE ---
        function openDeleteModal(url, message) {
            document.getElementById('modal-message').innerText = message;
            document.getElementById('modal-confirm-btn').href = url;
            document.getElementById('delete-modal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('delete-modal').classList.remove('active');
        }

        // Fermer la modale si on clique en dehors
        document.getElementById('delete-modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // --- AJAX PRIX & DISPO ---
        function showToast(message) { const toast = document.getElementById('toast'); toast.textContent = message; toast.classList.add('show'); setTimeout(() => { toast.classList.remove('show'); }, 3000); }
        async function updatePrix(itemId) {
            const newPrix = document.getElementById('prix-' + itemId).value;
            const formData = new FormData(); formData.append('action', 'update_prix'); formData.append('item_id', itemId); formData.append('prix', newPrix);
            try { const response = await fetch('../actions/update_item.php', { method: 'POST', body: formData }); const result = await response.json(); if(result.success) showToast('Prix mis à jour !'); } catch (error) { showToast('Erreur.'); }
        }
        async function toggleDispo(itemId, btn) {
            const formData = new FormData(); formData.append('action', 'toggle_dispo'); formData.append('item_id', itemId);
            try { const response = await fetch('../actions/update_item.php', { method: 'POST', body: formData }); const result = await response.json();
                if(result.success) { if(result.new_status) { btn.textContent = 'Disponible'; btn.classList.replace('unavailable', 'available'); showToast('Plat disponible.'); } else { btn.textContent = 'Épuisé'; btn.classList.replace('available', 'unavailable'); showToast('Plat épuisé.'); } }
            } catch (error) { showToast('Erreur.'); }
        }

        // --- FILTRE DE RECHERCHE ---
        function filterItems() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const categories = document.querySelectorAll('.category-block');
            let hasGlobalResults = false;

            categories.forEach(category => {
                const items = category.querySelectorAll('.item-row');
                let hasCategoryResults = false;

                items.forEach(item => {
                    const itemName = item.querySelector('.item-row-name').textContent.toLowerCase();
                    // On vérifie si le nom du plat contient la recherche
                    if (itemName.includes(query)) {
                        item.style.display = 'block';
                        hasCategoryResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // On cache la catégorie entière si elle ne contient aucun plat correspondant
                if (hasCategoryResults && query !== '') {
                    category.style.display = 'block';
                    hasGlobalResults = true;
                } else if (query === '') {
                    category.style.display = 'block'; // On affiche tout si la recherche est vide
                } else {
                    category.style.display = 'none';
                }
            });

            // Gestion du message "Aucun résultat"
            const noResultsMsg = document.getElementById('noResultsMsg');
            if (!hasGlobalResults && query !== '') {
                noResultsMsg.style.display = 'block';
            } else {
                noResultsMsg.style.display = 'none';
            }
        }

               function openEditCategory(element) {
            const id = element.getAttribute('data-id');
            const nom = element.getAttribute('data-nom');
            
            document.getElementById('edit-cat-id').value = id;
            document.getElementById('edit-cat-name').value = nom;
            document.getElementById('edit-category-modal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('edit-category-modal').classList.remove('active');
        }

                // --- RÉORGANISATION (TRI) ---
        async function moveItem(id, type, direction) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('type', type);
            formData.append('direction', direction);

            try {
                const response = await fetch('../actions/update_order.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) {
                    // On recharge la page pour voir le nouvel ordre instantanément
                    window.location.reload();
                }
            } catch (error) {
                showToast('Erreur lors du tri.');
            }
        }

    </script>

    <!-- MODALE POUR MODIFIER UNE CATÉGORIE -->
    <div id="edit-category-modal" class="modal-overlay">
        <div class="modal-box">
            <h2 class="modal-title">Modifier la catégorie</h2>
            <form action="../actions/update_category.php" method="POST">
                <input type="hidden" name="category_id" id="edit-cat-id">
                <input type="text" name="nom" id="edit-cat-name" class="form-control" required style="margin-bottom:1rem;">
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Annuler</button>
                    <button type="submit" class="btn-primary" style="flex:1; padding:0.875rem; border-radius:0.75rem;">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>