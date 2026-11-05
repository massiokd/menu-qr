<?php
require 'config/db.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Menu - <?= htmlspecialchars($resto['nom']) ?></title>
    
    <!-- Notre fichier CSS personnalisé -->
    <link rel="stylesheet" href="style.css">
    
    <!-- Librairie d'animations au scroll (AOS) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>

    <!-- En-tête du restaurant -->
    <header class="header">
        <?php if($resto['logo_url']): ?>
            <img src="<?= htmlspecialchars($resto['logo_url']) ?>" alt="Logo" class="restaurant-logo">
        <?php endif; ?>
        <h1 class="restaurant-name"><?= htmlspecialchars($resto['nom']) ?></h1>
        <p class="restaurant-subtitle">Bienvenue ! Découvrez notre carte.</p>
    </header>

    <!-- Barre de navigation des catégories (Sticky) -->
    <nav class="category-nav">
        <ul class="nav-list">
            <?php foreach ($categories as $index => $cat): ?>
                <li>
                    <a href="#cat-<?= $cat['id'] ?>" 
                       data-target="cat-<?= $cat['id'] ?>"
                       class="category-link <?= $index === 0 ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['nom']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Contenu du menu -->
    <main class="main-content">
        <?php foreach ($categories as $cat): ?>
            <div id="cat-<?= $cat['id'] ?>" class="category-section">
                <h2 class="category-title"><?= htmlspecialchars($cat['nom']) ?></h2>

                <?php
                $stmtItems = $pdo->prepare("SELECT * FROM items WHERE category_id = ? ORDER BY ordre ASC");
                $stmtItems->execute([$cat['id']]);
                $items = $stmtItems->fetchAll();
                ?>

                <div class="items-list">
                    <?php foreach ($items as $item): ?>
                        <!-- Carte du plat -->
                        <div class="menu-card <?= !$item['disponible'] ? 'unavailable' : '' ?>" data-aos="fade-up" data-aos-duration="500">
                            
                            <!-- Textes -->
                            <div class="item-details">
                                <h3 class="item-name"><?= htmlspecialchars($item['nom']) ?></h3>
                                <p class="item-desc"><?= htmlspecialchars($item['description']) ?></p>
                                <div class="item-bottom">
                                    <span class="item-price">
                                        <?= number_format($item['prix'], 0, ',', ' ') ?> <span>DZD</span>
                                    </span>
                                    <?php if(!$item['disponible']): ?>
                                        <span class="badge-out">Épuisé</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Image (Carré arrondi) -->
                            <?php if($item['image_url']): ?>
                                <div class="item-image-container">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                         loading="lazy" 
                                         alt="<?= htmlspecialchars($item['nom']) ?>">
                                    <?php if(!$item['disponible']): ?>
                                        <div class="image-overlay"></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- Pied de page -->
    <footer class="footer">
        <p>Propulsé par Fennec Tech 🦊</p>
    </footer>

    <!-- Script pour les animations au scroll -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Script pour la navigation dynamique des catégories -->
    <script>
        AOS.init({ once: true, offset: 50 });

        const links = document.querySelectorAll('.category-link');
        const sections = document.querySelectorAll('.category-section');

        window.addEventListener('scroll', () => {
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (scrollY >= (sectionTop - 150)) {
                    current = section.getAttribute('id');
                }
            });

            links.forEach(link => {
                link.classList.remove('active');
                if (link.dataset.target === current) {
                    link.classList.add('active');
                    link.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
        });
    </script>
</body>
</html>