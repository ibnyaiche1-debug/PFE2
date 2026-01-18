<?php
$dsn  = "mysql:host=localhost;dbname=stage_platform;charset=utf8mb4";
$user = "root";
$pass = "";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Active le mode exception : en cas d’erreur SQL, une exception est levée
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Définit le mode de récupération par défaut en tableau associatif
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Désactive l’émulation des requêtes préparées et utilise celles du serveur SQL
    ]);
} catch (PDOException $e) {
    die("Database connection failed");
}
?>