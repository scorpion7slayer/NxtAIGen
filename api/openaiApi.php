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
$OPENAI_API_KEY = $config['OPENAI_API_KEY'];

// Autodétection du modèle si non fourni
if (!isset($input['model']) || empty($input['model'])) {
  // Charger les modèles disponibles depuis models.php
  require_once __DIR__ . '/models.php';
  $modelsData = getOpenAIModels($OPENAI_API_KEY);

  if (isset($modelsData['models']) && !empty($modelsData['models'])) {
    $model = $modelsData['models'][0]['id']; // Premier modèle disponible
  } else {
    // Fallback si l'autodétection échoue
    http_response_code(500);
    echo json_encode(['error' => 'Impossible de détecter les modèles OpenAI disponibles']);
    exit();
  }
} else {
  $model = $input['model'];
}

// Appel à l'API OpenAI (Chat Completions API)
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
// Désactiver la vérification SSL (solution temporaire pour WAMP)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Authorization: Bearer ' . $OPENAI_API_KEY
]);

// Construire le contenu du message (texte + images)
$messageContent = [];

// Ajouter le texte si présent
if (!empty($userMessage)) {
  $messageContent[] = [
    'type' => 'text',
    'text' => $userMessage
  ];
}

// Ajouter les images si présentes
foreach ($files as $file) {
  // Vérifier si c'est une image supportée
  $supportedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (in_array($file['type'], $supportedImageTypes)) {
    $messageContent[] = [
      'type' => 'image_url',
      'image_url' => [
        'url' => $file['data'], // data:image/xxx;base64,... format
        'detail' => 'auto'
      ]
    ];
  } else {
    // Pour les fichiers non-image, ajouter le contenu comme texte
    // Extraire le contenu base64 et le décoder si c'est un fichier texte
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

// Si aucun contenu, ajouter un message par défaut
if (empty($messageContent)) {
  $messageContent = $userMessage ?: 'Analyse ce fichier';
}

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
  'model' => $model,
  'messages' => [
    ['role' => 'user', 'content' => $messageContent]
  ],
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
  echo json_encode(['error' => 'Erreur API: ' . ($responseData['error'] ?? 'Erreur inconnue')]);
  exit();
}

// Extraire le message de la réponse (API OpenAI Chat Completions)
$assistantMessage = $responseData['choices'][0]['message']['content'] ?? 'Pas de réponse';

echo json_encode([
  'success' => true,
  'message' => $assistantMessage
]);
