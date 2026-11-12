<?php
session_name('FENNEC_ADMIN');
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDB();

// --- STATISTIQUES GLOBALES ---
// Total Revenus
$stmtRev = $pdo->query("SELECT SUM(montant) as total FROM payments WHERE statut = 'valide'");
$total_rev = $stmtRev->fetch()['total'] ?? 0;

// Revenus ce mois
$stmtRevMonth = $pdo->query("SELECT SUM(montant) as total FROM payments WHERE statut = 'valide' AND DATE_TRUNC('month', created_at) = DATE_TRUNC('month', CURRENT_DATE)");
$rev_month = $stmtRevMonth->fetch()['total'] ?? 0;

// MRR (Monthly Recurring Revenue)
$stmtMRR = $pdo->query("SELECT SUM(CASE WHEN plan = 'monthly' THEN 800 WHEN plan = 'yearly' THEN 575 ELSE 0 END) FROM restaurants WHERE is_active = true AND plan != 'lifetime' AND (expires_at > NOW() OR expires_at IS NULL)");
$mrr = $stmtMRR->fetchColumn() ?? 0;

// Clients actifs
$stmtActive = $pdo->query("SELECT COUNT(*) FROM restaurants WHERE is_active = true AND (plan = 'lifetime' OR expires_at > NOW())");
$active_clients = $stmtActive->fetchColumn() ?? 0;

// Paiements en attente
$stmtPending = $pdo->query("SELECT COUNT(*) FROM payments WHERE statut = 'en_attente'");
$pending_payments = $stmtPending->fetchColumn() ?? 0;

// --- DONNÉES POUR LE GRAPHIQUE (6 derniers mois) ---
$stmtChart = $pdo->query("
    SELECT TO_CHAR(created_at, 'YYYY-MM') as month, SUM(montant) as total 
    FROM payments 
    WHERE statut = 'valide' AND created_at >= NOW() - INTERVAL '6 months'
    GROUP BY month ORDER BY month ASC
");
$chart_data = $stmtChart->fetchAll();
$chart_labels = [];
$chart_values = [];
foreach($chart_data as $row) {
    $chart_labels[] = $row['month'];
    $chart_values[] = $row['total'];
}

// --- LISTE DES RESTAURANTS ---
$stmt = $pdo->query("SELECT * FROM restaurants ORDER BY created_at DESC");
$restos = $stmt->fetchAll();

// --- HISTORIQUE DES PAIEMENTS ---
$stmtPay = $pdo->query("
    SELECT p.*, r.nom as resto_nom 
    FROM payments p 
    JOIN restaurants r ON p.restaurant_id = r.id 
    ORDER BY p.created_at DESC LIMIT 50
");
$payments = $stmtPay->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FenneTech - Super Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        /* Style de la scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    </style>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-gray-900 text-white flex flex-col shadow-2xl z-20">
        <div class="h-20 flex items-center justify-center border-b border-gray-800">
            <img src="assets/images/FenneTechLogo.png" alt="FenneTech" class="h-10 w-auto">
        </div>
        
        <nav class="flex-1 py-6 flex flex-col gap-2 px-4">
            <a href="#" onclick="showTab('dashboard')" id="nav-dashboard" class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg bg-orange-600 text-white transition-all">
                <i class="fa-solid fa-chart-pie w-5"></i> Tableau de Bord
            </a>
            <a href="#" onclick="showTab('restaurants')" id="nav-restaurants" class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition-all">
                <i class="fa-solid fa-store w-5"></i> Restaurants
            </a>
            <a href="#" onclick="showTab('payments')" id="nav-payments" class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition-all">
                <i class="fa-solid fa-money-bill-wave w-5"></i> Paiements
            </a>
        </nav>

        <div class="p-4 border-t border-gray-800">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-all">
                <i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Déconnexion
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        
        <!-- TOP BAR -->
        <header class="bg-white h-20 flex items-center justify-between px-8 shadow-sm z-10">
            <h1 class="text-2xl font-bold text-gray-800" id="page-title">Tableau de Bord</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-gray-500">Admin FenneTech</span>
                <div class="w-10 h-10 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center font-bold">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
            </div>
        </header>

        <!-- TAB: DASHBOARD -->
        <div id="tab-dashboard" class="p-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-gray-500 uppercase">Revenu Total</h3>
                        <div class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                    <p class="text-3xl font-extrabold text-gray-800"><?= number_format($total_rev, 0, ',', ' ') ?> <span class="text-lg text-gray-400">DZD</span></p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-gray-500 uppercase">MRR (Mensuel)</h3>
                        <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-chart-line"></i></div>
                    </div>
                    <p class="text-3xl font-extrabold text-gray-800"><?= number_format($mrr, 0, ',', ' ') ?> <span class="text-lg text-gray-400">DZD</span></p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-gray-500 uppercase">Clients Actifs</h3>
                        <div class="w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <p class="text-3xl font-extrabold text-gray-800"><?= $active_clients ?></p>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-orange-500">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-gray-500 uppercase">À Valider</h3>
                        <div class="w-8 h-8 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <p class="text-3xl font-extrabold text-gray-800"><?= $pending_payments ?></p>
                </div>
            </div>

            <!-- Chart -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 mb-8">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Évolution des Revenus (6 derniers mois)</h3>
                <div class="h-72 w-full">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- TAB: RESTAURANTS -->
        <div id="tab-restaurants" class="p-8 hidden">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Gestion des Clients</h2>
                <button onclick="openModal('add-resto-modal')" class="bg-orange-600 hover:bg-orange-700 text-white px-5 py-2.5 rounded-lg font-bold transition-all shadow-md flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Ajouter un restaurant
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-sm border-b border-gray-100">
                            <th class="p-4 font-bold">Restaurant</th>
                            <th class="p-4 font-bold">Plan</th>
                            <th class="p-4 font-bold">Expiration</th>
                            <th class="p-4 font-bold">Statut</th>
                            <th class="p-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($restos as $r): 
                            $is_expired = $r['plan'] !== 'lifetime' && strtotime($r['expires_at'] ?? 'now') < time();
                            $is_online = $r['is_active'] && !$is_expired;
                            
                            // Vérif paiement en attente
                            $stmtP = $pdo->prepare("SELECT id FROM payments WHERE restaurant_id = ? AND statut = 'en_attente' LIMIT 1");
                            $stmtP->execute([$r['id']]);
                            $has_pending = $stmtP->fetch();
                        ?>
                        <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <div class="font-bold text-gray-800"><?= htmlspecialchars($r['nom']) ?></div>
                                <div class="text-xs text-gray-500">/<?= htmlspecialchars($r['slug']) ?></div>
                                <?php if($has_pending): ?>
                                    <span class="inline-block mt-1 bg-yellow-100 text-yellow-700 text-[10px] font-bold px-2 py-0.5 rounded-full">⚠️ Paiement en attente</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded-md text-xs font-bold uppercase">
                                    <?php 
                                        $plans_fr = ['trial' => 'Essai', 'monthly' => 'Mensuel', 'yearly' => 'Annuel', 'lifetime' => 'À vie'];
                                        echo $plans_fr[$r['plan']] ?? $r['plan']; 
                                    ?>
                                </span>
                            </td>
                            <td class="p-4 text-sm text-gray-600">
                                <?= $r['plan'] === 'lifetime' ? '<span class="text-green-600 font-bold">À vie</span>' : ($r['expires_at'] ? date('d/m/Y', strtotime($r['expires_at'])) : 'Non défini') ?>
                            </td>
                            <td class="p-4">
                                <?php if($is_online): ?>
                                    <span class="bg-green-100 text-green-700 px-2.5 py-1 rounded-md text-xs font-bold">En ligne</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 px-2.5 py-1 rounded-md text-xs font-bold">Hors ligne</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right">
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($r)) ?>)" class="text-blue-500 hover:text-blue-700 p-2" title="Modifier"><i class="fa-solid fa-pen"></i></button>
                                <form action="actions/super_admin_actions.php" method="POST" class="inline">
                                    <input type="hidden" name="action" value="update_resto">
                                    <input type="hidden" name="resto_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="toggle_active" value="1" class="text-<?= $r['is_active'] ? 'red' : 'green' ?>-500 hover:text-<?= $r['is_active'] ? 'red' : 'green' ?>-700 p-2" title="<?= $r['is_active'] ? 'Suspendre' : 'Réactiver' ?>">
                                        <i class="fa-solid fa-<?= $r['is_active'] ? 'ban' : 'check' ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB: PAYMENTS -->
        <div id="tab-payments" class="p-8 hidden">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Historique des Paiements</h2>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-sm border-b border-gray-100">
                            <th class="p-4 font-bold">Date</th>
                            <th class="p-4 font-bold">Restaurant</th>
                            <th class="p-4 font-bold">Plan</th>
                            <th class="p-4 font-bold">Montant</th>
                            <th class="p-4 font-bold">Statut</th>
                            <th class="p-4 font-bold">Reçu</th>
                            <th class="p-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="p-4 text-sm text-gray-600"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                            <td class="p-4 font-bold text-gray-800"><?= htmlspecialchars($p['resto_nom']) ?></td>
                            <td class="p-4 text-sm uppercase font-bold text-gray-500"><?= $p['plan'] ?></td>
                            <td class="p-4 font-bold text-gray-800"><?= number_format($p['montant'], 0, ',', ' ') ?> DZD</td>
                            <td class="p-4">
                                <?php if($p['statut'] === 'valide'): ?>
                                    <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">Validé</span>
                                <?php elseif($p['statut'] === 'refuse'): ?>
                                    <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold">Refusé</span>
                                <?php else: ?>
                                    <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-bold">En attente</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <?php if($p['receipt_url']): ?>
                                    <a href="<?= htmlspecialchars($p['receipt_url']) ?>" target="_blank" class="text-blue-500 hover:underline text-sm"><i class="fa-solid fa-file-invoice"></i> Voir</a>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right">
                                <?php if($p['statut'] === 'en_attente'): ?>
                                    <a href="actions/validate_payment.php?id=<?= $p['id'] ?>" class="bg-green-500 text-white px-3 py-1 rounded text-xs font-bold hover:bg-green-600" onclick="return confirm('Valider ?')">Valider</a>
                                    <button onclick="openRejectModal(<?= $p['id'] ?>)" class="bg-red-500 text-white px-3 py-1 rounded text-xs font-bold hover:bg-red-600 ml-1">Refuser</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- MODAL : AJOUTER UN RESTO -->
    <div id="add-resto-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl transform scale-95 transition-transform duration-300">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Ajouter un restaurant</h2>
            <form action="actions/super_admin_actions.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="add_resto">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Nom du restaurant</label>
                    <input type="text" name="nom" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:border-orange-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Slug (URL)</label>
                    <input type="text" name="slug" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:border-orange-500 outline-none" placeholder="ex: chez-paul">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Email du gérant</label>
                    <input type="email" name="email" required class="w-full border border-gray-300 rounded-lg p-2.5 focus:border-orange-500 outline-none">
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="closeModal('add-resto-modal')" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold hover:bg-gray-200">Annuler</button>
                    <button type="submit" class="flex-1 bg-orange-600 text-white py-2.5 rounded-lg font-bold hover:bg-orange-700">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL : EDITER UN RESTO -->
    <div id="edit-resto-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Modifier le restaurant</h2>
            <form action="actions/super_admin_actions.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="update_resto">
                <input type="hidden" name="resto_id" id="edit-resto-id">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Nom</label>
                    <input type="text" name="nom" id="edit-resto-nom" required class="w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Plan d'abonnement</label>
                    <select name="plan" id="edit-resto-plan" class="w-full border border-gray-300 rounded-lg p-2.5">
                        <option value="trial">Essai (14j)</option>
                        <option value="monthly">Mensuel (800 DZD)</option>
                        <option value="yearly">Annuel (6900 DZD)</option>
                        <option value="lifetime">À vie (14900 DZD)</option>
                    </select>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="button" onclick="closeModal('edit-resto-modal')" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold">Annuler</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg font-bold">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL : REFUSER PAIEMENT -->
    <div id="reject-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
            <h2 class="text-xl font-bold text-red-600 mb-2">Refuser le paiement</h2>
            <p class="text-sm text-gray-500 mb-4">Expliquez la raison au gérant (il recevra une notification sur son espace).</p>
            <form action="actions/reject_payment.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="payment_id" id="reject-payment-id">
                <textarea name="reason" required class="w-full border border-gray-300 rounded-lg p-2.5 h-24" placeholder="Ex: La référence est introuvable sur mon compte Baridimob."></textarea>
                <div class="flex gap-2">
                    <button type="button" onclick="closeModal('reject-modal')" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold">Annuler</button>
                    <button type="submit" class="flex-1 bg-red-600 text-white py-2.5 rounded-lg font-bold">Confirmer le refus</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script>
        // Gestion des Onglets
        function showTab(tabName) {
            // Cacher tous les tabs
            document.getElementById('tab-dashboard').classList.add('hidden');
            document.getElementById('tab-restaurants').classList.add('hidden');
            document.getElementById('tab-payments').classList.add('hidden');
            
            // Afficher le bon
            document.getElementById('tab-' + tabName).classList.remove('hidden');

            // Mettre à jour les boutons de navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('bg-orange-600', 'text-white');
                link.classList.add('text-gray-400');
            });
            const activeLink = document.getElementById('nav-' + tabName);
            activeLink.classList.remove('text-gray-400');
            activeLink.classList.add('bg-orange-600', 'text-white');

            // Mettre à jour le titre
            const titles = {
                'dashboard': 'Tableau de Bord',
                'restaurants': 'Gestion des Restaurants',
                'payments': 'Historique des Paiements'
            };
            document.getElementById('page-title').innerText = titles[tabName];
        }

        // Modales
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.getElementById(id).classList.add('flex');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.getElementById(id).classList.remove('flex');
        }

        // Ouvrir Modale Edition
        function openEditModal(resto) {
            document.getElementById('edit-resto-id').value = resto.id;
            document.getElementById('edit-resto-nom').value = resto.nom;
            document.getElementById('edit-resto-plan').value = resto.plan;
            openModal('edit-resto-modal');
        }

        // Ouvrir Modale Refus
        function openRejectModal(paymentId) {
            document.getElementById('reject-payment-id').value = paymentId;
            openModal('reject-modal');
        }

        // --- CHART.JS CONFIGURATION ---
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(234, 88, 12, 0.5)'); // Orange transparent
        gradient.addColorStop(1, 'rgba(234, 88, 12, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Revenus (DZD)',
                    data: <?= json_encode($chart_values) ?>,
                    borderColor: '#ea580c',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#ea580c',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>