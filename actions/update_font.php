<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE restaurants SET font_family = ? WHERE id = ?");
    $stmt->execute([$_POST['font_family'], $_SESSION['restaurant_id']]);
}

echo json_encode(['success' => true]);