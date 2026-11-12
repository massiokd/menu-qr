<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

$resto_id = $_GET['resto_id'] ?? 0;
$pdo = getDB();

// On cherche l'utilisateur gérant de ce restaurant
$stmt = $pdo->prepare("SELECT id FROM users WHERE restaurant_id = ? AND role = 'gerant' LIMIT 1");
$stmt->execute([$resto_id]);
$user = $stmt->fetch();

if ($user) {
    // On sauvegarde l'ID admin
    $admin_id = $_SESSION['user_id'];
    
    // On ferme la session admin
    session_write_close();
    
    // On ouvre la session Gérant
    session_name('FENNEC_GERANT');
    session_start();
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['restaurant_id'] = $resto_id;
    $_SESSION['role'] = 'gerant';
    $_SESSION['impersonated_by'] = $admin_id; // Flag magique
    
    header('Location: ../admin/admin.php');
    exit;
}
header('Location: ../super_admin.php');
exit;