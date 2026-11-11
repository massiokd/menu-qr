<?php
// actions/email_helper.php

function sendEmailBrevo($apiKey, $to, $subject, $html) {
    $senderEmail = "massiokd@gmail.com"; // L'adresse que tu as vérifiée sur Brevo
    $senderName = "FenneTech";

    $data = [
        "sender" => [
            "name" => $senderName,
            "email" => $senderEmail
        ],
        "to" => [
            ["email" => $to]
        ],
        "subject" => $subject,
        "htmlContent" => $html
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 201 && $httpCode != 200) {
        error_log("BREVO_ERROR ($httpCode): " . $response);
    }

    return $httpCode == 201 || $httpCode == 200;
}

// Fonction pour envoyer le lien de réinitialisation
function sendResetEmail($toEmail, $restoName, $resetLink) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $htmlContent = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px;'>
        <h2 style='color: #c2410c;'>Réinitialisation de mot de passe</h2>
        <p>Bonjour,</p>
        <p>Vous avez demandé à réinitialiser le mot de passe de votre compte pour le restaurant <strong>{$restoName}</strong>.</p>
        <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien expirera dans 1 heure.</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='{$resetLink}' style='background-color: #c2410c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Choisir un nouveau mot de passe</a>
        </div>
        <p style='font-size: 12px; color: #6b7280;'>Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.</p>
        <p style='font-size: 12px; color: #6b7280;'>L'équipe FenneTech 🦊</p>
    </div>
    ";

    return sendEmailBrevo($apiKey, $toEmail, "Réinitialisation de votre mot de passe", $htmlContent);
}

// Fonction pour envoyer l'alerte de sécurité (anti-phishing)
function sendSecurityAlert($toEmail, $restoName) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px;'>
        <h2 style='color: #dc2626;'>⚠️ Alerte de sécurité</h2>
        <p>Bonjour,</p>
        <p>Une demande de réinitialisation de mot de passe a été faite pour le compte <strong>{$restoName}</strong>.</p>
        <p>Si vous êtes à l'origine de cette demande, vous pouvez ignorer cet email et utiliser le lien reçu séparément.</p>
        <p>Si vous n'avez rien demandé, quelqu'un essaie peut-être d'accéder à votre compte. Dans ce cas, <strong>ne cliquez pas</strong> sur le lien de réinitialisation et contactez-nous immédiatement.</p>
        <p style='font-size: 12px; color: #6b7280;'>L'équipe FenneTech 🦊</p>
    </div>
    ";

    return sendEmailBrevo($apiKey, $toEmail, "⚠️ Alerte de sécurité - Demande de réinitialisation", $html);
}

// Fonction : Abonnement expire bientôt
function sendExpiringSoonEmail($toEmail, $restoName, $daysLeft, $expiryDate) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px;'>
        <h2 style='color: #f59e0b;'>⚠️ Votre abonnement expire bientôt</h2>
        <p>Bonjour,</p>
        <p>L'abonnement de votre restaurant <strong>{$restoName}</strong> expire dans <strong>{$daysLeft} jours</strong> (le {$expiryDate}).</p>
        <p>Pour éviter que votre menu ne soit coupé pour vos clients, pensez à renouveler votre abonnement dès maintenant.</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='https://menu-qr-ebke.onrender.com/login.php' style='background-color: #c2410c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Se connecter pour renouveler</a>
        </div>
        <p style='font-size: 12px; color: #6b7280;'>L'équipe FenneTech 🦊</p>
    </div>
    ";
    return sendEmailBrevo($apiKey, $toEmail, "⚠️ Votre abonnement expire bientôt", $html);
}

// Fonction : Abonnement expiré
function sendExpiredEmail($toEmail, $restoName) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px;'>
        <h2 style='color: #dc2626;'>🚨 Votre abonnement a expiré</h2>
        <p>Bonjour,</p>
        <p>L'abonnement de votre restaurant <strong>{$restoName}</strong> a expiré.</p>
        <p>Votre menu n'est plus accessible par vos clients. Pour le réactiver, veuillez régulariser votre situation.</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='https://menu-qr-ebke.onrender.com/login.php' style='background-color: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Réactiver mon compte</a>
        </div>
        <p style='font-size: 12px; color: #6b7280;'>L'équipe FenneTech 🦊</p>
    </div>
    ";
    return sendEmailBrevo($apiKey, $toEmail, "🚨 Votre abonnement a expiré", $html);
}

// Fonction : Paiement validé
function sendPaymentConfirmedEmail($toEmail, $restoName, $plan, $expiryDate) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px;'>
        <h2 style='color: #10b981;'>✅ Paiement validé</h2>
        <p>Bonjour,</p>
        <p>Nous avons le plaisir de vous informer que le paiement pour votre restaurant <strong>{$restoName}</strong> a bien été validé.</p>
        <p>Votre abonnement <strong>{$plan}</strong> est maintenant actif jusqu'au <strong>{$expiryDate}</strong>.</p>
        <p>Merci pour votre confiance !</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='https://menu-qr-ebke.onrender.com/login.php' style='background-color: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Accéder à mon menu</a>
        </div>
        <p style='font-size: 12px; color: #6b7280;'>L'équipe FenneTech 🦊</p>
    </div>
    ";
    return sendEmailBrevo($apiKey, $toEmail, "✅ Votre paiement a été validé !", $html);
}

// Fonction : Confirmation de création de compte
function sendVerificationEmail($toEmail, $restoName, $verifyLink) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px;'>
        <h2 style='color: #c2410c;'>Bienvenue sur FenneTech ! 🦊</h2>
        <p>Bonjour,</p>
        <p>Votre compte pour le restaurant <strong>{$restoName}</strong> a bien été créé.</p>
        <p>Pour activer votre compte et commencer à configurer votre menu, veuillez cliquer sur le bouton ci-dessous :</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='{$verifyLink}' style='background-color: #c2410c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Activer mon compte</a>
        </div>
        <p style='font-size: 12px; color: #6b7280;'>Si vous n'êtes pas à l'origine de cette inscription, ignorez simplement cet email.</p>
        <p style='font-size: 12px; color: #6b7280;'>L'équipe FenneTech 🦊</p>
    </div>
    ";
    return sendEmailBrevo($apiKey, $toEmail, "Activez votre compte FenneTech", $html);
}

?>