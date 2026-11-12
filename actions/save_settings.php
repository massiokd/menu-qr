<?php
// actions/save_settings.php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE app_settings SET value = ? WHERE key = ?");
        $stmt->execute([$_POST['price_monthly'], 'price_monthly']);
        $stmt->execute([$_POST['price_yearly'], 'price_yearly']);
        $stmt->execute([$_POST['price_lifetime'], 'price_lifetime']);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}
header('Location: ../super_admin.php?success=settings_saved');
exit;