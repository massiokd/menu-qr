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

$stmtResto = $pdo->prepare("SELECT nom FROM restaurants WHERE id = ?");
$stmtResto->execute([$resto_id]);
$resto = $stmtResto->fetch();

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM menu_scans WHERE restaurant_id = ?");
$stmtTotal->execute([$resto_id]);
$total_scans = $stmtTotal->fetchColumn();

$stmtToday = $pdo->prepare("SELECT COUNT(*) FROM menu_scans WHERE restaurant_id = ? AND DATE(scanned_at) = CURRENT_DATE");
$stmtToday->execute([$resto_id]);
$scans_today = $stmtToday->fetchColumn();

$stmtWeek = $pdo->prepare("SELECT COUNT(*) FROM menu_scans WHERE restaurant_id = ? AND scanned_at >= CURRENT_DATE - INTERVAL '7 days'");
$stmtWeek->execute([$resto_id]);
$scans_week = $stmtWeek->fetchColumn();

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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Statistiques — <?= htmlspecialchars($resto['nom']) ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#c2410c">
    <link rel="apple-touch-icon" href="icons/1apple-touch-icon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@7.3.0/dist/turbo.es2017-umd.js" data-turbo-track="reload"></script>
</head>
<body data-turbo="true">

    <header class="admin-header">
        <div class="header-brand">
            <span class="header-eyebrow"><?= htmlspecialchars($resto['nom']) ?></span>
            <h1>Statistiques</h1>
        </div>
        <div class="header-actions">
            <a href="../logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </header>

    <nav class="admin-tabs" aria-label="Navigation principale">
        <a href="admin.php">Gérer le Menu</a>
        <a href="stats.php" class="active">Statistiques</a>
        <a href="settings.php">Paramètres</a>
        <a href="subscription.php">Abonnement</a>
    </nav>

    <main class="container">
        <div class="kpi-grid">
            <div class="kpi-card">
                <p class="kpi-label">Total</p>
                <p class="kpi-value kpi-brand"><?= number_format($total_scans, 0, ',', ' ') ?></p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">Aujourd'hui</p>
                <p class="kpi-value kpi-success"><?= number_format($scans_today, 0, ',', ' ') ?></p>
            </div>
            <div class="kpi-card">
                <p class="kpi-label">7 jours</p>
                <p class="kpi-value kpi-blue"><?= number_format($scans_week, 0, ',', ' ') ?></p>
            </div>
        </div>

        <div class="app-card">
            <div class="app-card-header">
                <h2>Scans des 7 derniers jours</h2>
                <p>Consultations du menu via votre QR Code.</p>
            </div>

            <div class="chart-box">
                <div id="chart-loader" class="chart-loader">
                    <div class="spinner spinner-dark" style="width:26px; height:26px; border-width:3px;"></div>
                    <span>Chargement...</span>
                </div>
                <canvas id="scansChart"></canvas>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener("turbo:load", function () {
            const chartElement = document.getElementById('scansChart');
            const loader = document.getElementById('chart-loader');
            if (!chartElement) return;

            if (loader) loader.style.display = 'flex';

            function waitForChart() {
                if (typeof Chart === 'undefined') {
                    setTimeout(waitForChart, 50);
                    return;
                }
                if (window.myScansChart) {
                    window.myScansChart.destroy();
                }

                const ctx = chartElement.getContext('2d');
                window.myScansChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($labels) ?>,
                        datasets: [{
                            label: 'Scans',
                            data: <?= json_encode($data) ?>,
                            borderColor: '#c2410c',
                            backgroundColor: 'rgba(194, 65, 12, 0.12)',
                            borderWidth: 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#c2410c',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            fill: true,
                            tension: 0.35
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            onComplete: function () {
                                if (loader) loader.style.display = 'none';
                            }
                        },
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            waitForChart();
        });
    </script>
</body>
</html>