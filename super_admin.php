<?php
session_name('FENNEC_ADMIN');
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pdo = getDB();

// --- RÉCUPÉRATION DES TARIFS ---
$stmtSettings = $pdo->query("SELECT * FROM app_settings");
$settings = [];
while ($row = $stmtSettings->fetch()) { $settings[$row['key']] = $row['value']; }
$price_monthly = $settings['price_monthly'] ?? 800;
$price_yearly = $settings['price_yearly'] ?? 6900;
$price_lifetime = $settings['price_lifetime'] ?? 14900;

// --- STATISTIQUES ---
$total_rev = $pdo->query("SELECT SUM(montant) FROM payments WHERE statut = 'valide'")->fetchColumn() ?? 0;
$mrr = $pdo->query("SELECT SUM(CASE WHEN plan = 'monthly' THEN $price_monthly WHEN plan = 'yearly' THEN ($price_yearly / 12) ELSE 0 END) FROM restaurants WHERE is_active = true AND plan != 'lifetime' AND (expires_at > NOW() OR expires_at IS NULL)")->fetchColumn() ?? 0;
$active_clients = $pdo->query("SELECT COUNT(*) FROM restaurants WHERE is_active = true AND (plan = 'lifetime' OR expires_at > NOW())")->fetchColumn() ?? 0;
$pending_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE statut = 'en_attente'")->fetchColumn() ?? 0;

// --- ALERTE EXPIRATION (3 JOURS) ---
$stmtExpiring = $pdo->query("
    SELECT id, nom, expires_at 
    FROM restaurants 
    WHERE is_active = true AND plan != 'lifetime' AND expires_at BETWEEN NOW() AND NOW() + INTERVAL '3 days'
    ORDER BY expires_at ASC
");
$expiring_soon = $stmtExpiring->fetchAll();

// --- GRAPHIQUES ---
$chart_data = $pdo->query("SELECT TO_CHAR(created_at, 'YYYY-MM') as month, SUM(montant) as total FROM payments WHERE statut = 'valide' AND created_at >= NOW() - INTERVAL '6 months' GROUP BY month ORDER BY month ASC")->fetchAll();
$chart_labels = []; $chart_values = [];
foreach($chart_data as $row) { $chart_labels[] = $row['month']; $chart_values[] = $row['total']; }

$new_clients_data = $pdo->query("SELECT TO_CHAR(created_at, 'YYYY-MM') as month, COUNT(*) as total FROM restaurants WHERE created_at >= NOW() - INTERVAL '6 months' GROUP BY month ORDER BY month ASC")->fetchAll();
$new_clients_labels = []; $new_clients_values = [];
foreach($new_clients_data as $row) { $new_clients_labels[] = $row['month']; $new_clients_values[] = $row['total']; }

// --- RECHERCHE & PAGINATION ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$whereClause = "WHERE 1=1";
$params = [];
if (!empty($search)) {
    $whereClause .= " AND (nom ILIKE ? OR slug ILIKE ? OR email ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$total_restos = $pdo->prepare("SELECT COUNT(*) FROM restaurants $whereClause");
$total_restos->execute($params);
$total_count = $total_restos->fetchColumn();
$total_pages = ceil($total_count / $limit);

$stmt = $pdo->prepare("SELECT * FROM restaurants $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$restos = $stmt->fetchAll();

// --- HISTORIQUE PAIEMENTS (50 derniers) ---
$payments = $pdo->query("SELECT p.*, r.nom as resto_nom FROM payments p JOIN restaurants r ON p.restaurant_id = r.id ORDER BY p.created_at DESC LIMIT 50")->fetchAll();
$all_restos_for_select = $pdo->query("SELECT id, nom FROM restaurants ORDER BY nom ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FenneTech - Super Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='18' height='18' rx='2' ry='2'></rect><path d='M8 8h.01M16 8h.01M8 16h.01M16 16h.01M12 12h.01'></path></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
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
            <a href="#" onclick="showTab('dashboard')" id="nav-dashboard" class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg bg-orange-600 text-white transition-all"><i class="fa-solid fa-chart-pie w-5"></i> Tableau de Bord</a>
            <a href="#" onclick="showTab('restaurants')" id="nav-restaurants" class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition-all"><i class="fa-solid fa-store w-5"></i> Restaurants</a>
            <a href="#" onclick="showTab('payments')" id="nav-payments" class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition-all"><i class="fa-solid fa-money-bill-wave w-5"></i> Paiements</a>
            <a href="#" onclick="showTab('settings')" id="nav-settings" class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition-all"><i class="fa-solid fa-gear w-5"></i> Paramètres</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-all"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Déconnexion</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="bg-white h-20 flex items-center justify-between px-8 shadow-sm z-10">
            <h1 class="text-2xl font-bold text-gray-800" id="page-title">Tableau de Bord</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium text-gray-500">Admin FenneTech</span>
                <div class="w-10 h-10 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center font-bold"><i class="fa-solid fa-user-shield"></i></div>
            </div>
        </header>

        <!-- TAB: DASHBOARD -->
        <div id="tab-dashboard" class="p-8">
            
            <!-- Alerte Expiration -->
            <?php if(count($expiring_soon) > 0): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0"><i class="fa-solid fa-triangle-exclamation text-red-500"></i></div>
                    <div class="ml-3">
                        <h3 class="text-sm font-bold text-red-800">Attention ! <?= count($expiring_soon) ?> abonnement(s) expire(nt) dans moins de 3 jours :</h3>
                        <div class="mt-2 text-sm text-red-700 flex flex-wrap gap-2">
                            <?php foreach($expiring_soon as $exp): ?>
                                <span class="bg-white px-2 py-1 rounded border border-red-200 font-medium"><?= htmlspecialchars($exp['nom']) ?> (<?= date('d/m', strtotime($exp['expires_at'])) ?>)</span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-bold text-gray-500 uppercase">Revenu Total</h3><div class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-wallet"></i></div></div>
                    <p class="text-3xl font-extrabold text-gray-800"><?= number_format($total_rev, 0, ',', ' ') ?> <span class="text-lg text-gray-400">DZD</span></p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-bold text-gray-500 uppercase">MRR (Mensuel)</h3><div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-chart-line"></i></div></div>
                    <p class="text-3xl font-extrabold text-gray-800"><?= number_format($mrr, 0, ',', ' ') ?> <span class="text-lg text-gray-400">DZD</span></p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-bold text-gray-500 uppercase">Clients Actifs</h3><div class="w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-users"></i></div></div>
                    <p class="text-3xl font-extrabold text-gray-800"><?= $active_clients ?></p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-orange-500">
                    <div class="flex items-center justify-between mb-4"><h3 class="text-sm font-bold text-gray-500 uppercase">À Valider</h3><div class="w-8 h-8 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-clock"></i></div></div>
                    <p class="text-3xl font-extrabold text-gray-800"><?= $pending_payments ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100"><h3 class="text-lg font-bold text-gray-800 mb-4">Évolution des Revenus</h3><div class="h-64 w-full"><canvas id="revenueChart"></canvas></div></div>
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100"><h3 class="text-lg font-bold text-gray-800 mb-4">Nouveaux Clients</h3><div class="h-64 w-full"><canvas id="clientsChart"></canvas></div></div>
            </div>
        </div>

        <!-- TAB: RESTAURANTS -->
        <div id="tab-restaurants" class="p-8 hidden">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <form method="GET" class="flex-1 flex gap-2 w-full md:w-auto">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher un resto (nom, slug, email)..." class="w-full md:w-80 border border-gray-300 rounded-lg px-4 py-2 focus:border-orange-500 outline-none">
                    <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-900"><i class="fa-solid fa-search"></i></button>
                    <?php if($search): ?><a href="super_admin.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-bold hover:bg-gray-300">X</a><?php endif; ?>
                </form>
                
                <div class="flex gap-2">
                    <a href="actions/export_csv.php?type=restos" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg font-bold shadow-sm flex items-center gap-2"><i class="fa-solid fa-file-excel"></i> Exporter</a>
                    <button onclick="openModal('add-resto-modal')" class="bg-orange-600 hover:bg-orange-700 text-white px-5 py-2.5 rounded-lg font-bold shadow-md flex items-center gap-2"><i class="fa-solid fa-plus"></i> Ajouter</button>
                </div>
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
                            $stmtP = $pdo->prepare("SELECT id FROM payments WHERE restaurant_id = ? AND statut = 'en_attente' LIMIT 1");
                            $stmtP->execute([$r['id']]);
                            $has_pending = $stmtP->fetch();
                        ?>
                        <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <div class="font-bold text-gray-800"><?= htmlspecialchars($r['nom']) ?></div>
                                <div class="text-xs text-gray-500">/<?= htmlspecialchars($r['slug']) ?></div>
                                <?php if($has_pending): ?><span class="inline-block mt-1 bg-yellow-100 text-yellow-700 text-[10px] font-bold px-2 py-0.5 rounded-full">⚠️ Paiement en attente</span><?php endif; ?>
                            </td>
                            <td class="p-4"><span class="bg-gray-100 text-gray-700 px-2.5 py-1 rounded-md text-xs font-bold uppercase"><?php $plans_fr = ['trial' => 'Essai', 'monthly' => 'Mensuel', 'yearly' => 'Annuel', 'lifetime' => 'À vie']; echo $plans_fr[$r['plan']] ?? $r['plan']; ?></span></td>
                            <td class="p-4 text-sm text-gray-600"><?= $r['plan'] === 'lifetime' ? '<span class="text-green-600 font-bold">À vie</span>' : ($r['expires_at'] ? date('d/m/Y', strtotime($r['expires_at'])) : 'Non défini') ?></td>
                            <td class="p-4">
                                <?php if($is_online): ?><span class="bg-green-100 text-green-700 px-2.5 py-1 rounded-md text-xs font-bold">En ligne</span>
                                <?php else: ?><span class="bg-red-100 text-red-700 px-2.5 py-1 rounded-md text-xs font-bold">Hors ligne</span><?php endif; ?>
                            </td>
                            <td class="p-4 text-right whitespace-nowrap">
                                <!-- Impersonation -->
                                <a href="actions/impersonate.php?resto_id=<?= $r['id'] ?>" class="text-gray-400 hover:text-purple-600 p-2" title="Se connecter en tant que" onclick="return confirm('Prendre le contrôle de ce compte ?')"><i class="fa-solid fa-user-secret"></i></a>
                                <button onclick="openStatsModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['nom'])) ?>')" class="text-gray-400 hover:text-blue-600 p-2" title="Statistiques"><i class="fa-solid fa-eye"></i></button>
                                <button onclick="openEmailModal(<?= $r['id'] ?>)" class="text-gray-400 hover:text-indigo-600 p-2" title="Envoyer un email"><i class="fa-solid fa-envelope"></i></button>
                                <form action="actions/reset_password_admin.php" method="POST" class="inline" onsubmit="return confirm('Réinitialiser le mot de passe à Fennec2026 ?')"><input type="hidden" name="resto_id" value="<?= $r['id'] ?>"><button type="submit" class="text-gray-400 hover:text-yellow-600 p-2" title="Reset mot de passe"><i class="fa-solid fa-key"></i></button></form>
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($r)) ?>)" class="text-gray-400 hover:text-orange-600 p-2" title="Modifier"><i class="fa-solid fa-pen"></i></button>
                                <button onclick="openDeleteModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['nom'])) ?>')" class="text-gray-400 hover:text-red-600 p-2" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if($total_pages > 1): ?>
            <div class="flex justify-between items-center mt-6 bg-white px-4 py-3 rounded-2xl border border-gray-100 shadow-sm">
                <span class="text-sm text-gray-500">Affichage <?= $offset + 1 ?> à <?= min($offset + $limit, $total_count) ?> sur <?= $total_count ?> restos</span>
                <div class="flex gap-1">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="px-3 py-1 rounded <?= $i === $page ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: PAYMENTS -->
        <div id="tab-payments" class="p-8 hidden">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Historique des Paiements</h2>
                <div class="flex gap-2">
                    <button onclick="openModal('manual-payment-modal')" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2.5 rounded-lg font-bold shadow-md flex items-center gap-2"><i class="fa-solid fa-plus"></i> Ajouter un paiement (Cash)</button>
                    <a href="actions/export_csv.php?type=payments" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg font-bold shadow-sm flex items-center gap-2"><i class="fa-solid fa-file-excel"></i> Exporter</a>
                </div>
            </div>
            
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
                            <td class="p-4 text-sm uppercase font-bold text-gray-500"><?= $plans_fr[$p['plan']] ?? $p['plan'] ?></td>
                            <td class="p-4 font-bold text-gray-800"><?= number_format($p['montant'], 0, ',', ' ') ?> DZD</td>
                            <td class="p-4">
                                <?php if($p['statut'] === 'valide'): ?><span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold">Validé</span>
                                <?php elseif($p['statut'] === 'refuse'): ?><span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold">Refusé</span>
                                <?php else: ?><span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-bold">En attente</span><?php endif; ?>
                            </td>
                            <td class="p-4"><?php if($p['receipt_url']): ?><a href="<?= htmlspecialchars($p['receipt_url']) ?>" target="_blank" class="text-blue-500 hover:underline text-sm"><i class="fa-solid fa-file-invoice"></i> Voir</a><?php endif; ?></td>
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

        <!-- TAB: SETTINGS -->
        <div id="tab-settings" class="p-8 hidden">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Paramètres des Tarifs</h2>
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 max-w-2xl">
                <form action="actions/save_settings.php" method="POST" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">Prix Mensuel (DZD)</label><input type="number" name="price_monthly" value="<?= $price_monthly ?>" class="w-full border border-gray-300 rounded-lg p-2.5"></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">Prix Annuel (DZD)</label><input type="number" name="price_yearly" value="<?= $price_yearly ?>" class="w-full border border-gray-300 rounded-lg p-2.5"></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">Prix À Vie (DZD)</label><input type="number" name="price_lifetime" value="<?= $price_lifetime ?>" class="w-full border border-gray-300 rounded-lg p-2.5"></div>
                    </div>
                    <button type="submit" class="bg-orange-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-orange-700 w-max mt-2">Mettre à jour</button>
                </form>
            </div>
        </div>
    </main>

    <!-- MODALES -->
    <!-- 1. Ajouter Resto -->
    <div id="add-resto-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Ajouter un restaurant</h2>
            <form action="actions/super_admin_actions.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="add_resto">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Nom</label><input type="text" name="nom" required class="w-full border border-gray-300 rounded-lg p-2"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">Slug</label><input type="text" name="slug" required class="w-full border border-gray-300 rounded-lg p-2"></div>
                </div>
                <div><label class="block text-sm font-bold text-gray-700 mb-1">Email du gérant</label><input type="email" name="email" required class="w-full border border-gray-300 rounded-lg p-2"></div>
                <div class="flex gap-2 mt-4"><button type="button" onclick="closeModal('add-resto-modal')" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold">Annuler</button><button type="submit" class="flex-1 bg-orange-600 text-white py-2.5 rounded-lg font-bold">Créer</button></div>
            </form>
        </div>
    </div>

    <!-- 2. Edit Resto -->
    <div id="edit-resto-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Modifier le restaurant</h2>
            <form action="actions/super_admin_actions.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="update_resto">
                <input type="hidden" name="resto_id" id="edit-resto-id">
                <div><label class="block text-sm font-bold text-gray-700 mb-1">Nom</label><input type="text" name="nom" id="edit-resto-nom" required class="w-full border border-gray-300 rounded-lg p-2"></div>
                <div><label class="block text-sm font-bold text-gray-700 mb-1">Plan</label><select name="plan" id="edit-resto-plan" class="w-full border border-gray-300 rounded-lg p-2"><option value="trial">Essai</option><option value="monthly">Mensuel</option><option value="yearly">Annuel</option><option value="lifetime">À vie</option></select></div>
                <div class="flex gap-2 mt-4"><button type="button" onclick="closeModal('edit-resto-modal')" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold">Annuler</button><button type="submit" class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg font-bold">Mettre à jour</button></div>
            </form>
        </div>
    </div>

    <!-- 3. Manual Payment Modal -->
    <div id="manual-payment-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Ajouter un paiement (Espèces)</h2>
            <form action="actions/add_manual_payment.php" method="POST" class="flex flex-col gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Restaurant</label>
                    <select name="resto_id" required class="w-full border border-gray-300 rounded-lg p-2">
                        <?php foreach($all_restos_for_select as $ar): ?><option value="<?= $ar['id'] ?>"><?= htmlspecialchars($ar['nom']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Formule souscrite</label>
                    <select name="plan" id="manual-plan" onchange="updateManualAmount()" class="w-full border border-gray-300 rounded-lg p-2">
                        <option value="monthly" data-price="<?= $price_monthly ?>">Mensuel (<?= $price_monthly ?> DZD)</option>
                        <option value="yearly" data-price="<?= $price_yearly ?>">Annuel (<?= $price_yearly ?> DZD)</option>
                        <option value="lifetime" data-price="<?= $price_lifetime ?>">À vie (<?= $price_lifetime ?> DZD)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Montant encaissé (DZD)</label>
                    <input type="number" name="montant" id="manual-amount" value="<?= $price_monthly ?>" required class="w-full border border-gray-300 rounded-lg p-2">
                </div>
                <div class="flex gap-2 mt-4"><button type="button" onclick="closeModal('manual-payment-modal')" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold">Annuler</button><button type="submit" class="flex-1 bg-green-600 text-white py-2.5 rounded-lg font-bold">Valider le paiement</button></div>
            </form>
        </div>
    </div>

    <!-- 4. Stats Modal -->
    <div id="stats-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl text-center">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Stats : <span id="stats-resto-name"></span></h2>
            <div class="text-4xl font-extrabold text-blue-600 mb-2" id="stats-scans">0</div>
            <p class="text-sm text-gray-500 mb-6">Scans du QR Code au total</p>
            <button onclick="closeModal('stats-modal')" class="w-full bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold">Fermer</button>
        </div>
    </div>

    <!-- 5. Email Modal -->
    <div id="email-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Envoyer un email</h2>
            <form action="actions/send_manual_email.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="resto_id" id="email-resto-id">
                <div><label class="block text-sm font-bold text-gray-700 mb-1">Sujet</label><input type="text" name="subject" required class="w-full border border-gray-300 rounded-lg p-2" value="Information importante de FenneTech"></div>
                <div><label class="block text-sm font-bold text-gray-700 mb-1">Message</label><textarea name="message" required class="w-full border border-gray-300 rounded-lg p-2 h-32"></textarea></div>
                <div class="flex gap-2"><button type="button" onclick="closeModal('email-modal')" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold">Annuler</button><button type="submit" class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg font-bold">Envoyer</button></div>
            </form>
        </div>
    </div>

    <!-- 6. Delete Modal -->
    <div id="delete-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
            <div class="text-center mb-4 text-red-500 text-4xl"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h2 class="text-xl font-bold text-center text-gray-800 mb-2">Supprimer <span id="delete-resto-name"></span> ?</h2>
            <form action="actions/delete_resto.php" method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="resto_id" id="delete-resto-id">
                <div class="flex gap-2"><button type="button" onclick="closeModal('delete-modal')" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-bold">Annuler</button><button type="submit" class="flex-1 bg-red-600 text-white py-2.5 rounded-lg font-bold">Oui, supprimer</button></div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            ['dashboard', 'restaurants', 'payments', 'settings'].forEach(tab => {
                document.getElementById('tab-' + tab).classList.add('hidden');
                document.getElementById('nav-' + tab).classList.remove('bg-orange-600', 'text-white');
                document.getElementById('nav-' + tab).classList.add('text-gray-400');
            });
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            document.getElementById('nav-' + tabName).classList.remove('text-gray-400');
            document.getElementById('nav-' + tabName).classList.add('bg-orange-600', 'text-white');
            const titles = { 'dashboard': 'Tableau de Bord', 'restaurants': 'Gestion des Restaurants', 'payments': 'Historique des Paiements', 'settings': 'Paramètres' };
            document.getElementById('page-title').innerText = titles[tabName];
        }
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }
        function openEditModal(resto) {
            document.getElementById('edit-resto-id').value = resto.id;
            document.getElementById('edit-resto-nom').value = resto.nom;
            document.getElementById('edit-resto-plan').value = resto.plan;
            openModal('edit-resto-modal');
        }
        function openRejectModal(id) { document.getElementById('reject-payment-id').value = id; openModal('reject-modal'); }
        function openEmailModal(id) { document.getElementById('email-resto-id').value = id; openModal('email-modal'); }
        function openDeleteModal(id, name) { document.getElementById('delete-resto-id').value = id; document.getElementById('delete-resto-name').innerText = name; openModal('delete-modal'); }
        function openStatsModal(id, name) {
            document.getElementById('stats-resto-name').innerText = name;
            document.getElementById('stats-scans').innerText = '...';
            openModal('stats-modal');
            fetch('actions/get_resto_stats.php?id=' + id).then(res => res.json()).then(data => { document.getElementById('stats-scans').innerText = data.scans; });
        }
        function updateManualAmount() {
            const select = document.getElementById('manual-plan');
            const price = select.options[select.selectedIndex].getAttribute('data-price');
            document.getElementById('manual-amount').value = price;
        }

        // Charts
        const ctx1 = document.getElementById('revenueChart').getContext('2d');
        const gradient1 = ctx1.createLinearGradient(0, 0, 0, 400);
        gradient1.addColorStop(0, 'rgba(234, 88, 12, 0.5)');
        gradient1.addColorStop(1, 'rgba(234, 88, 12, 0.0)');
        new Chart(ctx1, {
            type: 'line',
            data: { labels: <?= json_encode($chart_labels) ?>, datasets: [{ label: 'Revenus', data: <?= json_encode($chart_values) ?>, borderColor: '#ea580c', backgroundColor: gradient1, borderWidth: 3, fill: true, tension: 0.4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const ctx2 = document.getElementById('clientsChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: { labels: <?= json_encode($new_clients_labels) ?>, datasets: [{ label: 'Nouveaux Clients', data: <?= json_encode($new_clients_values) ?>, backgroundColor: '#3b82f6', borderRadius: 6 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    </script>
</body>
</html>