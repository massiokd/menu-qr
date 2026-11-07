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
// On incrémente le compteur de scans
// On enregistre le scan dans l'historique
$stmtScan = $pdo->prepare("INSERT INTO menu_scans (restaurant_id) VALUES (?)");
$stmtScan->execute([$resto['id']]);

// On met à jour le compteur global sur la table restaurants (pour aller plus vite)
$stmtCount = $pdo->prepare("UPDATE restaurants SET scan_count = scan_count + 1 WHERE id = ?");
$stmtCount->execute([$resto['id']]);
$resto['scan_count']++; // On met à jour la variable locale

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
    <link rel="stylesheet" href="assets/css/menu.css">
    <!-- Icônes FontAwesome pour le téléphone -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-theme="<?= htmlspecialchars($resto['theme'] ?? 'classique') ?>" data-font="<?= htmlspecialchars($resto['font_family'] ?? 'Plus Jakarta Sans') ?>">

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
        <p class="restaurant-subtitle"><?= htmlspecialchars($resto['slogan'] ?? 'Découvrez notre carte') ?></p>
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
                        <!-- La classe .reveal sert pour l'animation au scroll -->
                        <div class="menu-card reveal <?= !$item['disponible'] ? 'unavailable' : '' ?>">
                            
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

    <!-- Footer Propre et Discret -->
       <footer class="footer">
        <!-- Réseaux sociaux si remplis -->
        <?php if(!empty($resto['instagram_url']) || !empty($resto['facebook_url'])): ?>
            <div style="display:flex; justify-content:center; gap:1rem; margin-bottom:1.5rem;">
                <?php if(!empty($resto['instagram_url'])): ?>
                    <a href="<?= htmlspecialchars($resto['instagram_url']) ?>" target="_blank" style="color:var(--gray); transition:color 0.2s;" onmouseover="this.style.color='var(--brand)'" onmouseout="this.style.color='var(--gray)'">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    </a>
                <?php endif; ?>
                
                <?php if(!empty($resto['facebook_url'])): ?>
                    <a href="<?= htmlspecialchars($resto['facebook_url']) ?>" target="_blank" style="color:var(--gray); transition:color 0.2s;" onmouseover="this.style.color='var(--brand)'" onmouseout="this.style.color='var(--gray)'">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Ton numéro à toi (Fennec Tech) -->
        <div class="footer-contact">
            <a href="https://wa.me/213559322518" target="_blank" class="contact-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                Support Fennec Tech
            </a>
        </div>
        
        <div class="footer-brand">
            <p>Propulsé par</p>
            <a href="https://fennec.tech" target="_blank" class="brand-link">
                FENNEC TECH
            </a>
        </div>
    </footer>

    <!-- BOUTON RETOUR EN HAUT -->
    <button id="backToTop" class="back-to-top" aria-label="Retour en haut">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
    </button>

    <script>
        // 1. Barre de progression
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById("progressBar").style.width = scrolled + "%";
        });

        // 2. Menu Actif au scroll (Scrollspy)
        const links = document.querySelectorAll('.category-link');
        const sections = document.querySelectorAll('.category-section');

        const observerNav = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.getAttribute('id');
                    links.forEach(link => {
                        link.classList.remove('active');
                        if (link.dataset.target === id) {
                            link.classList.add('active');
                            link.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        }
                    });
                }
            });
        }, { rootMargin: "-150px 0px -50% 0px" });
        sections.forEach(section => observerNav.observe(section));

        // 3. ANIMATION MAGIQUE AU SCROLL (IntersectionObserver = ultra fluide sur mobile)
        const revealElements = document.querySelectorAll('.reveal');
        
        const observerReveal = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // On ajoute la classe 'active' pour déclencher l'animation CSS
                    entry.target.classList.add('active');
                    // On arrête d'observer une fois que l'élément est apparu
                    observerReveal.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: "0px 0px -50px 0px"
        });

        revealElements.forEach(el => observerReveal.observe(el));

        // --- BOUTON RETOUR EN HAUT ---
        const backToTopBtn = document.getElementById("backToTop");
        window.addEventListener("scroll", () => {
            if (window.scrollY > 300) {
                backToTopBtn.classList.add("show");
            } else {
                backToTopBtn.classList.remove("show");
            }
        });
        backToTopBtn.addEventListener("click", () => {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });

    </script>



</body>
</html>