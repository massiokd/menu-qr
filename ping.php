<?php
// ping.php - Fichier de Health Check pour UptimeRobot et Cron-job.org
require 'config/db.php';

header('Content-Type: text/plain');

try {
    // On teste la connexion à la base de données
    $pdo = getDB();
    $pdo->query("SELECT 1");
    
    // Si tout va bien, on renvoie "OK" avec un code 200 (Succès)
    http_response_code(200);
    echo "OK";
} catch (Exception $e) {
    // Si la base de données plante, on renvoie une erreur 500
    http_response_code(500);
    echo "DB_ERROR";
}