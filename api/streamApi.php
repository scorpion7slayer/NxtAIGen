<?php

/**
 * API de streaming universelle pour tous les providers
 * Utilise Server-Sent Events (SSE) pour le streaming temps réel
 */

session_start();

// Désactiver les limites de temps pour le streaming long
set_time_limit(0);
ini_set('max_execution_time', 0);
ignore_user_abort(false);  // Arrêter si l'utilisateur ferme la connexion

// Headers pour SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Désactiver le buffering PHP
if (ob_get_level()) ob_end_clean();
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ini_set('implicit_flush', 1);
ob_implicit_flush(true);

// Fonction pour envoyer un événement SSE
function sendSSE($data, $event = 'message')
{
  echo "event: {$event}\n";
  echo "data: " . json_encode($data) . "\n\n";
  flush();
}

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
  sendSSE(['error' => 'Non authentifié'], 'error');
  exit();
}

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  sendSSE(['error' => 'Méthode non autorisée'], 'error');
  exit();
}

// Récupérer les données JSON envoyées
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty(trim($input['message']))) {
  if (!isset($input['files']) || empty($input['files'])) {
    sendSSE(['error' => 'Message ou fichier requis'], 'error');
    exit();
  }
}

$userMessage = isset($input['message']) ? trim($input['message']) : '';
$files = isset($input['files']) ? $input['files'] : [];
$provider = isset($input['provider']) ? $input['provider'] : 'openai';
$model = isset($input['model']) ? $input['model'] : '';

// Charger la configuration
$config = require __DIR__ . '/config.php';

// Préparer le contenu du message avec fichiers
require_once __DIR__ . '/helpers.php';

// Configuration par provider
$streamConfigs = [
  'openai' => [
    'url' => 'https://api.openai.com/v1/chat/completions',
    'key' => $config['OPENAI_API_KEY'] ?? '',
    'default_model' => 'gpt-4o-mini'
  ],
  'anthropic' => [
    'url' => 'https://api.anthropic.com/v1/messages',
    'key' => $config['ANTHROPIC_API_KEY'] ?? '',
    'default_model' => 'claude-3-haiku-20240307'
  ],
  'gemini' => [
    'url' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:streamGenerateContent?alt=sse',
    'key' => $config['GEMINI_API_KEY'] ?? '',
    'default_model' => 'gemini-1.5-flash'
  ],
  'deepseek' => [
    'url' => 'https://api.deepseek.com/chat/completions',
    'key' => $config['DEEPSEEK_API_KEY'] ?? '',
    'default_model' => 'deepseek-chat'
  ],
  'mistral' => [
    'url' => 'https://api.mistral.ai/v1/chat/completions',
    'key' => $config['MISTRAL_API_KEY'] ?? '',
    'default_model' => 'mistral-small-latest'
  ],
  'xai' => [
    'url' => 'https://api.x.ai/v1/chat/completions',
    'key' => $config['XAI_API_KEY'] ?? '',
    'default_model' => 'grok-beta'
  ],
  'openrouter' => [
    'url' => 'https://openrouter.ai/api/v1/chat/completions',
    'key' => $config['OPENROUTER_API_KEY'] ?? '',
    'default_model' => 'openai/gpt-4o-mini'
  ],
  'perplexity' => [
    'url' => 'https://api.perplexity.ai/chat/completions',
    'key' => $config['PERPLEXITY_API_KEY'] ?? '',
    'default_model' => 'llama-3.1-sonar-small-128k-online'
  ],
  'huggingface' => [
    'url' => 'https://api-inference.huggingface.co/models/{model}/v1/chat/completions',
    'key' => $config['HUGGINGFACE_API_KEY'] ?? '',
    'default_model' => 'mistralai/Mistral-7B-Instruct-v0.3'
  ],
  'moonshot' => [
    'url' => 'https://api.moonshot.cn/v1/chat/completions',
    'key' => $config['MOONSHOT_API_KEY'] ?? '',
    'default_model' => 'moonshot-v1-8k'
  ],
  'github' => [
    'url' => 'https://api.githubcopilot.com/chat/completions',
    'key' => '', // Utilise OAuth token
    'default_model' => 'gpt-4o'
  ],
  'ollama' => [
    'url' => ($config['OLLAMA_API_URL'] ?? $config['OLLAMA_BASE_URL'] ?? 'http://localhost:11434') . '/api/chat',
    'key' => $config['OLLAMA_API_KEY'] ?? '',
    'default_model' => 'llama3.2'
  ]
];

// Vérifier si le provider est supporté
if (!isset($streamConfigs[$provider])) {
  sendSSE(['error' => "Provider '{$provider}' non supporté pour le streaming"], 'error');
  exit();
}

$streamConfig = $streamConfigs[$provider];
$apiKey = $streamConfig['key'];
$apiUrl = $streamConfig['url'];
$model = $model ?: $streamConfig['default_model'];

// Gestion spéciale pour GitHub Copilot (OAuth token)
if ($provider === 'github') {
  if (!isset($_SESSION['github_token'])) {
    sendSSE(['error' => 'GitHub Copilot: Connexion requise'], 'error');
    exit();
  }
  $apiKey = $_SESSION['github_token'];
}

// Vérifier la clé API (sauf Ollama)
if ($provider !== 'ollama' && $provider !== 'github' && empty($apiKey)) {
  sendSSE(['error' => "Clé API {$provider} non configurée"], 'error');
  exit();
}

// Construire la requête selon le provider
switch ($provider) {
  case 'anthropic':
    $messageContent = prepareAnthropicMessageContent($userMessage, $files);
    $postData = [
      'model' => $model,
      'max_tokens' => 4096,
      'stream' => true,
      'messages' => [
        ['role' => 'user', 'content' => $messageContent]
      ]
    ];
    $headers = [
      'Content-Type: application/json',
      'x-api-key: ' . $apiKey,
      'anthropic-version: 2023-06-01'
    ];
    break;

  case 'gemini':
    $parts = prepareGeminiParts($userMessage, $files);
    $apiUrl = str_replace('{model}', $model, $apiUrl) . '&key=' . $apiKey;
    $postData = [
      'contents' => [
        ['parts' => $parts]
      ]
    ];
    $headers = ['Content-Type: application/json'];
    break;

  case 'ollama':
    $ollamaData = prepareOllamaMessage($userMessage, $files);
    $ollamaMessage = ['role' => 'user', 'content' => $ollamaData['content']];
    if (!empty($ollamaData['images'])) {
      $ollamaMessage['images'] = $ollamaData['images'];
    }
    $postData = [
      'model' => $model,
      'messages' => [$ollamaMessage],
      'stream' => true,
      'keep_alive' => 0  // Décharger le modèle immédiatement après la génération
    ];
    $headers = ['Content-Type: application/json'];
    // Ajouter l'authentification si une clé API est fournie (pour Ollama distant)
    if (!empty($apiKey)) {
      $headers[] = 'Authorization: Bearer ' . $apiKey;
    }
    break;

  case 'huggingface':
    $apiUrl = str_replace('{model}', $model, $apiUrl);
    $messageContent = prepareOpenAIMessageContent($userMessage, $files);
    $postData = [
      'model' => $model,
      'messages' => [
        ['role' => 'user', 'content' => $messageContent]
      ],
      'stream' => true,
      'max_tokens' => 4096
    ];
    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey
    ];
    break;

  case 'github':
    $messageContent = prepareOpenAIMessageContent($userMessage, $files);
    $postData = [
      'model' => $model,
      'messages' => [
        ['role' => 'user', 'content' => $messageContent]
      ],
      'stream' => true,
      'max_tokens' => 4096
    ];
    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey,
      'Editor-Version: vscode/1.85.0',
      'Editor-Plugin-Version: copilot/1.0.0'
    ];
    break;

  default: // OpenAI-compatible (openai, deepseek, mistral, xai, openrouter, perplexity, moonshot)
    $messageContent = prepareOpenAIMessageContent($userMessage, $files);
    $postData = [
      'model' => $model,
      'messages' => [
        ['role' => 'user', 'content' => $messageContent]
      ],
      'stream' => true,
      'max_tokens' => 4096
    ];
    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey
    ];

    // Headers supplémentaires pour OpenRouter
    if ($provider === 'openrouter') {
      $headers[] = 'HTTP-Referer: http://localhost';
      $headers[] = 'X-Title: NxtGenAI';
    }
    break;
}

// Initialiser cURL avec streaming
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);           // Pas de timeout global
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);   // 30s pour la connexion
curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);   // Au moins 1 byte/sec
curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 300);  // Pendant 5 minutes max d'inactivité

// Callback pour traiter les chunks
$fullResponse = '';
$hasError = false;
$errorBuffer = '';

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$fullResponse, &$hasError, &$errorBuffer, $provider) {
  // Vérifier le code HTTP
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  // Si erreur HTTP, accumuler les données pour le message d'erreur
  if ($httpCode >= 400) {
    $errorBuffer .= $data;
    return strlen($data);
  }

  $lines = explode("\n", $data);

  foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    // Ignorer les lignes "event:" pour SSE
    if (strpos($line, 'event:') === 0) continue;

    // Extraire les données
    if (strpos($line, 'data:') === 0) {
      $jsonData = trim(substr($line, 5));

      // Ignorer [DONE]
      if ($jsonData === '[DONE]') {
        sendSSE(['done' => true], 'done');
        continue;
      }

      $decoded = json_decode($jsonData, true);
      if (!$decoded) continue;

      // Vérifier s'il y a une erreur dans la réponse
      if (isset($decoded['error'])) {
        $errorMsg = is_array($decoded['error'])
          ? ($decoded['error']['message'] ?? json_encode($decoded['error']))
          : $decoded['error'];
        sendSSE(['error' => $errorMsg], 'error');
        $hasError = true;
        continue;
      }

      $content = '';

      // Extraire le contenu selon le provider
      switch ($provider) {
        case 'anthropic':
          if (isset($decoded['type']) && $decoded['type'] === 'content_block_delta') {
            $content = $decoded['delta']['text'] ?? '';
          }
          // Gérer message_stop
          if (isset($decoded['type']) && $decoded['type'] === 'message_stop') {
            sendSSE(['done' => true], 'done');
          }
          break;

        case 'gemini':
          if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $decoded['candidates'][0]['content']['parts'][0]['text'];
          }
          break;

        default: // OpenAI-compatible
          if (isset($decoded['choices'][0]['delta']['content'])) {
            $content = $decoded['choices'][0]['delta']['content'];
          }
          // Vérifier finish_reason
          if (isset($decoded['choices'][0]['finish_reason']) && $decoded['choices'][0]['finish_reason'] !== null) {
            sendSSE(['done' => true], 'done');
          }
          break;
      }

      if (!empty($content)) {
        $fullResponse .= $content;
        sendSSE(['content' => $content], 'content');
      }
    } else if ($provider === 'ollama') {
      // Ollama ne préfixe pas avec "data:"
      $decoded = json_decode($line, true);
      if ($decoded) {
        // Vérifier erreur Ollama
        if (isset($decoded['error'])) {
          sendSSE(['error' => $decoded['error']], 'error');
          $hasError = true;
          continue;
        }
        if (isset($decoded['message']['content'])) {
          $content = $decoded['message']['content'];
          $fullResponse .= $content;
          sendSSE(['content' => $content], 'content');

          if (isset($decoded['done']) && $decoded['done']) {
            sendSSE(['done' => true], 'done');
          }
        }
      }
    }
  }

  return strlen($data);
});

// Exécuter la requête
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Gérer les erreurs cURL
if ($curlError) {
  sendSSE(['error' => 'Erreur de connexion: ' . $curlError], 'error');
  sendSSE(['done' => true], 'done');
  exit();
}

// Gérer les erreurs HTTP
if ($httpCode >= 400 && !empty($errorBuffer)) {
  $errorData = json_decode($errorBuffer, true);
  if ($errorData && isset($errorData['error'])) {
    $errorMsg = is_array($errorData['error'])
      ? ($errorData['error']['message'] ?? json_encode($errorData['error']))
      : $errorData['error'];
    sendSSE(['error' => "Erreur API ({$httpCode}): {$errorMsg}"], 'error');
  } else {
    sendSSE(['error' => "Erreur API: HTTP {$httpCode}"], 'error');
  }
  sendSSE(['done' => true], 'done');
  exit();
}

// Envoyer l'événement de fin si pas déjà fait et pas d'erreur
if (!$hasError) {
  sendSSE(['done' => true, 'fullResponse' => $fullResponse], 'done');
}
