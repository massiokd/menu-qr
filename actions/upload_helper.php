<?php
function uploadToSupabase($fileArray, $fileNamePrefix = 'img') {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    
    if (!$supabaseUrl || !$supabaseKey) {
        die("Erreur : Les variables SUPABASE_URL ou SUPABASE_SERVICE_ROLE_KEY sont manquantes sur Render.");
    }

    $tmpName = $fileArray['tmp_name'];
    
    // --- COMPRESSION ---
    $image = imagecreatefromstring(file_get_contents($tmpName));
    if (!$image) { die("Erreur : Impossible de lire l'image. Format non supporté."); }

    // Redimensionnement Max 1000px
    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = 1000;

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($newImage, 0, 0, imagecolorallocate($newImage, 255, 255, 255));
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }

    ob_start();
    imagejpeg($image, null, 75); // 75% de qualité
    $compressedData = ob_get_clean();
    imagedestroy($image);

    // --- UPLOAD ---
    $fileName = $fileNamePrefix . '_' . time() . '_' . uniqid() . '.jpg';
    $url = $supabaseUrl . '/storage/v1/object/menu-images/' . $fileName;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $compressedData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: image/jpeg',
        'x-upsert: true'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // SI L'UPLOAD ÉCHOUE, ON AFFICHE L'ERREUR EXACTE
    if ($httpCode != 200 && $httpCode != 201) {
        echo "<h1>Erreur d'upload Supabase (Code $httpCode)</h1>";
        echo "<p>Réponse de Supabase : </p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        echo "<p>Vérifie que ton bucket s'appelle bien <strong>menu-images</strong> et qu'il est public.</p>";
        die();
    }

    return $supabaseUrl . '/storage/v1/object/public/menu-images/' . $fileName;
}
?>