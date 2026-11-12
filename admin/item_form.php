<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];
$item_id = $_GET['id'] ?? null;
$cat_id = $_GET['cat_id'] ?? null;

$item = ['nom' => '', 'description' => '', 'prix' => '', 'image_url' => '', 'category_id' => $cat_id];

if ($item_id) {
    $stmt = $pdo->prepare("SELECT i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE i.id = ? AND c.restaurant_id = ?");
    $stmt->execute([$item_id, $resto_id]);
    $item = $stmt->fetch();
    if (!$item) die("Plat introuvable.");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $item_id ? 'Modifier' : 'Ajouter' ?> un plat</title>
     <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/android-chrome-192x192.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="admin-header">
        <h1><?= $item_id ? 'Modifier le plat' : 'Nouveau plat' ?></h1>
        <a href="admin.php" class="btn-logout">Annuler</a>
    </header>

    <main class="container">
                <!-- Affichage de l'erreur d'upload -->
        <?php if(isset($_GET['error']) && $_GET['error'] == 'upload_failed'): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border: 1px solid #fecaca; text-align: center; font-weight: bold;">
                ❌ L'image n'a pas pu être envoyée. <br>
                <span style="font-size: 0.85rem; font-weight: normal;">Vérifiez votre connexion internet et réessayez.</span>
            </div>
        <?php endif; ?>
        <form action="../actions/save_item.php" method="POST" enctype="multipart/form-data" class="admin-card">
            
            <?php if($item_id): ?>
                <input type="hidden" name="item_id" value="<?= $item_id ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Catégorie</label>
                <select name="category_id" class="form-control" required>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $item['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Nom du plat</label>
                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($item['nom']) ?>" required placeholder="Ex: Pizza Margherita">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" placeholder="Ingrédients..."><?= htmlspecialchars($item['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Prix (DZD)</label>
                <input type="number" name="prix" class="form-control" value="<?= htmlspecialchars($item['prix']) ?>" required placeholder="Ex: 800" step="50">
            </div>

            <div class="form-group">
                <label>Photo du plat</label>
                
                <div class="image-preview-box">
                    <img id="image-preview" src="<?= $item['image_url'] ?: '' ?>" style="display: <?= $item['image_url'] ? 'block' : 'none' ?>;">
                    <?php if(!$item['image_url']): ?>
                        <span id="no-image-text" class="image-preview-text">Aucune image sélectionnée</span>
                    <?php endif; ?>
                </div>

                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($item['image_url']) ?>">
                
                <div class="upload-buttons" style="display: flex; justify-content: center;">
                    <!-- Bouton unique : Ouvre le menu natif (Caméra ou Galerie) -->
                    <label class="btn-upload" style="flex-direction: column; padding: 2rem 1rem; width: 100%;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--brand-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 0.5rem;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                        <span style="font-size: 1rem; font-weight: 700; color: var(--text-dark);">Ajouter une photo</span>
                        <span style="font-size: 0.75rem; color: var(--text-gray); margin-top: 0.25rem;">Prenez une photo ou choisissez dans la galerie</span>
                        
                        <!-- L'input cache qui fait tout le travail -->
                        <input type="file" name="image_camera" accept="image/*" style="display:none;" onchange="previewImage(event)">
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-primary btn-block">Enregistrer le plat</button>
        </form>
    </main>

    <script>
        // Fonction pour compresser et prévisualiser l'image
        function previewImage(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    // 1. Création d'un canvas pour redimensionner
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
                    } else {
                        if (height > MAX_HEIGHT) {
                            width *= MAX_HEIGHT / height;
                            height = MAX_HEIGHT;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // 2. On compresse en JPEG à 80% de qualité
                    const compressedBase64 = canvas.toDataURL('image/jpeg', 0.8);

                    // 3. On affiche l'aperçu
                    const output = document.getElementById('image-preview');
                    const noImageText = document.getElementById('no-image-text');
                    output.src = compressedBase64;
                    output.style.display = 'block';
                    if (noImageText) noImageText.style.display = 'none';

                    // 4. On transforme le Base64 en Blob (fichier) pour l'envoi
                    fetch(compressedBase64)
                        .then(res => res.blob())
                        .then(blob => {
                            // On remplace le fichier original par le fichier compressé dans l'input
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

        // Script pour le bouton de soumission (Spinner)
        document.querySelector('form').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            if(btn) {
                btn.classList.add('btn-loading');
                btn.innerHTML = '<span class="spinner"></span> Enregistrement...';
            }
        });

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