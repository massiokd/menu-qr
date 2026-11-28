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
    ORDER BY items.ordre ASC, items.id ASC
");
$stmtAllItems->execute([$resto_id]);
$all_items = $stmtAllItems->fetchAll();

$items_by_category = [];
foreach ($all_items as $item) {
    $items_by_category[$item['cat_id']][] = $item;
}

// Catégorie active : soit celle passée dans l'URL, soit la première par défaut
if (isset($_GET['cat_id']) && !empty($_GET['cat_id'])) {
    $first_category_id = (int)$_GET['cat_id'];
} else {
    $first_category_id = !empty($categories) ? $categories[0]['id'] : null;
}

$base_url = "https://menu.fennetech.com";
$menu_url = $base_url . "/menu.php?slug=" . ($resto['slug'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Menu — <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/1apple-touch-icon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js" data-turbo-track="reload"></script>
</head>
<body data-turbo="true" class="app-shell">

    <header class="admin-header">
        <div class="header-brand">
            <span class="header-eyebrow"><?= htmlspecialchars($resto['nom']) ?></span>
            <h1>Gérer le Menu</h1>
        </div>

        <div class="header-actions">
            <?php if (isset($_SESSION['impersonated_by'])): ?>
                <a href="../actions/stop_impersonate.php" class="btn-impersonate">
                    Retour Admin
                </a>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($menu_url) ?>" target="_blank" rel="noopener" class="btn-ghost-icon" title="Voir le menu client" aria-label="Voir le menu client">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
            </a>

            <a href="../logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </header>

    <!-- Navigation principale (Barre supérieure sur desktop, Bottom Nav sur mobile) -->
    <nav class="admin-tabs" aria-label="Navigation principale">
        <a href="admin.php" class="active">Gérer le Menu</a>
        <a href="stats.php">Statistiques</a>
        <a href="settings.php">Paramètres</a>
        <a href="subscription.php">Abonnement</a>
    </nav>

    <!-- ZONE STICKY : Alertes + Recherche + Catégories + Barre d'actions -->
    <div class="admin-sticky-container">

        <?php if ($resto['subscription_status'] === 'pending_verification' || ($days_left !== null && $days_left <= 7)): ?>
            <div class="admin-alerts">
                <?php if ($resto['subscription_status'] === 'pending_verification'): ?>
                    <div class="alert-banner alert-info">
                        <span>⏳ Paiement en vérification — menu actif.</span>
                    </div>
                <?php elseif ($days_left !== null && $days_left > 0 && $days_left <= 7): ?>
                    <div class="alert-banner alert-warning">
                        <span>⚠️ Expire dans <strong><?= $days_left ?> j</strong>.</span>
                        <a href="subscription.php">Renouveler</a>
                    </div>
                <?php elseif ($days_left !== null && $days_left <= 0): ?>
                    <div class="alert-banner alert-danger">
                        <span>🚨 Abonnement expiré.</span>
                        <a href="subscription.php">Réactiver</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Recherche -->
        <div class="search-bar-container">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="searchInput" placeholder="Rechercher un plat ou une boisson..." oninput="filterItems()" autocomplete="off">
            <button type="button" id="clearSearchBtn" class="clear-search-btn" onclick="clearSearch()" aria-label="Effacer la recherche" style="display:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <!-- Onglets de catégories (navigation uniquement) -->
        <div class="admin-tabs-wrapper" id="adminTabsWrapper">
            <div class="admin-category-tabs">
                <?php foreach ($categories as $cat): ?>
                    <button type="button"
                            class="admin-tab-btn <?= $first_category_id == $cat['id'] ? 'active' : '' ?>"
                            data-id="<?= $cat['id'] ?>"
                            onclick="switchTab(<?= $cat['id'] ?>)">
                        <?= htmlspecialchars($cat['nom']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Barre d'actions séparée pour la structure du menu -->
        <div class="catalog-toolbar">
            <button type="button" class="toolbar-btn toolbar-btn-primary" onclick="openAddCategoryModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Catégorie
            </button>

            <a href="reorder.php" class="toolbar-btn toolbar-btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                Réorganiser
            </a>
        </div>

    </div>

    <main class="container">

        <div id="noResultsMsg" class="no-results">
            <p>Aucun plat ne correspond à votre recherche.</p>
            <button type="button" class="btn-secondary-sm" onclick="clearSearch()">Réinitialiser</button>
        </div>

        <?php if (empty($categories)): ?>
            <div class="empty-state-card">
                <h2>Votre carte est vide</h2>
                <p>Créez votre première catégorie (ex. : Entrées, Plats, Boissons) pour commencer à ajouter vos articles.</p>
                <button type="button" class="btn-primary" onclick="openAddCategoryModal()">Créer une catégorie</button>
            </div>
        <?php endif; ?>

        <?php foreach ($categories as $cat):
            $items = $items_by_category[$cat['id']] ?? [];
        ?>
            <div id="cat-<?= $cat['id'] ?>" class="category-block <?= $first_category_id == $cat['id'] ? 'active-category' : '' ?>">

                <div class="category-header-container">
                    <div class="category-title-wrap">
                        <h2 class="category-header"><?= htmlspecialchars($cat['nom']) ?></h2>
                        <span class="category-count"><?= count($items) ?></span>
                    </div>

                    <div class="category-actions">
                        <a href="item_form.php?cat_id=<?= $cat['id'] ?>" class="btn-inline-add" title="Ajouter un plat">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Plat
                        </a>

                        <button type="button"
                                data-id="<?= $cat['id'] ?>"
                                data-nom="<?= htmlspecialchars($cat['nom']) ?>"
                                onclick="openEditCategory(this)"
                                class="icon-btn edit"
                                title="Modifier la catégorie"
                                aria-label="Modifier la catégorie">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        </button>

                        <button type="button"
                                onclick="openDeleteModal('../actions/delete_category.php?id=<?= $cat['id'] ?>', 'Voulez-vous vraiment supprimer la catégorie <?= htmlspecialchars(addslashes($cat['nom'])) ?> ? Tous les plats qu\'elle contient seront effacés.')"
                                class="icon-btn delete"
                                title="Supprimer la catégorie"
                                aria-label="Supprimer la catégorie">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="items-list-container">
                    <?php if (empty($items)): ?>
                        <div class="empty-category-box">
                            <p>Aucun plat dans cette catégorie pour le moment.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <div class="item-row searchable-item <?= $item['disponible'] ? '' : 'is-out' ?>">
                            <div class="item-row-header">
                                <div class="item-title-group">
                                    <span class="item-row-name item-search-name"><?= htmlspecialchars($item['nom']) ?></span>
                                    <span class="item-state-pill <?= $item['disponible'] ? 'is-on' : 'is-off' ?>">
                                        <?= $item['disponible'] ? 'En ligne' : 'Épuisé' ?>
                                    </span>
                                </div>

                                <div class="item-actions">
                                    <a href="item_form.php?id=<?= $item['id'] ?>" class="icon-btn edit" title="Modifier le plat" aria-label="Modifier le plat">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    </a>
                                    <button type="button"
                                            onclick="openDeleteModal('../actions/delete_item.php?id=<?= $item['id'] ?>', 'Voulez-vous vraiment supprimer le plat <?= htmlspecialchars(addslashes($item['nom'])) ?> ?')"
                                            class="icon-btn delete"
                                            title="Supprimer le plat"
                                            aria-label="Supprimer le plat">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="item-row-controls">
                                <div class="price-input-group">
                                    <input type="number" id="prix-<?= $item['id'] ?>" value="<?= htmlspecialchars($item['prix']) ?>" step="50" inputmode="numeric" aria-label="Prix en DZD">
                                    <span>DZD</span>
                                    <button type="button" onclick="updatePrix(<?= $item['id'] ?>, this)" class="btn-save-price">OK</button>
                                </div>

                                <button type="button"
                                        onclick="toggleDispo(<?= $item['id'] ?>, this)"
                                        class="btn-status <?= $item['disponible'] ? 'available' : 'unavailable' ?>">
                                    <?= $item['disponible'] ? 'Disponible' : 'Épuisé' ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <a href="item_form.php?cat_id=<?= $cat['id'] ?>" class="btn-add-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Ajouter un plat dans <?= htmlspecialchars($cat['nom']) ?>
                </a>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- BOTTOM SHEET : AJOUTER UNE CATÉGORIE -->
    <div id="add-category-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="add-cat-title">
        <div class="modal-box sheet-box">
            <div class="sheet-handle" aria-hidden="true"></div>
            <h2 id="add-cat-title" class="modal-title">Nouvelle catégorie</h2>
            <p class="sheet-subtitle">Organisez votre carte par famille de produits.</p>
            <form action="../actions/add_category.php" method="POST">
                <input type="text" name="nom" class="form-control sheet-input" placeholder="Ex. : Burgers, Café & Boissons, Desserts..." required autofocus>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddCategoryModal()">Annuler</button>
                    <button type="submit" class="btn-primary sheet-submit">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- BOTTOM SHEET : MODIFIER UNE CATÉGORIE -->
    <div id="edit-category-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="edit-cat-title">
        <div class="modal-box sheet-box">
            <div class="sheet-handle" aria-hidden="true"></div>
            <h2 id="edit-cat-title" class="modal-title">Modifier la catégorie</h2>
            <form action="../actions/update_category.php" method="POST">
                <input type="hidden" name="category_id" id="edit-cat-id">
                <input type="text" name="nom" id="edit-cat-name" class="form-control sheet-input" required>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Annuler</button>
                    <button type="submit" class="btn-primary sheet-submit">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- BOTTOM SHEET : SUPPRESSION -->
    <div id="delete-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
        <div class="modal-box sheet-box">
            <div class="sheet-handle" aria-hidden="true"></div>
            <div class="modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <h2 id="delete-modal-title" class="modal-title">Confirmer la suppression</h2>
            <p class="modal-message" id="modal-message">Êtes-vous sûr ?</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
                <a href="#" id="modal-confirm-btn" class="btn-confirm">Supprimer</a>
            </div>
        </div>
    </div>

    <div id="toast" class="toast" role="status" aria-live="polite">Action réussie</div>

    <!-- BANNIÈRE PWA -->
    <div id="pwa-install-banner" class="pwa-banner">
        <div class="pwa-content">
            <img src="icons/1android-chrome-192x192.png" alt="FenneTech" class="pwa-icon">
            <div>
                <p class="pwa-title">Installer FenneTech</p>
                <p class="pwa-text" id="pwa-install-text">Accès rapide depuis l'écran d'accueil.</p>
            </div>
        </div>
        <div class="pwa-actions">
            <button type="button" onclick="dismissPwa()" class="pwa-btn-later">Plus tard</button>
            <button type="button" id="pwa-install-btn" onclick="installPwa()" class="pwa-btn-install">Installer</button>
        </div>
    </div>

    <script>
        let currentCategoryId = <?= $first_category_id ?? 'null' ?>;
        let deferredPrompt;

        function toggleClearBtn() {
            const input = document.getElementById('searchInput');
            const clearBtn = document.getElementById('clearSearchBtn');
            if (input && clearBtn) {
                clearBtn.style.display = input.value.trim().length > 0 ? 'flex' : 'none';
            }
        }

        function clearSearch() {
            const input = document.getElementById('searchInput');
            if (input) {
                input.value = '';
                toggleClearBtn();
                filterItems();
                input.focus();
            }
        }

        function switchTab(catId) {
            currentCategoryId = catId;
            const searchInput = document.getElementById('searchInput');
            if (searchInput && searchInput.value.trim() !== '') {
                searchInput.value = '';
                toggleClearBtn();
                document.getElementById('adminTabsWrapper').style.display = 'block';
                document.getElementById('noResultsMsg').style.display = 'none';
            }

            document.querySelectorAll('.category-block').forEach(sec => {
                sec.classList.remove('active-category');
                sec.style.display = '';
                sec.querySelectorAll('.searchable-item').forEach(item => item.style.display = '');
            });

            const target = document.getElementById('cat-' + catId);
            if (target) target.classList.add('active-category');

            document.querySelectorAll('.admin-tab-btn').forEach(b => {
                b.classList.remove('active');
                if (b.getAttribute('data-id') == catId) {
                    b.classList.add('active');
                    b.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function filterItems() {
            toggleClearBtn();
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
                        item.style.display = '';
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

        function openAddCategoryModal() {
            const modal = document.getElementById('add-category-modal');
            modal.classList.add('active');
            const input = modal.querySelector('input[name="nom"]');
            if (input) setTimeout(() => input.focus(), 120);
        }

        function closeAddCategoryModal() {
            document.getElementById('add-category-modal').classList.remove('active');
        }

        function openEditCategory(element) {
            document.getElementById('edit-cat-id').value = element.getAttribute('data-id');
            document.getElementById('edit-cat-name').value = element.getAttribute('data-nom');
            const modal = document.getElementById('edit-category-modal');
            modal.classList.add('active');
            const input = document.getElementById('edit-cat-name');
            if (input) setTimeout(() => input.focus(), 120);
        }

        function closeEditModal() {
            document.getElementById('edit-category-modal').classList.remove('active');
        }

        function openDeleteModal(url, message) {
            document.getElementById('modal-message').innerText = message;
            document.getElementById('modal-confirm-btn').href = url;
            document.getElementById('delete-modal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('delete-modal').classList.remove('active');
        }

        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(() => {
                toast.classList.remove('show');
            }, 2600);
        }

        function setLoading(element, isLoading) {
            if (isLoading) {
                element.classList.add('btn-loading');
                element.dataset.originalHtml = element.innerHTML;
                element.innerHTML = '<span class="spinner"></span>';
            } else {
                element.classList.remove('btn-loading');
                if (element.dataset.originalHtml) {
                    element.innerHTML = element.dataset.originalHtml;
                }
            }
        }

        async function updatePrix(itemId, btnElement) {
            const newPrix = document.getElementById('prix-' + itemId).value;
            const formData = new FormData();
            formData.append('action', 'update_prix');
            formData.append('item_id', itemId);
            formData.append('prix', newPrix);

            setLoading(btnElement, true);
            try {
                const response = await fetch('../actions/update_item.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showToast('Prix mis à jour');
                } else {
                    showToast('Mise à jour impossible');
                }
            } catch (error) {
                showToast('Erreur réseau');
            } finally {
                setLoading(btnElement, false);
            }
        }

        async function toggleDispo(itemId, btnElement) {
            const formData = new FormData();
            formData.append('action', 'toggle_dispo');
            formData.append('item_id', itemId);

            setLoading(btnElement, true);
            try {
                const response = await fetch('../actions/update_item.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    const row = btnElement.closest('.item-row');
                    const pill = row ? row.querySelector('.item-state-pill') : null;
                    if (result.new_status) {
                        btnElement.textContent = 'Disponible';
                        btnElement.classList.replace('unavailable', 'available');
                        if (row) row.classList.remove('is-out');
                        if (pill) {
                            pill.textContent = 'En ligne';
                            pill.classList.replace('is-off', 'is-on');
                        }
                        showToast('Plat marqué disponible');
                    } else {
                        btnElement.textContent = 'Épuisé';
                        btnElement.classList.replace('available', 'unavailable');
                        if (row) row.classList.add('is-out');
                        if (pill) {
                            pill.textContent = 'Épuisé';
                            pill.classList.replace('is-on', 'is-off');
                        }
                        showToast('Plat marqué épuisé');
                    }
                }
            } catch (error) {
                showToast('Erreur réseau');
            } finally {
                setLoading(btnElement, false);
            }
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
        });

        document.addEventListener("turbo:load", function () {
            document.querySelectorAll('.modal-overlay').forEach(overlay => {
                overlay.addEventListener('click', function (e) {
                    if (e.target === this) this.classList.remove('active');
                });
            });

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('service-worker.js')
                    .catch(error => console.log('Échec PWA:', error));
            }

            const pwaBanner = document.getElementById('pwa-install-banner');
            const pwaBtn = document.getElementById('pwa-install-btn');
            const pwaText = document.getElementById('pwa-install-text');
            if (!pwaBanner) return;

            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
            const isDismissed = localStorage.getItem('pwa_dismissed');
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

            if (isDismissed) {
                const daysSinceDismissed = (Date.now() - parseInt(isDismissed, 10)) / (1000 * 60 * 60 * 24);
                if (daysSinceDismissed > 7) localStorage.removeItem('pwa_dismissed');
            }

            if (!isStandalone && !localStorage.getItem('pwa_dismissed')) {
                setTimeout(() => {
                    if (isIOS) {
                        if (pwaBtn) pwaBtn.style.display = 'none';
                        if (pwaText) pwaText.innerHTML = "Partager ➔ <strong>Sur l'écran d'accueil</strong>";
                    } else if (!deferredPrompt && pwaText) {
                        pwaText.innerHTML = "Menu navigateur ➔ <strong>Installer l'application</strong>";
                    }
                    pwaBanner.classList.add('show');
                }, 3000);
            }
        });

        window.installPwa = function () {
            const pwaBanner = document.getElementById('pwa-install-banner');
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(() => {
                    deferredPrompt = null;
                    if (pwaBanner) pwaBanner.classList.remove('show');
                });
            } else {
                alert("Ouvrez le menu de votre navigateur (⋮) puis sélectionnez « Installer l'application » ou « Ajouter à l'écran d'accueil ».");
            }
        };

        window.dismissPwa = function () {
            const pwaBanner = document.getElementById('pwa-install-banner');
            if (pwaBanner) pwaBanner.classList.remove('show');
            localStorage.setItem('pwa_dismissed', Date.now());
        };
    </script>
</body>
</html>