<?php
session_name('FENNEC_ADMIN');
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') { die(json_encode(['error' => 'Non autorisé'])); }

$resto_id = $_GET['id'] ?? 0;
$pdo = getDB();

$stmt = $pdo->prepare("SELECT scan_count FROM restaurants WHERE id = ?");
$stmt->execute([$resto_id]);
$scans = $stmt->fetchColumn();

echo json_encode(['scans' => $scans ?: 0]);