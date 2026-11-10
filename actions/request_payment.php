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

    // Activer le menu temporairement (en attente de vérification)
    $new_expires_at = date('Y-m-d H:i:s', strtotime('+1 month'));
    $stmtUpdate = $pdo->prepare("UPDATE restaurants SET subscription_status = 'pending_verification', is_active = true, expires_at = ? WHERE id = ?");
    $stmtUpdate->execute([$new_expires_at, $resto_id]);
}

header('Location: ../admin/subscription.php?success=1');
exit;