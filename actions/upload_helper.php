<?php
function uploadToSupabase($fileArray, $fileNamePrefix = 'img') {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    
    if (!$supabaseUrl || !$supabaseKey || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpName = $fileArray['tmp_name'];
    $mime = mime_content_type($tmpName);
    
    // --- 1. COMPRESSION DE L'IMAGE (GD) ---
    // On crée l'image en mémoire selon son format
    if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
        $image = imagecreatefromjpeg($tmpName);
    } elseif ($mime == 'image/png') {
        $image = imagecreatefrompng($tmpName);
    } elseif ($mime == 'image/webp') {
        $image = imagecreatefromwebp($tmpName);
    } else {
        return null; // Format non supporté
    }

    if (!$image) return null;

    // Redimensionnement (Max 1000px de large pour garder une bonne qualité)
    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = 1000;

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Garder la transparence pour les PNG
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }

    // Capture de l'image compressée en JPEG (qualité 75%)
    ob_start();
    // On met un fond blanc pour les PNG transparents qui deviennent des JPEG
    $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
    imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
    imagejpeg($bg, null, 75);
    $compressedData = ob_get_clean();
    imagedestroy($image);
    imagedestroy($bg);

    // --- 2. UPLOAD VERS SUPABASE STORAGE ---
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

    if ($httpCode == 200 || $httpCode == 201) {
        return $supabaseUrl . '/storage/v1/object/public/menu-images/' . $fileName;
    }
    
    return null;
}
?>