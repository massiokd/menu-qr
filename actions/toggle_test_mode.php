<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    die(json_encode(['success' => false, 'error' => 'Non autorisé']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resto_id = $_POST['resto_id'] ?? 0;
    $is_test = $_POST['is_test'] === 'true' ? true : false;

    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE restaurants SET is_test = ? WHERE id = ?");
    // PostgreSQL attend 'true'/'false' ou 1/0
    $stmt->execute([$is_test ? 'true' : 'false', $resto_id]);

    echo json_encode(['success' => true]);
    exit;
}
echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);