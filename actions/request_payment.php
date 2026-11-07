<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    $resto_id = $_SESSION['restaurant_id'];
    $plan = $_POST['plan'];
    $reference = trim($_POST['reference'] ?? '');
    
    $prix = ['monthly' => 800, 'yearly' => 6900, 'lifetime' => 14900];
    $montant = $prix[$plan] ?? 0;

    // 1. Upload de l'image sur ImgBB
    $receipt_url = '';
    if (isset($_FILES['receipt']) && $_FILES['receipt']['size'] > 0) {
        $imageData = base64_encode(file_get_contents($_FILES['receipt']['tmp_name']));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . IMGBB_API_KEY);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $imageData]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 secondes max
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout de connexion
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        if (isset($result['data']['url'])) {
            $receipt_url = $result['data']['url'];
        }
    }

    // 2. Enregistrer le paiement
    $stmt = $pdo->prepare("INSERT INTO payments (restaurant_id, plan, montant, reference, receipt_url, statut) VALUES (?, ?, ?, ?, ?, 'en_attente')");
    $stmt->execute([$resto_id, $plan, $montant, $reference, $receipt_url]);

    // 3. ACTIVER LE MENU IMMÉDIATEMENT (mais statut en vérification)
    // On calcule une date d'expiration provisoire (ex: +1 mois) pour que le menu reste en ligne
    $new_expires_at = date('Y-m-d H:i:s', strtotime('+1 month')); // Provisoire
    
    $stmtUpdate = $pdo->prepare("UPDATE restaurants SET subscription_status = 'pending_verification', is_active = true, expires_at = ? WHERE id = ?");
    $stmtUpdate->execute([$new_expires_at, $resto_id]);

    // 4. (Optionnel) Envoyer une notification WhatsApp ici si tu veux
}

header('Location: ../admin/subscription.php?success=1');
exit;