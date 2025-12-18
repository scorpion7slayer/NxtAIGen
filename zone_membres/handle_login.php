<?php
session_start();
require 'db.php';

if (empty($_POST['username']) || empty($_POST['password'])) {
    header("Location: login.php?error=Veuillez remplir tous les champs");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_POST['username']]);
$user = $stmt->fetch();

if ($user && password_verify($_POST['password'], $user['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    header("Location: ../index.php");
    exit;
} else {
    header("Location: login.php?error=Identifiants incorrects");
    exit;
}
