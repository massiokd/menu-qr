<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

// Sécurité : vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];
$action = $_POST['action'] ?? '';
$item_id = $_POST['item_id'] ?? 0;

// Vérifier que l'item appartient bien au restaurant connecté (sécurité)
$stmtCheck = $pdo->prepare("
    SELECT i.id FROM items i 
    JOIN categories c ON i.category_id = c.id 
    WHERE i.id = ? AND c.restaurant_id = ?
");
$stmtCheck->execute([$item_id, $resto_id]);
if (!$stmtCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Plat introuvable']);
    exit;
}

// Traitement des actions
if ($action === 'update_prix') {
    $prix = $_POST['prix'] ?? 0;
    $stmt = $pdo->prepare("UPDATE items SET prix = ? WHERE id = ?");
    $stmt->execute([$prix, $item_id]);
    echo json_encode(['success' => true]);
} 
elseif ($action === 'toggle_dispo') {
    // Inverser la disponibilité actuelle (true devient false, etc.)
    $stmt = $pdo->prepare("UPDATE items SET disponible = NOT disponible WHERE id = ? RETURNING disponible");
    $stmt->execute([$item_id]);
    $newStatus = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'new_status' => $newStatus]);
}
else {
    echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}