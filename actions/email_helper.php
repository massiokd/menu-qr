<?php
function sendResetEmail($toEmail, $restoName, $resetLink) {
    $apiKey = getenv('RESEND_API_KEY');
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

    $data = [
        "from" => "FenneTech <onboarding@resend.dev>",
        "to" => [$toEmail],
        "subject" => "Réinitialisation de votre mot de passe",
        "html" => $htmlContent
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode == 200 || $httpCode == 201;
}
?>