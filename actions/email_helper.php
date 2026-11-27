<?php
// actions/email_helper.php

function sendEmailBrevo($apiKey, $to, $subject, $html) {
    $senderEmail = "massiokd@gmail.com"; 
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

// --- FONCTION DE DESIGN COMMUNE ---
function getEmailTemplate($title, $content, $btnText = '', $btnLink = '') {
    $logoUrl = "https://menu.fennetech.com/assets/images/FenneTechLogo.png";
    
    $buttonHtml = '';
    if (!empty($btnText) && !empty($btnLink)) {
        $buttonHtml = "
        <div style='text-align: center; margin: 30px 0;'>
            <a href='{$btnLink}' style='background-color: #c2410c; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block;'>{{$btnText}}</a>
        </div>";
    }

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin: 0; padding: 0; background-color: #f9fafb; font-family: Arial, sans-serif;'>
        <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #f9fafb; padding: 40px 20px;'>
            <tr>
                <td align='center'>
                    <table width='100%' max-width='600' border='0' cellspacing='0' cellpadding='0' style='background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; max-width: 600px;'>
                        <!-- HEADER -->
                        <tr>
                            <td align='center' style='padding: 30px 20px; border-bottom: 1px solid #f3f4f6;'>
                                <img src='{$logoUrl}' alt='FenneTech' style='height: 60px; width: auto; max-width: 100%;'>
                            </td>
                        </tr>
                        <!-- CONTENT -->
                        <tr>
                            <td style='padding: 40px 30px; color: #1c1917;'>
                                <h2 style='margin: 0 0 20px 0; font-size: 22px; color: #1c1917;'>{$title}</h2>
                                <div style='font-size: 16px; line-height: 1.6; color: #4b5563;'>
                                    {$content}
                                </div>
                                {$buttonHtml}
                            </td>
                        </tr>
                        <!-- FOOTER -->
                        <tr>
                            <td align='center' style='padding: 20px; background-color: #f9fafb; border-top: 1px solid #f3f4f6; font-size: 12px; color: #9ca3af;'>
                                <p style='margin: 0 0 5px 0;'>© " . date('Y') . " FenneTech. Tous droits réservés.</p>
                                <p style='margin: 0;'>L'innovation digitale au service des restaurants algériens.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";
}

// 1. Email de réinitialisation de mot de passe
function sendResetEmail($toEmail, $restoName, $resetLink) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $title = "Réinitialisation de mot de passe";
    $content = "
        <p>Bonjour,</p>
        <p>Vous avez demandé à réinitialiser le mot de passe de votre compte pour le restaurant <strong>{$restoName}</strong>.</p>
        <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien expirera dans 1 heure.</p>
        <p style='font-size: 13px; color: #9ca3af; margin-top: 20px;'>Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email en toute sécurité.</p>
    ";

    $html = getEmailTemplate($title, $content, "Choisir un nouveau mot de passe", $resetLink);
    return sendEmailBrevo($apiKey, $toEmail, $title, $html);
}

// 2. Alerte de sécurité (anti-phishing)
function sendSecurityAlert($toEmail, $restoName) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $title = "Alerte de sécurité";
    $content = "
        <p>Bonjour,</p>
        <p>Une demande de réinitialisation de mot de passe a été effectuée pour le compte du restaurant <strong>{$restoName}</strong>.</p>
        <p>Si vous êtes à l'origine de cette demande, vous pouvez ignorer cet email et utiliser le lien reçu séparément.</p>
        <p style='color: #dc2626; font-weight: bold;'>Si vous n'avez rien demandé, quelqu'un essaie peut-être d'accéder à votre compte. Contactez-nous immédiatement.</p>
    ";

    $html = getEmailTemplate($title, $content);
    return sendEmailBrevo($apiKey, $toEmail, $title, $html);
}

// 3. Abonnement expire bientôt
function sendExpiringSoonEmail($toEmail, $restoName, $daysLeft, $expiryDate) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $title = "Votre abonnement expire bientôt";
    $content = "
        <p>Bonjour,</p>
        <p>L'abonnement de votre restaurant <strong>{$restoName}</strong> expire dans <strong>{$daysLeft} jours</strong> (le {$expiryDate}).</p>
        <p>Pour éviter que votre menu ne soit inaccessible à vos clients, nous vous invitons à renouveler votre abonnement dès maintenant.</p>
    ";

  $html = getEmailTemplate($title, $content, "Se connecter pour renouveler", "https://menu.fennetech.com/admin/subscription.php");
    return sendEmailBrevo($apiKey, $toEmail, $title, $html);
}

// 4. Abonnement expiré
function sendExpiredEmail($toEmail, $restoName) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $title = "Votre abonnement a expiré";
    $content = "
        <p>Bonjour,</p>
        <p>L'abonnement de votre restaurant <strong>{$restoName}</strong> a expiré.</p>
        <p>Votre menu n'est plus accessible par vos clients pour le moment. Pour le réactiver et retrouver vos statistiques, veuillez régulariser votre situation.</p>
    ";

    $html = getEmailTemplate($title, $content, "Réactiver mon compte", "https://menu.fennetech.com/admin/subscription.php");
    return sendEmailBrevo($apiKey, $toEmail, $title, $html);
}

// 5. Paiement validé
function sendPaymentConfirmedEmail($toEmail, $restoName, $plan, $expiryDate) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $title = "Votre paiement a été validé";
    $content = "
        <p>Bonjour,</p>
        <p>Nous avons le plaisir de vous informer que le paiement pour votre restaurant <strong>{$restoName}</strong> a bien été validé.</p>
        <p>Votre abonnement <strong>{$plan}</strong> est maintenant actif jusqu'au <strong>{$expiryDate}</strong>.</p>
        <p>Merci pour votre confiance.</p>
    ";

  $html = getEmailTemplate($title, $content, "Accéder à mon menu", "https://menu.fennetech.com/admin/admin.php");
    return sendEmailBrevo($apiKey, $toEmail, $title, $html);
}

// 6. Confirmation de création de compte
function sendVerificationEmail($toEmail, $restoName, $verifyLink) {
    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) return false;

    $title = "Bienvenue sur FenneTech";
    $content = "
        <p>Bonjour,</p>
        <p>Votre compte pour le restaurant <strong>{$restoName}</strong> a bien été créé.</p>
        <p>Pour activer votre compte et commencer à configurer votre menu digital, veuillez cliquer sur le bouton ci-dessous :</p>
    ";

    $html = getEmailTemplate($title, $content, "Activer mon compte", $verifyLink);
    return sendEmailBrevo($apiKey, $toEmail, $title, $html);
}
?>