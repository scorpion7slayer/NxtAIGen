<?php

$dbHost = 'localhost';
$dbName = 'nxtgenai';
$dbUser = 'root';
$dbPass = '';

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
