<?php

/**
 * GitHub Copilot API Integration
 * Documentation: https://docs.github.com/en/copilot
 * Utilise le token OAuth GitHub de l'utilisateur avec abonnement Copilot Pro/Pro+
 * Endpoint: https://api.githubcopilot.com/chat/completions
 */

session_start();
header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Non authentifié']);
  exit();
}

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Méthode non autorisée']);
  exit();
}

// Récupérer les données JSON envoyées
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty(trim($input['message']))) {
  if (!isset($input['files']) || empty($input['files'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message ou fichier requis']);
    exit();
  }
}

$userMessage = isset($input['message']) ? trim($input['message']) : '';
$files = isset($input['files']) ? $input['files'] : [];

// Récupérer le token GitHub de l'utilisateur connecté
require_once __DIR__ . '/../zone_membres/db.php';

$stmt = $pdo->prepare("SELECT github_token FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$GITHUB_TOKEN = $user['github_token'] ?? null;

// Si pas de token utilisateur, essayer le token global
if (empty($GITHUB_TOKEN)) {
  $config = require __DIR__ . '/config.php';
  $GITHUB_TOKEN = $config['GITHUB_TOKEN'] ?? '';
}

if (empty($GITHUB_TOKEN)) {
  http_response_code(401);
  echo json_encode([
    'error' => 'Compte GitHub non connecté. Allez dans Paramètres pour connecter votre compte GitHub.',
    'action' => 'connect_github'
  ]);
  exit();
}

// Autodétection du modèle si non fourni
if (!isset($input['model']) || empty($input['model'])) {
  // Charger les modèles disponibles depuis models.php
  require_once __DIR__ . '/models.php';
  $modelsData = getGitHubModels($GITHUB_TOKEN);

  if (isset($modelsData['models']) && !empty($modelsData['models'])) {
    $model = $modelsData['models'][0]['id']; // Premier modèle disponible
  } else {
    // Fallback si l'autodétection échoue
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de détecter les modèles GitHub Copilot disponibles']);
    exit();
  }
} else {
  $model = $input['model'];
}

// API GitHub Copilot pour les abonnés Copilot Pro/Pro+
// Endpoint: https://api.githubcopilot.com/chat/completions
$ch = curl_init('https://api.githubcopilot.com/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Accept: application/json',
  'Authorization: Bearer ' . $GITHUB_TOKEN,
  'Editor-Version: vscode/1.95.0',
  'Editor-Plugin-Version: copilot/1.0.0',
  'Copilot-Integration-Id: vscode-chat',
  'User-Agent: NxtGenAI/1.0'
]);
// Préparer le contenu du message avec fichiers
require_once __DIR__ . '/helpers.php';
$messageContent = prepareOpenAIMessageContent($userMessage, $files);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'model' => $model,
  'messages' => [
    ['role' => 'user', 'content' => $messageContent]
  ],
  'stream' => false,
  'max_tokens' => 4096
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Gérer les erreurs cURL
if ($curlError) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur de connexion: ' . $curlError]);
  exit();
}

// Décoder et retourner la réponse
$responseData = json_decode($response, true);

if ($httpCode !== 200) {
  http_response_code($httpCode);
  echo json_encode(['error' => 'Erreur API: ' . ($responseData['message'] ?? $responseData['error']['message'] ?? 'Erreur inconnue')]);
  exit();
}

// Extraire le message de la réponse
$assistantMessage = $responseData['choices'][0]['message']['content'] ?? 'Pas de réponse';

echo json_encode([
  'success' => true,
  'message' => $assistantMessage
]);
