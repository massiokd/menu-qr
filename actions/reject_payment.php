<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = $_POST['payment_id'] ?? 0;
    $reason = trim($_POST['reason'] ?? 'Paiement refusé par l\'administrateur.');
    
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND statut = 'en_attente'");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if ($payment) {
        // 1. Mettre à jour le paiement avec la raison
        $stmtPay = $pdo->prepare("UPDATE payments SET statut = 'refuse', admin_note = ? WHERE id = ?");
        $stmtPay->execute([$reason, $payment_id]);

        // 2. Suspendre le restaurant (car le paiement est faux)
        $stmtResto = $pdo->prepare("UPDATE restaurants SET subscription_status = 'suspended', is_active = false WHERE id = ?");
        $stmtResto->execute([$payment['restaurant_id']]);
    }
}

header('Location: ../super_admin.php');
exit;