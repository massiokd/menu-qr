<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') {
    die("Non autorisé");
}

$id = $_GET['id'] ?? 0;
$pdo = getDB();
$stmt = $pdo->prepare("DELETE FROM items WHERE id = ? AND category_id IN (SELECT id FROM categories WHERE restaurant_id = ?)");
$stmt->execute([$id, $_SESSION['restaurant_id']]);

header('Location: ../admin/admin.php');
exit;