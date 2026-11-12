<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die("Non autorisé"); }

$type = $_GET['type'] ?? 'restos';
$pdo = getDB();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF))); // BOM UTF-8 pour Excel

if ($type === 'restos') {
    fputcsv($output, ['ID', 'Nom', 'Slug', 'Plan', 'Expiration', 'Statut', 'Scans']);
    $stmt = $pdo->query("SELECT id, nom, slug, plan, expires_at, is_active, scan_count FROM restaurants ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} elseif ($type === 'payments') {
    fputcsv($output, ['ID', 'Restaurant', 'Plan', 'Montant', 'Statut', 'Date']);
    $stmt = $pdo->query("SELECT p.id, r.nom, p.plan, p.montant, p.statut, p.created_at FROM payments p JOIN restaurants r ON p.restaurant_id = r.id ORDER BY p.created_at DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
}
fclose($output);
exit;