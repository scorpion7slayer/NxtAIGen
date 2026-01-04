<?php

/**
 * Callback OAuth GitHub
 * Reçoit le code d'autorisation et l'échange contre un token d'accès
 */

session_start();

require_once __DIR__ . '/../../zone_membres/db.php';

// Charger la configuration
$config = require __DIR__ . '/config.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../zone_membres/login.php');
  exit();
}

// Vérifier les erreurs de GitHub
if (isset($_GET['error'])) {
  $error = $_GET['error_description'] ?? $_GET['error'];
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('GitHub: ' . $error));
  exit();
}

// Vérifier le code d'autorisation
if (!isset($_GET['code'])) {
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Code d\'autorisation manquant'));
  exit();
}

// Vérifier le state (protection CSRF)
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['github_oauth_state'] ?? '')) {
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('State invalide - tentative CSRF détectée'));
  exit();
}

// Nettoyer le state de la session
unset($_SESSION['github_oauth_state']);

$code = $_GET['code'];

// Échanger le code contre un token d'accès
$tokenData = [
  'client_id' => $config['GITHUB_CLIENT_ID'],
  'client_secret' => $config['GITHUB_CLIENT_SECRET'],
  'code' => $code,
  'redirect_uri' => $config['GITHUB_CALLBACK_URL'],
];

$ch = curl_init($config['GITHUB_TOKEN_URL']);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query($tokenData),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Accept: application/json',
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: NxtGenAI'
  ],
  // Désactiver vérification SSL pour dev local (WAMP)
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_SSL_VERIFYHOST => 0,
  CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug en cas d'erreur cURL
if ($response === false) {
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Erreur cURL: ' . $curlError));
  exit();
}

if ($httpCode !== 200) {
  // Log pour debug
  error_log("GitHub OAuth Token Error - HTTP $httpCode - Response: $response");
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Erreur lors de l\'échange du token (HTTP ' . $httpCode . ')'));
  exit();
}

$tokenResponse = json_decode($response, true);

if (isset($tokenResponse['error'])) {
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('GitHub: ' . ($tokenResponse['error_description'] ?? $tokenResponse['error'])));
  exit();
}

$accessToken = $tokenResponse['access_token'] ?? null;
$refreshToken = $tokenResponse['refresh_token'] ?? null; // GitHub peut fournir un refresh token
$expiresIn = $tokenResponse['expires_in'] ?? 28800; // Par défaut 8 heures

if (!$accessToken) {
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Token d\'accès non reçu'));
  exit();
}

// Récupérer les informations de l'utilisateur GitHub
$ch = curl_init($config['GITHUB_API_URL'] . '/user');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $accessToken,
    'Accept: application/json',
    'User-Agent: NxtGenAI',
    'X-GitHub-Api-Version: 2022-11-28'
  ],
  // Désactiver vérification SSL pour dev local (WAMP)
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_SSL_VERIFYHOST => 0,
]);

$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Erreur lors de la récupération du profil GitHub'));
  exit();
}

$githubUser = json_decode($userResponse, true);

if (!isset($githubUser['id'])) {
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Profil GitHub invalide'));
  exit();
}

// Sauvegarder les informations GitHub dans la base de données
try {
  $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

  $stmt = $pdo->prepare("
        UPDATE users 
        SET github_id = ?, 
            github_username = ?, 
            github_token = ?, 
            github_refresh_token = ?,
            github_token_expires_at = ?,
            github_connected_at = NOW() 
        WHERE id = ?
    ");

  $stmt->execute([
    $githubUser['id'],
    $githubUser['login'],
    $accessToken, // En production, chiffrez ce token !
    $refreshToken,
    $expiresAt,
    $_SESSION['user_id']
  ]);

  // Stocker en session pour accès rapide
  $_SESSION['github_connected'] = true;
  $_SESSION['github_username'] = $githubUser['login'];
  $_SESSION['oauth_success'] = 'Compte GitHub "' . $githubUser['login'] . '" connecté avec succès !';

  header('Location: ../../zone_membres/dashboard.php?oauth_success=1');
  exit();
} catch (PDOException $e) {
  // Vérifier si les colonnes existent
  if (strpos($e->getMessage(), 'Unknown column') !== false) {
    header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Base de données non mise à jour. Exécutez la migration 002_github_oauth_improvements.sql'));
  } else {
    header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Erreur base de données: ' . $e->getMessage()));
  }
  exit();
}
