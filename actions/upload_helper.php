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
    $image = @imagecreatefromstring(file_get_contents($tmpName));
    if (!$image) { 
        error_log("Compression: Impossible de lire l'image.");
        return null; 
    }

    // Redimensionnement Max 800px (idéal pour un logo)
    $width = imagesx($image);
    $height = imagesy($image);
    $maxWidth = 800;

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // On garde la transparence
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }

    // On sauvegarde TOUJOURS en PNG pour garder la transparence
    ob_start();
    imagepng($image);
    $compressedData = ob_get_clean();
    imagedestroy($image);

    // --- UPLOAD ---
    $cleanUrl = rtrim($supabaseUrl, '/'); // Sécurité anti-slash
    $fileName = $fileNamePrefix . '_' . time() . '_' . uniqid() . '.png';
    $url = $cleanUrl . '/storage/v1/object/menu-images/' . $fileName;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $compressedData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
                'Content-Type: image/png',
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

function deleteFromSupabase($fileUrl) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    
    if (!$supabaseUrl || !$supabaseKey || empty($fileUrl)) return false;

    // On extrait juste le nom du fichier depuis l'URL complète
    $filename = basename(parse_url($fileUrl, PHP_URL_PATH));
    if (!$filename) return false;

    $cleanUrl = rtrim($supabaseUrl, '/');
    $url = $cleanUrl . '/storage/v1/object/menu-images/' . $filename;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // On utilise la méthode DELETE
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 200 ou 204 = succès de la suppression
    return $httpCode == 200 || $httpCode == 204;
}

?>