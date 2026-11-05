<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

// Récupérer les infos du resto
$stmtResto = $pdo->prepare("SELECT nom FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

// Récupérer les catégories
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
<body class="bg-gray-50 text-gray-800">
    
    <header class="bg-white shadow-sm mb-6">
        <div class="max-w-3xl mx-auto p-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">Admin : <?= htmlspecialchars($resto['nom']) ?></h1>
            <a href="logout.php" class="text-red-500 font-bold text-sm">Déconnexion</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto p-4">
        <?php foreach ($categories as $cat): ?>
            <div class="mb-8">
                <h2 class="text-lg font-bold text-gray-900 mb-4 bg-gray-200 p-2 rounded"><?= htmlspecialchars($cat['nom']) ?></h2>
                
                <?php
                $stmtItems = $pdo->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY ordre ASC");
                $stmtItems->execute([$cat['id']]);
                $items = $stmtItems->fetchAll();
                ?>

                <div class="space-y-3">
                    <?php foreach ($items as $item): ?>
                        <div class="flex items-center gap-4 bg-white p-3 rounded-lg shadow-sm border border-gray-100">
                            
                            <!-- Nom du plat -->
                            <div class="flex-1 font-semibold"><?= htmlspecialchars($item['nom']) ?></div>
                            
                            <!-- Modification du prix -->
                            <div class="flex items-center gap-1">
                                <input type="number" id="prix-<?= $item['id'] ?>" value="<?= $item['prix'] ?>" class="w-20 border rounded px-2 py-1 text-sm" step="50">
                                <span class="text-sm text-gray-500">DZD</span>
                                <button onclick="updatePrix(<?= $item['id'] ?>)" class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600">OK</button>
                            </div>

                            <!-- Bouton de disponibilité -->
                            <button onclick="toggleDispo(<?= $item['id'] ?>, this)" 
                                class="px-3 py-1 rounded text-xs font-bold text-white <?= $item['disponible'] ? 'bg-green-500' : 'bg-red-500' ?>">
                                <?= $item['disponible'] ? 'Disponible' : 'Épuisé' ?>
                            </button>

                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- SCRIPT AJAX (Vanilla JS) -->
    <script>
        // Fonction pour changer le prix
        async function updatePrix(itemId) {
            const newPrix = document.getElementById('prix-' + itemId).value;
            const formData = new FormData();
            formData.append('action', 'update_prix');
            formData.append('item_id', itemId);
            formData.append('prix', newPrix);

            try {
                const response = await fetch('update_item.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) {
                    alert('Prix mis à jour avec succès !');
                } else {
                    alert('Erreur : ' + result.message);
                }
            } catch (error) {
                alert('Erreur de connexion.');
            }
        }

        // Fonction pour basculer la disponibilité
        async function toggleDispo(itemId, btn) {
            const formData = new FormData();
            formData.append('action', 'toggle_dispo');
            formData.append('item_id', itemId);

            try {
                const response = await fetch('update_item.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if(result.success) {
                    // On change le texte et la couleur du bouton sans recharger la page
                    if(result.new_status) {
                        btn.textContent = 'Disponible';
                        btn.classList.remove('bg-red-500');
                        btn.classList.add('bg-green-500');
                    } else {
                        btn.textContent = 'Épuisé';
                        btn.classList.remove('bg-green-500');
                        btn.classList.add('bg-red-500');
                    }
                }
            } catch (error) {
                alert('Erreur de connexion.');
            }
        }
    </script>
</body>
</html>