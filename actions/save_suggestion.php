<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $resto_id = $_SESSION['restaurant_id'];
    $pdo = getDB();

    // 🛡️ SÉCURITÉ : Vérifier combien de suggestions ont été faites dans les dernières 24h
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM suggestions WHERE restaurant_id = ? AND created_at >= NOW() - INTERVAL '24 hours'");
    $stmtCheck->execute([$resto_id]);
    $recent_suggestions = $stmtCheck->fetchColumn();

    if ($recent_suggestions >= 3) {
        // Limite atteinte : on redirige avec un message d'erreur
        header('Location: ../admin/settings.php?tab=suggestions&error=limit_reached');
        exit;
    }

    // Vérification de la taille (500 caractères max) et enregistrement
    if (!empty($message) && mb_strlen($message) <= 500) {
        $stmt = $pdo->prepare("INSERT INTO suggestions (restaurant_id, message) VALUES (?, ?)");
        $stmt->execute([$resto_id, $message]);
    }
}

header('Location: ../admin/settings.php?tab=suggestions&success=1');
exit;