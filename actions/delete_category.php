<?php
session_start();
require '../config/db.php';

// Vérification de sécurité
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') {
    header('Location: login.php');
    exit;
}

$cat_id = $_GET['id'] ?? 0;
$pdo = getDB();

// On supprime la catégorie SEULEMENT si elle appartient au restaurant connecté
$stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$cat_id, $_SESSION['restaurant_id']]);

// Redirection vers l'admin
header('Location: ../admin/admin.php');
exit;