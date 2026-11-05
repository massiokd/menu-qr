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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
    }
}

// 👇 La clé doit être ICI, en dehors de la fonction 👇
define('IMGBB_API_KEY', 'b8778d75e2c085fbf336a8f1e0c4e7cb'); 