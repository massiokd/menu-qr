<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';
require 'upload_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    if ($_FILES['logo']['size'] > 0) {
        $new_url = uploadToSupabase($_FILES['logo'], 'logo');
        
        if ($new_url) {
            $pdo = getDB();
            $stmt = $pdo->prepare("UPDATE restaurants SET logo_url = ? WHERE id = ?");
            $stmt->execute([$new_url, $_SESSION['restaurant_id']]);
        }
    }
}

header('Location: ../admin/settings.php?tab=branding&success=logo');
exit;