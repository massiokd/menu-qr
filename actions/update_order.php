<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { 
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']); 
    exit; 
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

// On reçoit un tableau d'IDs dans l'ordre exact
$ids = $_POST['ids'] ?? []; // ex: [5, 2, 8, 1]
$type = $_POST['type'] ?? ''; // 'category' ou 'item'

if (!empty($ids) && is_array($ids)) {
    if ($type === 'category') {
        foreach ($ids as $index => $id) {
            $stmt = $pdo->prepare("UPDATE categories SET ordre = ? WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$index, $id, $resto_id]);
        }
    } elseif ($type === 'item') {
        foreach ($ids as $index => $id) {
            $stmt = $pdo->prepare("UPDATE items SET ordre = ? WHERE id = ?");
            $stmt->execute([$index, $id]);
        }
    }
}

echo json_encode(['success' => true]);