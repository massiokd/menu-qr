<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmtResto = $pdo->prepare("SELECT nom, slug FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

// URL de ton site (à adapter si l'URL Render change)
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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 pb-20">
    
    <header class="bg-white shadow-sm mb-6 sticky top-0 z-10">
        <div class="max-w-3xl mx-auto p-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">Admin : <?= htmlspecialchars($resto['nom']) ?></h1>
            <a href="logout.php" class="text-red-500 font-bold text-sm">Déconnexion</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto p-4">
        
        <!-- SECTION QR CODE -->
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100 mb-8 flex items-center gap-6">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($menu_url) ?>" alt="QR Code" class="w-32 h-32 border p-2 rounded">
            <div>
                <h2 class="text-lg font-bold text-gray-900 mb-2">Votre QR Code</h2>
                <p class="text-sm text-gray-500 mb-3">Imprimez ce code et collez-le sur vos tables. Vos clients le scanneront pour voir votre menu.</p>
                <a href="<?= htmlspecialchars($menu_url) ?>" target="_blank" class="text-blue-600 font-bold text-sm underline break-all"><?= htmlspecialchars($menu_url) ?></a>
            </div>
        </div>

        <!-- LISTE DES CATEGORIES ET PLATS -->
        <?php foreach ($categories as $cat): ?>
            <div class="mb-8">
                <h2 class="text-lg font-bold text-gray-900 mb-4 bg-gray-200 p-2 rounded"><?= htmlspecialchars($cat['nom']) ?></h2>
                
                <?php
                $stmtItems = $pdo->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY ordre ASC");
                $stmtItems->execute([$cat['id']]);
                $items = $stmtItems->fetchAll();
                ?>

                <div class="space-y-3 mb-4">
                    <?php foreach ($items as $item): ?>
                        <div class="flex items-center gap-4 bg-white p-3 rounded-lg shadow-sm border border-gray-100">
                            <div class="flex-1 font-semibold"><?= htmlspecialchars($item['nom']) ?></div>
                            
                            <div class="flex items-center gap-1">
                                <input type="number" id="prix-<?= $item['id'] ?>" value="<?= $item['prix'] ?>" class="w-20 border rounded px-2 py-1 text-sm" step="50">
                                <span class="text-sm text-gray-500">DZD</span>
                                <button onclick="updatePrix(<?= $item['id'] ?>)" class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600">OK</button>
                            </div>

                            <button onclick="toggleDispo(<?= $item['id'] ?>, this)" 
                                class="px-3 py-1 rounded text-xs font-bold text-white <?= $item['disponible'] ? 'bg-green-500' : 'bg-red-500' ?>">
                                <?= $item['disponible'] ? 'Disponible' : 'Épuisé' ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- FORMULAIRE D'AJOUT DE PLAT -->
                <details class="bg-gray-100 p-3 rounded-lg text-sm">
                    <summary class="cursor-pointer font-bold text-blue-600">+ Ajouter un plat dans "<?= htmlspecialchars($cat['nom']) ?>"</summary>
                    <form action="add_item.php" method="POST" class="mt-3 flex flex-col gap-2">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                        <input type="text" name="nom" placeholder="Nom du plat (ex: Coca-Cola)" required class="border p-2 rounded">
                        <input type="text" name="description" placeholder="Description (optionnel)" class="border p-2 rounded">
                        <div class="flex gap-2">
                            <input type="number" name="prix" placeholder="Prix en DZD" step="50" required class="border p-2 rounded w-32">
                            <button type="submit" class="bg-green-600 text-white font-bold px-4 py-2 rounded hover:bg-green-700">Ajouter</button>
                        </div>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- SCRIPT AJAX -->
    <script>
        async function updatePrix(itemId) {
            const newPrix = document.getElementById('prix-' + itemId).value;
            const formData = new FormData();
            formData.append('action', 'update_prix');
            formData.append('item_id', itemId);
            formData.append('prix', newPrix);
            try {
                const response = await fetch('update_item.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) { alert('Prix mis à jour !'); }
            } catch (error) { alert('Erreur.'); }
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
                        btn.classList.replace('bg-red-500', 'bg-green-500');
                    } else {
                        btn.textContent = 'Épuisé';
                        btn.classList.replace('bg-green-500', 'bg-red-500');
                    }
                }
            } catch (error) { alert('Erreur.'); }
        }
    </script>
</body>
</html>