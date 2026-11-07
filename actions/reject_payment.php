<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

$payment_id = $_GET['id'] ?? 0;
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND statut = 'en_attente'");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if ($payment) {
    // Mettre à jour le paiement
    $stmtPay = $pdo->prepare("UPDATE payments SET statut = 'refuse' WHERE id = ?");
    $stmtPay->execute([$payment_id]);

    // Mettre à jour le restaurant (on le repasse en attente, ou on le suspend)
    // Ici, on le suspend car le paiement est faux
    $stmtResto = $pdo->prepare("UPDATE restaurants SET subscription_status = 'suspended', is_active = false WHERE id = ?");
    $stmtResto->execute([$payment['restaurant_id']]);
}

header('Location: ../super_admin.php');
exit;