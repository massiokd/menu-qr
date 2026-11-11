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
        $resto_id = $payment['restaurant_id'];

        // 1. Mettre à jour le paiement
        $stmtPay = $pdo->prepare("UPDATE payments SET statut = 'refuse', admin_note = ? WHERE id = ?");
        $stmtPay->execute([$reason, $payment_id]);

        // 2. Vérifier si le restaurant est encore valide
        $stmtCurrent = $pdo->prepare("SELECT expires_at FROM restaurants WHERE id = ?");
        $stmtCurrent->execute([$resto_id]);
        $current_expiry = $stmtCurrent->fetchColumn();
        
        $is_still_valid = ($current_expiry && strtotime($current_expiry) > time());

        // 3. Si le restaurant est encore valide, on le laisse tranquille. Sinon, on le suspend.
        if ($is_still_valid) {
            // Il lui reste du temps, on annule juste le statut "en attente"
            $stmtResto = $pdo->prepare("UPDATE restaurants SET subscription_status = 'active' WHERE id = ?");
        } else {
            // Il était déjà expiré, on le suspend
            $stmtResto = $pdo->prepare("UPDATE restaurants SET subscription_status = 'suspended', is_active = false WHERE id = ?");
        }
        $stmtResto->execute([$resto_id]);
    }
}

header('Location: ../super_admin.php');
exit;