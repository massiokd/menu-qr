<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

$resto_id = $_POST['resto_id'] ?? 0;
$duration = $_POST['duration'] ?? '1_month';
$pdo = getDB();

// 1. Récupérer la date d'expiration actuelle
$stmt = $pdo->prepare("SELECT expires_at FROM restaurants WHERE id = ?");
$stmt->execute([$resto_id]);
$current_expiry = $stmt->fetch()['expires_at'];

// 2. Calculer la nouvelle date
// Si l'abonnement est encore actif, on ajoute à la date actuelle. Sinon, on repart d'aujourd'hui.
$base_date = ($current_expiry && strtotime($current_expiry) > time()) ? $current_expiry : date('Y-m-d H:i:s');
$new_expiry = date('Y-m-d H:i:s', strtotime($base_date . ($duration === '1_year' ? ' +1 year' : ' +1 month')));

// 3. Mettre à jour le restaurant
$stmtUpdate = $pdo->prepare("UPDATE restaurants SET expires_at = ?, is_active = true, subscription_status = 'active' WHERE id = ?");
$stmtUpdate->execute([$new_expiry, $resto_id]);

header('Location: ../super_admin.php');
exit;