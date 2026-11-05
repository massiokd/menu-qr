<?php
require 'config/db.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) { die("Menu introuvable."); }

$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE slug = ? AND is_active = true AND (plan = 'lifetime' OR expires_at > NOW())");
$stmt->execute([$slug]);
$resto = $stmt->fetch();

if (!$resto) { 
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Menu indisponible</title><script src="https://cdn.tailwindcss.com"></script><style>body{background:#fdfbf7;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;font-family:sans-serif;}.error-box{background:white;padding:2.5rem;border-radius:1.5rem;box-shadow:0 10px 30px rgba(0,0,0,0.1);text-align:center;max-width:350px;width:90%;}</style></head><body><div class="error-box"><div class="text-5xl mb-4">🦊</div><h1 class="text-xl font-bold text-gray-800 mb-2">Menu indisponible</h1><p class="text-sm text-gray-500">Ce menu est temporairement suspendu ou n\'existe pas.</p></div></body></html>';
    exit;
}

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
    <link rel="stylesheet" href="style.css">
</head>
<body data-theme="<?= htmlspecialchars($resto['theme'] ?? 'classique') ?>">

    <div class="bg-aurora">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>
    <div class="noise-overlay"></div>
    
    <div class="scroll-progress" id="progressBar"></div>

    <header class="header">
        <?php if($resto['logo_url']): ?>
            <img src="<?= htmlspecialchars($resto['logo_url']) ?>" alt="Logo" class="restaurant-logo">
        <?php endif; ?>
        <h1 class="restaurant-name"><?= htmlspecialchars($resto['nom']) ?></h1>
        <p class="restaurant-subtitle">Découvrez notre carte</p>
    </header>

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
                        <div class="menu-card <?= !$item['disponible'] ? 'unavailable' : '' ?>">
                            
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

                            <?php if($item['image_url']): ?>
                                <div class="item-image-container">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" loading="lazy" alt="<?= htmlspecialchars($item['nom']) ?>">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <footer class="footer">
        <p>Besoin d'aide ? Appelez-nous au <a href="tel:+213559322518" class="phone-link">+213 559 32 25 18</a></p>
        <p style="margin-top: 1rem; opacity: 0.6; font-size: 0.65rem;">Propulsé par Fennec Tech 🦊</p>
    </footer>

    <script>
        // Barre de progression au scroll (optimisé mobile)
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById("progressBar").style.width = scrolled + "%";
        });

        // Surbrillance du menu (Scrollspy)
        const links = document.querySelectorAll('.category-link');
        const sections = document.querySelectorAll('.category-section');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.getAttribute('id');
                    links.forEach(link => {
                        link.classList.remove('active');
                        if (link.dataset.target === id) {
                            link.classList.add('active');
                            // Centre le lien actif dans la barre de nav
                            link.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        }
                    });
                }
            });
        }, { rootMargin: "-150px 0px -50% 0px" });

        sections.forEach(section => observer.observe(section));
    </script>
</body>
</html>