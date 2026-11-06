<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$pdo = getDB();
$theme = $_POST['theme'] ?? 'classique';
$resto_id = $_SESSION['restaurant_id'];

$stmt = $pdo->prepare("UPDATE restaurants SET theme = ? WHERE id = ?");
$stmt->execute([$theme, $resto_id]);

echo json_encode(['success' => true]);