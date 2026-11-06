<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    die("Non autorisé.");
}

$pdo = getDB();
$action = $_POST['action'] ?? '';

if ($action === 'add_resto') {
    $nom = $_POST['nom'];
    $slug = strtolower(trim($_POST['slug']));
    $email = strtolower(trim($_POST['email']));
    
    // Générer un mot de passe par défaut pour le gérant
    $default_password = 'resto123';
    $hash = password_hash($default_password, PASSWORD_DEFAULT);

    // Date d'expiration (14 jours d'essai)
    $expires_at = date('Y-m-d H:i:s', strtotime('+14 days'));

    try {
        $pdo->beginTransaction();

        // 1. Créer le restaurant
        $stmt = $pdo->prepare("INSERT INTO restaurants (nom, slug, plan, expires_at, is_active) VALUES (?, ?, 'trial', ?, true)");
        $stmt->execute([$nom, $slug, $expires_at]);
        $resto_id = $pdo->lastInsertId();

        // 2. Créer le compte gérant lié
        $stmtUser = $pdo->prepare("INSERT INTO users (restaurant_id, email, password_hash, role) VALUES (?, ?, ?, 'gerant')");
        $stmtUser->execute([$resto_id, $email, $hash]);

        // 3. Créer une catégorie par défaut pour ne pas avoir un menu vide
        $stmtCat = $pdo->prepare("INSERT INTO categories (restaurant_id, nom, ordre) VALUES (?, 'Menu Principal', 1)");
        $stmtCat->execute([$resto_id]);

        $pdo->commit();
        header('Location: ../super_admin.php?success=1');
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de la création : " . $e->getMessage());
    }
}

if ($action === 'update_resto') {
    $resto_id = $_POST['resto_id'];
    $plan = $_POST['plan'];
    
    // Calculer la nouvelle date d'expiration selon le plan
    $expires_at = null;
    if ($plan === 'trial') {
        $expires_at = date('Y-m-d H:i:s', strtotime('+14 days'));
    } elseif ($plan === 'monthly') {
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 month'));
    } elseif ($plan === 'yearly') {
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
    } // 'lifetime' reste NULL

    // Mettre à jour le plan et la date
    $stmt = $pdo->prepare("UPDATE restaurants SET plan = ?, expires_at = ? WHERE id = ?");
    $stmt->execute([$plan, $expires_at, $resto_id]);

    // Gérer la suspension/réactivation
    if (isset($_POST['toggle_active'])) {
        $stmtToggle = $pdo->prepare("UPDATE restaurants SET is_active = NOT is_active WHERE id = ?");
        $stmtToggle->execute([$resto_id]);
    }

   header('Location: ../super_admin.php');
}