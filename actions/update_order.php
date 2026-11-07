<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

$id = $_POST['id'] ?? 0;
$type = $_POST['type'] ?? ''; // 'category' ou 'item'
$direction = $_POST['direction'] ?? ''; // 'up' ou 'down'
$resto_id = $_SESSION['restaurant_id'];

$pdo = getDB();

if ($type === 'category') {
    $stmt = $pdo->prepare("SELECT id, ordre FROM categories WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$id, $resto_id]);
    $current = $stmt->fetch();
    
    if ($current) {
        $new_ordre = $direction === 'up' ? $current['ordre'] - 1 : $current['ordre'] + 1;
        $stmtUpdate = $pdo->prepare("UPDATE categories SET ordre = ? WHERE id = ?");
        $stmtUpdate->execute([$new_ordre, $id]);
    }
}
elseif ($type === 'item') {
    $stmt = $pdo->prepare("SELECT id, ordre FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    
    if ($current) {
        $new_ordre = $direction === 'up' ? $current['ordre'] - 1 : $current['ordre'] + 1;
        $stmtUpdate = $pdo->prepare("UPDATE items SET ordre = ? WHERE id = ?");
        $stmtUpdate->execute([$new_ordre, $id]);
    }
}

echo json_encode(['success' => true]);