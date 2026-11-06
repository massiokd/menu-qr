<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    $file = $_FILES['logo'];
    if ($file['size'] > 0) {
        $imageData = base64_encode(file_get_contents($file['tmp_name']));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . IMGBB_API_KEY);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $imageData]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['data']['url'])) {
            $pdo = getDB();
            $stmt = $pdo->prepare("UPDATE restaurants SET logo_url = ? WHERE id = ?");
            $stmt->execute([$result['data']['url'], $_SESSION['restaurant_id']]);
        }
    }
}
header('Location: ../admin/settings.php?success=logo');
exit;