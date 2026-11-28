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
$item_id = $_GET['id'] ?? null;
$cat_id = $_GET['cat_id'] ?? null;

$item = [
    'nom' => '',
    'description' => '',
    'prix' => '',
    'image_url' => '',
    'category_id' => $cat_id
];

if ($item_id) {
    $stmt = $pdo->prepare("SELECT i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE i.id = ? AND c.restaurant_id = ?");
    $stmt->execute([$item_id, $resto_id]);
    $item = $stmt->fetch();
    if (!$item) {
        die("Plat introuvable.");
    }
}

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
    <title><?= $item_id ? 'Modifier' : 'Ajouter' ?> un plat</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/1apple-touch-icon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js" data-turbo-track="reload"></script>
</head>
<body data-turbo="true">

    <header class="admin-header">
        <div class="header-brand">
            <span class="header-eyebrow">Carte &amp; Produits</span>
            <h1><?= $item_id ? 'Modifier le plat' : 'Nouveau plat' ?></h1>
        </div>
        <div class="header-actions">
            <a href="admin.php<?= !empty($item['category_id']) ? '?cat_id=' . (int)$item['category_id'] : '' ?>" class="btn-back-pill">Annuler</a>
        </div>
    </header>

    <main class="container">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'upload_failed'): ?>
            <div class="feedback-banner feedback-danger">
                L'image n'a pas pu être envoyée. Vérifiez votre connexion et réessayez.
            </div>
        <?php endif; ?>

        <form id="item-form" action="../actions/save_item.php" method="POST" enctype="multipart/form-data" class="app-card stack-form">

            <?php if ($item_id): ?>
                <input type="hidden" name="item_id" value="<?= (int)$item_id ?>">
            <?php endif; ?>

            <div class="field-group">
                <label class="field-label" for="category_id">Catégorie</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (string)$item['category_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-group">
                <label class="field-label" for="nom">Nom du plat</label>
                <input type="text" id="nom" name="nom" class="form-control" value="<?= htmlspecialchars($item['nom']) ?>" required placeholder="Ex. : Pizza Margherita">
            </div>

            <div class="field-group">
                <label class="field-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control" placeholder="Ingrédients, accompagnement..."><?= htmlspecialchars($item['description']) ?></textarea>
            </div>

            <div class="field-group">
                <label class="field-label" for="prix">Prix (DZD)</label>
                <input type="number" id="prix" name="prix" class="form-control" value="<?= htmlspecialchars($item['prix']) ?>" required placeholder="Ex. : 800" step="50" inputmode="numeric">
            </div>

            <div class="field-group">
                <span class="field-label">Photo</span>

                <div class="image-preview-box">
                    <img id="image-preview" src="<?= htmlspecialchars($item['image_url'] ?: '') ?>" alt="Aperçu du plat" style="display: <?= !empty($item['image_url']) ? 'block' : 'none' ?>;">
                    <span id="no-image-text" class="image-preview-text" style="display: <?= !empty($item['image_url']) ? 'none' : 'inline' ?>;">Aucune image sélectionnée</span>
                </div>

                <div class="photo-action-center">
                    <label class="btn-upload-pill" style="margin-top:0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                        Choisir une photo
                        <input type="file" name="image_camera" accept="image/*" style="display:none;" onchange="previewImage(event)">
                    </label>
                </div>

                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($item['image_url']) ?>">
            </div>

            <button type="submit" class="btn-primary btn-block">Enregistrer le plat</button>
        </form>
    </main>

    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.onload = function () {
                    const canvas = document.createElement('canvas');
                    const MAX_WIDTH = 1000;
                    const MAX_HEIGHT = 1000;
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > MAX_WIDTH) {
                            height *= MAX_WIDTH / width;
                            width = MAX_WIDTH;
                        }
                    } else if (height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    const compressedBase64 = canvas.toDataURL('image/jpeg', 0.8);

                    const output = document.getElementById('image-preview');
                    const noImageText = document.getElementById('no-image-text');
                    if (output) {
                        output.src = compressedBase64;
                        output.style.display = 'block';
                    }
                    if (noImageText) {
                        noImageText.style.display = 'none';
                    }

                    fetch(compressedBase64)
                        .then(res => res.blob())
                        .then(blob => {
                            const compressedFile = new File([blob], "image.jpg", { type: "image/jpeg" });
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(compressedFile);
                            event.target.files = dataTransfer.files;
                        });
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        document.addEventListener("turbo:load", function () {
            const form = document.getElementById('item-form');
            if (form) {
                form.addEventListener('submit', function () {
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.classList.add('btn-loading');
                        btn.innerHTML = '<span class="spinner"></span> Enregistrement...';
                    }
                });
            }

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('service-worker.js')
                    .catch(error => console.log('Échec PWA:', error));
            }
        });
    </script>
</body>
</html>