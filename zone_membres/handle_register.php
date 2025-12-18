<?php
session_start();
require 'db.php';

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    header("Location: register.php?error=Token de sécurité invalide. Veuillez réessayer.");
    exit;
}

// Régénérer le token CSRF après utilisation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if (empty($_POST['username']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['confirm_password'])) {
    header("Location: register.php?error=Veuillez remplir tous les champs");
    exit;
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    header("Location: register.php?error=Format d'email invalide");
    exit;
}

if ($_POST['password'] !== $_POST['confirm_password']) {
    header("Location: register.php?error=Les mots de passe ne correspondent pas");
    exit;
}

if (strlen($_POST['password']) < 6) {
    header("Location: register.php?error=Mot de passe trop court (min 6 caractères)");
    exit;
}

// Valider le nom d'utilisateur (alphanumérique uniquement)
if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $_POST['username'])) {
    header("Location: register.php?error=Nom d'utilisateur invalide (3-30 caractères, alphanumériques et underscore uniquement)");
    exit;
}

$password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['username'], $_POST['email'], $password_hash]);
    header("Location: login.php?success=Compte créé avec succès");
    exit;
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        header("Location: register.php?error=Ce nom d'utilisateur ou email existe déjà");
    } else {
        header("Location: register.php?error=Erreur lors de l'inscription");
    }
    exit;
}
