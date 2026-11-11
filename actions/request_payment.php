<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';
require 'upload_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $resto_id = $_SESSION['restaurant_id'];
    
    // 🛡️ SÉCURITÉ : Vérifier s'il n'y a pas déjà un paiement en attente
    $stmtCheck = $pdo->prepare("SELECT id FROM payments WHERE restaurant_id = ? AND statut = 'en_attente'");
    $stmtCheck->execute([$resto_id]);
    if ($stmtCheck->fetch()) {
        header('Location: ../admin/subscription.php?error=already_pending');
        exit;
    }

    $plan = $_POST['plan'] ?? '';
    $reference = trim($_POST['reference'] ?? '');
    
    // 🛡️ SÉCURITÉ : Vérifier que le plan demandé est valide
    $prix = ['monthly' => 800, 'yearly' => 6900, 'lifetime' => 14900];
    if (!array_key_exists($plan, $prix)) {
        die("Plan invalide.");
    }
    $montant = $prix[$plan];

    $receipt_url = '';
    if (isset($_FILES['receipt']) && $_FILES['receipt']['size'] > 0) {
        $new_url = uploadToSupabase($_FILES['receipt'], 'recu');
        if ($new_url) {
            $receipt_url = $new_url;
        } else {
            // Échec de l'upload, on ne valide pas la demande
            header('Location: ../admin/subscription.php?error=upload_failed');
            exit;
        }
    } else {
        // Pas de reçu, on bloque
        header('Location: ../admin/subscription.php?error=no_receipt');
        exit;
    }

    // Enregistrer le paiement
    $stmt = $pdo->prepare("INSERT INTO payments (restaurant_id, plan, montant, reference, receipt_url, statut) VALUES (?, ?, ?, ?, ?, 'en_attente')");
    $stmt->execute([$resto_id, $plan, $montant, $reference, $receipt_url]);

    // Activer le menu temporairement (en attente de vérification)
    $stmtUpdate = $pdo->prepare("UPDATE restaurants SET subscription_status = 'pending_verification', is_active = true WHERE id = ?");
    $stmtUpdate->execute([$resto_id]);
}

header('Location: ../admin/subscription.php?success=1');
exit;