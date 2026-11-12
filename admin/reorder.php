<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmtResto = $pdo->prepare("SELECT nom FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

$stmtCat = $pdo->prepare("SELECT * FROM categories WHERE restaurant_id = ? ORDER BY ordre ASC");
$stmtCat->execute([$resto_id]);
$categories = $stmtCat->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Réorganiser - <?= htmlspecialchars($resto['nom']) ?></title>
     <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/android-chrome-192x192.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <header class="admin-header">
        <h1>Réorganiser</h1>
        <a href="admin.php" class="btn-logout" style="background:#f3f4f6; color:#374151;">Retour au menu</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php">Gérer le Menu</a>
        <a href="stats.php">Statistiques</a>
        <a href="settings.php">Paramètres</a>
        <a href="subscription.php">Abonnement</a>
    </div>

    <main class="container">
        <div style="background: #e0f2fe; color: #0369a1; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; font-size: 0.85rem;">
            💡 <strong>Astuce :</strong> Utilisez les flèches pour déplacer les catégories et les plats. L'ordre est sauvegardé automatiquement.
        </div>

        <!-- BLOC CATÉGORIES -->
        <div id="categories-container">
            <?php foreach ($categories as $cat): ?>
                <div class="reorder-card category-item" data-id="<?= $cat['id'] ?>">
                    
                    <div class="reorder-header">
                        <h2 class="reorder-title"><?= htmlspecialchars($cat['nom']) ?></h2>
                        
                        <div class="reorder-actions">
                            <button onclick="moveCategory(this, 'up')" class="btn-move" title="Monter">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"></polyline></svg>
                            </button>
                            <button onclick="moveCategory(this, 'down')" class="btn-move" title="Descendre">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </button>
                        </div>
                    </div>

                    <!-- PLATS DE LA CATÉGORIE -->
                    <div class="items-reorder-list" data-cat-id="<?= $cat['id'] ?>">
                        <?php
                        $stmtItems = $pdo->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY ordre ASC");
                        $stmtItems->execute([$cat['id']]);
                        $items = $stmtItems->fetchAll();
                        ?>
                        
                        <?php if(empty($items)): ?>
                            <p style="color:#9ca3af; font-size:0.85rem; padding: 0.5rem;">Aucun plat dans cette catégorie.</p>
                        <?php endif; ?>

                        <?php foreach ($items as $item): ?>
                            <div class="reorder-item-row item-item" data-id="<?= $item['id'] ?>">
                                <span class="item-name-reorder"><?= htmlspecialchars($item['nom']) ?></span>
                                <div class="reorder-actions">
                                    <button onclick="moveItem(this, 'up')" class="btn-move btn-sm">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"></polyline></svg>
                                    </button>
                                    <button onclick="moveItem(this, 'down')" class="btn-move btn-sm">
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

    <div id="toast" class="toast">Ordre mis à jour !</div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 2000);
        }

        // --- LOGIQUE DE DÉPLACEMENT ---
        function moveCategory(btn, direction) {
            const currentBlock = btn.closest('.category-item');
            const container = document.getElementById('categories-container');
            
            if (direction === 'up') {
                const prev = currentBlock.previousElementSibling;
                if (prev) {
                    container.insertBefore(currentBlock, prev);
                    saveOrder(container, 'category');
                }
            } else {
                const next = currentBlock.nextElementSibling;
                if (next) {
                    container.insertBefore(next, currentBlock);
                    saveOrder(container, 'category');
                }
            }
        }

        function moveItem(btn, direction) {
            const currentRow = btn.closest('.item-item');
            const container = btn.closest('.items-reorder-list');
            
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

        // --- SAUVEGARDE EN ARRIÈRE-PLAN (AJAX) ---
        async function saveOrder(container, type) {
            let items;
            if (type === 'category') {
                items = container.querySelectorAll('.category-item');
            } else {
                items = container.querySelectorAll('.item-item');
            }

            const ids = Array.from(items).map(el => el.getAttribute('data-id'));

            const formData = new FormData();
            formData.append('type', type);
            ids.forEach(id => formData.append('ids[]', id));

            try {
                const response = await fetch('../actions/update_order.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) { showToast('Ordre sauvegardé !'); }
            } catch (error) {
                showToast('Erreur de connexion.');
            }
        }

                // Enregistrement du Service Worker pour la PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then(registration => {
                        console.log('ServiceWorker enregistré:', registration.scope);
                    })
                    .catch(error => {
                        console.log('Échec PWA:', error);
                    });
            });
        }
    </script>

</body>
</html>