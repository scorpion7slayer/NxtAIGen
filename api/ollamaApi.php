<?php
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

// Charger la configuration pour récupérer l'URL/clé Ollama
$config = require __DIR__ . '/config.php';

$ollamaBaseUrl = $input['base_url'] ?? $config['OLLAMA_API_URL'] ?? 'http://localhost:11434';
$ollamaBaseUrl = rtrim($ollamaBaseUrl, '/');
$ollamaApiKey = $input['api_key'] ?? $config['OLLAMA_API_KEY'] ?? '';

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

// Autodétection du modèle si non fourni
if (!isset($input['model']) || empty($input['model'])) {
  // Charger les modèles disponibles depuis models.php
  require_once __DIR__ . '/models.php';
  $modelsData = getOllamaModels($ollamaBaseUrl);

  if (isset($modelsData['models']) && !empty($modelsData['models'])) {
    $model = $modelsData['models'][0]['id']; // Premier modèle disponible
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Aucun modèle Ollama détecté sur ' . $ollamaBaseUrl]);
    exit();
  }
} else {
  $model = $input['model'];
}

// Appel à l'API Ollama (URL configurable)
$endpoint = $ollamaBaseUrl . '/api/chat';
$headers = ['Content-Type: application/json'];
if (!empty($ollamaApiKey)) {
  $headers[] = 'Authorization: Bearer ' . $ollamaApiKey;
}

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// Désactiver la vérification SSL (solution temporaire pour WAMP)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Préparer le message avec les images pour Ollama
$messageContent = !empty($userMessage) ? $userMessage : 'Analyse cette image';
$images = [];

// Ajouter les images si présentes
foreach ($files as $file) {
  $supportedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (in_array($file['type'], $supportedImageTypes)) {
    // Ollama attend les images en base64 sans le préfixe data:
    $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
    $images[] = $base64Data;
  } else {
    // Pour les fichiers non-image, ajouter le contenu au message
    if (
      strpos($file['type'], 'text/') === 0 ||
      $file['type'] === 'application/json' ||
      $file['type'] === 'application/xml'
    ) {
      $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
      $fileContent = base64_decode($base64Data);
      $messageContent .= "\n\nContenu du fichier {$file['name']}:\n```\n{$fileContent}\n```";
    }
  }
}

// Construire le message
$messageData = [
  'role' => 'user',
  'content' => $messageContent
];

// Ajouter les images si présentes
if (!empty($images)) {
  $messageData['images'] = $images;
}

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'model' => $model,
  'messages' => [$messageData],
  'stream' => false,
  'keep_alive' => 0  // Décharger le modèle immédiatement après la génération
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
  echo json_encode(['error' => 'Erreur API: ' . ($responseData['error'] ?? 'Erreur inconnue')]);
  exit();
}

// Extraire le message de la réponse (adapter selon la structure de l'API Ollama)
$assistantMessage = $responseData['message']['content'] ?? $responseData['response'] ?? 'Pas de réponse';

echo json_encode([
  'success' => true,
  'message' => $assistantMessage
]);
