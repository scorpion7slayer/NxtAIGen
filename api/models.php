<?php

/**
 * API d'autodétection des modèles
 * Récupère dynamiquement les modèles disponibles pour chaque provider
 * Accessible aux visiteurs (utilise les clés globales) et aux utilisateurs connectés
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../zone_membres/db.php';
require_once __DIR__ . '/api_keys_helper.php';

// Vérifier si l'utilisateur est connecté
$isGuest = !isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

// Charger la configuration depuis config.php (clés globales)
$configFile = require __DIR__ . '/config.php';
$config = [];
foreach ($configFile as $key => $value) {
  $config[$key] = $value;
}

// Si l'utilisateur est connecté, fusionner avec ses clés personnelles
if (!$isGuest && $userId) {
  $allConfigs = getAllApiConfigs($pdo, $userId);

  // Override avec les valeurs de la DB si disponibles
  foreach ($allConfigs as $provider => $providerConfig) {
    foreach ($providerConfig as $keyName => $keyValue) {
      if (!empty($keyValue)) {
        $config[$keyName] = $keyValue;
      }
    }
  }
}

// Récupérer le provider demandé
$provider = $_GET['provider'] ?? 'all';

// Base URL Ollama (configurable)
$ollamaBaseUrl = $_GET['base_url'] ?? $config['OLLAMA_API_URL'] ?? 'http://localhost:11434';
$ollamaBaseUrl = rtrim($ollamaBaseUrl, '/');

// Récupérer le token GitHub de l'utilisateur si connecté
$userGithubToken = null;
if (!$isGuest && $userId) {
  $stmt = $pdo->prepare("SELECT github_token FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch();
  $userGithubToken = $user['github_token'] ?? null;
}

/**
 * Fonction helper pour les requêtes cURL
 */
function fetchModels($url, $headers = [])
{
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => $headers
  ]);
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  // curl_close() supprimé - deprecated depuis PHP 8.0, handle fermé automatiquement

  return ['response' => $response, 'httpCode' => $httpCode];
}

/**
 * Récupère les modèles OpenAI
 * Filtrage amélioré - décembre 2025
 */
function getOpenAIModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  $result = fetchModels('https://api.openai.com/v1/models', [
    'Authorization: Bearer ' . $apiKey
  ]);

  if ($result['httpCode'] !== 200) return ['error' => 'Erreur API'];

  $data = json_decode($result['response'], true);
  $models = [];
  $seenIds = [];

  // Modèles à exclure (anciens, embeddings, audio, etc.)
  $excludePatterns = ['embed', 'whisper', 'tts', 'dall-e', 'davinci', 'babbage', 'ada', 'curie', 'instruct', 'search', 'similarity', 'code-', 'text-'];

  // Modèles prioritaires à garder (décembre 2025)
  $priorityModels = ['gpt-4o', 'gpt-4o-mini', 'o1', 'o1-mini', 'o3-mini', 'gpt-4-turbo', 'chatgpt-4o-latest'];

  foreach ($data['data'] ?? [] as $model) {
    $id = $model['id'];

    // Éviter les doublons
    if (isset($seenIds[$id])) continue;

    // Exclure les modèles non-chat
    $exclude = false;
    foreach ($excludePatterns as $pattern) {
      if (stripos($id, $pattern) !== false) {
        $exclude = true;
        break;
      }
    }
    if ($exclude) continue;

    // Garder uniquement gpt-*, o1-*, o3-*, chatgpt-*
    if (strpos($id, 'gpt') === false && strpos($id, 'o1') === false && strpos($id, 'o3') === false && strpos($id, 'chatgpt') === false) {
      continue;
    }

    $seenIds[$id] = true;
    $models[] = [
      'id' => $id,
      'name' => $id,
      'owned_by' => $model['owned_by'] ?? 'openai'
    ];
  }

  // Trier: modèles prioritaires en premier, puis par nom
  usort($models, function ($a, $b) use ($priorityModels) {
    $aIdx = array_search($a['id'], $priorityModels);
    $bIdx = array_search($b['id'], $priorityModels);
    if ($aIdx !== false && $bIdx !== false) return $aIdx - $bIdx;
    if ($aIdx !== false) return -1;
    if ($bIdx !== false) return 1;
    return strcmp($b['id'], $a['id']); // Tri inverse pour avoir les plus récents en premier
  });

  // Limiter à 15 modèles maximum
  $models = array_slice($models, 0, 15);

  return ['models' => $models];
}

/**
 * Récupère les modèles Anthropic
 * Mise à jour décembre 2025 - Documentation officielle
 * Source: platform.claude.com/docs/en/about-claude/models
 */
function getAnthropicModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  // Liste officielle décembre 2025
  return ['models' => [
    ['id' => 'claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5', 'description' => 'Le plus intelligent pour agents et code'],
    ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5', 'description' => 'Rapide avec intelligence frontier'],
    ['id' => 'claude-opus-4-5-20251101', 'name' => 'Claude Opus 4.5', 'description' => 'Maximum intelligence (preview)'],
    ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'description' => 'Équilibré et polyvalent'],
  ]];
}

/**
 * Récupère les modèles Ollama (URL configurable)
 */
function getOllamaModels($baseUrl = 'http://localhost:11434')
{
  $baseUrl = rtrim($baseUrl ?: 'http://localhost:11434', '/');
  $result = fetchModels($baseUrl . '/api/tags');

  if ($result['httpCode'] !== 200) {
    return ['error' => 'Ollama non disponible (' . $baseUrl . ')'];
  }

  $data = json_decode($result['response'], true);
  $models = [];

  foreach ($data['models'] ?? [] as $model) {
    $models[] = [
      'id' => $model['name'],
      'name' => $model['name'],
      'size' => $model['size'] ?? null,
      'modified' => $model['modified_at'] ?? null
    ];
  }

  return ['models' => $models];
}

/**
 * Récupère les modèles Google Gemini
 */
function getGeminiModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  $result = fetchModels('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey);

  if ($result['httpCode'] !== 200) return ['error' => 'Erreur API'];

  $data = json_decode($result['response'], true);
  $models = [];

  foreach ($data['models'] ?? [] as $model) {
    // Filtrer pour les modèles Gemini qui supportent generateContent
    if (in_array('generateContent', $model['supportedGenerationMethods'] ?? [])) {
      $name = str_replace('models/', '', $model['name']);
      $models[] = [
        'id' => $name,
        'name' => $model['displayName'] ?? $name,
        'description' => $model['description'] ?? ''
      ];
    }
  }

  return ['models' => $models];
}

/**
 * Récupère les modèles DeepSeek
 */
function getDeepSeekModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  $result = fetchModels('https://api.deepseek.com/v1/models', [
    'Authorization: Bearer ' . $apiKey
  ]);

  if ($result['httpCode'] !== 200) {
    return ['error' => 'Erreur API DeepSeek'];
  }

  $data = json_decode($result['response'], true);
  $models = [];

  foreach ($data['data'] ?? [] as $model) {
    $models[] = [
      'id' => $model['id'],
      'name' => $model['id'],
      'owned_by' => $model['owned_by'] ?? 'deepseek'
    ];
  }

  return ['models' => $models];
}

/**
 * Récupère les modèles Mistral
 */
function getMistralModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  $result = fetchModels('https://api.mistral.ai/v1/models', [
    'Authorization: Bearer ' . $apiKey
  ]);

  if ($result['httpCode'] !== 200) {
    return ['error' => 'Erreur API Mistral'];
  }

  $data = json_decode($result['response'], true);
  $models = [];

  foreach ($data['data'] ?? [] as $model) {
    $models[] = [
      'id' => $model['id'],
      'name' => $model['id'],
      'owned_by' => $model['owned_by'] ?? 'mistralai'
    ];
  }

  return ['models' => $models];
}

/**
 * Récupère les modèles OpenRouter
 */
function getOpenRouterModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  $headers = [
    'HTTP-Referer: http://localhost/NxtGenAI',
    'Authorization: Bearer ' . $apiKey
  ];

  $result = fetchModels('https://openrouter.ai/api/v1/models', $headers);

  if ($result['httpCode'] !== 200) return ['error' => 'Erreur API'];

  $data = json_decode($result['response'], true);
  $models = [];

  foreach ($data['data'] ?? [] as $model) {
    $models[] = [
      'id' => $model['id'],
      'name' => $model['name'] ?? $model['id'],
      'context_length' => $model['context_length'] ?? null,
      'pricing' => $model['pricing'] ?? null
    ];
  }

  // Limiter à 50 modèles les plus populaires
  $models = array_slice($models, 0, 50);

  return ['models' => $models];
}

/**
 * Récupère les modèles Hugging Face
 * Mise à jour décembre 2025
 */
function getHuggingFaceModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  // Hugging Face Inference API - modèles populaires décembre 2025
  return ['models' => [
    ['id' => 'meta-llama/Llama-3.3-70B-Instruct', 'name' => 'Llama 3.3 70B', 'description' => 'Meta - Dernier modèle'],
    ['id' => 'Qwen/Qwen2.5-72B-Instruct', 'name' => 'Qwen 2.5 72B', 'description' => 'Alibaba Qwen'],
    ['id' => 'Qwen/QwQ-32B', 'name' => 'QwQ 32B', 'description' => 'Qwen raisonnement'],
    ['id' => 'mistralai/Mistral-Large-Instruct-2411', 'name' => 'Mistral Large', 'description' => 'Mistral AI flagship'],
    ['id' => 'google/gemma-2-27b-it', 'name' => 'Gemma 2 27B', 'description' => 'Google Gemma'],
    ['id' => 'microsoft/Phi-4', 'name' => 'Phi-4', 'description' => 'Microsoft Phi dernier'],
  ]];
}

/**
 * Récupère les modèles Perplexity
 * Mise à jour décembre 2025
 * Source: docs.perplexity.ai
 */
function getPerplexityModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  // Perplexity API - modèles Sonar (basés sur Llama 3.3 70B)
  return ['models' => [
    ['id' => 'sonar-pro', 'name' => 'Sonar Pro', 'description' => 'Recherche multi-sources avancée'],
    ['id' => 'sonar', 'name' => 'Sonar', 'description' => 'Recherche web standard'],
    ['id' => 'sonar-reasoning-pro', 'name' => 'Sonar Reasoning Pro', 'description' => 'Raisonnement avancé avec recherche'],
    ['id' => 'sonar-reasoning', 'name' => 'Sonar Reasoning', 'description' => 'Raisonnement avec recherche'],
    ['id' => 'sonar-deep-research', 'name' => 'Sonar Deep Research', 'description' => 'Recherche approfondie'],
  ]];
}

/**
 * Récupère les modèles xAI (Grok)
 */
function getXAIModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  $result = fetchModels('https://api.x.ai/v1/models', [
    'Authorization: Bearer ' . $apiKey
  ]);

  if ($result['httpCode'] !== 200) {
    return ['error' => 'Erreur API xAI'];
  }

  $data = json_decode($result['response'], true);
  $models = [];

  foreach ($data['data'] ?? [] as $model) {
    $models[] = [
      'id' => $model['id'],
      'name' => $model['id']
    ];
  }

  return ['models' => $models];
}

/**
 * Récupère les modèles Moonshot
 */
function getMoonshotModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  $result = fetchModels('https://api.moonshot.cn/v1/models', [
    'Authorization: Bearer ' . $apiKey
  ]);

  if ($result['httpCode'] !== 200) {
    return ['error' => 'Erreur API Moonshot'];
  }

  $data = json_decode($result['response'], true);
  $models = [];

  foreach ($data['data'] ?? [] as $model) {
    $models[] = [
      'id' => $model['id'],
      'name' => $model['id']
    ];
  }

  return ['models' => $models];
}

/**
 * Récupère les modèles GitHub Copilot (Pro/Pro+)
 * Liste vérifiée via Context7 - Décembre 2025
 */
function getGitHubModels($token)
{
  if (empty($token)) return ['error' => 'Compte GitHub non connecté'];

  // GitHub Copilot API - Endpoint pour les modèles Copilot Pro/Pro+
  $result = fetchModels('https://api.githubcopilot.com/models', [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Editor-Version: vscode/1.95.0',
    'Editor-Plugin-Version: copilot/1.0.0',
    'Copilot-Integration-Id: vscode-chat',
    'User-Agent: NxtGenAI/1.0'
  ]);

  if ($result['httpCode'] === 200) {
    $data = json_decode($result['response'], true);
    $models = [];

    foreach ($data['models'] ?? $data['data'] ?? $data ?? [] as $model) {
      if (is_array($model)) {
        $models[] = [
          'id' => $model['id'] ?? $model['name'] ?? '',
          'name' => $model['name'] ?? $model['id'] ?? '',
          'description' => $model['description'] ?? 'GitHub Copilot',
          'version' => $model['version'] ?? null
        ];
      }
    }

    if (!empty($models)) {
      return ['models' => $models];
    }
  }

  // L'API n'a pas retourné de modèles valides
  return ['error' => 'Copilot Pro/Pro+ non disponible - Vérifiez votre abonnement'];
}

// Construire la réponse
$response = [];

switch ($provider) {
  case 'openai':
    $response = getOpenAIModels($config['OPENAI_API_KEY'] ?? '');
    break;

  case 'anthropic':
    $response = getAnthropicModels($config['ANTHROPIC_API_KEY'] ?? '');
    break;

  case 'ollama':
    $response = getOllamaModels($ollamaBaseUrl);
    break;

  case 'gemini':
    $response = getGeminiModels($config['GEMINI_API_KEY'] ?? '');
    break;

  case 'deepseek':
    $response = getDeepSeekModels($config['DEEPSEEK_API_KEY'] ?? '');
    break;

  case 'mistral':
    $response = getMistralModels($config['MISTRAL_API_KEY'] ?? '');
    break;

  case 'openrouter':
    $response = getOpenRouterModels($config['OPENROUTER_API_KEY'] ?? '');
    break;

  case 'huggingface':
    $response = getHuggingFaceModels($config['HUGGINGFACE_API_KEY'] ?? '');
    break;

  case 'perplexity':
    $response = getPerplexityModels($config['PERPLEXITY_API_KEY'] ?? '');
    break;

  case 'xai':
    $response = getXAIModels($config['XAI_API_KEY'] ?? '');
    break;

  case 'moonshot':
    $response = getMoonshotModels($config['MOONSHOT_API_KEY'] ?? '');
    break;

  case 'github':
    $response = getGitHubModels($userGithubToken ?? $config['GITHUB_TOKEN'] ?? '');
    break;

  case 'all':
  default:
    // Récupérer tous les providers
    $response = [
      'openai' => getOpenAIModels($config['OPENAI_API_KEY'] ?? ''),
      'anthropic' => getAnthropicModels($config['ANTHROPIC_API_KEY'] ?? ''),
      'ollama' => getOllamaModels($ollamaBaseUrl),
      'gemini' => getGeminiModels($config['GEMINI_API_KEY'] ?? ''),
      'deepseek' => getDeepSeekModels($config['DEEPSEEK_API_KEY'] ?? ''),
      'mistral' => getMistralModels($config['MISTRAL_API_KEY'] ?? ''),
      'openrouter' => getOpenRouterModels($config['OPENROUTER_API_KEY'] ?? ''),
      'huggingface' => getHuggingFaceModels($config['HUGGINGFACE_API_KEY'] ?? ''),
      'perplexity' => getPerplexityModels($config['PERPLEXITY_API_KEY'] ?? ''),
      'xai' => getXAIModels($config['XAI_API_KEY'] ?? ''),
      'moonshot' => getMoonshotModels($config['MOONSHOT_API_KEY'] ?? ''),
      'github' => getGitHubModels($userGithubToken ?? $config['GITHUB_TOKEN'] ?? ''),
    ];
    break;
}

$response['provider'] = $provider;
$response['timestamp'] = date('c');

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
