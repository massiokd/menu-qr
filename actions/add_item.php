<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $category_id = $_POST['category_id'];
    $nom = $_POST['nom'];
    $description = $_POST['description'];
    $prix = $_POST['prix'];

    // Sécurité : vérifier que la catégorie appartient bien au resto connecté
    $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND restaurant_id = ?");
    $stmtCheck->execute([$category_id, $_SESSION['restaurant_id']]);
    
    if ($stmtCheck->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO items (category_id, nom, description, prix, disponible) VALUES (?, ?, ?, ?, true)");
        $stmt->execute([$category_id, $nom, $description, $prix]);
    }
}

header('Location: ../admin/admin.php');
exit;