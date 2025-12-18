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
$ANTHROPIC_API_KEY = $config['ANTHROPIC_API_KEY'];

// Autodétection du modèle si non fourni
if (!isset($input['model']) || empty($input['model'])) {
  // Charger les modèles disponibles depuis models.php
  require_once __DIR__ . '/models.php';
  $modelsData = getAnthropicModels($ANTHROPIC_API_KEY);

  if (isset($modelsData['models']) && !empty($modelsData['models'])) {
    $model = $modelsData['models'][0]['id']; // Premier modèle disponible
  } else {
    // Fallback si l'autodétection échoue
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de détecter les modèles Anthropic disponibles']);
    exit();
  }
} else {
  $model = $input['model'];
}

// Appel à l'API Anthropic (Messages API)
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// Désactiver la vérification SSL (solution temporaire pour WAMP)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'x-api-key: ' . $ANTHROPIC_API_KEY,
  'anthropic-version: 2023-06-01'
]);

// Construire le contenu du message (texte + images)
$messageContent = [];

// Ajouter les images si présentes (Anthropic requiert les images avant le texte)
foreach ($files as $file) {
  // Vérifier si c'est une image supportée
  $supportedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (in_array($file['type'], $supportedImageTypes)) {
    // Extraire les données base64 sans le préfixe data:
    $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
    $messageContent[] = [
      'type' => 'image',
      'source' => [
        'type' => 'base64',
        'media_type' => $file['type'],
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
      $messageContent[] = [
        'type' => 'text',
        'text' => "Contenu du fichier {$file['name']}:\n```\n{$fileContent}\n```"
      ];
    }
  }
}

// Ajouter le texte à la fin
if (!empty($userMessage)) {
  $messageContent[] = [
    'type' => 'text',
    'text' => $userMessage
  ];
} else if (empty($messageContent)) {
  $messageContent[] = [
    'type' => 'text',
    'text' => 'Analyse ce fichier'
  ];
}

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'model' => $model,
  'max_tokens' => 4096,
  'messages' => [
    [
      'role' => 'user',
      'content' => $messageContent
    ]
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
  $errorMessage = $responseData['error']['message'] ?? 'Erreur inconnue';
  echo json_encode(['error' => 'Erreur API: ' . $errorMessage]);
  exit();
}

// Extraire le message de la réponse (API Anthropic Messages)
$assistantMessage = $responseData['content'][0]['text'] ?? 'Pas de réponse';

echo json_encode([
  'success' => true,
  'message' => $assistantMessage
]);
