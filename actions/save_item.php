<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';
require 'upload_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $item_id = $_POST['item_id'] ?? null;
    $category_id = $_POST['category_id'];
    $nom = $_POST['nom'];
    $description = $_POST['description'];
    $prix = $_POST['prix'];
    $existing_image = $_POST['existing_image'] ?? '';
    
    $nom_ar = $_POST['nom_ar'] ?? '';
    $description_ar = $_POST['description_ar'] ?? '';

    $image_url = $existing_image; // On garde l'ancienne par défaut

    // Gestion de l'upload de la nouvelle image (compressée)
    if (isset($_FILES['image_camera']) && $_FILES['image_camera']['size'] > 0) {
        $new_url = uploadToSupabase($_FILES['image_camera'], 'plat');
        if ($new_url) $image_url = $new_url;
    } 
    elseif (isset($_FILES['image_gallery']) && $_FILES['image_gallery']['size'] > 0) {
        $new_url = uploadToSupabase($_FILES['image_gallery'], 'plat');
        if ($new_url) $image_url = $new_url;
    }

    // Mise à jour ou Insertion
    if ($item_id) {
        $stmt = $pdo->prepare("UPDATE items SET category_id=?, nom=?, description=?, prix=?, image_url=?, nom_ar=?, description_ar=? WHERE id=?");
        $stmt->execute([$category_id, $nom, $description, $prix, $image_url, $nom_ar, $description_ar, $item_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO items (category_id, nom, description, prix, image_url, nom_ar, description_ar) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$category_id, $nom, $description, $prix, $image_url, $nom_ar, $description_ar]);
    }
}

header('Location: ../admin/admin.php');
exit;