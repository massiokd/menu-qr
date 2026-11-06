<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $nom = $_POST['nom'] ?? '';
    $slogan = $_POST['slogan'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $instagram = $_POST['instagram_url'] ?? '';
    $facebook = $_POST['facebook_url'] ?? '';

    $stmt = $pdo->prepare("UPDATE restaurants SET nom = ?, slogan = ?, telephone = ?, instagram_url = ?, facebook_url = ? WHERE id = ?");
    $stmt->execute([$nom, $slogan, $telephone, $instagram, $facebook, $_SESSION['restaurant_id']]);
}

header('Location: ../admin/settings.php?success=profile');
exit;