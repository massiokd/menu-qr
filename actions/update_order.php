<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

// Vérification de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { 
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']); 
    exit; 
}

$pdo = getDB();
$resto_id = $_SESSION['restaurant_id'];

// On reçoit le tableau d'IDs dans le nouvel ordre depuis le JavaScript
$ids = $_POST['ids'] ?? []; 
$type = $_POST['type'] ?? ''; // 'category' ou 'item'

if (!empty($ids) && is_array($ids)) {
    
    // 1. Si c'est pour les catégories
    if ($type === 'category') {
        foreach ($ids as $index => $id) {
            // On met à jour l'ordre (0, 1, 2...) en vérifiant que la catégorie appartient bien au resto
            $stmt = $pdo->prepare("UPDATE categories SET ordre = ? WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$index, $id, $resto_id]);
        }
    } 
    // 2. Si c'est pour les plats
    elseif ($type === 'item') {
        foreach ($ids as $index => $id) {
            // Sécurité : On vérifie que le plat appartient à une catégorie de CE restaurant
            $stmt = $pdo->prepare("
                UPDATE items 
                SET ordre = ? 
                WHERE id = ? AND category_id IN (
                    SELECT id FROM categories WHERE restaurant_id = ?
                )
            ");
            $stmt->execute([$index, $id, $resto_id]);
        }
    }
}

// On renvoie une réponse JSON pour que le JavaScript puisse afficher le message de succès
echo json_encode(['success' => true]);
exit;