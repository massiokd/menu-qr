<?php
session_name('FENNEC_GERANT');
session_start();
require '../config/db.php';
require 'upload_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant') { die("Non autorisé"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    if ($_FILES['logo']['size'] > 0) {
        $pdo = getDB();
        $resto_id = $_SESSION['restaurant_id'];

        // 1. Récupérer l'ancien logo avant de le remplacer
        $stmtOld = $pdo->prepare("SELECT logo_url FROM restaurants WHERE id = ?");
        $stmtOld->execute([$resto_id]);
        $old_logo = $stmtOld->fetchColumn();

        // 2. Uploader le nouveau
        $new_url = uploadToSupabase($_FILES['logo'], 'logo');
        
        if ($new_url) {
            // 3. Supprimer l'ancien fichier sur Supabase
            if (!empty($old_logo)) {
                deleteFromSupabase($old_logo);
            }

            // 4. Mettre à jour la base de données
            $stmt = $pdo->prepare("UPDATE restaurants SET logo_url = ? WHERE id = ?");
            $stmt->execute([$new_url, $resto_id]);
        } else {
            // Échec de l'upload
            header('Location: ../admin/settings.php?tab=branding&error=logo_failed');
            exit;
        }
    }
}

header('Location: ../admin/settings.php?tab=branding&success=logo');
exit;