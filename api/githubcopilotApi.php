<?php

/**
 * GitHub Copilot API Integration - Version améliorée
 * Documentation: https://docs.github.com/en/copilot
 * Utilise le token OAuth GitHub de l'utilisateur avec abonnement Copilot Pro/Pro+
 * Endpoint: https://api.githubcopilot.com/chat/completions
 * 
 * Améliorations:
 * - Refresh automatique du token OAuth si expiré
 * - Vérification de l'abonnement Copilot
 * - Meilleure gestion des erreurs OAuth
 * - Messages d'erreur contextuels
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

// Charger le helper OAuth
require_once __DIR__ . '/../zone_membres/db.php';
require_once __DIR__ . '/github/oauth_helper.php';

$oauthHelper = new GitHubCopilotOAuth($pdo);

// Récupérer le token (avec refresh automatique si expiré)
$GITHUB_TOKEN = $oauthHelper->getToken($_SESSION['user_id']);

// Si pas de token utilisateur, essayer le token global
if (empty($GITHUB_TOKEN)) {
  $config = require __DIR__ . '/config.php';
  $GITHUB_TOKEN = $config['GITHUB_TOKEN'] ?? '';
}

if (empty($GITHUB_TOKEN)) {
  http_response_code(401);
  echo json_encode([
    'error' => 'Compte GitHub non connecté ou token expiré.',
    'action' => 'connect_github',
    'details' => 'Allez dans Paramètres → GitHub → Connecter pour autoriser l\'accès à Copilot.',
    'required_scopes' => ['copilot', 'user:read']
  ]);
  exit();
}

// Vérifier l'abonnement Copilot (optionnel, peut être désactivé pour performances)
$checkSubscription = $input['check_subscription'] ?? false;
if ($checkSubscription) {
  $subscription = $oauthHelper->hasCopilotSubscription($GITHUB_TOKEN);

  if (!$subscription['has_subscription']) {
    http_response_code(403);
    echo json_encode([
      'error' => 'Abonnement GitHub Copilot requis',
      'action' => 'subscribe_copilot',
      'details' => 'GitHub Copilot nécessite un abonnement Pro ou Pro+. Souscrivez sur github.com/github-copilot',
      'subscription_status' => $subscription
    ]);
    exit();
  }
}

// Autodétection du modèle si non fourni
if (!isset($input['model']) || empty($input['model'])) {
  // Charger les modèles disponibles depuis models.php
  require_once __DIR__ . '/models.php';
  $modelsData = getGitHubModels($GITHUB_TOKEN);

  if (isset($modelsData['models']) && !empty($modelsData['models'])) {
    $model = $modelsData['models'][0]['id']; // Premier modèle disponible
  } else {
    // Fallback vers un modèle par défaut
    $model = 'gpt-4o'; // Copilot utilise GPT-4o par défaut
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
  'Editor-Plugin-Version: copilot-chat/0.22.4', // Version mise à jour
  'Copilot-Integration-Id: vscode-chat',
  'User-Agent: NxtGenAI/1.0',
  'X-GitHub-Api-Version: 2024-11-01' // Version API explicite
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
  'max_tokens' => 4096,
  'temperature' => 0.7
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
  $errorMessage = 'Erreur inconnue';
  $errorDetails = [];

  if (isset($responseData['error'])) {
    if (is_array($responseData['error'])) {
      $errorMessage = $responseData['error']['message'] ?? $errorMessage;
      $errorDetails = $responseData['error'];
    } else {
      $errorMessage = $responseData['error'];
    }
  } elseif (isset($responseData['message'])) {
    $errorMessage = $responseData['message'];
  }

  // Erreurs spécifiques GitHub Copilot
  if ($httpCode === 401) {
    $errorMessage = 'Token GitHub invalide ou expiré. Reconnectez votre compte GitHub dans les paramètres.';
    $errorDetails['action'] = 'reconnect_github';
  } elseif ($httpCode === 403) {
    $errorMessage = 'Accès refusé. Vérifiez que vous avez un abonnement GitHub Copilot actif.';
    $errorDetails['action'] = 'check_subscription';
  } elseif ($httpCode === 429) {
    $errorMessage = 'Limite de taux atteinte. Veuillez réessayer dans quelques instants.';
    $errorDetails['retry_after'] = $responseData['retry_after'] ?? 60;
  }

  http_response_code($httpCode);
  echo json_encode([
    'error' => $errorMessage,
    'details' => $errorDetails,
    'http_code' => $httpCode
  ]);
  exit();
}

// Extraire le message de la réponse
$assistantMessage = $responseData['choices'][0]['message']['content'] ?? 'Pas de réponse';
$tokensUsed = $responseData['usage']['total_tokens'] ?? null;

echo json_encode([
  'success' => true,
  'message' => $assistantMessage,
  'model' => $model,
  'tokens_used' => $tokensUsed,
  'provider' => 'github_copilot'
]);
