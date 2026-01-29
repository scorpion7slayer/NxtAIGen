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

// Récupérer les paramètres provider_settings (ex: openrouter_free_only)
$providerSettings = [];
try {
  $settingsStmt = $pdo->query("SELECT provider, setting_key, setting_value FROM provider_settings WHERE is_global = 1");
  while ($row = $settingsStmt->fetch()) {
    $providerSettings[$row['provider']][$row['setting_key']] = $row['setting_value'];
  }
} catch (PDOException $e) {
  // Table might not exist yet
}

// OpenRouter: modèles gratuits uniquement ?
$openrouterFreeOnly = ($providerSettings['openrouter']['OPENROUTER_FREE_ONLY'] ?? '0') === '1';

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
 * Filtrage amélioré - décembre 2026
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
  $excludePatterns = ['embed', 'whisper', 'tts', 'dall-e', 'davinci', 'babbage', 'ada', 'curie', 'instruct', 'search', 'similarity', 'code-', 'text-', 'realtime'];

  // Modèles prioritaires à garder (janvier 2026)
  $priorityModels = ['gpt-5.2', 'gpt-5-mini', 'gpt-5-nano', 'o3', 'o4-mini', 'gpt-4.1', 'gpt-4o', 'gpt-4o-mini', 'o1', 'chatgpt-4o-latest'];

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

    // Garder uniquement gpt-*, o1-*, o3-*, o4-*, chatgpt-*
    if (strpos($id, 'gpt') === false && strpos($id, 'o1') === false && strpos($id, 'o3') === false && strpos($id, 'o4') === false && strpos($id, 'chatgpt') === false) {
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
 * Récupère les modèles Anthropic dynamiquement via l'API
 * Endpoint: GET https://api.anthropic.com/v1/models
 * Source: platform.claude.com/docs/en/api/models-list
 */
function getAnthropicModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  $result = fetchModels('https://api.anthropic.com/v1/models?limit=100', [
    'x-api-key: ' . $apiKey,
    'anthropic-version: 2023-06-01',
    'Content-Type: application/json'
  ]);

  if ($result['httpCode'] !== 200) {
    // Fallback sur liste statique en cas d'erreur
    return ['models' => [
      ['id' => 'claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5', 'description' => 'Le plus intelligent pour agents et code'],
      ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5', 'description' => 'Rapide avec intelligence frontier'],
      ['id' => 'claude-opus-4-5-20251101', 'name' => 'Claude Opus 4.5', 'description' => 'Maximum intelligence (preview)'],
      ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'description' => 'Équilibré et polyvalent'],
    ]];
  }

  $data = json_decode($result['response'], true);
  $models = [];

  foreach ($data['data'] ?? [] as $model) {
    $models[] = [
      'id' => $model['id'],
      'name' => $model['display_name'] ?? $model['id'],
      'created_at' => $model['created_at'] ?? null
    ];
  }

  // Retourner les modèles récupérés ou fallback
  if (empty($models)) {
    return ['models' => [
      ['id' => 'claude-sonnet-4-5-20250929', 'name' => 'Claude Sonnet 4.5', 'description' => 'Le plus intelligent pour agents et code'],
      ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5', 'description' => 'Rapide avec intelligence frontier'],
      ['id' => 'claude-opus-4-5-20251101', 'name' => 'Claude Opus 4.5', 'description' => 'Maximum intelligence (preview)'],
      ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4', 'description' => 'Équilibré et polyvalent'],
    ]];
  }

  return ['models' => $models];
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
 * @param string $apiKey Clé API OpenRouter
 * @param bool $freeOnly Si true, ne retourne que les modèles gratuits
 */
function getOpenRouterModels($apiKey, $freeOnly = false)
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
    // Filtrer les modèles gratuits si demandé
    if ($freeOnly) {
      // Les modèles gratuits ont ":free" dans leur ID ou un pricing à 0
      $isFree = strpos($model['id'], ':free') !== false;
      if (!$isFree && isset($model['pricing'])) {
        $prompt = floatval($model['pricing']['prompt'] ?? 1);
        $completion = floatval($model['pricing']['completion'] ?? 1);
        $isFree = ($prompt == 0 && $completion == 0);
      }
      if (!$isFree) continue;
    }

    $models[] = [
      'id' => $model['id'],
      'name' => $model['name'] ?? $model['id'],
      'context_length' => $model['context_length'] ?? null,
      'pricing' => $model['pricing'] ?? null,
      'is_free' => strpos($model['id'], ':free') !== false ||
                   (isset($model['pricing']) &&
                    floatval($model['pricing']['prompt'] ?? 1) == 0 &&
                    floatval($model['pricing']['completion'] ?? 1) == 0)
    ];
  }

  // Limiter à 100 modèles (plus si free only car moins de résultats)
  $models = array_slice($models, 0, $freeOnly ? 100 : 50);

  return ['models' => $models];
}

/**
 * Récupère les modèles Hugging Face dynamiquement via l'API Hub
 * Endpoint: GET https://huggingface.co/api/models
 * Filtré pour text-generation avec conversational tag
 */
function getHuggingFaceModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  // Récupérer les modèles de text-generation les plus populaires
  $url = 'https://huggingface.co/api/models?' . http_build_query([
    'pipeline_tag' => 'text-generation',
    'sort' => 'likes',
    'direction' => -1,
    'limit' => 20,
    'filter' => 'conversational'
  ]);

  $result = fetchModels($url, [
    'Authorization: Bearer ' . $apiKey
  ]);

  if ($result['httpCode'] === 200) {
    $data = json_decode($result['response'], true);
    $models = [];

    foreach ($data ?? [] as $model) {
      // Filtrer les modèles avec "Instruct" ou "Chat" dans le nom (conversationnels)
      $modelId = $model['modelId'] ?? $model['id'] ?? '';
      if (empty($modelId)) continue;

      $models[] = [
        'id' => $modelId,
        'name' => $model['modelId'] ?? $modelId,
        'likes' => $model['likes'] ?? 0,
        'downloads' => $model['downloads'] ?? 0
      ];
    }

    if (!empty($models)) {
      return ['models' => array_slice($models, 0, 15)];
    }
  }

  // Fallback sur liste statique si l'API échoue
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
 * Mise à jour janvier 2026 - Liste officielle Sonar
 * Note: Perplexity n'a pas d'endpoint /models, liste curatée
 * Source: docs.perplexity.ai/guides/model-cards
 */
function getPerplexityModels($apiKey)
{
  if (empty($apiKey)) return ['error' => 'Clé API non configurée'];

  // Perplexity API - famille Sonar (basée sur Llama 3.3 70B)
  // Pas d'endpoint de listing disponible - liste officielle
  return ['models' => [
    ['id' => 'sonar', 'name' => 'Sonar', 'description' => 'Recherche web rapide (128k tokens)', 'context' => 128000],
    ['id' => 'sonar-pro', 'name' => 'Sonar Pro', 'description' => 'Recherche approfondie (200k tokens)', 'context' => 200000],
    ['id' => 'sonar-reasoning', 'name' => 'Sonar Reasoning', 'description' => 'Raisonnement avec recherche (128k)', 'context' => 128000],
    ['id' => 'sonar-reasoning-pro', 'name' => 'Sonar Reasoning Pro', 'description' => 'Raisonnement avancé DeepSeek-R1 (128k)', 'context' => 128000],
    ['id' => 'sonar-deep-research', 'name' => 'Sonar Deep Research', 'description' => 'Recherche exhaustive multi-sources', 'context' => 128000],
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
    $response = getOpenRouterModels($config['OPENROUTER_API_KEY'] ?? '', $openrouterFreeOnly);
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
      'openrouter' => getOpenRouterModels($config['OPENROUTER_API_KEY'] ?? '', $openrouterFreeOnly),
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

// =====================================================
// FILTRE 1: Modèles désactivés par l'admin (models_status)
// S'applique à tous les utilisateurs (invités et connectés)
// =====================================================
$tableCheck = $pdo->query("SHOW TABLES LIKE 'models_status'");
if ($tableCheck->rowCount() > 0) {
  // Charger les modèles désactivés par l'admin
  $adminDisabledStmt = $pdo->query("SELECT provider, model_id FROM models_status WHERE is_enabled = 0");
  $adminDisabled = [];
  while ($row = $adminDisabledStmt->fetch()) {
    $key = strtolower($row['provider']) . '::' . $row['model_id'];
    $adminDisabled[$key] = true;
  }

  // Filtrer les modèles désactivés par l'admin
  if (!empty($adminDisabled)) {
    if ($provider === 'all') {
      foreach ($response as $prov => &$provData) {
        if (is_array($provData) && isset($provData['models'])) {
          $provData['models'] = array_values(array_filter($provData['models'], function ($model) use ($prov, $adminDisabled) {
            $modelId = $model['id'] ?? '';
            $key = strtolower($prov) . '::' . $modelId;
            return !isset($adminDisabled[$key]);
          }));
        }
      }
      unset($provData);
    } else {
      if (isset($response['models'])) {
        $response['models'] = array_values(array_filter($response['models'], function ($model) use ($provider, $adminDisabled) {
          $modelId = $model['id'] ?? '';
          $key = strtolower($provider) . '::' . $modelId;
          return !isset($adminDisabled[$key]);
        }));
      }
    }
  }
}

// =====================================================
// FILTRE 2: Préférences utilisateur (user_models_preferences)
// S'applique uniquement aux utilisateurs connectés
// =====================================================
// Sauf si ?no_filter=1 est passé (pour la page settings)
$applyUserFilter = !isset($_GET['no_filter']) && !$isGuest && $userId;

if ($applyUserFilter) {
  // Vérifier si la table existe
  $tableCheck = $pdo->query("SHOW TABLES LIKE 'user_models_preferences'");
  if ($tableCheck->rowCount() > 0) {
    // Charger les préférences utilisateur
    $prefStmt = $pdo->prepare("SELECT provider, model_id, is_enabled FROM user_models_preferences WHERE user_id = ?");
    $prefStmt->execute([$userId]);

    $userPrefs = [];
    while ($row = $prefStmt->fetch()) {
      if (!isset($userPrefs[$row['provider']])) {
        $userPrefs[$row['provider']] = [];
      }
      $userPrefs[$row['provider']][$row['model_id']] = (bool)$row['is_enabled'];
    }

    // Filtrer les modèles si des préférences existent
    if (!empty($userPrefs)) {
      if ($provider === 'all') {
        // Filtrer chaque provider
        foreach ($response as $prov => &$provData) {
          if (is_array($provData) && isset($provData['models'])) {
            $provData['models'] = array_values(array_filter($provData['models'], function ($model) use ($prov, $userPrefs) {
              $modelId = $model['id'] ?? '';
              // Si pas de préférence définie, garder le modèle (true par défaut)
              return !isset($userPrefs[$prov][$modelId]) || $userPrefs[$prov][$modelId] === true;
            }));
          }
        }
        unset($provData);
      } else {
        // Filtrer un seul provider
        if (isset($response['models']) && isset($userPrefs[$provider])) {
          $response['models'] = array_values(array_filter($response['models'], function ($model) use ($provider, $userPrefs) {
            $modelId = $model['id'] ?? '';
            return !isset($userPrefs[$provider][$modelId]) || $userPrefs[$provider][$modelId] === true;
          }));
        }
      }
    }
  }
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
