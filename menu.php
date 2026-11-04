<?php
require 'config/db.php';

// On récupère le slug dans l'URL (ex: menu.php?slug=pizzeria-test)
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    die("Menu introuvable (slug manquant).");
}

$pdo = getDB();

// 1. Récupérer le restaurant
$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE slug = ? AND is_active = true AND (plan = 'lifetime' OR expires_at > NOW())");
$stmt->execute([$slug]);
$resto = $stmt->fetch();

if (!$resto) {
    die("Ce restaurant n'existe pas ou son abonnement a expiré.");
}

// 2. Récupérer les catégories
$stmtCat = $pdo->prepare("SELECT * FROM categories WHERE restaurant_id = ? AND is_active = true ORDER BY ordre ASC");
$stmtCat->execute([$resto['id']]);
$categories = $stmtCat->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - <?= htmlspecialchars($resto['nom']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

    <!-- En-tête du restaurant -->
    <header class="bg-white shadow-sm sticky top-0 z-10">
        <div class="max-w-md mx-auto p-4 flex items-center gap-4">
            <?php if($resto['logo_url']): ?>
                <img src="<?= htmlspecialchars($resto['logo_url']) ?>" alt="Logo" class="w-12 h-12 rounded-full object-cover">
            <?php endif; ?>
            <div>
                <h1 class="text-xl font-bold"><?= htmlspecialchars($resto['nom']) ?></h1>
                <?php if($resto['telephone']): ?>
                    <a href="tel:<?= htmlspecialchars($resto['telephone']) ?>" class="text-sm text-blue-600">Appeler le serveur</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Contenu du menu -->
    <main class="max-w-md mx-auto p-4 pb-20">
        <?php if (empty($categories)): ?>
            <p class="text-center text-gray-500 mt-10">Le menu est en cours de préparation.</p>
        <?php endif; ?>

        <?php foreach ($categories as $cat): ?>
            <div class="mb-8">
                <h2 class="text-lg font-bold text-gray-900 border-b-2 border-orange-500 pb-2 mb-4">
                    <?= htmlspecialchars($cat['nom']) ?>
                </h2>

                <?php
                // Récupérer les items de cette catégorie
                $stmtItems = $pdo->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY ordre ASC");
                $stmtItems->execute([$cat['id']]);
                $items = $stmtItems->fetchAll();
                ?>

                <div class="space-y-4">
                    <?php foreach ($items as $item): ?>
                        <div class="flex gap-4 bg-white p-3 rounded-lg shadow-sm <?= !$item['disponible'] ? 'opacity-50' : '' ?>">
                            <?php if($item['image_url']): ?>
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" class="w-20 h-20 rounded-md object-cover">
                            <?php endif; ?>
                            
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($item['nom']) ?></h3>
                                    <span class="font-bold text-orange-600"><?= number_format($item['prix'], 0, ',', ' ') ?> DZD</span>
                                </div>
                                <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($item['description']) ?></p>
                                <?php if(!$item['disponible']): ?>
                                    <span class="text-xs text-red-500 font-bold mt-1 inline-block">Épuisé</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

</body>
</html>