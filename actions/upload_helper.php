<?php
function uploadToSupabase($fileArray, $fileNamePrefix = 'img') {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    
    if (!$supabaseUrl || !$supabaseKey || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpName = $fileArray['tmp_name'];
    
    // --- 1. COMPRESSION ET CONVERSION EN WEBP ---
    $image = @imagecreatefromstring(file_get_contents($tmpName));
    if (!$image) { 
        error_log("Erreur: Impossible de lire l'image.");
        return null; 
    }

    // Redimensionnement Max 1200px (idéal pour le web)
    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = 800;

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Garder la transparence pour le logo
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }

    // Conversion en WebP (Qualité 75)
    ob_start();
    imagewebp($image, null, 75);
    $compressedData = ob_get_clean();
    imagedestroy($image);

    // --- 2. UPLOAD VERS SUPABASE STORAGE ---
    $cleanUrl = rtrim($supabaseUrl, '/');
    // On change l'extension en .webp
    $fileName = $fileNamePrefix . '_' . time() . '_' . uniqid() . '.webp';
    $url = $cleanUrl . '/storage/v1/object/menu-images/' . $fileName;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $compressedData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: image/webp', // Type MIME du WebP
        'x-upsert: true'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 || $httpCode == 201) {
        return $cleanUrl . '/storage/v1/object/public/menu-images/' . $fileName;
    }
    
    error_log("Supabase Upload Error ($httpCode): " . $response);
    return null;
}

// La fonction deleteFromSupabase reste inchangée
function deleteFromSupabase($fileUrl) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$supabaseUrl || !$supabaseKey || empty($fileUrl)) return false;
    $filename = basename(parse_url($fileUrl, PHP_URL_PATH));
    if (!$filename) return false;
    $cleanUrl = rtrim($supabaseUrl, '/');
    $url = $cleanUrl . '/storage/v1/object/menu-images/' . $filename;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode == 200 || $httpCode == 204;
}
?>