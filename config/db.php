<?php
// config/db.php
function getDB() {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');

    // sslmode=require est obligatoire pour Supabase
    $dsn = "pgsql:host=$host;port=$port;dbname=$name;sslmode=require";
    
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // 👇 LES OPTIMISATIONS AJOUTÉES ICI 👇
            PDO::ATTR_PERSISTENT         => false, // Évite de saturer les connexions de Supabase
            PDO::ATTR_EMULATE_PREPARES   => false, // Meilleure performance et sécurité pour PostgreSQL
            PDO::ATTR_TIMEOUT            => 5      // Si la base met plus de 5s à répondre, on annule (évite le crash infini)
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // On affiche une erreur plus propre au lieu d'un écran blanc
        die("Une erreur est survenue lors de la connexion au serveur. Veuillez rafraîchir la page.");
    }
}

// 👇 La clé doit être ICI, en dehors de la fonction 👇
define('IMGBB_API_KEY', 'b8778d75e2c085fbf336a8f1e0c4e7cb'); 