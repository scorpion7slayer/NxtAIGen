<?php

/**
 * Google Gemini API Integration
 * Documentation: https://ai.google.dev/docs
 * Endpoint: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
 * Models: gemini-1.5-flash, gemini-1.5-pro, gemini-2.0-flash
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
  // Vérifier si des fichiers sont fournis (message peut être vide avec des fichiers)
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
$GEMINI_API_KEY = $config['GEMINI_API_KEY'] ?? '';

if (empty($GEMINI_API_KEY)) {
  http_response_code(500);
  echo json_encode(['error' => 'Clé API Gemini non configurée']);
  exit();
}

// Autodétection du modèle si non fourni
if (!isset($input['model']) || empty($input['model'])) {
  // Charger les modèles disponibles depuis models.php
  require_once __DIR__ . '/models.php';
  $modelsData = getGeminiModels($GEMINI_API_KEY);

  if (isset($modelsData['models']) && !empty($modelsData['models'])) {
    $model = $modelsData['models'][0]['id']; // Premier modèle disponible
  } else {
    // Fallback si l'autodétection échoue
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de détecter les modèles Gemini disponibles']);
    exit();
  }
} else {
  $model = $input['model'];
}

// Appel à l'API Gemini
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json'
]);

// Construire les parts du contenu (texte + images)
$parts = [];

// Ajouter les images si présentes
foreach ($files as $file) {
  // Vérifier si c'est une image supportée
  $supportedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (in_array($file['type'], $supportedImageTypes)) {
    // Extraire les données base64 sans le préfixe data:
    $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
    $parts[] = [
      'inlineData' => [
        'mimeType' => $file['type'],
        'data' => $base64Data
      ]
    ];
  } else {
    // Pour les fichiers non-image, ajouter le contenu comme texte
    if (
      strpos($file['type'], 'text/') === 0 ||
      $file['type'] === 'application/json' ||
      $file['type'] === 'application/xml'
    ) {
      $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
      $fileContent = base64_decode($base64Data);
      $parts[] = [
        'text' => "Contenu du fichier {$file['name']}:\n```\n{$fileContent}\n```"
      ];
    }
  }
}

// Ajouter le texte
if (!empty($userMessage)) {
  $parts[] = ['text' => $userMessage];
} else if (empty($parts)) {
  $parts[] = ['text' => 'Analyse ce fichier'];
}

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'contents' => [
    [
      'parts' => $parts
    ]
  ],
  'generationConfig' => [
    'temperature' => 0.9,
    'maxOutputTokens' => 4096
  ]
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
// curl_close() supprimé - deprecated depuis PHP 8.0

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

// Extraire le message de la réponse Gemini
$assistantMessage = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'Pas de réponse';

echo json_encode([
  'success' => true,
  'message' => $assistantMessage
]);
