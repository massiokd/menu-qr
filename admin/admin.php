<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmtResto = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

// Calcul des jours restants d'abonnement
$days_left = null;
if ($resto['plan'] !== 'lifetime' && !empty($resto['expires_at'])) {
    $days_left = ceil((strtotime($resto['expires_at']) - time()) / 86400);
}

$stmtCat = $pdo->prepare("SELECT * FROM categories WHERE restaurant_id = ? ORDER BY ordre ASC");
$stmtCat->execute([$resto_id]);
$categories = $stmtCat->fetchAll();

// Récupérer tous les items en une fois pour éviter le N+1
$stmtAllItems = $pdo->prepare("
    SELECT items.*, categories.id as cat_id 
    FROM items 
    JOIN categories ON items.category_id = categories.id 
    WHERE categories.restaurant_id = ? 
    ORDER BY items.ordre ASC
");
$stmtAllItems->execute([$resto_id]);
$all_items = $stmtAllItems->fetchAll();

$items_by_category = [];
foreach ($all_items as $item) {
    $items_by_category[$item['cat_id']][] = $item;
}

// Première catégorie active
$first_category_id = !empty($categories) ? $categories[0]['id'] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <header class="admin-header">
        <h1>Gérer le Menu</h1>
        <a href="../logout.php" class="btn-logout">Déconnexion</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php" class="active">Gérer le Menu</a>
        <a href="stats.php">Statistiques</a>
        <a href="settings.php">Paramètres</a>
        <a href="subscription.php">Abonnement</a>
    </div>

    <!-- ZONE STICKY (Recherche + Onglets catégories) -->
    <div class="admin-sticky-container">
        
        <!-- Alertes -->
        <div class="admin-alerts">
            <?php if($resto['subscription_status'] === 'pending_verification'): ?>
                <div style="background:#fef3c7; color:#92400e; padding:0.75rem; border-radius:0.5rem; border:1px solid #fcd34d; font-size:0.85rem; text-align:center; margin-bottom:0.5rem;">
                    ⏳ Paiement en cours de vérification. Votre menu est actif.
                </div>
            <?php elseif ($days_left !== null && $days_left > 0 && $days_left <= 7): ?>
                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 0.75rem; border-radius: 0.5rem; font-size:0.85rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>⚠️ Expire dans <strong><?= $days_left ?> jours</strong>.</span>
                    <a href="subscription.php" style="color: #92400e; font-weight: bold; text-decoration: underline;">Renouveler</a>
                </div>
            <?php elseif ($days_left !== null && $days_left <= 0): ?>
                <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 0.75rem; border-radius: 0.5rem; font-size:0.85rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>🚨 Menu expiré.</span>
                    <a href="subscription.php" style="color: #991b1b; font-weight: bold; text-decoration: underline;">Réactiver</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Barre de recherche -->
        <div class="search-bar-container">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="searchInput" placeholder="Rechercher un plat..." onkeyup="filterItems()">
        </div>

        

        <!-- Onglets des catégories -->
        <div class="admin-tabs-wrapper" id="adminTabsWrapper">
            <div class="admin-category-tabs">
                <?php foreach ($categories as $cat): ?>
                    <button class="admin-tab-btn <?= $first_category_id == $cat['id'] ? 'active' : '' ?>" 
                            data-id="<?= $cat['id'] ?>"
                            onclick="switchTab(<?= $cat['id'] ?>)">
                        <?= htmlspecialchars($cat['nom']) ?>
                    </button>
                <?php endforeach; ?>
                
                <!-- Bouton pour aller à la page de Réorganisation -->
                <a href="reorder.php" class="admin-tab-btn" style="background: var(--text-dark); color: white; border: none; display:flex; align-items:center; gap:0.4rem; padding: 0.6rem 1rem; text-decoration: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    Trier
                </a>

                <!-- Bouton Ajouter Catégorie -->
                <button class="admin-tab-btn add-btn" onclick="openAddCategoryModal()" style="background: var(--brand-color); color: white; border: none; display:flex; align-items:center; gap:0.25rem; padding: 0.6rem 1rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Nouvelle
                </button>
            </div>
        </div>

    </div>

    <main class="container" style="padding-top: 1rem;">
        
        <div id="noResultsMsg" class="no-results">Aucun plat ne correspond à votre recherche.</div>

        <?php foreach ($categories as $cat): 
            $items = $items_by_category[$cat['id']] ?? [];
        ?>
            <div id="cat-<?= $cat['id'] ?>" class="category-block <?= $first_category_id == $cat['id'] ? 'active-category' : '' ?>">
                
                <div class="category-header-container">
                    <h2 class="category-header"><?= htmlspecialchars($cat['nom']) ?></h2>
                    <div class="category-actions">
                        <!-- Bouton Modifier Catégorie -->
                        <a href="#" data-id="<?= $cat['id'] ?>" data-nom="<?= htmlspecialchars($cat['nom']) ?>" onclick="openEditCategory(this); return false;" class="icon-btn edit" title="Modifier">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        </a>
                        <!-- Bouton Supprimer Catégorie -->
                        <a href="#" onclick="openDeleteModal('../actions/delete_category.php?id=<?= $cat['id'] ?>', 'Voulez-vous vraiment supprimer la catégorie <?= htmlspecialchars(addslashes($cat['nom'])) ?> ? Tous les plats qu\'elle contient seront effacés.'); return false;" class="icon-btn delete" title="Supprimer">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </a>
                    </div>
                </div>
                
                <div class="items-list-container">
                    <?php if (empty($items)): ?>
                        <p style="color:var(--text-gray); font-size:0.9rem; font-style:italic; margin-bottom:1rem;">Aucun plat dans cette catégorie.</p>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <div class="item-row searchable-item">
                            <div class="item-row-header">
                                <span class="item-row-name item-search-name"><?= htmlspecialchars($item['nom']) ?></span>
                                
                                <div class="item-actions">
                                    <!-- Icône Modifier -->
                                    <a href="item_form.php?id=<?= $item['id'] ?>" class="icon-btn edit">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    </a>
                                    <!-- Icône Supprimer -->
                                    <a href="#" onclick="openDeleteModal('../actions/delete_item.php?id=<?= $item['id'] ?>', 'Voulez-vous vraiment supprimer le plat <?= htmlspecialchars(addslashes($item['nom'])) ?> ?'); return false;" class="icon-btn delete">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="item-row-controls">
                                <!-- Le champ de prix -->
                                <div class="price-input-group">
                                    <input type="number" id="prix-<?= $item['id'] ?>" value="<?= $item['prix'] ?>" step="50">
                                    <span>DZD</span>
                                    <button onclick="updatePrix(<?= $item['id'] ?>, this)" class="btn-save-price">OK</button>
                                </div>
                                <!-- Le bouton Disponible -->
                                <button onclick="toggleDispo(<?= $item['id'] ?>, this)" class="btn-status <?= $item['disponible'] ? 'available' : 'unavailable' ?>">
                                    <?= $item['disponible'] ? 'Disponible' : 'Épuisé' ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <a href="item_form.php?cat_id=<?= $cat['id'] ?>" class="btn-add-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Ajouter un plat
                </a>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- MODALE : AJOUTER UNE CATÉGORIE -->
    <div id="add-category-modal" class="modal-overlay">
        <div class="modal-box">
            <h2 class="modal-title">Nouvelle catégorie</h2>
            <form action="../actions/add_category.php" method="POST">
                <input type="text" name="nom" class="form-control" placeholder="Ex: Boissons, Desserts..." required style="margin-bottom:1rem;">
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddCategoryModal()">Annuler</button>
                    <button type="submit" class="btn-primary" style="flex:1; padding:0.875rem; border-radius:0.75rem;">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODALE : MODIFIER UNE CATÉGORIE -->
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

    <!-- MODALE : SUPPRESSION -->
    <div id="delete-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <h2 class="modal-title">Confirmer la suppression</h2>
            <p class="modal-message" id="modal-message">Êtes-vous sûr ?</p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">Annuler</button>
                <a href="#" id="modal-confirm-btn" class="btn-confirm">Supprimer</a>
            </div>
        </div>
    </div>

    <div id="toast" class="toast">Action réussie !</div>

    <script>
        let currentCategoryId = <?= $first_category_id ?? 'null' ?>;

        // --- GESTION DES ONGLETS ---
        function switchTab(catId) {
            currentCategoryId = catId;
            
            // Reset recherche
            const searchInput = document.getElementById('searchInput');
            if (searchInput.value.trim() !== '') {
                searchInput.value = '';
                document.getElementById('adminTabsWrapper').style.display = 'block';
                document.getElementById('noResultsMsg').style.display = 'none';
            }

            // Cacher toutes les catégories
            document.querySelectorAll('.category-block').forEach(sec => {
                sec.classList.remove('active-category');
                sec.style.display = ''; 
                sec.querySelectorAll('.searchable-item').forEach(item => item.style.display = 'flex');
            });

            // Afficher la catégorie demandée
            const target = document.getElementById('cat-' + catId);
            if(target) {
                target.classList.add('active-category');
            }

            // Mettre à jour l'onglet actif
            document.querySelectorAll('.admin-tab-btn').forEach(b => {
                b.classList.remove('active');
                if (b.getAttribute('data-id') == catId) {
                    b.classList.add('active');
                    b.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // --- RECHERCHE ---
        function filterItems() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const categories = document.querySelectorAll('.category-block');
            const noResultsMsg = document.getElementById('noResultsMsg');
            const tabsWrapper = document.getElementById('adminTabsWrapper');
            let hasGlobalResults = false;

            if (query === '') {
                tabsWrapper.style.display = 'block';
                switchTab(currentCategoryId);
                return;
            }

            tabsWrapper.style.display = 'none';

            categories.forEach(category => {
                const items = category.querySelectorAll('.searchable-item');
                let hasCategoryResults = false;

                items.forEach(item => {
                    const itemName = item.querySelector('.item-search-name').textContent.toLowerCase();
                    if (itemName.includes(query)) {
                        item.style.display = 'flex';
                        hasCategoryResults = true;
                        hasGlobalResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (hasCategoryResults) {
                    category.style.display = 'block';
                    category.classList.add('active-category');
                } else {
                    category.style.display = 'none';
                    category.classList.remove('active-category');
                }
            });

            noResultsMsg.style.display = hasGlobalResults ? 'none' : 'block';
        }

        // --- MODALES ---
        function openAddCategoryModal() { document.getElementById('add-category-modal').classList.add('active'); }
        function closeAddCategoryModal() { document.getElementById('add-category-modal').classList.remove('active'); }

        function openEditCategory(element) {
            document.getElementById('edit-cat-id').value = element.getAttribute('data-id');
            document.getElementById('edit-cat-name').value = element.getAttribute('data-nom');
            document.getElementById('edit-category-modal').classList.add('active');
        }
        function closeEditModal() { document.getElementById('edit-category-modal').classList.remove('active'); }

        function openDeleteModal(url, message) {
            document.getElementById('modal-message').innerText = message;
            document.getElementById('modal-confirm-btn').href = url;
            document.getElementById('delete-modal').classList.add('active');
        }
        function closeModal() { document.getElementById('delete-modal').classList.remove('active'); }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
        });

        // --- AJAX PRIX & DISPO ---
        function showToast(message) { const toast = document.getElementById('toast'); toast.textContent = message; toast.classList.add('show'); setTimeout(() => { toast.classList.remove('show'); }, 3000); }
        function setLoading(element, isLoading) {
            if (isLoading) { element.classList.add('btn-loading'); element.dataset.originalHtml = element.innerHTML; element.innerHTML = '<span class="spinner"></span>'; } 
            else { element.classList.remove('btn-loading'); if (element.dataset.originalHtml) element.innerHTML = element.dataset.originalHtml; }
        }

        async function updatePrix(itemId, btnElement) {
            const newPrix = document.getElementById('prix-' + itemId).value;
            const formData = new FormData(); formData.append('action', 'update_prix'); formData.append('item_id', itemId); formData.append('prix', newPrix);
            setLoading(btnElement, true);
            try { 
                const response = await fetch('../actions/update_item.php', { method: 'POST', body: formData }); 
                const result = await response.json(); 
                if(result.success) showToast('Prix mis à jour !'); 
            } catch (error) { showToast('Erreur.'); } finally { setLoading(btnElement, false); }
        }

        async function toggleDispo(itemId, btnElement) {
            const formData = new FormData(); formData.append('action', 'toggle_dispo'); formData.append('item_id', itemId);
            setLoading(btnElement, true);
            try { 
                const response = await fetch('../actions/update_item.php', { method: 'POST', body: formData }); 
                const result = await response.json();
                if(result.success) { 
                    if(result.new_status) { btnElement.textContent = 'Disponible'; btnElement.classList.replace('unavailable', 'available'); showToast('Plat disponible.'); } 
                    else { btnElement.textContent = 'Épuisé'; btnElement.classList.replace('available', 'unavailable'); showToast('Plat épuisé.'); }
                }
            } catch (error) { showToast('Erreur.'); } finally { setLoading(btnElement, false); }
        }
    </script>
</body>
</html>