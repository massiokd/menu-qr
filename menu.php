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

// 5. Préparer la première catégorie active (celle qui a des plats)
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

    <!-- ZONE STICKY (Recherche + Onglets) -->
    <div class="sticky-container">
        
        <!-- Barre de recherche -->
        <div class="client-search">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="clientSearch" placeholder="Rechercher un plat..." onkeyup="filterMenu()">
            <button type="button" onclick="clearSearch()" id="clearBtn" style="display:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <!-- Onglets des catégories (On saute les vides) -->
        <div class="category-tabs-wrapper" id="tabsWrapper">
            <div class="category-tabs">
                <?php foreach ($categories as $cat): 
                    $count = count($items_by_category[$cat['id']] ?? []);
                    if ($count === 0) continue; // On ne crée pas d'onglet si c'est vide
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
        <!-- Message aucun résultat -->
        <div id="noResultMsg" style="display:none; text-align:center; padding:3rem 1rem; color:var(--gray);">
            <p style="font-size:3rem; margin-bottom:1rem;">🦊</p>
            <p style="font-weight:600; font-size:1.1rem;">Aucun plat ne correspond à votre recherche.</p>
            <button onclick="clearSearch()" style="margin-top:1rem; background:var(--brand); color:white; border:none; padding:0.6rem 1.2rem; border-radius:0.5rem; font-weight:bold; cursor:pointer;">Voir tout le menu</button>
        </div>

        <?php foreach ($categories as $cat): 
            $items = $items_by_category[$cat['id']] ?? [];
            if (empty($items)) continue; // On saute les sections vides
        ?>
            <div id="cat-<?= $cat['id'] ?>" class="category-section <?= $first_category_id == $cat['id'] ? 'active' : '' ?>">
                <h2 class="category-title"><?= htmlspecialchars($cat['nom']) ?></h2>

                <div class="items-list">
                    <?php foreach ($items as $item): ?>
                        <div class="menu-card reveal <?= !$item['disponible'] ? 'unavailable' : '' ?>">
                            <div class="item-details">
                                <h3 class="item-name" 
                                    data-fr="<?= htmlspecialchars($item['nom']) ?>" 
                                    data-ar="<?= htmlspecialchars($item['nom_ar'] ?: $item['nom']) ?>">
                                    <?= htmlspecialchars($item['nom']) ?>
                                </h3>
                                <p class="item-desc" 
                                   data-fr="<?= htmlspecialchars($item['description']) ?>" 
                                   data-ar="<?= htmlspecialchars($item['description_ar'] ?: $item['description']) ?>">
                                    <?= htmlspecialchars($item['description']) ?>
                                </p>
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

    <!-- Footer -->
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
    </footer>

    <button id="backToTop" class="back-to-top" aria-label="Retour en haut">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
    </button>

    <script>
        // ID de la catégorie active au départ
        let currentCategoryId = <?= $first_category_id ?? 'null' ?>;

        // --- GESTION DES ONGLETS ---
        function switchTab(catId) {
            currentCategoryId = catId;
            
            // 1. On réinitialise la recherche si on était en train de chercher
            const searchInput = document.getElementById('clientSearch');
            if (searchInput.value.trim() !== '') {
                searchInput.value = '';
                document.getElementById('clearBtn').style.display = 'none';
            }
            document.getElementById('tabsWrapper').style.display = 'block';
            document.getElementById('noResultMsg').style.display = 'none';

            // 2. On cache TOUTES les sections et on nettoie les styles inline
            document.querySelectorAll('.category-section').forEach(sec => {
                sec.classList.remove('active');
                sec.style.display = ''; // On efface le style inline pour laisser le CSS faire son travail
                
                // On réaffiche tous les plats et on force l'animation
                sec.querySelectorAll('.menu-card').forEach(item => {
                    item.style.display = 'flex';
                    item.classList.add('active');
                });
            });

            // 3. On affiche la section demandée
            const target = document.getElementById('cat-' + catId);
            if(target) {
                target.classList.add('active');
            }

            // 4. On met à jour l'onglet actif
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
                if (b.getAttribute('data-id') == catId) {
                    b.classList.add('active');
                    b.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // --- BARRE DE RECHERCHE ---
        function filterMenu() {
            const query = document.getElementById('clientSearch').value.toLowerCase().trim();
            const clearBtn = document.getElementById('clearBtn');
            const tabsWrapper = document.getElementById('tabsWrapper');
            const sections = document.querySelectorAll('.category-section');
            const noResultMsg = document.getElementById('noResultMsg');
            
            clearBtn.style.display = query.length > 0 ? 'flex' : 'none';

            // SI LA RECHERCHE EST VIDE : On retourne au fonctionnement normal (onglets)
            if (query === '') {
                switchTab(currentCategoryId);
                return;
            }

            // SI ON CHERCHE : On cache les onglets et on cherche partout
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
                        item.classList.add('active'); // On force l'affichage
                        sectionHasVisibleItem = true;
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // On gère l'affichage de la section
                if (sectionHasVisibleItem) {
                    sec.style.display = 'block'; // Style inline pour forcer l'affichage pendant la recherche
                    sec.classList.remove('active'); // On retire .active pour éviter les conflits
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

        // --- ANIMATIONS ---
        // On active les animations des éléments visibles au chargement
        document.querySelectorAll('.category-section.active .reveal').forEach(el => el.classList.add('active'));

        // --- BOUTON RETOUR EN HAUT ---
        const backToTopBtn = document.getElementById("backToTop");
        window.addEventListener("scroll", () => {
            if (window.scrollY > 300) { backToTopBtn.classList.add("show"); } 
            else { backToTopBtn.classList.remove("show"); }
        });
        backToTopBtn.addEventListener("click", () => { window.scrollTo({ top: 0, behavior: "smooth" }); });
    </script>
</body>
</html>