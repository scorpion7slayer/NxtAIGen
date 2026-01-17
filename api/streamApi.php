<?php

/**
 * API de streaming universelle pour tous les providers
 * Utilise Server-Sent Events (SSE) pour le streaming temps réel
 * 
 * @security SSL vérifié en production, CSRF token requis pour utilisateurs connectés
 */

// Headers de sécurité
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

session_start();

// Génération token CSRF si absent
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Désactiver les limites de temps pour le streaming long
set_time_limit(0);
ini_set('max_execution_time', 0);
ignore_user_abort(false);  // Arrêter si l'utilisateur ferme la connexion

// Headers pour SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Désactiver le buffering PHP - Ordre optimisé
while (ob_get_level() > 0) {
  ob_end_clean();
}
ini_set('output_buffering', 0);
ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);
ob_implicit_flush(true);

// Envoyer un caractère invisible pour forcer l'établissement de la connexion
echo ": ping\n\n";
if (function_exists('ob_flush')) ob_flush();
if (function_exists('flush')) flush();

// Fonction pour envoyer un événement SSE
function sendSSE($data, $event = 'message')
{
  echo "event: {$event}\n";
  echo "data: " . json_encode($data) . "\n\n";
  if (function_exists('ob_flush')) @ob_flush();
  if (function_exists('flush')) @flush();
}

// Constante pour la limite d'utilisations des visiteurs (messages)
define('GUEST_USAGE_LIMIT', 5);

// Charger la connexion DB (nécessaire pour le rate limiting)
require_once __DIR__ . '/../zone_membres/db.php';
require_once __DIR__ . '/rate_limiter.php';

// Vérifier si l'utilisateur est connecté ou est un visiteur
$isGuest = !isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

// Initialiser le rate limiter pour les utilisateurs connectés
$rateLimiter = null;
if (!$isGuest) {
  $rateLimiter = new RateLimiter($pdo);
}

// Pour les visiteurs, vérifier et incrémenter le compteur d'utilisations
if ($isGuest) {
  // Initialiser le timestamp de première utilisation si nécessaire
  if (!isset($_SESSION['guest_first_usage_time'])) {
    $_SESSION['guest_first_usage_time'] = time();
    $_SESSION['guest_usage_count'] = 0;
  }

  // Vérifier si 24h se sont écoulées depuis la première utilisation
  $timeSinceFirstUsage = time() - $_SESSION['guest_first_usage_time'];
  if ($timeSinceFirstUsage >= 86400) { // 86400 secondes = 24 heures
    // Réinitialiser le compteur et le timestamp
    $_SESSION['guest_usage_count'] = 0;
    $_SESSION['guest_first_usage_time'] = time();
  }

  // Initialiser le compteur de visiteur si nécessaire
  if (!isset($_SESSION['guest_usage_count'])) {
    $_SESSION['guest_usage_count'] = 0;
  }

  // Vérifier si la limite est atteinte
  if ($_SESSION['guest_usage_count'] >= GUEST_USAGE_LIMIT) {
    $timeRemaining = 86400 - (time() - $_SESSION['guest_first_usage_time']);
    $hours = floor($timeRemaining / 3600);
    $minutes = floor(($timeRemaining % 3600) / 60);
    $seconds = $timeRemaining % 60;

    $timeText = '';
    if ($hours > 0) {
      $timeText = "Réinitialisation dans {$hours}h {$minutes}min {$seconds}s";
    } else if ($minutes > 0) {
      $timeText = "Réinitialisation dans {$minutes}min {$seconds}s";
    } else {
      $timeText = "Réinitialisation dans {$seconds}s";
    }

    sendSSE(['error' => "Limite atteinte. {$timeText}, ou inscrivez-vous pour un accès illimité !", 'limit_reached' => true, 'usage_count' => $_SESSION['guest_usage_count'], 'usage_limit' => GUEST_USAGE_LIMIT], 'error');
    exit();
  }

  // PRÉ-INCRÉMENTER le compteur AVANT de fermer la session
  // Cette approche évite le problème de "session cannot be started after headers sent"
  $_SESSION['guest_usage_count']++;
  $guestUsageAfterIncrement = $_SESSION['guest_usage_count'];
  $guestSessionId = session_id(); // Sauvegarder l'ID pour rollback si erreur
  session_write_close();
} else {
  // Pour les utilisateurs connectés, vérifier le rate limiting
  $limitCheck = $rateLimiter->checkLimit($userId, 'message');
  if (!$limitCheck['allowed']) {
    sendSSE([
      'error' => $limitCheck['message'],
      'limit_type' => $limitCheck['limit_type'] ?? 'unknown',
      'reset_at' => $limitCheck['reset_at'] ?? null,
      'remaining' => $limitCheck['remaining'] ?? [],
      'limit_reached' => true
    ], 'error');
    exit();
  }
}

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  sendSSE(['error' => 'Méthode non autorisée'], 'error');
  exit();
}

// Vérification CSRF pour les utilisateurs connectés
if (!$isGuest) {
  $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (empty($csrfToken) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    sendSSE(['error' => 'Token de sécurité invalide. Veuillez rafraîchir la page.'], 'error');
    exit();
  }
}

// Récupérer les données JSON envoyées
$input = json_decode(file_get_contents('php://input'), true);

// Valider que le JSON est correctement parsé
if (!is_array($input)) {
  sendSSE(['error' => 'Format JSON invalide'], 'error');
  exit();
}

if (!isset($input['message']) || empty(trim($input['message']))) {
  if (!isset($input['files']) || empty($input['files'])) {
    sendSSE(['error' => 'Message ou fichier requis'], 'error');
    exit();
  }
}

$userMessage = isset($input['message']) ? trim($input['message']) : '';
$files = isset($input['files']) ? $input['files'] : [];

// Validation et sanitization du provider (whitelist stricte)
$allowedProviders = ['openai', 'anthropic', 'gemini', 'deepseek', 'mistral', 'xai', 'openrouter', 'perplexity', 'huggingface', 'moonshot', 'github', 'ollama'];
$provider = isset($input['provider']) ? strtolower(trim($input['provider'])) : 'openai';
if (!in_array($provider, $allowedProviders, true)) {
  sendSSE(['error' => 'Provider non supporté'], 'error');
  exit();
}

// Validation du modèle (caractères autorisés uniquement)
$model = isset($input['model']) ? trim($input['model']) : '';
if (!empty($model) && !preg_match('/^[a-zA-Z0-9\-_\.\/\:]+$/', $model)) {
  sendSSE(['error' => 'Format de modèle invalide'], 'error');
  exit();
}
if (strlen($model) > 128) {
  sendSSE(['error' => 'Nom de modèle trop long'], 'error');
  exit();
}

// Charger les helpers (db.php déjà chargé au début du fichier)
require_once __DIR__ . '/api_keys_helper.php';
require_once __DIR__ . '/helpers.php';

// Marquer le début pour le calcul du temps de réponse
$startTime = microtime(true);

// Récupérer les configurations de tous les providers pour l'utilisateur
$userId = $_SESSION['user_id'] ?? null;
$allConfigs = $userId ? getAllApiConfigs($pdo, $userId) : [];

// Fallback vers config.php pour compatibilité
$configFile = require __DIR__ . '/config.php';
$config = [];
foreach ($configFile as $key => $value) {
  $config[$key] = $value;
}

// Override avec les valeurs de la DB si disponibles
foreach ($allConfigs as $providerName => $providerConfig) {
  foreach ($providerConfig as $keyName => $keyValue) {
    if (!empty($keyValue)) {
      $config[$keyName] = $keyValue;
    }
  }
}

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

// Gestion spéciale pour GitHub Copilot (OAuth token depuis la DB)
if ($provider === 'github') {
  // GitHub Copilot nécessite une connexion
  if ($isGuest) {
    sendSSE(['error' => 'GitHub Copilot nécessite un compte. Inscrivez-vous pour l\'utiliser.'], 'error');
    exit();
  }
  // Récupérer le token GitHub depuis la base de données
  $stmt = $pdo->prepare("SELECT github_token FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $userRow = $stmt->fetch();
  $githubToken = $userRow['github_token'] ?? null;

  if (empty($githubToken)) {
    sendSSE(['error' => 'GitHub Copilot: Connexion requise. Connectez votre compte GitHub dans Paramètres.'], 'error');
    exit();
  }
  $apiKey = $githubToken;
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

// SSL: activé par défaut, désactivé pour localhost ou Ollama distant (certificat potentiellement auto-signé)
$isLocalhost = preg_match('/^https?:\/\/(localhost|127\.0\.0\.1|\[::1\])/', $apiUrl);
$isOllamaCustomUrl = ($provider === 'ollama' && !empty($config['OLLAMA_API_URL']) && !$isLocalhost);

// Option: permettre de désactiver SSL via config pour serveurs avec certificats auto-signés
$skipSslVerify = $isLocalhost || $isOllamaCustomUrl || !empty($config['SKIP_SSL_VERIFY']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$skipSslVerify);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $skipSslVerify ? 0 : 2);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);            // Pas de timeout global
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);    // 30s pour la connexion (augmenté pour serveurs distants)
curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);    // Au moins 1 byte/sec
curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 120);   // 120s d'inactivité max (augmenté pour IA lentes)

// Optimisations performance streaming
curl_setopt($ch, CURLOPT_TCP_NODELAY, true);     // Désactive Nagle algorithm (-40ms latence)
curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024);      // Chunks plus petits = latence réduite

// Callback pour traiter les chunks
$fullResponse = '';
$hasError = false;
$errorBuffer = '';
$tokensInput = 0;
$tokensOutput = 0;
$tokensTotal = 0;

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$fullResponse, &$hasError, &$errorBuffer, &$tokensInput, &$tokensOutput, &$tokensTotal, $provider) {
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

      // Capturer les tokens si disponibles (OpenAI, OpenRouter, etc.)
      if (isset($decoded['usage'])) {
        if (isset($decoded['usage']['prompt_tokens'])) {
          $tokensInput = $decoded['usage']['prompt_tokens'];
        }
        if (isset($decoded['usage']['completion_tokens'])) {
          $tokensOutput = $decoded['usage']['completion_tokens'];
        }
        if (isset($decoded['usage']['total_tokens'])) {
          $tokensTotal = $decoded['usage']['total_tokens'];
        }
      }

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
// curl_close() supprimé - deprecated depuis PHP 8.0

// Gérer les erreurs cURL (message générique pour la sécurité)
if ($curlError) {
  // Log détaillé côté serveur
  error_log("[NxtAIGen] cURL Error for {$provider}: {$curlError} - URL: " . preg_replace('/key=[^&]+/', 'key=***', $apiUrl));

  // Message générique pour l'utilisateur (évite l'exposition d'informations)
  $userMessage = 'Erreur de connexion au provider';
  if (strpos($curlError, 'timed out') !== false) {
    $userMessage = 'Le provider ne répond pas. Réessayez.';
  } else if (strpos($curlError, 'resolve') !== false) {
    $userMessage = 'Provider inaccessible. Vérifiez votre connexion.';
  }

  sendSSE(['error' => $userMessage], 'error');
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

// Gestion du compteur d'utilisations (visiteurs et utilisateurs connectés)
if (!$hasError) {
  if ($isGuest) {
    // Compteur déjà pré-incrémenté avant le streaming, rien à faire ici
  } else {
    // Calculer tokens utilisés (utiliser les valeurs capturées ou estimation)
    $tokensUsed = 0;
    if ($tokensTotal > 0) {
      $tokensUsed = $tokensTotal;
    } else if (isset($responseData['usage']['total_tokens'])) {
      $tokensUsed = $responseData['usage']['total_tokens'];
    } else {
      // Estimation: ~1 token = 4 caractères
      $tokensUsed = intval((strlen($userMessage) + strlen($fullResponse)) / 4);
      $tokensInput = intval(strlen($userMessage) / 4);
      $tokensOutput = intval(strlen($fullResponse) / 4);
      $tokensTotal = $tokensInput + $tokensOutput;
    }

    // Enregistrer l'usage avec le rate limiter déjà instancié
    $rateLimiter->incrementUsage($userId, 'message', $tokensUsed, [
      'provider' => $provider,
      'model' => $model,
      'response_time' => isset($startTime) ? intval((microtime(true) - $startTime) * 1000) : 0,
      'status' => 'success'
    ]);
  }
}

// Envoyer l'événement de fin si pas déjà fait et pas d'erreur
if (!$hasError) {
  $doneData = ['done' => true, 'fullResponse' => $fullResponse];

  // Ajouter les infos de tokens
  if ($tokensTotal > 0 || $tokensInput > 0 || $tokensOutput > 0) {
    $doneData['tokens'] = [
      'input' => $tokensInput,
      'output' => $tokensOutput,
      'total' => $tokensTotal
    ];
  }

  // Ajouter les infos d'utilisation
  if ($isGuest) {
    // Utiliser la valeur pré-incrémentée
    $doneData['usage_count'] = $guestUsageAfterIncrement;
    $doneData['usage_limit'] = GUEST_USAGE_LIMIT;
    $doneData['remaining'] = GUEST_USAGE_LIMIT - $guestUsageAfterIncrement;
  } else {
    // Ajouter les limites restantes pour les utilisateurs connectés
    $remaining = $rateLimiter->getRemainingLimits($userId);
    $doneData['rate_limits'] = $remaining;
  }

  sendSSE($doneData, 'done');
}
