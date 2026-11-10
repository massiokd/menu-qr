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

    $image_url = $existing_image; 

    // Gestion de l'upload
    $fileToUpload = null;
    if (isset($_FILES['image_camera']) && $_FILES['image_camera']['size'] > 0) {
        $fileToUpload = $_FILES['image_camera'];
    } elseif (isset($_FILES['image_gallery']) && $_FILES['image_gallery']['size'] > 0) {
        $fileToUpload = $_FILES['image_gallery'];
    }

    if ($fileToUpload) {
        $new_url = uploadToSupabase($fileToUpload, 'plat');
        if ($new_url) {
            
            if (!empty($existing_image)) {
                deleteFromSupabase($existing_image);
            }
            $image_url = $new_url;
        } else {
            // L'UPLOAD A ÉCHOUÉ : On redirige avec une erreur
            $redirectUrl = $item_id ? "item_form.php?id=$item_id&error=upload_failed" : "item_form.php?cat_id=$category_id&error=upload_failed";
            header("Location: ../admin/$redirectUrl");
            exit;
        }
    }

    // Sauvegarde en base
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