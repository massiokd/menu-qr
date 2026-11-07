<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $plan = $_POST['plan'];
    $reference = trim($_POST['reference']);
    
    $prix = ['monthly' => 800, 'yearly' => 6900, 'lifetime' => 14900];
    $montant = $prix[$plan] ?? 0;

    $stmt = $pdo->prepare("INSERT INTO payments (restaurant_id, plan, montant, reference) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['restaurant_id'], $plan, $montant, $reference]);
}

header('Location: ../admin/subscription.php?success=1');
exit;