<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

// Récupération des infos du restaurant
$stmtResto = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

// Récupération de l'email de l'utilisateur
$stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user_data = $stmtUser->fetch();
$user_email = $user_data['email'];

// URL du menu pour le QR Code
$base_url = "https://menu-qr-ebke.onrender.com"; 
$menu_url = $base_url . "/menu.php?slug=" . $resto['slug'];

// Gestion des sous-onglets
$tab = $_GET['tab'] ?? 'profil';

$themes = [
    'classique' => ['nom' => 'Classique', 'couleur' => '#c2410c'],
    'minuit' => ['nom' => 'Minuit', 'couleur' => '#fbbf24'],
    'ocean' => ['nom' => 'Océan', 'couleur' => '#0284c7'],
    'jardin' => ['nom' => 'Jardin', 'couleur' => '#059669'],
    'tokyo' => ['nom' => 'Néo-Tokyo', 'couleur' => '#ef4444'],
    'rustique' => ['nom' => 'Rustique', 'couleur' => '#92400e'],
    'rose' => ['nom' => 'Rose Poudré', 'couleur' => '#db2777'],
    'fastfood' => ['nom' => 'Fast Food', 'couleur' => '#dc2626'],
    'minimaliste' => ['nom' => 'Minimaliste', 'couleur' => '#171717'],
    'royal' => ['nom' => 'Royal', 'couleur' => '#7e22ce'],
    'toscane' => ['nom' => 'Toscane', 'couleur' => '#b45309'],
    'hiver' => ['nom' => 'Hiver', 'couleur' => '#3b82f6']
];

$fonts = [
    'Plus Jakarta Sans' => 'Moderne (Par défaut)',
    'Poppins' => 'Arrondi & Amical',
    'Montserrat' => 'Élégant & Classique',
    'Lora' => 'Traditionnel & Serif'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - <?= htmlspecialchars($resto['nom']) ?></title>
     <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/android-chrome-192x192.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js" data-turbo-track="reload"></script>
</head>
<body data-turbo="true">

    <header class="admin-header">
        <h1>Paramètres</h1>
        <a href="../logout.php" class="btn-logout">Déconnexion</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>">Gérer le Menu</a>
        <a href="stats.php" class="<?= basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : '' ?>">Statistiques</a>
        <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">Paramètres</a>
        <a href="subscription.php" class="<?= basename($_SERVER['PHP_SELF']) == 'subscription.php' ? 'active' : '' ?>">Abonnement</a>
    </div>

    <main class="container">
        
        <!-- Sous-onglets (Sous-catégories) -->
        <div style="display:flex; gap:1rem; margin-bottom:2rem; border-bottom:1px solid #e5e7eb; padding-bottom:0.5rem; overflow-x:auto;">
            <a href="?tab=profil" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'profil' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'profil' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Profil</a>
            <a href="?tab=branding" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'branding' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'branding' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Apparence</a>
            <a href="?tab=qrcode" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'qrcode' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'qrcode' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">QR Code</a>
            <a href="?tab=securite" style="text-decoration:none; font-weight:700; font-size:0.9rem; padding-bottom:0.5rem; border-bottom:3px solid <?= $tab === 'securite' ? 'var(--brand-color)' : 'transparent' ?>; color: <?= $tab === 'securite' ? 'var(--text-dark)' : 'var(--text-gray)' ?>;">Sécurité</a>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem; text-align:center; font-weight:bold;">Modifications enregistrées !</div>
        <?php endif; ?>

        <!-- CONTENU DES SOUS-ONGLETS -->
        
        <!-- ONGLET PROFIL -->
        <?php if($tab === 'profil'): ?>
            <div class="theme-selector-card">
                <h2>Profil du Restaurant</h2>
                <p>Ces informations apparaîtront sur votre menu.</p>
                <form action="../actions/update_profile.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Nom du restaurant</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($resto['nom']) ?>" class="form-control" required>
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Slogan</label>
                        <input type="text" name="slogan" value="<?= htmlspecialchars($resto['slogan'] ?? 'Découvrez notre carte') ?>" class="form-control" required>
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Téléphone (Privé)</label>
                        <input type="text" name="telephone" value="<?= htmlspecialchars($resto['telephone'] ?? '') ?>" class="form-control">
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Instagram</label>
                        <input type="url" name="instagram_url" value="<?= htmlspecialchars($resto['instagram_url'] ?? '') ?>" class="form-control">
                    </div>
                    <div>
                        <label style="font-size:0.85rem; font-weight:700;">Facebook</label>
                        <input type="url" name="facebook_url" value="<?= htmlspecialchars($resto['facebook_url'] ?? '') ?>" class="form-control">
                    </div>
                    <button type="submit" class="btn-primary">Enregistrer</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ONGLET APPARENCE (Branding) -->
        <?php if($tab === 'branding'): ?>
            <!-- Logo -->
            <div class="theme-selector-card" style="display:flex; gap:1.5rem; align-items:center;">
                <div style="flex:1;">
                    <h2>Logo</h2>
                    <p>Ce logo apparaîtra en haut de votre menu.</p>
                    <form action="../actions/update_logo.php" method="POST" enctype="multipart/form-data">
                        <label class="btn-upload" style="display:inline-flex; width:auto;">Changer le logo
                            <input type="file" name="logo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
                <?php if($resto['logo_url']): ?>
                    <img src="<?= htmlspecialchars($resto['logo_url']) ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid white; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                <?php endif; ?>
            </div>

            <!-- Thèmes de couleurs -->
            <div class="theme-selector-card">
                <h2>Thème de couleurs</h2>
                <p>Choisissez l'ambiance visuelle de votre carte.</p>
                <div class="theme-grid">
                    <?php foreach ($themes as $key => $t): $isActive = ($resto['theme'] ?? 'classique') === $key; ?>
                        <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectTheme('<?= $key ?>', this)">
                            <div class="color-circle" style="background: <?= $t['couleur'] ?>;"></div>
                            <span><?= $t['nom'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- STYLE D'ÉCRITURE -->
            <div class="theme-selector-card">
                <h2>Style d'écriture</h2>
                <p>Choisissez la police qui correspond à votre style.</p>
                <div class="theme-grid">
                    <?php foreach ($fonts as $key => $nom): $isActive = ($resto['font_family'] ?? 'Plus Jakarta Sans') === $key; ?>
                        <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectFont('<?= $key ?>', this)" style="font-family: '<?= $key ?>', sans-serif;">
                            <span style="font-size:1.2rem; font-weight:bold;">Aa</span>
                            <span><?= $nom ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

         <!-- ONGLET QR CODE -->
        <?php if($tab === 'qrcode'): ?>
            <div class="theme-selector-card" style="text-align:center; padding: 2rem 1rem;">
                <h2>Votre QR Code</h2>
                <p>Imprimez ce code et placez-le sur vos tables.</p>
                
                <!-- ZONE À TÉLÉCHARGER (Styles forcés pour le PDF) -->
                <div style="display: flex; justify-content: center; margin: 2rem 0;">
                    <div id="qr-export-zone" style="background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; border-radius: 20px; width: 320px; text-align: center; font-family: Arial, sans-serif;">
                        <h3 style="font-size: 20px; color: #1c1917; margin: 0 0 5px 0; font-weight: bold;"><?= htmlspecialchars($resto['nom']) ?></h3>
                        <p style="font-size: 12px; color: #78716c; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 20px 0;">Scannez pour voir le menu</p>
                        
                        <!-- Le conteneur du QR Code -->
                        <div id="qr-container" style="width: 250px; height: 250px; margin: 0 auto;"></div>
                    </div>
                </div>

                <div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap; margin-top: 1rem;">
                    <a href="<?= htmlspecialchars($menu_url) ?>" target="_blank" class="btn-primary" style="text-decoration:none; background:#f3f4f6; color:var(--text-dark);">
                        Voir le menu
                    </a>
                    
                    <!-- Bouton de téléchargement PDF -->
                    <button id="download-pdf-btn" class="btn-primary" style="display:flex; align-items:center; gap:0.5rem; border:none; cursor:pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Télécharger en PDF
                    </button>
                </div>
            </div>

            <!-- SCRIPT POUR GÉNÉRER LE QR CODE -->
            <!-- SCRIPT POUR GÉNÉRER LE QR CODE ET LE PDF -->
            <script>
                document.addEventListener("turbo:load", function() {
                    const qrContainer = document.getElementById("qr-container");
                    
                    if (!qrContainer) return;

                    const menuUrl = "<?= htmlspecialchars($menu_url) ?>";
                    const logoUrl = "<?= !empty($resto['logo_url']) ? htmlspecialchars($resto['logo_url']) : '' ?>";
                    const brandColor = "#c2410c"; // On force la couleur pour le PDF

                    qrContainer.innerHTML = '';

                    const qrCode = new QRCodeStyling({
                        width: 600,
                        height: 600,
                        data: menuUrl,
                        image: logoUrl,
                        margin: 10,
                        qrOptions: { typeNumber: 0, mode: "Byte", errorCorrectionLevel: "H" },
                        imageOptions: { hideBackgroundDots: true, imageSize: 0.4, margin: 4, crossOrigin: "anonymous" },
                        dotsOptions: { color: "#1c1917", type: "rounded" },
                        backgroundOptions: { color: "#ffffff" },
                        cornersSquareOptions: { color: brandColor, type: "extra-rounded" },
                        cornersDotOptions: { color: brandColor, type: "dot" }
                    });

                    qrCode.append(qrContainer);
                    
                    const canvas = qrContainer.querySelector('canvas');
                    if(canvas) {
                        canvas.style.width = '250px';
                        canvas.style.height = '250px';
                    }

                    // Téléchargement en PDF (Configuration stricte)
                    document.getElementById("download-pdf-btn").addEventListener("click", function() {
                        const element = document.getElementById("qr-export-zone");
                        
                        // On attend 100ms que le canvas soit bien dessiné avant de lancer la capture
                        setTimeout(() => {
                            const opt = {
                                margin:       0,
                                filename:     'QR_Menu_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $resto['nom']) ?>.pdf',
                                image:        { type: 'jpeg', quality: 1 },
                                html2canvas:  { 
                                    scale: 3, // Très haute définition
                                    useCORS: true, 
                                    letterRendering: true,
                                    scrollX: 0,
                                    scrollY: 0
                                },
                                jsPDF:        { unit: 'px', format: [380, 400], orientation: 'portrait' } // Taille exacte de la carte
                            };
                            
                            html2pdf().set(opt).from(element).save();
                        }, 100);
                    });
                });
            </script>
        <?php endif; ?>

        <!-- ONGLET SÉCURITÉ -->
        <?php if($tab === 'securite'): ?>
            
            <!-- Messages d'erreur -->
            <?php if(isset($_GET['error'])): ?>
                <div style="background:#fee2e2; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem; text-align:center; font-weight:bold;">
                    <?php 
                        if($_GET['error'] == 'wrong_password') echo "Le mot de passe actuel est incorrect.";
                        if($_GET['error'] == 'email_taken') echo "Cet email est déjà utilisé par un autre compte.";
                        if($_GET['error'] == 'mismatch') echo "Les nouveaux mots de passe ne correspondent pas.";
                        if($_GET['error'] == 'short') echo "Le mot de passe doit contenir au moins 6 caractères.";
                    ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['success']) && ($_GET['success'] == 'email' || $_GET['success'] == 'password')): ?>
                <div style="background:#dcfce7; color:#166534; padding:12px; border-radius:8px; margin-bottom:1.5rem; font-size:0.9rem; text-align:center; font-weight:bold;">
                    <?php 
                        if($_GET['success'] == 'email') echo "Adresse email mise à jour avec succès !";
                        if($_GET['success'] == 'password') echo "Mot de passe mis à jour avec succès !";
                    ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire Email -->
            <div class="theme-selector-card">
                <h2>Changer d'adresse email</h2>
                <form action="../actions/update_email.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                    <input type="email" name="new_email" placeholder="Nouvel email" required class="form-control" value="<?= htmlspecialchars($user_email) ?>">
                    
                    <div class="password-wrapper">
                        <input type="password" name="current_password" id="pass-email" placeholder="Mot de passe actuel (pour confirmer)" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-email', this)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <button type="submit" class="btn-primary" style="align-self:flex-start;">Mettre à jour l'email</button>
                </form>
            </div>

            <!-- Formulaire Mot de passe -->
            <div class="theme-selector-card">
                <h2>Changer de mot de passe</h2>
                <form action="../actions/update_password.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
                    
                    <div class="password-wrapper">
                        <input type="password" name="current_password" id="pass-actuel" placeholder="Mot de passe actuel" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-actuel', this)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>

                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="pass-new" placeholder="Nouveau mot de passe" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-new', this)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>

                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="pass-conf" placeholder="Confirmer le nouveau mot de passe" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-conf', this)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>

                    <button type="submit" class="btn-primary" style="align-self:flex-start;">Mettre à jour le mot de passe</button>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <div id="toast" class="toast">Mis à jour !</div>

    <script>
        function showToast(message) { const toast = document.getElementById('toast'); toast.textContent = message; toast.classList.add('show'); setTimeout(() => { toast.classList.remove('show'); }, 3000); }
        
        async function selectTheme(theme, element) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            const formData = new FormData(); formData.append('theme', theme);
            await fetch('../actions/update_theme.php', { method: 'POST', body: formData });
            showToast('Thème mis à jour !');
        }

        async function selectFont(font, element) {
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            const formData = new FormData(); formData.append('font_family', font);
            await fetch('../actions/update_font.php', { method: 'POST', body: formData });
            showToast('Police mise à jour !');
        }

        function togglePassword(inputId, iconElement) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                // Icône Œil barré
                iconElement.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                input.type = "password";
                // Icône Œil normal
                iconElement.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        }

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