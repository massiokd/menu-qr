<?php
require 'config/db.php';

// Fonction globale pour afficher une erreur propre
function showError($message) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>Menu introuvable - FenneTech</title>
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
        <link rel="stylesheet" href="assets/css/menu.css">
        <style>
            .error-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; position: relative; z-index: 10; }
            .error-card { background: var(--card); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 1.5rem; padding: 3rem 2rem; text-align: center; max-width: 400px; width: 100%; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
            .error-icon { width: 80px; height: 80px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
            .error-title { font-family: var(--font-title); font-size: 1.8rem; font-weight: 700; color: var(--text); margin-bottom: 0.75rem; }
            .error-message { color: var(--gray); font-size: 0.95rem; line-height: 1.5; margin-bottom: 2rem; }
            .error-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; background: var(--brand); color: white; text-decoration: none; padding: 1rem 1.5rem; border-radius: 0.75rem; font-weight: 700; font-size: 0.95rem; transition: all 0.2s; width: 100%; }
            .error-btn:hover { opacity: 0.9; transform: translateY(-2px); }
            @keyframes slideUpFade { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        </style>
    </head>
    <body data-theme="classique" data-font="Plus Jakarta Sans">
        <div class="bg-aurora"><div class="blob blob-1"></div><div class="blob blob-2"></div></div>
        <div class="noise-overlay"></div>
        <div class="error-page">
            <div class="error-card">
                <div class="error-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </div>
                <h1 class="error-title">Oups, menu introuvable</h1>
                <p class="error-message"><?= htmlspecialchars($message) ?></p>
                <a href="https://wa.me/213559322518" target="_blank" class="error-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                    Contacter le support
                </a>
                <p style="margin-top: 1.5rem; font-size: 0.75rem; color: var(--gray);">Propulsé par <a href="https://fennec.tech" style="color: var(--brand); text-decoration: none; font-weight: bold;">FenneTech</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$slug = $_GET['slug'] ?? '';
if (empty($slug)) { showError("Aucun identifiant de restaurant n'a été fourni dans le lien."); }

$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE slug = ? AND is_active = true AND (plan = 'lifetime' OR expires_at > NOW() OR subscription_status = 'pending_verification')");
$stmt->execute([$slug]);
$resto = $stmt->fetch();

if (!$resto) { showError("Ce menu n'existe pas, a été déplacé, ou l'abonnement du restaurant a expiré."); }

// 1. Enregistrer le scan
$stmtScan = $pdo->prepare("INSERT INTO menu_scans (restaurant_id) VALUES (?)");
$stmtScan->execute([$resto['id']]);

$stmtCount = $pdo->prepare("UPDATE restaurants SET scan_count = scan_count + 1 WHERE id = ?");
$stmtCount->execute([$resto['id']]);
$resto['scan_count']++; 

// 2. Récupérer les catégories
$stmtCat = $pdo->prepare("SELECT * FROM categories WHERE restaurant_id = ? AND is_active = true ORDER BY ordre ASC");
$stmtCat->execute([$resto['id']]);
$categories = $stmtCat->fetchAll();

// 3. Récupérer TOUS les plats en UNE SEULE requête
$stmtAllItems = $pdo->prepare("
    SELECT items.*, categories.id as cat_id 
    FROM items 
    JOIN categories ON items.category_id = categories.id 
    WHERE categories.restaurant_id = ? AND categories.is_active = true
    ORDER BY items.ordre ASC
");
$stmtAllItems->execute([$resto['id']]);
$all_items = $stmtAllItems->fetchAll();

// 4. Regrouper les plats par catégorie
$items_by_category = [];
foreach ($all_items as $item) {
    $items_by_category[$item['cat_id']][] = $item;
}

// 5. Préparer la première catégorie active
$first_category_id = null;
foreach ($categories as $cat) {
    if (!empty($items_by_category[$cat['id']])) {
        $first_category_id = $cat['id'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Menu - <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <link rel="stylesheet" href="assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-theme="<?= htmlspecialchars($resto['theme'] ?? 'classique') ?>" data-font="<?= htmlspecialchars($resto['font_family'] ?? 'Plus Jakarta Sans') ?>">

    <div class="bg-aurora">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>
    <div class="noise-overlay"></div>

    <header class="header">
        <?php if($resto['logo_url']): ?>
            <img src="<?= htmlspecialchars($resto['logo_url']) ?>" alt="Logo" class="restaurant-logo">
        <?php endif; ?>
        <h1 class="restaurant-name"><?= htmlspecialchars($resto['nom']) ?></h1>
        <p class="restaurant-subtitle"><?= htmlspecialchars($resto['slogan'] ?? 'Découvrez notre carte') ?></p>
    </header>

    <div class="sticky-container">
        <div class="client-search">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="clientSearch" placeholder="Rechercher un plat..." onkeyup="filterMenu()">
            <button type="button" onclick="clearSearch()" id="clearBtn" style="display:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <div class="category-tabs-wrapper" id="tabsWrapper">
            <div class="category-tabs">
                <?php foreach ($categories as $cat): 
                    $count = count($items_by_category[$cat['id']] ?? []);
                    if ($count === 0) continue;
                ?>
                    <button class="tab-btn <?= $first_category_id == $cat['id'] ? 'active' : '' ?>" 
                            data-id="<?= $cat['id'] ?>"
                            onclick="switchTab(<?= $cat['id'] ?>)">
                        <?= htmlspecialchars($cat['nom']) ?> 
                        <span class="tab-count"><?= $count ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <main class="main-content">
        <div id="noResultMsg" style="display:none; text-align:center; padding:3rem 1rem; color:var(--gray);">
            <p style="font-size:3rem; margin-bottom:1rem;">🦊</p>
            <p style="font-weight:600; font-size:1.1rem;">Aucun plat ne correspond à votre recherche.</p>
            <button onclick="clearSearch()" style="margin-top:1rem; background:var(--brand); color:white; border:none; padding:0.6rem 1.2rem; border-radius:0.5rem; font-weight:bold; cursor:pointer;">Voir tout le menu</button>
        </div>

        <?php foreach ($categories as $cat): 
            $items = $items_by_category[$cat['id']] ?? [];
            if (empty($items)) continue;
        ?>
            <div id="cat-<?= $cat['id'] ?>" class="category-section <?= $first_category_id == $cat['id'] ? 'active' : '' ?>">
                <h2 class="category-title"><?= htmlspecialchars($cat['nom']) ?></h2>

                <div class="items-list">
                    <?php foreach ($items as $item): ?>
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

    <footer class="footer">
        <?php if(!empty($resto['instagram_url']) || !empty($resto['facebook_url'])): ?>
            <div style="display:flex; justify-content:center; gap:1rem; margin-bottom:1.5rem;">
                <?php if(!empty($resto['instagram_url'])): ?>
                    <a href="<?= htmlspecialchars($resto['instagram_url']) ?>" target="_blank" style="color:var(--gray);"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
                <?php endif; ?>
                <?php if(!empty($resto['facebook_url'])): ?>
                    <a href="<?= htmlspecialchars($resto['facebook_url']) ?>" target="_blank" style="color:var(--gray);"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="footer-contact">
            <a href="https://wa.me/213559322518" target="_blank" class="contact-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                Support FenneTech
            </a>
        </div>
        <div class="footer-brand">
            <p style="margin-bottom: 0.75rem;">Propulsé par</p>
            <a href="https://fennetech.pages.dev/" target="_blank" style="display: inline-block; transition: opacity 0.3s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                                <img src="assets/images/FenneTechLogo.png" alt="FenneTech" style="height: 50px; width: auto; display: block;">
            </a>
        </div>
    </footer>

    <button id="backToTop" class="back-to-top" aria-label="Retour en haut">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
    </button>

    <script>
        let currentCategoryId = <?= $first_category_id ?? 'null' ?>;

        function switchTab(catId) {
            currentCategoryId = catId;
            const searchInput = document.getElementById('clientSearch');
            if (searchInput.value.trim() !== '') {
                searchInput.value = '';
                document.getElementById('clearBtn').style.display = 'none';
            }
            document.getElementById('tabsWrapper').style.display = 'block';
            document.getElementById('noResultMsg').style.display = 'none';

            document.querySelectorAll('.category-section').forEach(sec => {
                sec.classList.remove('active');
                sec.style.display = ''; 
                sec.querySelectorAll('.menu-card').forEach(item => {
                    item.style.display = 'flex';
                    item.classList.add('active');
                });
            });

            const target = document.getElementById('cat-' + catId);
            if(target) target.classList.add('active');

            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
                if (b.getAttribute('data-id') == catId) {
                    b.classList.add('active');
                    b.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function filterMenu() {
            const query = document.getElementById('clientSearch').value.toLowerCase().trim();
            const clearBtn = document.getElementById('clearBtn');
            const tabsWrapper = document.getElementById('tabsWrapper');
            const sections = document.querySelectorAll('.category-section');
            const noResultMsg = document.getElementById('noResultMsg');
            
            clearBtn.style.display = query.length > 0 ? 'flex' : 'none';

            if (query === '') {
                switchTab(currentCategoryId);
                return;
            }

            tabsWrapper.style.display = 'none';
            let hasResults = false;

            sections.forEach(sec => {
                let sectionHasVisibleItem = false;
                const items = sec.querySelectorAll('.menu-card');
                
                items.forEach(item => {
                    const name = item.querySelector('.item-name').textContent.toLowerCase();
                    const desc = item.querySelector('.item-desc').textContent.toLowerCase();
                    
                    if (name.includes(query) || desc.includes(query)) {
                        item.style.display = 'flex';
                        item.classList.add('active'); 
                        sectionHasVisibleItem = true;
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (sectionHasVisibleItem) {
                    sec.style.display = 'block'; 
                    sec.classList.remove('active'); 
                } else {
                    sec.style.display = 'none';
                    sec.classList.remove('active');
                }
            });

            noResultMsg.style.display = hasResults ? 'none' : 'block';
        }

        function clearSearch() {
            document.getElementById('clientSearch').value = '';
            filterMenu();
        }

        document.querySelectorAll('.category-section.active .reveal').forEach(el => el.classList.add('active'));

        const backToTopBtn = document.getElementById("backToTop");
        window.addEventListener("scroll", () => {
            if (window.scrollY > 300) { backToTopBtn.classList.add("show"); } 
            else { backToTopBtn.classList.remove("show"); }
        });
        backToTopBtn.addEventListener("click", () => { window.scrollTo({ top: 0, behavior: "smooth" }); });
    </script>
</body>
</html>