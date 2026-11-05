<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') {
    header('Location: login.php'); exit;
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];
$item_id = $_GET['id'] ?? null;
$cat_id = $_GET['cat_id'] ?? null; // Si on ajoute un nouveau plat

$item = [
    'nom' => '', 'description' => '', 'prix' => '', 'image_url' => '', 'category_id' => $cat_id
];

if ($item_id) {
    $stmt = $pdo->prepare("SELECT i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE i.id = ? AND c.restaurant_id = ?");
    $stmt->execute([$item_id, $resto_id]);
    $item = $stmt->fetch();
    if (!$item) die("Plat introuvable.");
}

// Récupérer les catégories pour le menu déroulant
$stmtCat = $pdo->prepare("SELECT * FROM categories WHERE restaurant_id = ? ORDER BY ordre ASC");
$stmtCat->execute([$resto_id]);
$categories = $stmtCat->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $item_id ? 'Modifier' : 'Ajouter' ?> un plat</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <header class="admin-header">
        <h1><?= $item_id ? 'Modifier le plat' : 'Nouveau plat' ?></h1>
        <a href="admin.php" class="btn-logout" style="background:#f3f4f6; color:#374151;">Annuler</a>
    </header>

    <main class="container" style="padding-top: 2rem;">
        <form action="save_item.php" method="POST" enctype="multipart/form-data" class="add-form" style="background: white; padding: 1.5rem; border-radius: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            
            <?php if($item_id): ?>
                <input type="hidden" name="item_id" value="<?= $item_id ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Catégorie</label>
                <select name="category_id" required style="width:100%; padding: 0.75rem; border:1px solid #e5e7eb; border-radius:0.5rem;">
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $item['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Nom du plat</label>
                <input type="text" name="nom" value="<?= htmlspecialchars($item['nom']) ?>" required placeholder="Ex: Pizza Margherita">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Ingrédients..." style="width:100%; padding: 0.75rem; border:1px solid #e5e7eb; border-radius:0.5rem; font-family:inherit; height:80px;"><?= htmlspecialchars($item['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Prix (DZD)</label>
                <input type="number" name="prix" value="<?= htmlspecialchars($item['prix']) ?>" required placeholder="Ex: 800" step="50">
            </div>

            <div class="form-group">
                <label>Photo du plat</label>
                <?php if($item['image_url']): ?>
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" style="width:100%; height:150px; object-fit:cover; border-radius:0.5rem; margin-bottom:1rem;">
                    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($item['image_url']) ?>">
                <?php endif; ?>
                
                <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                    <!-- Capture Caméra -->
                    <label class="btn-upload">
                        📸 Prendre une photo
                        <input type="file" name="image_camera" accept="image/*" capture="environment" style="display:none;">
                    </label>
                    <!-- Galerie -->
                    <label class="btn-upload">
                        🖼️ Galerie
                        <input type="file" name="image_gallery" accept="image/*" style="display:none;">
                    </label>
                </div>
                <p style="font-size:0.75rem; color:var(--text-gray); margin-top:0.5rem;">Laissez vide pour garder l'image actuelle.</p>
            </div>

            <button type="submit" class="btn-primary" style="width:100%; margin-top:1rem; padding:1rem; font-size:1rem;">Enregistrer le plat</button>
        </form>
    </main>
</body>
</html>