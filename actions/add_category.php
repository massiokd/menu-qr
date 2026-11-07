<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['restaurant_id'])) {
    $nom = $_POST['nom'];
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO categories (restaurant_id, nom) VALUES (?, ?)");
    $stmt->execute([$_SESSION['restaurant_id'], $nom]);
}
header('Location: ../admin/admin.php');
exit;