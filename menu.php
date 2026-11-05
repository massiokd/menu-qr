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
    
    <!-- GSAP pour les animations Ultra Pro -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
</head>
<body data-theme="<?= htmlspecialchars($resto['theme'] ?? 'classique') ?>">


    <!-- Éléments d'arrière-plan -->
    <div class="bg-aurora">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>
    <div class="noise-overlay"></div>
    
    <!-- Barre de progression -->
    <div class="scroll-progress" id="progressBar"></div>

    <!-- En-tête -->
    <header class="header">
        <?php if($resto['logo_url']): ?>
            <img src="<?= htmlspecialchars($resto['logo_url']) ?>" alt="Logo" class="restaurant-logo" id="mainLogo">
        <?php endif; ?>
        <h1 class="restaurant-name" id="mainTitle"><?= htmlspecialchars($resto['nom']) ?></h1>
        <p class="restaurant-subtitle" id="mainSubtitle">Découvrez notre carte</p>
    </header>

    <!-- Navigation -->
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

    <!-- Contenu -->
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
        <p>Propulsé par Fennec Tech 🦊</p>
    </footer>

    <!-- SCRIPT ANIMATIONS & NAVIGATION -->
    <script>
        // 1. Initialisation de GSAP
        gsap.registerPlugin(ScrollTrigger);

        // Animation de l'en-tête au chargement
        gsap.from("#mainLogo", { duration: 1, y: 30, opacity: 0, ease: "power3.out", delay: 0.2 });
        gsap.from("#mainTitle", { duration: 1, y: 20, opacity: 0, ease: "power3.out", delay: 0.3 });
        gsap.from("#mainSubtitle", { duration: 1, y: 20, opacity: 0, ease: "power3.out", delay: 0.4 });

        // Animation en cascade des plats au scroll
        const cards = document.querySelectorAll('.menu-card');
        cards.forEach((card, index) => {
            gsap.from(card, {
                scrollTrigger: {
                    trigger: card,
                    start: "top 90%",
                    toggleActions: "play none none none"
                },
                duration: 0.8,
                y: 40,
                opacity: 0,
                ease: "power2.out",
                delay: index % 3 * 0.1 // Crée un effet de cascade
            });
        });

        // 2. Barre de progression au scroll
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById("progressBar").style.width = scrolled + "%";
        });

        // 3. Surbrillance du menu (Scrollspy)
        const links = document.querySelectorAll('.category-link');
        const sections = document.querySelectorAll('.category-section');

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (scrollY >= (sectionTop - 150)) current = section.getAttribute('id');
            });

            links.forEach(link => {
                link.classList.remove('active');
                if (link.dataset.target === current) {
                    link.classList.add('active');
                    link.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
        });

        // 4. Effet de TILT 3D (Vanilla JS)
        cards.forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                // Calcul de l'inclinaison
                const rotateX = ((y - centerY) / centerY) * -5;
                const rotateY = ((x - centerX) / centerX) * 5;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
            });
        });
    </script>
</body>
</html>