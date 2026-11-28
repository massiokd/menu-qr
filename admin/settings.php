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

$stmtResto = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

$stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$user_data = $stmtUser->fetch();
$user_email = $user_data['email'];

$base_url = "https://menu.fennetech.com";
$menu_url = $base_url . "/menu.php?slug=" . $resto['slug'];

$tab = $_GET['tab'] ?? 'profil';

$themes = [
    'classique'   => ['nom' => 'Classique',    'couleur' => '#c2410c'],
    'minuit'      => ['nom' => 'Minuit',       'couleur' => '#fbbf24'],
    'ocean'       => ['nom' => 'Océan',        'couleur' => '#0284c7'],
    'jardin'      => ['nom' => 'Jardin',       'couleur' => '#059669'],
    'tokyo'       => ['nom' => 'Néo-Tokyo',    'couleur' => '#ef4444'],
    'rustique'    => ['nom' => 'Rustique',     'couleur' => '#92400e'],
    'rose'        => ['nom' => 'Rose Poudré',  'couleur' => '#db2777'],
    'fastfood'    => ['nom' => 'Fast Food',    'couleur' => '#dc2626'],
    'minimaliste' => ['nom' => 'Minimaliste',  'couleur' => '#171717'],
    'royal'       => ['nom' => 'Royal',        'couleur' => '#7e22ce'],
    'toscane'     => ['nom' => 'Toscane',      'couleur' => '#b45309'],
    'hiver'       => ['nom' => 'Hiver',        'couleur' => '#3b82f6']
];

$fonts = [
    'Plus Jakarta Sans' => 'Moderne',
    'Poppins'           => 'Arrondi',
    'Montserrat'        => 'Élégant',
    'Lora'              => 'Classique'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Paramètres — <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/1apple-touch-icon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js" data-turbo-track="reload"></script>
</head>
<body data-turbo="true">

    <header class="admin-header">
        <div class="header-brand">
            <span class="header-eyebrow"><?= htmlspecialchars($resto['nom']) ?></span>
            <h1>Paramètres</h1>
        </div>
        <div class="header-actions">
            <a href="../logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </header>

    <nav class="admin-tabs" aria-label="Navigation principale">
        <a href="admin.php">Gérer le Menu</a>
        <a href="stats.php">Statistiques</a>
        <a href="settings.php" class="active">Paramètres</a>
        <a href="subscription.php">Abonnement</a>
    </nav>

    <main class="container">

        <div class="settings-nav-pills">
            <a href="?tab=profil" class="settings-pill <?= $tab === 'profil' ? 'active' : '' ?>">Profil</a>
            <a href="?tab=branding" class="settings-pill <?= $tab === 'branding' ? 'active' : '' ?>">Apparence</a>
            <a href="?tab=qrcode" class="settings-pill <?= $tab === 'qrcode' ? 'active' : '' ?>">QR Code</a>
            <a href="?tab=securite" class="settings-pill <?= $tab === 'securite' ? 'active' : '' ?>">Sécurité</a>
            <a href="?tab=suggestions" class="settings-pill <?= $tab === 'suggestions' ? 'active' : '' ?>">Idées</a>
        </div>

        <?php if (isset($_GET['success']) && !in_array($_GET['success'], ['email', 'password'], true)): ?>
            <div class="feedback-banner feedback-success">
                <?= $tab === 'suggestions' ? 'Merci ! Votre suggestion a bien été envoyée.' : 'Modifications enregistrées.' ?>
            </div>
        <?php endif; ?>

        <!-- PROFIL -->
        <?php if ($tab === 'profil'): ?>
            <div class="app-card">
                <div class="app-card-header">
                    <h2>Profil de l'établissement</h2>
                    <p>Informations affichées sur votre carte numérique.</p>
                </div>
                <form action="../actions/update_profile.php" method="POST" class="stack-form">
                    <div class="field-group">
                        <label class="field-label">Nom du restaurant</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($resto['nom']) ?>" class="form-control" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Slogan</label>
                        <input type="text" name="slogan" value="<?= htmlspecialchars($resto['slogan'] ?? 'Découvrez notre carte') ?>" class="form-control" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Téléphone (privé)</label>
                        <input type="tel" name="telephone" value="<?= htmlspecialchars($resto['telephone'] ?? '') ?>" class="form-control">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Instagram</label>
                        <input type="url" name="instagram_url" value="<?= htmlspecialchars($resto['instagram_url'] ?? '') ?>" class="form-control" placeholder="https://instagram.com/...">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Facebook</label>
                        <input type="url" name="facebook_url" value="<?= htmlspecialchars($resto['facebook_url'] ?? '') ?>" class="form-control" placeholder="https://facebook.com/...">
                    </div>
                    <button type="submit" class="btn-primary">Enregistrer</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- APPARENCE -->
        <?php if ($tab === 'branding'): ?>
            <div class="app-card">
                <div class="logo-row">
                    <div>
                        <div class="app-card-header" style="margin-bottom:0;">
                            <h2>Logo</h2>
                            <p>Affiché en tête de votre menu client.</p>
                        </div>
                        <form action="../actions/update_logo.php" method="POST" enctype="multipart/form-data">
                            <label class="btn-upload-pill">
                                Changer le logo
                                <input type="file" name="logo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                            </label>
                        </form>
                    </div>
                    <?php if (!empty($resto['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($resto['logo_url']) ?>" alt="Logo" class="logo-preview-circle">
                    <?php endif; ?>
                </div>
            </div>

            <div class="app-card">
                <div class="app-card-header">
                    <h2>Thème de couleurs</h2>
                    <p>Choisissez l'ambiance visuelle de votre carte.</p>
                </div>
                <div class="theme-grid">
                    <?php foreach ($themes as $key => $t): $isActive = ($resto['theme'] ?? 'classique') === $key; ?>
                        <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectTheme('<?= $key ?>', this)">
                            <div class="color-circle" style="background: <?= $t['couleur'] ?>;"></div>
                            <span><?= $t['nom'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="app-card">
                <div class="app-card-header">
                    <h2>Police d'écriture</h2>
                    <p>Typographie utilisée sur le menu client.</p>
                </div>
                <div class="theme-grid">
                    <?php foreach ($fonts as $key => $nom): $isActive = ($resto['font_family'] ?? 'Plus Jakarta Sans') === $key; ?>
                        <div class="theme-option <?= $isActive ? 'active' : '' ?>" onclick="selectFont('<?= $key ?>', this)" style="font-family: '<?= $key ?>', sans-serif;">
                            <span style="font-size:1.15rem; font-weight:800;">Aa</span>
                            <span><?= $nom ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- QR CODE -->
        <?php if ($tab === 'qrcode'): ?>
            <div class="app-card" style="text-align:center;">
                <div class="app-card-header">
                    <h2>Votre QR Code</h2>
                    <p>Téléchargez la fiche prête à imprimer pour vos tables.</p>
                </div>

                <div class="qr-preview-wrap">
                    <div id="qr-export-zone" class="qr-card-export">
                        <h3><?= htmlspecialchars($resto['nom']) ?></h3>
                        <p>Scannez pour voir le menu</p>
                        <div id="qr-container" style="width: 240px; height: 240px; margin: 0 auto;"></div>
                    </div>
                </div>

                <div class="qr-actions-row">
                    <a href="<?= htmlspecialchars($menu_url) ?>" target="_blank" rel="noopener" class="btn-outline-card">
                        Voir le menu
                    </a>
                    <button type="button" id="download-pdf-btn" class="btn-primary">
                        Télécharger PDF
                    </button>
                </div>
            </div>

            <script>
                document.addEventListener("turbo:load", function () {
                    const qrContainer = document.getElementById("qr-container");
                    if (!qrContainer) return;

                    const menuUrl = "<?= htmlspecialchars($menu_url) ?>";
                    const logoUrl = "<?= !empty($resto['logo_url']) ? htmlspecialchars($resto['logo_url']) : '' ?>";
                    const brandColor = "#c2410c";

                    qrContainer.innerHTML = '';

                    const qrCode = new QRCodeStyling({
                        width: 600,
                        height: 600,
                        data: menuUrl,
                        image: logoUrl,
                        margin: 10,
                        qrOptions: { typeNumber: 0, mode: "Byte", errorCorrectionLevel: "H" },
                        imageOptions: { hideBackgroundDots: true, imageSize: 0.4, margin: 4, crossOrigin: "anonymous" },
                        dotsOptions: { color: "#18181b", type: "rounded" },
                        backgroundOptions: { color: "#ffffff" },
                        cornersSquareOptions: { color: brandColor, type: "extra-rounded" },
                        cornersDotOptions: { color: brandColor, type: "dot" }
                    });

                    qrCode.append(qrContainer);

                    const canvas = qrContainer.querySelector('canvas');
                    if (canvas) {
                        canvas.style.width = '240px';
                        canvas.style.height = '240px';
                    }

                    const dlBtn = document.getElementById("download-pdf-btn");
                    if (dlBtn) {
                        dlBtn.onclick = function () {
                            const element = document.getElementById("qr-export-zone");
                            setTimeout(() => {
                                const width = element.offsetWidth;
                                const height = element.offsetHeight;
                                const opt = {
                                    margin: 0,
                                    filename: 'QR_Menu_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $resto['nom']) ?>.pdf',
                                    image: { type: 'jpeg', quality: 1 },
                                    html2canvas: { scale: 3, useCORS: true, letterRendering: true, scrollX: 0, scrollY: 0 },
                                    jsPDF: { unit: 'px', format: [width, height], orientation: 'portrait' }
                                };
                                html2pdf().set(opt).from(element).save();
                            }, 100);
                        };
                    }
                });
            </script>
        <?php endif; ?>

        <!-- SÉCURITÉ -->
        <?php if ($tab === 'securite'): ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="feedback-banner feedback-danger">
                    <?php
                        if ($_GET['error'] === 'wrong_password') echo "Le mot de passe actuel est incorrect.";
                        if ($_GET['error'] === 'email_taken') echo "Cet email est déjà utilisé.";
                        if ($_GET['error'] === 'mismatch') echo "Les nouveaux mots de passe ne correspondent pas.";
                        if ($_GET['error'] === 'short') echo "Le mot de passe doit contenir au moins 6 caractères.";
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && ($_GET['success'] === 'email' || $_GET['success'] === 'password')): ?>
                <div class="feedback-banner feedback-success">
                    <?= $_GET['success'] === 'email' ? "Adresse email mise à jour." : "Mot de passe mis à jour." ?>
                </div>
            <?php endif; ?>

            <div class="app-card">
                <div class="app-card-header">
                    <h2>Adresse email</h2>
                </div>
                <form action="../actions/update_email.php" method="POST" class="stack-form">
                    <input type="email" name="new_email" placeholder="Nouvel email" required class="form-control" value="<?= htmlspecialchars($user_email) ?>">
                    <div class="password-wrapper">
                        <input type="password" name="current_password" id="pass-email" placeholder="Mot de passe actuel" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-email', this)" aria-label="Afficher le mot de passe">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <button type="submit" class="btn-primary">Mettre à jour l'email</button>
                </form>
            </div>

            <div class="app-card">
                <div class="app-card-header">
                    <h2>Mot de passe</h2>
                </div>
                <form action="../actions/update_password.php" method="POST" class="stack-form">
                    <div class="password-wrapper">
                        <input type="password" name="current_password" id="pass-actuel" placeholder="Mot de passe actuel" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-actuel', this)" aria-label="Afficher le mot de passe">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="pass-new" placeholder="Nouveau mot de passe" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-new', this)" aria-label="Afficher le mot de passe">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="pass-conf" placeholder="Confirmer le mot de passe" required class="form-control">
                        <button type="button" class="toggle-password" onclick="togglePassword('pass-conf', this)" aria-label="Afficher le mot de passe">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <button type="submit" class="btn-primary">Mettre à jour le mot de passe</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- SUGGESTIONS -->
        <?php if ($tab === 'suggestions'): ?>
            <div class="app-card">
                <div class="app-card-header">
                    <h2>Boîte à idées</h2>
                    <p>Une fonctionnalité vous manque ? Écrivez-nous directement.</p>
                </div>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'limit_reached'): ?>
                    <div class="feedback-banner feedback-danger">
                        Limite de 3 suggestions par 24h atteinte.
                    </div>
                <?php endif; ?>

                <form action="../actions/save_suggestion.php" method="POST" class="stack-form">
                    <textarea name="message" class="form-control" placeholder="Ex. : Ajouter des suppléments ou variantes sur un plat..." required maxlength="500"></textarea>
                    <button type="submit" class="btn-primary">Envoyer ma suggestion</button>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <div id="toast" class="toast" role="status" aria-live="polite">Mis à jour</div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
        }

        async function selectTheme(theme, element) {
            element.parentElement.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            const formData = new FormData();
            formData.append('theme', theme);
            await fetch('../actions/update_theme.php', { method: 'POST', body: formData });
            showToast('Thème mis à jour');
        }

        async function selectFont(font, element) {
            element.parentElement.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
            const formData = new FormData();
            formData.append('font_family', font);
            await fetch('../actions/update_font.php', { method: 'POST', body: formData });
            showToast('Police mise à jour');
        }

        function togglePassword(inputId, iconElement) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                iconElement.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                input.type = "password";
                iconElement.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        }
    </script>
</body>
</html>