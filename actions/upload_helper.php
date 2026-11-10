<?php
function uploadToSupabase($fileArray, $fileNamePrefix = 'img') {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    
    if (!$supabaseUrl || !$supabaseKey) {
        error_log("Supabase: URL ou KEY manquante.");
        return null;
    }

    $tmpName = $fileArray['tmp_name'];
    
    // --- COMPRESSION ---
$imageData = file_get_contents($tmpName);
$image = @imagecreatefromstring($imageData);

if (!$image) { 
    // Si on ne peut pas lire l'image, on renvoie une erreur propre
    error_log("Compression: Impossible de lire l'image (format non supporté).");
    return null; 
}

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
    $cleanUrl = rtrim($supabaseUrl, '/'); // Sécurité anti-slash
    $fileName = $fileNamePrefix . '_' . time() . '_' . uniqid() . '.jpg';
    $url = $cleanUrl . '/storage/v1/object/menu-images/' . $fileName;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $compressedData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: image/jpeg',
        'x-upsert: true'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 || $httpCode == 201) {
        return $cleanUrl . '/storage/v1/object/public/menu-images/' . $fileName;
    }
    
    // Si erreur, on l'écrit dans les logs de Render pour toi (invisible pour le gérant)
    error_log("Supabase Upload Error ($httpCode): " . $response);
    return null;
}
?>