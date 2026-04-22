<?php

require_once __DIR__ . '/../api/env_loader.php';

$dbHost = env('DB_HOST');
$dbName = env('DB_NAME');
$dbUser = env('DB_USER');
$dbPass = env('DB_PASS');

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

$pdoOptions = [
     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false,
];

try {
     $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $ex) {
     throw new PDOException($ex->getMessage(), (int)$ex->getCode());
}
