<?php
session_start();
require '../config/db.php';

// Sécurité : super admin seulement
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    die("Non autorisé");
}

$payment_id = $_GET['id'] ?? 0;
$pdo = getDB();

// 1. Récupérer les infos du paiement
$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND statut = 'en_attente'");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if ($payment) {
    $resto_id = $payment['restaurant_id'];
    $plan = $payment['plan'];

    // 2. Calculer la nouvelle date d'expiration
    $expires_at = null;
    if ($plan === 'monthly') { $expires_at = date('Y-m-d H:i:s', strtotime('+1 month')); }
    elseif ($plan === 'yearly') { $expires_at = date('Y-m-d H:i:s', strtotime('+1 year')); }
    // 'lifetime' reste NULL

    // 3. Mettre à jour le restaurant (Plan + Date)
    $stmtUpdate = $pdo->prepare("UPDATE restaurants SET plan = ?, expires_at = ? WHERE id = ?");
    $stmtUpdate->execute([$plan, $expires_at, $resto_id]);

    // 4. Marquer le paiement comme validé
    $stmtPay = $pdo->prepare("UPDATE payments SET statut = 'valide' WHERE id = ?");
    $stmtPay->execute([$payment_id]);
}

header('Location: ../super_admin.php?success=payment_validated');
exit;