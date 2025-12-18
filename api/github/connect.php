<?php

/**
 * Initie la connexion OAuth GitHub
 * Redirige l'utilisateur vers GitHub pour autorisation
 */

session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../zone_membres/login.php');
  exit();
}

// Charger la configuration
$config = require __DIR__ . '/config.php';

// Vérifier que GitHub est configuré
if (empty($config['GITHUB_CLIENT_ID'])) {
  $_SESSION['oauth_error'] = 'GitHub OAuth n\'est pas configuré. Contactez l\'administrateur.';
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode($_SESSION['oauth_error']));
  exit();
}

// Générer un state unique pour protéger contre CSRF
$state = bin2hex(random_bytes(32));
$_SESSION['github_oauth_state'] = $state;

// Construire l'URL d'autorisation GitHub
$params = [
  'client_id' => $config['GITHUB_CLIENT_ID'],
  'redirect_uri' => $config['GITHUB_CALLBACK_URL'],
  'scope' => implode(' ', $config['GITHUB_SCOPES']),
  'state' => $state,
  'allow_signup' => 'false', // Ne pas permettre l'inscription via OAuth
];

$authorizeUrl = $config['GITHUB_AUTHORIZE_URL'] . '?' . http_build_query($params);

// Rediriger vers GitHub
header('Location: ' . $authorizeUrl);
exit();
