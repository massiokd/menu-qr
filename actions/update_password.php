<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    
    if (strlen($new_password) >= 6) {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        header('Location: ../admin/settings.php?success=password');
    } else {
        header('Location: ../admin/settings.php?error=short');
    }
    exit;
}