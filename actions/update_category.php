<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $cat_id = $_POST['category_id'] ?? 0;
    $nom = $_POST['nom'] ?? '';

    if (!empty($nom)) {
        $stmt = $pdo->prepare("UPDATE categories SET nom = ? WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$nom, $cat_id, $_SESSION['restaurant_id']]);
    }
}

header('Location: ../admin/admin.php');
exit;