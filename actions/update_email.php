<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id'])) { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_email = strtolower(trim($_POST['new_email'] ?? ''));
    $current_pass = $_POST['current_password'] ?? '';
    $pdo = getDB();

    // 1. Vérifier le mot de passe actuel
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && password_verify($current_pass, $user['password_hash'])) {
        // 2. Vérifier si le nouvel email n'est pas déjà pris
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmtCheck->execute([$new_email, $_SESSION['user_id']]);
        
        if ($stmtCheck->fetch()) {
            header('Location: ../admin/settings.php?tab=securite&error=email_taken');
            exit;
        }

        // 3. Mettre à jour
        $stmtUpdate = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmtUpdate->execute([$new_email, $_SESSION['user_id']]);
        header('Location: ../admin/settings.php?tab=securite&success=email');
    } else {
        header('Location: ../admin/settings.php?tab=securite&error=wrong_password');
    }
    exit;
}