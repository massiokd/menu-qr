<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    
    // Vérification de la taille (500 caractères max)
    if (!empty($message) && mb_strlen($message) <= 500) {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO suggestions (restaurant_id, message) VALUES (?, ?)");
        $stmt->execute([$_SESSION['restaurant_id'], $message]);
    }
}

header('Location: ../admin/settings.php?tab=suggestions&success=1');
exit;