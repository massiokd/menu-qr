<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') {
    header('Location: login.php'); exit;
}

$pdo = getDB();
$item_id = $_POST['item_id'] ?? null;
$category_id = $_POST['category_id'];
$nom = $_POST['nom'];
$description = $_POST['description'];
$prix = $_POST['prix'];
$existing_image = $_POST['existing_image'] ?? '';

$image_url = $existing_image; // On garde l'ancienne par défaut

// Gestion de l'upload d'image vers ImgBB (si un fichier est envoyé)
$file = $_FILES['image_camera']['size'] > 0 ? $_FILES['image_camera'] : ($_FILES['image_gallery']['size'] > 0 ? $_FILES['image_gallery'] : null);

if ($file) {
    $imageData = base64_encode(file_get_contents($file['tmp_name']));
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . IMGBB_API_KEY);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'image' => $imageData,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (isset($result['data']['url'])) {
        $image_url = $result['data']['url'];
    }
}

if ($item_id) {
    // UPDATE
    $stmt = $pdo->prepare("UPDATE items SET category_id=?, nom=?, description=?, prix=?, image_url=? WHERE id=?");
    $stmt->execute([$category_id, $nom, $description, $prix, $image_url, $item_id]);
} else {
    // INSERT
    $stmt = $pdo->prepare("INSERT INTO items (category_id, nom, description, prix, image_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$category_id, $nom, $description, $prix, $image_url]);
}

header('Location: ../admin/admin.php');
exit;