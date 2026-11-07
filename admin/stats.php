<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { header('Location: ../login.php'); exit; }

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

$stmtResto = $pdo->prepare("SELECT nom FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

// 1. Total des scans
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM menu_scans WHERE restaurant_id = ?");
$stmtTotal->execute([$resto_id]);
$total_scans = $stmtTotal->fetchColumn();

// 2. Scans aujourd'hui
$stmtToday = $pdo->prepare("SELECT COUNT(*) FROM menu_scans WHERE restaurant_id = ? AND DATE(scanned_at) = CURRENT_DATE");
$stmtToday->execute([$resto_id]);
$scans_today = $stmtToday->fetchColumn();

// 3. Scans cette semaine (7 derniers jours)
$stmtWeek = $pdo->prepare("SELECT COUNT(*) FROM menu_scans WHERE restaurant_id = ? AND scanned_at >= CURRENT_DATE - INTERVAL '7 days'");
$stmtWeek->execute([$resto_id]);
$scans_week = $stmtWeek->fetchColumn();

// 4. Données pour le graphique (7 derniers jours)
$labels = [];
$data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($date));
    
    $stmtDay = $pdo->prepare("SELECT COUNT(*) FROM menu_scans WHERE restaurant_id = ? AND DATE(scanned_at) = ?");
    $stmtDay->execute([$resto_id, $date]);
    $data[] = $stmtDay->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Chart.js pour le graphique -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <header class="admin-header">
        <h1>Statistiques</h1>
        <a href="../logout.php" class="btn-logout">Déconnexion</a>
    </header>

    <div class="admin-tabs">
        <a href="admin.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : '' ?>">Gérer le Menu</a>
        <a href="stats.php" class="<?= basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'active' : '' ?>">Statistiques</a>
        <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">Paramètres</a>
        <a href="subscription.php" class="<?= basename($_SERVER['PHP_SELF']) == 'subscription.php' ? 'active' : '' ?>">Abonnement</a>
    </div>

    <main class="container">
        
        <!-- Cartes de résumé -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:1rem; margin-bottom:2rem;">
            
            <div class="theme-selector-card" style="padding:1.5rem; text-align:center; margin-bottom:0;">
                <p style="font-size:0.85rem; color:var(--text-gray); font-weight:700; text-transform:uppercase;">Total</p>
                <h2 style="font-size:2.5rem; font-weight:800; color:var(--brand-color); margin:0.5rem 0;"><?= number_format($total_scans, 0, ',', ' ') ?></h2>
            </div>

            <div class="theme-selector-card" style="padding:1.5rem; text-align:center; margin-bottom:0;">
                <p style="font-size:0.85rem; color:var(--text-gray); font-weight:700; text-transform:uppercase;">Aujourd'hui</p>
                <h2 style="font-size:2.5rem; font-weight:800; color:var(--success); margin:0.5rem 0;"><?= number_format($scans_today, 0, ',', ' ') ?></h2>
            </div>

            <div class="theme-selector-card" style="padding:1.5rem; text-align:center; margin-bottom:0;">
                <p style="font-size:0.85rem; color:var(--text-gray); font-weight:700; text-transform:uppercase;">7 derniers jours</p>
                <h2 style="font-size:2.5rem; font-weight:800; color:#3b82f6; margin:0.5rem 0;"><?= number_format($scans_week, 0, ',', ' ') ?></h2>
            </div>

        </div>

        <!-- Graphique -->
        <div class="theme-selector-card">
            <h2>Évolution des scans (7 derniers jours)</h2>
            <div style="position: relative; height:300px; width:100%; margin-top:1rem;">
                <canvas id="scansChart"></canvas>
            </div>
        </div>

    </main>

    <script>
        const ctx = document.getElementById('scansChart').getContext('2d');
        const scansChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Nombre de scans',
                    data: <?= json_encode($data) ?>,
                    borderColor: '#c2410c', /* Couleur de ta marque */
                    backgroundColor: 'rgba(194, 65, 12, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#c2410c',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    fill: true,
                    tension: 0.4 /* Courbe lissée */
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>