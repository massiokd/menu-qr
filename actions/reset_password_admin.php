<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resto_id = $_POST['resto_id'] ?? 0;
    $pdo = getDB();
    
    $new_password = 'Fennec2026'; // Mot de passe temporaire
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE restaurant_id = ? AND role = 'gerant'");
    $stmt->execute([$hash, $resto_id]);
}
header('Location: ../super_admin.php?success=password_reset');
exit;