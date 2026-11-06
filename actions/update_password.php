<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // 1. Vérifier le mot de passe actuel
    if (!$user || !password_verify($current_pass, $user['password_hash'])) {
        header('Location: ../admin/settings.php?tab=securite&error=wrong_password');
        exit;
    }

    // 2. Vérifier la correspondance des nouveaux
    if ($new_pass !== $confirm_pass) {
        header('Location: ../admin/settings.php?tab=securite&error=mismatch');
        exit;
    }

    // 3. Vérifier la longueur
    if (strlen($new_pass) < 6) {
        header('Location: ../admin/settings.php?tab=securite&error=short');
        exit;
    }

    // 4. Mise à jour
    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmtUpdate->execute([$hash, $_SESSION['user_id']]);
    
    header('Location: ../admin/settings.php?tab=securite&success=password');
    exit;
}