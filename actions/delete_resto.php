<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resto_id = $_POST['resto_id'] ?? 0;
    $pdo = getDB();
    // Le ON DELETE CASCADE dans Supabase supprimera tout (users, categories, items, payments)
    $stmt = $pdo->prepare("DELETE FROM restaurants WHERE id = ?");
    $stmt->execute([$resto_id]);
}
header('Location: ../super_admin.php?success=deleted');
exit;