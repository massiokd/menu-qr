<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';
require 'email_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resto_id = $_POST['resto_id'] ?? 0;
    $subject = $_POST['subject'] ?? 'Message de FenneTech';
    $message = $_POST['message'] ?? '';
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE restaurant_id = ? AND role = 'gerant'");
    $stmt->execute([$resto_id]);
    $email = $stmt->fetchColumn();

    if ($email) {
        $apiKey = getenv('BREVO_API_KEY');
        $html = nl2br(htmlspecialchars($message));
        
        // On utilise la fonction sendEmailBrevo de ton email_helper.php
        sendEmailBrevo($apiKey, $email, $subject, $html);
    }
}
header('Location: ../super_admin.php?success=email_sent');
exit;