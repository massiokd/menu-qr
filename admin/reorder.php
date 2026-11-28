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

$stmtResto = $pdo->prepare("SELECT nom FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

$stmtCat = $pdo->prepare("SELECT * FROM categories WHERE restaurant_id = ? ORDER BY ordre ASC");
$stmtCat->execute([$resto_id]);
$categories = $stmtCat->fetchAll();

// Récupérer tous les items en une seule requête pour éviter le N+1
$stmtAllItems = $pdo->prepare("
    SELECT items.*
    FROM items
    JOIN categories ON items.category_id = categories.id
    WHERE categories.restaurant_id = ?
    ORDER BY items.ordre ASC, items.id ASC
");
$stmtAllItems->execute([$resto_id]);
$all_items = $stmtAllItems->fetchAll();

$items_by_category = [];
foreach ($all_items as $item) {
    $items_by_category[$item['category_id']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Réorganiser — <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/1apple-touch-icon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js" data-turbo-track="reload"></script>
</head>
<body data-turbo="true">

    <header class="admin-header">
        <div class="header-brand">
            <span class="header-eyebrow"><?= htmlspecialchars($resto['nom']) ?></span>
            <h1>Réorganiser</h1>
        </div>
        <div class="header-actions">
            <a href="admin.php" class="btn-back-pill">Retour</a>
        </div>
    </header>

    <nav class="admin-tabs" aria-label="Navigation principale">
        <a href="admin.php" class="active">Gérer le Menu</a>
        <a href="stats.php">Statistiques</a>
        <a href="settings.php">Paramètres</a>
        <a href="subscription.php">Abonnement</a>
    </nav>

    <main class="container">
        <div class="feedback-banner feedback-info">
            Utilisez les flèches pour déplacer vos catégories et vos plats. L'ordre est enregistré automatiquement.
        </div>

        <div id="categories-container">
            <?php foreach ($categories as $cat):
                $items = $items_by_category[$cat['id']] ?? [];
            ?>
                <div class="reorder-card category-item" data-id="<?= $cat['id'] ?>">

                    <div class="reorder-header">
                        <h2 class="reorder-title"><?= htmlspecialchars($cat['nom']) ?></h2>

                        <div class="reorder-actions">
                            <button type="button" onclick="moveCategory(this, 'up')" class="btn-move" title="Monter la catégorie" aria-label="Monter la catégorie">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"></polyline></svg>
                            </button>
                            <button type="button" onclick="moveCategory(this, 'down')" class="btn-move" title="Descendre la catégorie" aria-label="Descendre la catégorie">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                        </div>
                    </div>

                    <div class="items-reorder-list" data-cat-id="<?= $cat['id'] ?>">
                        <?php if (empty($items)): ?>
                            <p class="empty-reorder-note">Aucun plat dans cette catégorie.</p>
                        <?php endif; ?>

                        <?php foreach ($items as $item): ?>
                            <div class="reorder-item-row item-item" data-id="<?= $item['id'] ?>">
                                <span class="item-name-reorder"><?= htmlspecialchars($item['nom']) ?></span>
                                <div class="reorder-actions">
                                    <button type="button" onclick="moveItem(this, 'up')" class="btn-move" title="Monter le plat" aria-label="Monter le plat">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"></polyline></svg>
                                    </button>
                                    <button type="button" onclick="moveItem(this, 'down')" class="btn-move" title="Descendre le plat" aria-label="Descendre le plat">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="toast" class="toast" role="status" aria-live="polite">Ordre mis à jour</div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            if (!toast) return;
            toast.textContent = message;
            toast.classList.add('show');
            clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(() => {
                toast.classList.remove('show');
            }, 2000);
        }

        function moveCategory(btn, direction) {
            const currentBlock = btn.closest('.category-item');
            const container = document.getElementById('categories-container');
            if (!currentBlock || !container) return;

            if (direction === 'up') {
                const prev = currentBlock.previousElementSibling;
                if (prev && prev.classList.contains('category-item')) {
                    container.insertBefore(currentBlock, prev);
                    saveOrder(container, 'category');
                }
            } else {
                const next = currentBlock.nextElementSibling;
                if (next && next.classList.contains('category-item')) {
                    container.insertBefore(next, currentBlock);
                    saveOrder(container, 'category');
                }
            }
        }

        function moveItem(btn, direction) {
            const currentRow = btn.closest('.item-item');
            const container = btn.closest('.items-reorder-list');
            if (!currentRow || !container) return;

            if (direction === 'up') {
                const prev = currentRow.previousElementSibling;
                if (prev && prev.classList.contains('item-item')) {
                    container.insertBefore(currentRow, prev);
                    saveOrder(container, 'item');
                }
            } else {
                const next = currentRow.nextElementSibling;
                if (next && next.classList.contains('item-item')) {
                    container.insertBefore(next, currentRow);
                    saveOrder(container, 'item');
                }
            }
        }

        async function saveOrder(container, type) {
            const selector = type === 'category' ? '.category-item' : '.item-item';
            const items = container.querySelectorAll(selector);
            const ids = Array.from(items).map(el => el.getAttribute('data-id'));

            const formData = new FormData();
            formData.append('type', type);
            ids.forEach(id => formData.append('ids[]', id));

            try {
                const response = await fetch('../actions/update_order.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showToast('Ordre sauvegardé');
                } else {
                    showToast('Sauvegarde impossible');
                }
            } catch (error) {
                showToast('Erreur réseau');
            }
        }

        document.addEventListener("turbo:load", function () {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('service-worker.js')
                    .catch(error => console.log('Échec PWA:', error));
            }
        });
    </script>
</body>
</html>