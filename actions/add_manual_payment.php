<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';
require 'email_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $resto_id = $_POST['resto_id'] ?? 0;
    $plan = $_POST['plan'] ?? 'monthly';
    $montant = (int)($_POST['montant'] ?? 0);

    $baseDate = new DateTime();
    $expires_at = null;
    if ($plan === 'monthly') { $baseDate->modify('+1 month'); $expires_at = $baseDate->format('Y-m-d H:i:s'); }
    elseif ($plan === 'yearly') { $baseDate->modify('+1 year'); $expires_at = $baseDate->format('Y-m-d H:i:s'); }

    // 1. Enregistrer le paiement validé
    $stmt = $pdo->prepare("INSERT INTO payments (restaurant_id, plan, montant, statut, reference, admin_note) VALUES (?, ?, ?, 'valide', 'Paiement Manuel', 'Encaissé en espèces par l''admin.')");
    $stmt->execute([$resto_id, $plan, $montant]);

    // 2. Mettre à jour le restaurant
    $stmtResto = $pdo->prepare("UPDATE restaurants SET plan = ?, expires_at = ?, is_active = true, subscription_status = 'active' WHERE id = ?");
    $stmtResto->execute([$plan, $expires_at, $resto_id]);

    // 3. Envoyer l'email de confirmation au gérant
    $stmtUser = $pdo->prepare("SELECT u.email, r.nom as resto_nom FROM users u JOIN restaurants r ON u.restaurant_id = r.id WHERE u.restaurant_id = ? AND u.role = 'gerant'");
    $stmtUser->execute([$resto_id]);
    $user = $stmtUser->fetch();
    
    if ($user) {
        $expiry_fr = $plan === 'lifetime' ? 'À vie' : $baseDate->format('d/m/Y');
        $plans_fr = ['monthly' => 'Mensuel', 'yearly' => 'Annuel', 'lifetime' => 'À vie'];
        sendPaymentConfirmedEmail($user['email'], $user['resto_nom'], $plans_fr[$plan], $expiry_fr);
    }
}
header('Location: ../super_admin.php?success=manual_payment');
exit;