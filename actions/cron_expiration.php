<?php
require '../config/db.php';
require 'email_helper.php';

// Sécurité : On demande un mot de passe secret dans l'URL
$secret_key = "FENNEC_EXPIRATION_2026";
if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    die("Accès refusé.");
}

$pdo = getDB();

// --- 1. VÉRIFIER LES EXPIRATIONS DANS 3 JOURS ---
$date_3_jours = date('Y-m-d', strtotime('+3 days'));
$stmt = $pdo->prepare("
    SELECT u.email, r.nom as resto_nom, r.expires_at 
    FROM users u 
    JOIN restaurants r ON u.restaurant_id = r.id 
    WHERE u.role = 'gerant' 
    AND r.is_active = true 
    AND r.plan != 'lifetime' 
    AND DATE(r.expires_at) = ?
");
$stmt->execute([$date_3_jours]);
$expiring_soon = $stmt->fetchAll();

foreach ($expiring_soon as $user) {
    $expiry_date_fr = date('d/m/Y', strtotime($user['expires_at']));
    sendExpiringSoonEmail($user['email'], $user['resto_nom'], 3, $expiry_date_fr);
}

// --- 2. VÉRIFIER LES EXPIRATIONS AUJOURD'HUI (ou récentes) ---
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT u.email, r.nom as resto_nom 
    FROM users u 
    JOIN restaurants r ON u.restaurant_id = r.id 
    WHERE u.role = 'gerant' 
    AND r.is_active = true 
    AND r.plan != 'lifetime' 
    AND DATE(r.expires_at) = ?
");
$stmt->execute([$today]);
$expired = $stmt->fetchAll();

foreach ($expired as $user) {
    sendExpiredEmail($user['email'], $user['resto_nom']);
}

echo "Vérification terminée. " . count($expiring_soon) . " avertissements envoyés, " . count($expired) . " expirations notifiées.";
?>