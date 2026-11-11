<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';
require 'email_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

$payment_id = $_GET['id'] ?? 0;
$pdo = getDB();

// 1. On récupère le paiement
$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND statut = 'en_attente'");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if ($payment) {
    $resto_id = $payment['restaurant_id'];
    $plan = $payment['plan'];

    // 2. On récupère la date d'expiration actuelle
    $stmtCurrent = $pdo->prepare("SELECT expires_at FROM restaurants WHERE id = ?");
    $stmtCurrent->execute([$resto_id]);
    $current_expiry = $stmtCurrent->fetchColumn();

    // 3. On calcule la nouvelle date à partir de la date du PAIEMENT (pas d'aujourd'hui)
    $payment_date = $payment['created_at'];
    
    $baseDate = new DateTime($payment_date);
    // Si le client était encore valide, on ajoute à partir de sa date d'expiration
    if ($current_expiry && strtotime($current_expiry) > strtotime($payment_date)) {
        $baseDate = new DateTime($current_expiry);
    }

    $expires_at = null;
    if ($plan === 'monthly') { 
        $baseDate->modify('+1 month');
        $expires_at = $baseDate->format('Y-m-d H:i:s');
    } elseif ($plan === 'yearly') { 
        $baseDate->modify('+1 year');
        $expires_at = $baseDate->format('Y-m-d H:i:s');
    } 
    // Si 'lifetime', $expires_at reste NULL

    // 4. MISE À JOUR DU RESTAURANT (On applique la nouvelle date en base)
    $stmtUpdate = $pdo->prepare("UPDATE restaurants SET plan = ?, expires_at = ?, is_active = true, subscription_status = 'active' WHERE id = ?");
    $stmtUpdate->execute([$plan, $expires_at, $resto_id]);

    // 5. MISE À JOUR DU PAIEMENT
    $note_admin = "Votre paiement a été vérifié et validé avec succès. Merci !";
    $stmtPay = $pdo->prepare("UPDATE payments SET statut = 'valide', admin_note = ? WHERE id = ?");
    $stmtPay->execute([$note_admin, $payment_id]);

    // 6. RÉCUPÉRATION DES INFOS POUR L'EMAIL (On lit la base de données APRÈS la mise à jour)
    $stmtInfo = $pdo->prepare("SELECT u.email, r.nom as resto_nom, r.expires_at, r.plan FROM users u JOIN restaurants r ON u.restaurant_id = r.id WHERE u.restaurant_id = ? AND u.role = 'gerant'");
    $stmtInfo->execute([$resto_id]);
    $info = $stmtInfo->fetch();

    // 7. ENVOI DE L'EMAIL
    if ($info) {
        // On formate la date pour l'email. Si c'est lifetime, on met "À vie".
        $expiry_date_fr = ($info['plan'] === 'lifetime' || empty($info['expires_at'])) ? 'À vie' : date('d/m/Y', strtotime($info['expires_at']));
        
        // On traduit le plan pour faire joli dans l'email
        $plan_noms = ['monthly' => 'Mensuel', 'yearly' => 'Annuel', 'lifetime' => 'À vie'];
        $plan_affichage = $plan_noms[$info['plan']] ?? $info['plan'];

        sendPaymentConfirmedEmail($info['email'], $info['resto_nom'], $plan_affichage, $expiry_date_fr);
    }
}

header('Location: ../super_admin.php');
exit;