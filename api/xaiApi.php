<?php

/**
 * xAI Grok API Integration
 * Documentation: https://docs.x.ai/api
 * Endpoint: https://api.x.ai/v1/chat/completions
 * Models: grok-beta, grok-2, grok-2-mini
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
  // Vérifier si des fichiers sont fournis
  if (!isset($input['files']) || empty($input['files'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message ou fichier requis']);
    exit();
  }
}

$userMessage = isset($input['message']) ? trim($input['message']) : '';
$files = isset($input['files']) ? $input['files'] : [];

// Charger la configuration
$config = require __DIR__ . '/config.php';
$XAI_API_KEY = $config['XAI_API_KEY'] ?? '';

if (empty($XAI_API_KEY)) {
  http_response_code(500);
  echo json_encode(['error' => 'Clé API xAI non configurée']);
  exit();
}

// Autodétection du modèle si non fourni
if (!isset($input['model']) || empty($input['model'])) {
  // Charger les modèles disponibles depuis models.php
  require_once __DIR__ . '/models.php';
  $modelsData = getXAIModels($XAI_API_KEY);

  if (isset($modelsData['models']) && !empty($modelsData['models'])) {
    $model = $modelsData['models'][0]['id']; // Premier modèle disponible
  } else {
    // Fallback si l'autodétection échoue
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de détecter les modèles xAI disponibles']);
    exit();
  }
} else {
  $model = $input['model'];
}

// Appel à l'API xAI (compatible OpenAI)
$ch = curl_init('https://api.x.ai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Authorization: Bearer ' . $XAI_API_KEY
]);

// Préparer le contenu du message avec les fichiers
require_once __DIR__ . '/helpers.php';
$messageContent = prepareOpenAIMessageContent($userMessage, $files);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'model' => $model,
  'messages' => [
    ['role' => 'system', 'content' => 'You are Grok, a chatbot inspired by the Hitchhiker\'s Guide to the Galaxy.'],
    ['role' => 'user', 'content' => $messageContent]
  ]
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
  echo json_encode(['error' => 'Erreur API: ' . ($responseData['error']['message'] ?? 'Erreur inconnue')]);
  exit();
}

// Extraire le message de la réponse
$assistantMessage = $responseData['choices'][0]['message']['content'] ?? 'Pas de réponse';

echo json_encode([
  'success' => true,
  'message' => $assistantMessage
]);
