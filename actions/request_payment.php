<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';
require 'upload_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $resto_id = $_SESSION['restaurant_id'];
    $plan = $_POST['plan'];
    $reference = trim($_POST['reference'] ?? '');
    
    $prix = ['monthly' => 800, 'yearly' => 6900, 'lifetime' => 14900];
    $montant = $prix[$plan] ?? 0;

    // Upload de l'image du reçu
    $receipt_url = '';
    if (isset($_FILES['receipt']) && $_FILES['receipt']['size'] > 0) {
        $new_url = uploadToSupabase($_FILES['receipt'], 'recu');
        if ($new_url) {
            $receipt_url = $new_url;
        }
    }

    // Enregistrer le paiement
    $stmt = $pdo->prepare("INSERT INTO payments (restaurant_id, plan, montant, reference, receipt_url, statut) VALUES (?, ?, ?, ?, ?, 'en_attente')");
    $stmt->execute([$resto_id, $plan, $montant, $reference, $receipt_url]);

    // On active le menu temporairement, mais on NE TOUCHE PAS à la date d'expiration.
    // C'est la validation de l'admin qui calculera la vraie date.
    $stmtUpdate = $pdo->prepare("UPDATE restaurants SET subscription_status = 'pending_verification', is_active = true WHERE id = ?");
    $stmtUpdate->execute([$resto_id]);
}

header('Location: ../admin/subscription.php?success=1');
exit;