<?php
session_start();

// Vérification que l'utilisateur est admin
require_once '../zone_membres/db.php';
require_once '../api/api_keys_helper.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../zone_membres/login.php');
  exit();
}

try {
  $checkAdmin = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
  $checkAdmin->execute([$_SESSION['user_id']]);
  $userData = $checkAdmin->fetch();

  if (!$userData || $userData['is_admin'] != 1) {
    header('Location: ../index.php');
    exit();
  }
} catch (PDOException $ex) {
  header('Location: ../index.php');
  exit();
}

// Gérer les actions AJAX
if (isset($_GET['ajax'])) {
  header('Content-Type: application/json');

  switch ($_GET['ajax']) {
    case 'test_api':
      $provider = $_POST['provider'] ?? '';
      $result = testProviderConnection($provider, $pdo);
      echo json_encode($result);
      exit();

    case 'export_config':
      $config = exportConfiguration($pdo);
      echo json_encode(['success' => true, 'config' => $config]);
      exit();

    case 'bulk_toggle':
      $action = $_POST['action'] ?? '';
      $providers = json_decode($_POST['providers'] ?? '[]', true);
      $result = bulkToggleProviders($action, $providers, $pdo);
      echo json_encode($result);
      exit();
  }

  echo json_encode(['error' => 'Action inconnue']);
  exit();
}

// Fonction pour tester la connexion d'un provider
function testProviderConnection($provider, $pdo)
{
  $apiConfig = getAllApiConfigs($pdo);

  $testUrls = [
    'openai' => 'https://api.openai.com/v1/models',
    'anthropic' => 'https://api.anthropic.com/v1/models',
    'ollama' => ($apiConfig['ollama']['OLLAMA_API_URL'] ?? 'http://localhost:11434') . '/api/tags',
    'gemini' => 'https://generativelanguage.googleapis.com/v1beta/models',
    'deepseek' => 'https://api.deepseek.com/v1/models',
    'mistral' => 'https://api.mistral.ai/v1/models',
    'huggingface' => 'https://api-inference.huggingface.co/models',
    'openrouter' => 'https://openrouter.ai/api/v1/models',
    'perplexity' => 'https://api.perplexity.ai/chat/completions',
    'xai' => 'https://api.x.ai/v1/models',
    'moonshot' => 'https://api.moonshot.cn/v1/models',
    'github' => 'https://api.githubcopilot.com/models',
  ];

  if (!isset($testUrls[$provider])) {
    return ['success' => false, 'message' => 'Provider inconnu'];
  }

  $apiKeys = [
    'openai' => $apiConfig['openai']['OPENAI_API_KEY'] ?? '',
    'anthropic' => $apiConfig['anthropic']['ANTHROPIC_API_KEY'] ?? '',
    'ollama' => $apiConfig['ollama']['OLLAMA_API_KEY'] ?? '',
    'gemini' => $apiConfig['gemini']['GEMINI_API_KEY'] ?? '',
    'deepseek' => $apiConfig['deepseek']['DEEPSEEK_API_KEY'] ?? '',
    'mistral' => $apiConfig['mistral']['MISTRAL_API_KEY'] ?? '',
    'huggingface' => $apiConfig['huggingface']['HUGGINGFACE_API_KEY'] ?? '',
    'openrouter' => $apiConfig['openrouter']['OPENROUTER_API_KEY'] ?? '',
    'perplexity' => $apiConfig['perplexity']['PERPLEXITY_API_KEY'] ?? '',
    'xai' => $apiConfig['xai']['XAI_API_KEY'] ?? '',
    'moonshot' => $apiConfig['moonshot']['MOONSHOT_API_KEY'] ?? '',
    'github' => '', // OAuth based
  ];

  $apiKey = $apiKeys[$provider] ?? '';

  $ch = curl_init($testUrls[$provider]);
  $headers = ['Accept: application/json'];

  if (!empty($apiKey)) {
    if ($provider === 'anthropic') {
      $headers[] = 'x-api-key: ' . $apiKey;
      $headers[] = 'anthropic-version: 2023-06-01';
    } elseif ($provider === 'gemini') {
      curl_setopt($ch, CURLOPT_URL, $testUrls[$provider] . '?key=' . $apiKey);
    } else {
      $headers[] = 'Authorization: Bearer ' . $apiKey;
    }
  }

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false,
  ]);

  $startTime = microtime(true);
  $response = curl_exec($ch);
  $latency = round((microtime(true) - $startTime) * 1000);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  // curl_close() supprimé - deprecated depuis PHP 8.0

  if ($error) {
    return ['success' => false, 'message' => 'Erreur: ' . $error, 'latency' => $latency];
  }

  if ($httpCode >= 200 && $httpCode < 300) {
    return ['success' => true, 'message' => 'Connexion réussie', 'latency' => $latency, 'code' => $httpCode];
  } elseif ($httpCode === 401) {
    return ['success' => false, 'message' => 'Clé API invalide', 'latency' => $latency, 'code' => $httpCode];
  } else {
    return ['success' => false, 'message' => 'Erreur HTTP ' . $httpCode, 'latency' => $latency, 'code' => $httpCode];
  }
}

// Fonction pour exporter la configuration
function exportConfiguration($pdo)
{
  $config = [
    'exported_at' => date('c'),
    'providers' => [],
    'models' => []
  ];

  try {
    $stmt = $pdo->query("SELECT provider, is_enabled FROM provider_status");
    while ($row = $stmt->fetch()) {
      $config['providers'][$row['provider']] = (bool)$row['is_enabled'];
    }

    $stmt = $pdo->query("SELECT provider, model_id, is_enabled FROM models_status");
    while ($row = $stmt->fetch()) {
      if (!isset($config['models'][$row['provider']])) {
        $config['models'][$row['provider']] = [];
      }
      $config['models'][$row['provider']][$row['model_id']] = (bool)$row['is_enabled'];
    }
  } catch (PDOException $e) {
    // Ignorer
  }

  return $config;
}

// Fonction pour activer/désactiver en masse
function bulkToggleProviders($action, $providers, $pdo)
{
  $enabled = ($action === 'enable') ? 1 : 0;
  $count = 0;

  try {
    foreach ($providers as $provider) {
      $stmt = $pdo->prepare("INSERT INTO provider_status (provider, is_enabled) VALUES (?, ?) ON DUPLICATE KEY UPDATE is_enabled = ?");
      $stmt->execute([$provider, $enabled, $enabled]);
      $count++;
    }
    return ['success' => true, 'message' => "$count provider(s) mis à jour"];
  } catch (PDOException $e) {
    return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
  }
}

// Charger la configuration des API depuis la DB (avec fallback vers config.php)
$apiConfig = getAllApiConfigs($pdo);

// Vérifier si les tables existent
$tablesExist = false;
try {
  $tableCheck = $pdo->query("SHOW TABLES LIKE 'api_keys_global'");
  $tablesExist = $tableCheck->rowCount() > 0;
} catch (PDOException $e) {
  $tablesExist = false;
}

// Charger les clés depuis la DB
$dbApiKeys = [];
if ($tablesExist) {
  try {
    $stmt = $pdo->query("SELECT provider, key_name, key_value, is_active FROM api_keys_global");
    while ($row = $stmt->fetch()) {
      if (!isset($dbApiKeys[$row['provider']])) {
        $dbApiKeys[$row['provider']] = [];
      }
      $dbApiKeys[$row['provider']][$row['key_name']] = [
        'value' => decryptValue($row['key_value'], $pdo),
        'active' => $row['is_active']
      ];
    }

    // Charger aussi les settings (URLs, etc.)
    $settingsStmt = $pdo->query("SELECT provider, setting_key, setting_value FROM provider_settings WHERE is_global = 1");
    while ($row = $settingsStmt->fetch()) {
      if (!isset($dbApiKeys[$row['provider']])) {
        $dbApiKeys[$row['provider']] = [];
      }
      $dbApiKeys[$row['provider']][$row['setting_key']] = [
        'value' => $row['setting_value'],
        'active' => true
      ];
    }
  } catch (PDOException $e) {
    // Ignorer les erreurs
  }
}

// Charger les statuts des providers
$providerStatuses = [];
if ($tablesExist) {
  try {
    $stmt = $pdo->query("SELECT provider, is_enabled FROM provider_status");
    while ($row = $stmt->fetch()) {
      $providerStatuses[$row['provider']] = (bool)$row['is_enabled'];
    }
  } catch (PDOException $e) {
    // Ignorer
  }
}

// Charger les statuts des modèles
$modelStatuses = [];
if ($tablesExist) {
  try {
    $stmt = $pdo->query("SELECT provider, model_id, is_enabled FROM models_status");
    while ($row = $stmt->fetch()) {
      if (!isset($modelStatuses[$row['provider']])) {
        $modelStatuses[$row['provider']] = [];
      }
      $modelStatuses[$row['provider']][$row['model_id']] = (bool)$row['is_enabled'];
    }
  } catch (PDOException $e) {
    // Ignorer
  }
}

// Mapping des providers vers leurs icônes
$providerIcons = [
  'openai' => 'openai.svg',
  'anthropic' => 'anthropic.svg',
  'ollama' => 'ollama.svg',
  'gemini' => 'gemini.svg',
  'deepseek' => 'deepseek.svg',
  'mistral' => 'mistral.svg',
  'huggingface' => 'huggingface.svg',
  'openrouter' => 'openrouter.svg',
  'perplexity' => 'perplexity.svg',
  'xai' => 'xai.svg',
  'moonshot' => 'moonshot.svg',
  'github' => 'githubcopilot.svg',
];

// Mapping des providers vers leurs clés API config
$providerApiKeys = [
  'openai' => ['key' => 'OPENAI_API_KEY', 'label' => 'OpenAI API Key', 'url' => 'https://platform.openai.com/api-keys'],
  'anthropic' => ['key' => 'ANTHROPIC_API_KEY', 'label' => 'Anthropic API Key', 'url' => 'https://console.anthropic.com/'],
  'ollama' => ['key' => 'OLLAMA_API_KEY', 'label' => 'Ollama API Key', 'url' => 'https://ollama.com/', 'extra' => ['OLLAMA_API_URL' => 'URL Ollama']],
  'gemini' => ['key' => 'GEMINI_API_KEY', 'label' => 'Google Gemini API Key', 'url' => 'https://makersuite.google.com/app/apikey'],
  'deepseek' => ['key' => 'DEEPSEEK_API_KEY', 'label' => 'DeepSeek API Key', 'url' => 'https://platform.deepseek.com/'],
  'mistral' => ['key' => 'MISTRAL_API_KEY', 'label' => 'Mistral AI API Key', 'url' => 'https://console.mistral.ai/'],
  'huggingface' => ['key' => 'HUGGINGFACE_API_KEY', 'label' => 'Hugging Face Token', 'url' => 'https://huggingface.co/settings/tokens'],
  'openrouter' => ['key' => 'OPENROUTER_API_KEY', 'label' => 'OpenRouter API Key', 'url' => 'https://openrouter.ai/keys'],
  'perplexity' => ['key' => 'PERPLEXITY_API_KEY', 'label' => 'Perplexity API Key', 'url' => 'https://www.perplexity.ai/settings/api'],
  'xai' => ['key' => 'XAI_API_KEY', 'label' => 'xAI (Grok) API Key', 'url' => 'https://console.x.ai/'],
  'moonshot' => ['key' => 'MOONSHOT_API_KEY', 'label' => 'Moonshot API Key', 'url' => 'https://platform.moonshot.cn/'],
  'github' => ['key' => 'GITHUB_TOKEN', 'label' => 'GitHub Token (OAuth)', 'url' => 'https://github.com/settings/tokens', 'oauth' => true],
];

// Récupérer la liste de tous les modèles IA depuis l'API interne
function fetch_models_all()
{
  $sessionId = session_id();
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }

  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

  // Construire le chemin de base en remontant du dossier admin
  $scriptPath = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
  $basePath = dirname(dirname($scriptPath)); // Remonter de /admin à la racine
  $basePath = ($basePath === '\\' || $basePath === '/') ? '' : $basePath;
  $apiPath = $basePath . '/api/models.php?provider=all';
  $url = $scheme . '://' . $host . $apiPath;

  $ch = curl_init($url);
  $headers = ['Accept: application/json'];
  $cookie = 'PHPSESSID=' . $sessionId;
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPGET => true,
    CURLOPT_COOKIE => $cookie,
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  // curl_close() supprimé - deprecated depuis PHP 8.0

  if ($response === false || $httpCode !== 200) {
    return [
      'error' => 'Impossible de récupérer les modèles (' . ($httpCode ?: 'ERR') . ')',
      'details' => $err ?: null
    ];
  }

  $json = json_decode($response, true);
  if (!is_array($json)) {
    return ['error' => 'Réponse invalide du service des modèles'];
  }
  return $json;
}

$modelsData = fetch_models_all();

// Calculer les statistiques
$stats = [
  'total_providers' => 0,
  'active_providers' => 0,
  'total_models' => 0,
  'active_models' => 0,
];

if (!isset($modelsData['error'])) {
  $providers = array_diff(array_keys($modelsData), ['provider', 'timestamp']);
  $stats['total_providers'] = count($providers);

  foreach ($providers as $prov) {
    $hasKey = hasApiKey($prov, $apiConfig, $providerApiKeys);
    $isEnabled = $providerStatuses[$prov] ?? $hasKey;
    if ($isEnabled) $stats['active_providers']++;

    if (isset($modelsData[$prov]['models']) && is_array($modelsData[$prov]['models'])) {
      $modelCount = count($modelsData[$prov]['models']);
      $stats['total_models'] += $modelCount;

      foreach ($modelsData[$prov]['models'] as $m) {
        $modelId = $m['id'] ?? '';
        $isModelEnabled = $modelStatuses[$prov][$modelId] ?? true;
        if ($isModelEnabled && $isEnabled) $stats['active_models']++;
      }
    }
  }
}

// Helper pour masquer une clé API
function maskApiKey($key)
{
  if (empty($key)) return '';
  $len = strlen($key);
  if ($len <= 8) return str_repeat('•', $len);
  return substr($key, 0, 4) . str_repeat('•', min($len - 8, 20)) . substr($key, -4);
}

// Helper pour vérifier si un provider a une clé configurée
function hasApiKey($provider, $apiConfig, $providerApiKeys)
{
  if (!isset($providerApiKeys[$provider])) return false;
  $keyName = $providerApiKeys[$provider]['key'];
  // $apiConfig est maintenant un tableau par provider
  if (isset($apiConfig[$provider][$keyName])) {
    return !empty($apiConfig[$provider][$keyName]);
  }
  return false;
}

// Helper pour obtenir la valeur d'une clé API
function getApiKeyValue($provider, $keyName, $apiConfig, $dbApiKeys)
{
  // Priorité: DB > apiConfig
  if (isset($dbApiKeys[$provider][$keyName]['value'])) {
    return $dbApiKeys[$provider][$keyName]['value'];
  }
  if (isset($apiConfig[$provider][$keyName])) {
    return $apiConfig[$provider][$keyName];
  }
  return '';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <title>Gestion des Modèles - NxtGenAI</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-Light.ttf') format('truetype');
      font-weight: 300;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-Regular.ttf') format('truetype');
      font-weight: 400;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-Medium.ttf') format('truetype');
      font-weight: 500;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-SemiBold.ttf') format('truetype');
      font-weight: 600;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-Bold.ttf') format('truetype');
      font-weight: 700;
    }

    * {
      font-family: 'TikTok Sans', system-ui, sans-serif;
    }

    body {
      background-color: oklch(21% 0.006 285.885);
    }

    ::selection {
      background: #404040;
    }

    /* Toggle Switch */
    .toggle-switch {
      position: relative;
      width: 44px;
      height: 24px;
    }

    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .toggle-slider {
      position: absolute;
      cursor: pointer;
      inset: 0;
      background-color: #404040;
      transition: 0.3s;
      border-radius: 24px;
    }

    .toggle-slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: #9ca3af;
      transition: 0.3s;
      border-radius: 50%;
    }

    input:checked+.toggle-slider {
      background-color: #22c55e;
    }

    input:checked+.toggle-slider:before {
      transform: translateX(20px);
      background-color: white;
    }

    /* Mini toggle for compact view */
    .toggle-switch-sm {
      width: 36px;
      height: 20px;
    }

    .toggle-switch-sm .toggle-slider:before {
      height: 14px;
      width: 14px;
    }

    input:checked+.toggle-slider:before {
      transform: translateX(20px);
    }

    .toggle-switch-sm input:checked+.toggle-slider:before {
      transform: translateX(16px);
    }

    /* Collapse animation */
    .collapse-content {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out, opacity 0.2s ease-out;
      opacity: 0;
    }

    .collapse-content.expanded {
      max-height: 2000px;
      opacity: 1;
    }

    /* Pulse animation for testing */
    @keyframes pulse-dot {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.5;
      }
    }

    .animate-pulse-dot {
      animation: pulse-dot 1s infinite;
    }

    /* Stat card hover */
    .stat-card {
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    /* Stat number animation */
    .stat-card p[id^="stat"] {
      transition: transform 0.15s ease-out, color 0.15s ease-out;
    }

    /* Modal backdrop */
    .modal-backdrop {
      background-color: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(4px);
    }

    /* Scrollbar */
    ::-webkit-scrollbar {
      width: 6px;
    }

    ::-webkit-scrollbar-track {
      background: transparent;
    }

    ::-webkit-scrollbar-thumb {
      background: #404040;
      border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #525252;
    }
  </style>
</head>

<body class="min-h-screen text-neutral-400 overflow-x-hidden">
  <!-- Header -->
  <header class="fixed top-0 left-0 right-0 z-50 bg-[oklch(21%_0.006_285.885)]/90 backdrop-blur-md border-b border-neutral-700/50">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="../index.php" class="flex items-center gap-2.5 text-sm font-medium text-neutral-200 hover:text-white transition-colors">
        <img src="../assets/images/logo.svg" alt="NxtGenAI" class="w-7 h-7" />
        <span class="hidden sm:inline">NxtGenAI</span>
      </a>
      <!-- Navigation Desktop -->
      <nav class="hidden md:flex items-center gap-4">
        <a href="settings.php" class="text-sm text-neutral-400 hover:text-blue-400 transition-colors">
          <i class="fa-solid fa-shield-halved mr-1.5"></i>Admin
        </a>
        <a href="rate_limits.php" class="text-sm text-neutral-400 hover:text-amber-400 transition-colors">
          <i class="fa-solid fa-gauge-high mr-1.5"></i>Rate Limits
        </a>
        <div class="w-px h-4 bg-neutral-700"></div>
        <span class="text-sm text-neutral-400"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="../zone_membres/logout.php" class="text-sm text-neutral-500 hover:text-red-400 transition-colors">
          <i class="fa-solid fa-sign-out-alt"></i>
        </a>
      </nav>
      <!-- Navigation Mobile - Menu hamburger -->
      <div class="md:hidden relative">
        <button onclick="toggleNavMenu()" class="p-2.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-neutral-300 hover:text-white transition-colors">
          <i class="fa-solid fa-bars text-lg"></i>
        </button>
        <div id="navMenu" class="hidden absolute right-0 top-full mt-2 bg-neutral-800 border border-neutral-700 rounded-lg shadow-xl z-50 min-w-[180px] py-1">
          <div class="px-4 py-2 border-b border-neutral-700">
            <span class="text-sm font-medium text-neutral-300"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <a href="settings.php" class="block px-4 py-3 text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors">
            <i class="fa-solid fa-shield-halved text-blue-400 w-5 mr-2"></i>Admin
          </a>
          <a href="rate_limits.php" class="block px-4 py-3 text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors">
            <i class="fa-solid fa-gauge-high text-amber-400 w-5 mr-2"></i>Rate Limits
          </a>
          <a href="../index.php" class="block px-4 py-3 text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors">
            <i class="fa-solid fa-home text-green-400 w-5 mr-2"></i>Accueil
          </a>
          <div class="border-t border-neutral-700 mt-1 pt-1">
            <a href="../zone_membres/logout.php" class="block px-4 py-3 text-sm text-red-400 hover:bg-neutral-700 transition-colors">
              <i class="fa-solid fa-sign-out-alt w-5 mr-2"></i>Déconnexion
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="max-w-5xl mx-auto px-4 pt-20 pb-10">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="stat-card rounded-xl border border-neutral-700/50 bg-neutral-900/50 p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
            <i class="fa-solid fa-server text-blue-400"></i>
          </div>
          <div>
            <p id="statTotalProviders" class="text-2xl font-bold text-neutral-100"><?php echo $stats['total_providers']; ?></p>
            <p class="text-xs text-neutral-500">Providers</p>
          </div>
        </div>
      </div>
      <div class="stat-card rounded-xl border border-neutral-700/50 bg-neutral-900/50 p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
            <i class="fa-solid fa-check-circle text-green-400"></i>
          </div>
          <div>
            <p id="statActiveProviders" class="text-2xl font-bold text-neutral-100"><?php echo $stats['active_providers']; ?></p>
            <p class="text-xs text-neutral-500">Actifs</p>
          </div>
        </div>
      </div>
      <div class="stat-card rounded-xl border border-neutral-700/50 bg-neutral-900/50 p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
            <i class="fa-solid fa-robot text-purple-400"></i>
          </div>
          <div>
            <p id="statTotalModels" class="text-2xl font-bold text-neutral-100"><?php echo $stats['total_models']; ?></p>
            <p class="text-xs text-neutral-500">Modèles</p>
          </div>
        </div>
      </div>
      <div class="stat-card rounded-xl border border-neutral-700/50 bg-neutral-900/50 p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-amber-500/20 flex items-center justify-center">
            <i class="fa-solid fa-bolt text-amber-400"></i>
          </div>
          <div>
            <p id="statActiveModels" class="text-2xl font-bold text-neutral-100"><?php echo $stats['active_models']; ?></p>
            <p class="text-xs text-neutral-500">Disponibles</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 border-b border-neutral-700/50">
      <button id="tabModels" onclick="switchTab('models')" class="px-4 py-3 text-sm font-medium text-green-400 border-b-2 border-green-400 transition-colors">
        <i class="fa-solid fa-robot mr-2"></i>Modèles IA
      </button>
      <button id="tabApi" onclick="switchTab('api')" class="px-4 py-3 text-sm font-medium text-neutral-400 hover:text-neutral-200 border-b-2 border-transparent transition-colors">
        <i class="fa-solid fa-key mr-2"></i>Clés API
      </button>
    </div>

    <!-- Tab: Models -->
    <div id="panelModels">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 class="text-xl font-semibold text-neutral-200">Gestion des modèles</h1>
          <p class="text-sm text-neutral-500 mt-1">Activez ou désactivez les modèles disponibles pour vos utilisateurs.</p>
        </div>
        <div class="flex items-center gap-2">
          <!-- Search -->
          <div class="relative">
            <input type="text" id="searchModels" oninput="filterModels(this.value)" placeholder="Rechercher..." class="w-48 px-3 py-2 pl-9 text-sm bg-neutral-800 border border-neutral-700 rounded-lg text-neutral-200 placeholder-neutral-500 focus:outline-none focus:border-green-500/50" />
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500 text-xs"></i>
          </div>
          <!-- Sort -->
          <select id="sortProviders" onchange="sortProviders(this.value)" class="px-3 py-2 text-sm bg-neutral-800 border border-neutral-700 rounded-lg text-neutral-300 focus:outline-none focus:border-green-500/50">
            <option value="alpha">A-Z</option>
            <option value="models">Par modèles</option>
            <option value="status">Par statut</option>
          </select>
          <!-- Bulk Actions -->
          <div class="relative" id="bulkMenu">
            <button onclick="toggleBulkMenu()" class="px-3 py-2 text-sm bg-neutral-800 hover:bg-neutral-700 text-neutral-300 rounded-lg transition-colors">
              <i class="fa-solid fa-ellipsis-v"></i>
            </button>
            <div id="bulkDropdown" class="hidden absolute right-0 top-full mt-1 w-48 bg-neutral-800 border border-neutral-700 rounded-lg shadow-xl z-10">
              <!-- Actualiser (visible uniquement sur mobile dans ce menu) -->
              <button onclick="refreshModels(); toggleBulkMenu();" class="md:hidden w-full px-4 py-2 text-sm text-left text-neutral-300 hover:bg-neutral-700 flex items-center gap-2">
                <i class="fa-solid fa-refresh text-cyan-400"></i>Actualiser
              </button>
              <hr class="md:hidden border-neutral-700 my-1" />
              <button onclick="bulkToggle('enable')" class="w-full px-4 py-2 text-sm text-left text-neutral-300 hover:bg-neutral-700 flex items-center gap-2">
                <i class="fa-solid fa-toggle-on text-green-400"></i>Tout activer
              </button>
              <button onclick="bulkToggle('disable')" class="w-full px-4 py-2 text-sm text-left text-neutral-300 hover:bg-neutral-700 flex items-center gap-2">
                <i class="fa-solid fa-toggle-off text-red-400"></i>Tout désactiver
              </button>
              <hr class="border-neutral-700 my-1" />
              <button onclick="expandAll()" class="w-full px-4 py-2 text-sm text-left text-neutral-300 hover:bg-neutral-700 flex items-center gap-2">
                <i class="fa-solid fa-expand text-blue-400"></i>Tout déplier
              </button>
              <button onclick="collapseAll()" class="w-full px-4 py-2 text-sm text-left text-neutral-300 hover:bg-neutral-700 flex items-center gap-2">
                <i class="fa-solid fa-compress text-blue-400"></i>Tout replier
              </button>
              <hr class="border-neutral-700 my-1" />
              <button onclick="exportConfig()" class="w-full px-4 py-2 text-sm text-left text-neutral-300 hover:bg-neutral-700 flex items-center gap-2">
                <i class="fa-solid fa-download text-purple-400"></i>Exporter config
              </button>
            </div>
          </div>
          <!-- Refresh (visible uniquement sur desktop, en mobile c'est dans le bulk menu) -->
          <button onclick="refreshModels()" class="hidden md:flex items-center gap-2 px-3 py-2 text-sm bg-neutral-800 hover:bg-neutral-700 text-neutral-300 rounded-lg transition-colors">
            <i class="fa-solid fa-refresh" id="refreshIcon"></i>
            <span>Actualiser</span>
          </button>
        </div>
      </div>

      <?php if (isset($modelsData['error'])): ?>
        <div class="rounded-xl border border-red-500/30 bg-red-500/10 p-5 text-red-300">
          <div class="flex items-center gap-3 mb-2">
            <i class="fa-solid fa-circle-exclamation text-lg"></i>
            <span class="font-medium">Erreur de chargement</span>
          </div>
          <p class="text-sm"><?php echo htmlspecialchars($modelsData['error'], ENT_QUOTES, 'UTF-8'); ?></p>
          <?php if (!empty($modelsData['details'])): ?>
            <p class="text-xs opacity-70 mt-2"><?php echo htmlspecialchars($modelsData['details'], ENT_QUOTES, 'UTF-8'); ?></p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php
        $providers = array_diff(array_keys($modelsData), ['provider', 'timestamp']);
        sort($providers);
        ?>

        <div class="space-y-4" id="providersContainer">
          <?php foreach ($providers as $prov):
            $block = $modelsData[$prov];
            $icon = $providerIcons[$prov] ?? 'openai.svg';
            $hasKey = hasApiKey($prov, $apiConfig, $providerApiKeys);
            $isProviderEnabled = $providerStatuses[$prov] ?? $hasKey;
            $modelCount = isset($block['models']) && is_array($block['models']) ? count($block['models']) : 0;
          ?>
            <section class="provider-section rounded-xl border border-neutral-700/50 bg-neutral-900/50 overflow-hidden" data-provider="<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>" data-models="<?php echo $modelCount; ?>" data-status="<?php echo $isProviderEnabled ? '1' : '0'; ?>">
              <!-- Provider Header -->
              <div class="flex items-center justify-between px-4 py-3 bg-neutral-800/30 border-b border-neutral-700/30 cursor-pointer hover:bg-neutral-800/50 transition-colors" onclick="toggleProviderCollapse('<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>')">
                <div class="flex items-center gap-3">
                  <button class="collapse-btn text-neutral-500 hover:text-neutral-300 transition-colors" data-provider="<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-solid fa-chevron-down transition-transform"></i>
                  </button>
                  <img src="../assets/images/providers/<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>" class="w-6 h-6" onerror="this.src='../assets/images/logo.svg'" />
                  <div>
                    <h2 class="text-base font-medium text-neutral-100 capitalize"><?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <?php if ($hasKey): ?>
                      <span class="text-xs text-green-400"><i class="fa-solid fa-check-circle mr-1"></i>API configurée</span>
                    <?php else: ?>
                      <span class="text-xs text-amber-400"><i class="fa-solid fa-warning mr-1"></i>Clé API manquante</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="flex items-center gap-3" onclick="event.stopPropagation()">
                  <?php if ($modelCount > 0): ?>
                    <span class="text-xs text-neutral-500 bg-neutral-800 px-2 py-1 rounded-full"><?php echo $modelCount; ?> modèle<?php echo $modelCount > 1 ? 's' : ''; ?></span>
                  <?php endif; ?>
                  <!-- Test API Button -->
                  <button onclick="testApi('<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>')" class="p-2 text-neutral-500 hover:text-blue-400 hover:bg-neutral-700/50 rounded-lg transition-colors" title="Tester la connexion">
                    <i class="fa-solid fa-plug" id="testIcon_<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>"></i>
                  </button>
                  <!-- Toggle Provider -->
                  <label class="toggle-switch" title="Activer/Désactiver ce provider">
                    <input type="checkbox" id="providerToggle_<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isProviderEnabled ? 'checked' : ''; ?> onchange="toggleProvider('<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>', this.checked)" />
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>

              <!-- Models List (Collapsible) -->
              <div class="collapse-content expanded p-4" id="models_<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($block['error'])): ?>
                  <div class="flex items-center gap-2 text-sm text-amber-300/80">
                    <i class="fa-solid fa-info-circle"></i>
                    <span><?php echo htmlspecialchars($block['error'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                <?php elseif (empty($block['models'])): ?>
                  <p class="text-sm text-neutral-500 italic">Aucun modèle détecté pour ce provider.</p>
                <?php else: ?>
                  <div class="grid gap-2">
                    <?php foreach ($block['models'] as $idx => $m):
                      $modelId = $m['id'] ?? '';
                      $modelName = $m['name'] ?? $modelId;
                      $modelDesc = $m['description'] ?? '';
                      $modelData = htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8');
                      $isModelEnabled = $modelStatuses[$prov][$modelId] ?? true;
                    ?>
                      <div class="model-item flex items-center justify-between p-3 rounded-lg bg-neutral-800/40 hover:bg-neutral-800/60 transition-colors group" data-model-name="<?php echo htmlspecialchars(strtolower($modelName . ' ' . $modelId), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                          <!-- Toggle Model -->
                          <label class="toggle-switch toggle-switch-sm shrink-0" title="Activer/Désactiver ce modèle">
                            <input type="checkbox" <?php echo $isModelEnabled ? 'checked' : ''; ?> onchange="toggleModel('<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($modelId, ENT_QUOTES, 'UTF-8'); ?>', this.checked)" />
                            <span class="toggle-slider"></span>
                          </label>
                          <div class="min-w-0">
                            <div class="text-sm text-neutral-200 font-medium truncate model-name"><?php echo htmlspecialchars($modelName, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-xs text-neutral-500 truncate model-id"><?php echo htmlspecialchars($modelId, ENT_QUOTES, 'UTF-8'); ?></div>
                          </div>
                        </div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                          <button onclick="copyToClipboard('<?php echo htmlspecialchars($modelId, ENT_QUOTES, 'UTF-8'); ?>')" class="p-2 text-neutral-400 hover:text-green-400 hover:bg-neutral-700/50 rounded-lg transition-colors" title="Copier l'ID">
                            <i class="fa-solid fa-copy"></i>
                          </button>
                          <button onclick='showModelDetails(<?php echo $modelData; ?>, "<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>")' class="p-2 text-neutral-400 hover:text-blue-400 hover:bg-neutral-700/50 rounded-lg transition-colors" title="Voir les détails">
                            <i class="fa-solid fa-info-circle"></i>
                          </button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </section>
          <?php endforeach; ?>
        </div>

        <p class="text-xs text-neutral-600 mt-6 text-center">
          <i class="fa-solid fa-clock mr-1"></i>
          Dernière actualisation: <?php echo htmlspecialchars($modelsData['timestamp'] ?? date('c'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Tab: API Keys -->
    <div id="panelApi" class="hidden">
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-semibold text-neutral-200">Configuration des API</h1>
            <p class="text-sm text-neutral-500 mt-1">Gérez les clés API de chaque provider pour activer les modèles correspondants.</p>
          </div>
          <?php if (!$tablesExist): ?>
            <a href="../database/api_keys_migration.sql" target="_blank" class="px-3 py-2 text-sm bg-amber-600 hover:bg-amber-500 text-white rounded-lg transition-colors">
              <i class="fa-solid fa-database mr-2"></i>Exécuter migration DB
            </a>
          <?php else: ?>
            <button onclick="importFromConfig()" class="px-3 py-2 text-sm bg-neutral-700 hover:bg-neutral-600 text-neutral-200 rounded-lg transition-colors">
              <i class="fa-solid fa-file-import mr-2"></i>Importer depuis config.php
            </button>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$tablesExist): ?>
        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 mb-6">
          <div class="flex gap-3">
            <i class="fa-solid fa-warning text-amber-400 mt-0.5"></i>
            <div>
              <p class="text-sm text-amber-300 font-medium">Tables non créées</p>
              <p class="text-xs text-amber-300/70 mt-1">Exécutez le fichier <code class="bg-amber-500/20 px-1 rounded">database/api_keys_migration.sql</code> dans phpMyAdmin pour activer le stockage sécurisé des clés API.</p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="space-y-4">
        <?php foreach ($providerApiKeys as $prov => $config):
          $icon = $providerIcons[$prov] ?? 'openai.svg';
          $keyName = $config['key'];
          $currentValue = getApiKeyValue($prov, $keyName, $apiConfig, $dbApiKeys);
          $masked = maskApiKey($currentValue);
          $isConfigured = !empty($currentValue);
        ?>
          <div class="rounded-xl border border-neutral-700/50 bg-neutral-900/50 p-4">
            <div class="flex items-start gap-4">
              <img src="../assets/images/providers/<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>" class="w-8 h-8 mt-1" onerror="this.src='../assets/images/logo.svg'" />
              <div class="flex-1">
                <div class="flex items-center justify-between mb-2">
                  <div>
                    <h3 class="text-base font-medium text-neutral-200 capitalize"><?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-xs text-neutral-500"><?php echo htmlspecialchars($config['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                  </div>
                  <a href="<?php echo htmlspecialchars($config['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">
                    <i class="fa-solid fa-external-link mr-1"></i>Obtenir une clé
                  </a>
                </div>

                <div class="flex gap-2">
                  <div class="relative flex-1">
                    <input type="password" id="api_<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>" data-key-name="<?php echo htmlspecialchars($keyName, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($currentValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Entrez votre clé API..." class="w-full px-3 py-2 pr-10 text-sm bg-neutral-800 border border-neutral-700 rounded-lg text-neutral-200 placeholder-neutral-500 focus:outline-none focus:border-green-500/50 focus:ring-1 focus:ring-green-500/30" />
                    <button onclick="togglePasswordVisibility('api_<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-500 hover:text-neutral-300">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                  </div>
                  <button onclick="testApiFromKey('<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>')" class="px-3 py-2 text-sm bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-colors" title="Tester la connexion">
                    <i class="fa-solid fa-plug" id="testKeyIcon_<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>"></i>
                  </button>
                  <button onclick="saveApiKey('<?php echo htmlspecialchars($prov, ENT_QUOTES, 'UTF-8'); ?>')" class="px-4 py-2 text-sm bg-green-600 hover:bg-green-500 text-white rounded-lg transition-colors" <?php echo !$tablesExist ? 'disabled title="Exécutez d\'abord la migration DB"' : ''; ?>>
                    <i class="fa-solid fa-save"></i>
                  </button>
                </div>

                <?php if ($isConfigured): ?>
                  <div class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center text-xs text-green-400">
                      <i class="fa-solid fa-check-circle mr-1"></i>Configurée
                    </span>
                    <span class="text-xs text-neutral-600">•</span>
                    <span class="text-xs text-neutral-500 font-mono"><?php echo htmlspecialchars($masked, ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                <?php else: ?>
                  <p class="mt-2 text-xs text-amber-400">
                    <i class="fa-solid fa-warning mr-1"></i>Non configurée
                  </p>
                <?php endif; ?>

                <?php if (isset($config['extra'])): ?>
                  <?php foreach ($config['extra'] as $extraKey => $extraLabel):
                    $extraValue = getApiKeyValue($prov, $extraKey, $apiConfig, $dbApiKeys);
                  ?>
                    <div class="mt-3">
                      <label class="text-xs text-neutral-500 mb-1 block"><?php echo htmlspecialchars($extraLabel, ENT_QUOTES, 'UTF-8'); ?></label>
                      <input type="text" id="api_<?php echo htmlspecialchars($prov . '_' . strtolower($extraKey), ENT_QUOTES, 'UTF-8'); ?>" data-setting-key="<?php echo htmlspecialchars($extraKey, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($extraValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://..." class="w-full px-3 py-2 text-sm bg-neutral-800 border border-neutral-700 rounded-lg text-neutral-200 placeholder-neutral-500 focus:outline-none focus:border-green-500/50" />
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-6 p-4 rounded-xl bg-blue-500/10 border border-blue-500/20">
        <div class="flex gap-3">
          <i class="fa-solid fa-info-circle text-blue-400 mt-0.5"></i>
          <div>
            <p class="text-sm text-blue-300 font-medium">Sécurité des clés API</p>
            <p class="text-xs text-blue-300/70 mt-1">Les clés API sont chiffrées avec AES-256-CBC avant d'être stockées dans la base de données. Elles ne sont jamais stockées en clair.</p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <!-- Modal: Model Details -->
  <div id="modelModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop absolute inset-0" onclick="closeModal()"></div>
    <div class="absolute inset-4 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:w-full md:max-w-lg">
      <div class="bg-neutral-900 border border-neutral-700 rounded-2xl shadow-2xl overflow-hidden h-full md:h-auto">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-700/50 bg-neutral-800/30">
          <div class="flex items-center gap-3">
            <img id="modalProviderIcon" src="../assets/images/logo.svg" alt="" class="w-6 h-6" />
            <h3 id="modalTitle" class="text-lg font-semibold text-neutral-200">Détails du modèle</h3>
          </div>
          <button onclick="closeModal()" class="p-2 text-neutral-400 hover:text-white hover:bg-neutral-700/50 rounded-lg transition-colors">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 space-y-4 max-h-[60vh] overflow-y-auto">
          <div>
            <label class="text-xs text-neutral-500 uppercase tracking-wide">Nom</label>
            <p id="modalName" class="text-neutral-200 font-medium mt-1">-</p>
          </div>
          <div>
            <label class="text-xs text-neutral-500 uppercase tracking-wide">ID du modèle</label>
            <p id="modalId" class="text-neutral-300 font-mono text-sm mt-1 bg-neutral-800 px-3 py-2 rounded-lg break-all">-</p>
          </div>
          <div id="modalDescContainer" class="hidden">
            <label class="text-xs text-neutral-500 uppercase tracking-wide">Description</label>
            <p id="modalDesc" class="text-neutral-400 text-sm mt-1">-</p>
          </div>
          <div id="modalExtraInfo" class="space-y-3">
            <!-- Dynamic extra info -->
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end gap-3 px-5 py-4 border-t border-neutral-700/50 bg-neutral-800/20">
          <button onclick="closeModal()" class="px-4 py-2 text-sm text-neutral-400 hover:text-white hover:bg-neutral-700/50 rounded-lg transition-colors">
            Fermer
          </button>
          <button onclick="copyModelId()" class="px-4 py-2 text-sm bg-green-600 hover:bg-green-500 text-white rounded-lg transition-colors">
            <i class="fa-solid fa-copy mr-2"></i>Copier l'ID
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div id="toast" class="fixed bottom-4 right-4 z-50 transform translate-y-20 opacity-0 transition-all duration-300">
    <div class="flex items-center gap-3 px-4 py-3 bg-neutral-800 border border-neutral-700 rounded-xl shadow-lg">
      <i id="toastIcon" class="fa-solid fa-check-circle text-green-400"></i>
      <span id="toastMessage" class="text-sm text-neutral-200">Action effectuée</span>
    </div>
  </div>

  <script>
    // Provider icons mapping
    const providerIcons = <?php echo json_encode($providerIcons); ?>;
    const allProviders = <?php echo json_encode(array_diff(array_keys($modelsData ?? []), ['provider', 'timestamp'])); ?>;

    // Current modal model data
    let currentModalModel = null;

    // Tab switching
    function switchTab(tab) {
      const tabModels = document.getElementById('tabModels');
      const tabApi = document.getElementById('tabApi');
      const panelModels = document.getElementById('panelModels');
      const panelApi = document.getElementById('panelApi');

      if (tab === 'models') {
        tabModels.className = 'px-4 py-3 text-sm font-medium text-green-400 border-b-2 border-green-400 transition-colors';
        tabApi.className = 'px-4 py-3 text-sm font-medium text-neutral-400 hover:text-neutral-200 border-b-2 border-transparent transition-colors';
        panelModels.classList.remove('hidden');
        panelApi.classList.add('hidden');
      } else {
        tabApi.className = 'px-4 py-3 text-sm font-medium text-green-400 border-b-2 border-green-400 transition-colors';
        tabModels.className = 'px-4 py-3 text-sm font-medium text-neutral-400 hover:text-neutral-200 border-b-2 border-transparent transition-colors';
        panelApi.classList.remove('hidden');
        panelModels.classList.add('hidden');
      }
    }

    // Filter models by search
    function filterModels(query) {
      const q = query.toLowerCase().trim();
      const sections = document.querySelectorAll('.provider-section');

      sections.forEach(section => {
        const provider = section.dataset.provider.toLowerCase();
        const models = section.querySelectorAll('.model-item');
        let visibleCount = 0;

        if (!q) {
          section.style.display = '';
          models.forEach(m => m.style.display = '');
          return;
        }

        // Check if provider name matches
        if (provider.includes(q)) {
          section.style.display = '';
          models.forEach(m => m.style.display = '');
          return;
        }

        // Check individual models
        models.forEach(model => {
          const modelName = model.dataset.modelName || '';
          if (modelName.includes(q)) {
            model.style.display = '';
            visibleCount++;
          } else {
            model.style.display = 'none';
          }
        });

        section.style.display = visibleCount > 0 ? '' : 'none';
      });
    }

    // Sort providers
    function sortProviders(sortBy) {
      const container = document.getElementById('providersContainer');
      const sections = Array.from(container.querySelectorAll('.provider-section'));

      sections.sort((a, b) => {
        switch (sortBy) {
          case 'models':
            return parseInt(b.dataset.models) - parseInt(a.dataset.models);
          case 'status':
            return parseInt(b.dataset.status) - parseInt(a.dataset.status);
          case 'alpha':
          default:
            return a.dataset.provider.localeCompare(b.dataset.provider);
        }
      });

      sections.forEach(section => container.appendChild(section));
    }

    // Toggle bulk menu
    function toggleBulkMenu() {
      const dropdown = document.getElementById('bulkDropdown');
      dropdown.classList.toggle('hidden');
    }

    // Close bulk menu when clicking outside
    document.addEventListener('click', (e) => {
      const menu = document.getElementById('bulkMenu');
      if (!menu.contains(e.target)) {
        document.getElementById('bulkDropdown').classList.add('hidden');
      }
    });

    // Bulk toggle providers
    async function bulkToggle(action) {
      document.getElementById('bulkDropdown').classList.add('hidden');

      const formData = new FormData();
      formData.append('action', action);
      formData.append('providers', JSON.stringify(allProviders));

      try {
        const response = await fetch('?ajax=bulk_toggle', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();

        if (data.success) {
          showToast(data.message, 'success');
          // Update all toggles visually
          document.querySelectorAll('[id^="providerToggle_"]').forEach(toggle => {
            toggle.checked = (action === 'enable');
          });
          // Update data-status attribute
          document.querySelectorAll('.provider-section').forEach(section => {
            section.dataset.status = (action === 'enable') ? '1' : '0';
          });
          updateStats(); // Mise à jour dynamique des stats
        } else {
          showToast(data.message || 'Erreur', 'error');
        }
      } catch (err) {
        console.error(err);
        showToast('Erreur de connexion', 'error');
      }
    }

    // Expand all providers
    function expandAll() {
      document.getElementById('bulkDropdown').classList.add('hidden');
      document.querySelectorAll('.collapse-content').forEach(el => {
        el.classList.add('expanded');
      });
      document.querySelectorAll('.collapse-btn i').forEach(icon => {
        icon.style.transform = 'rotate(0deg)';
      });
    }

    // Collapse all providers
    function collapseAll() {
      document.getElementById('bulkDropdown').classList.add('hidden');
      document.querySelectorAll('.collapse-content').forEach(el => {
        el.classList.remove('expanded');
      });
      document.querySelectorAll('.collapse-btn i').forEach(icon => {
        icon.style.transform = 'rotate(-90deg)';
      });
    }

    // Toggle provider collapse
    function toggleProviderCollapse(provider) {
      const content = document.getElementById('models_' + provider);
      const btn = document.querySelector(`.collapse-btn[data-provider="${provider}"] i`);

      if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        btn.style.transform = 'rotate(-90deg)';
      } else {
        content.classList.add('expanded');
        btn.style.transform = 'rotate(0deg)';
      }
    }

    // Test API connection
    async function testApi(provider) {
      const icon = document.getElementById('testIcon_' + provider);
      icon.className = 'fa-solid fa-spinner animate-spin';

      const formData = new FormData();
      formData.append('provider', provider);

      try {
        const response = await fetch('?ajax=test_api', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();

        if (data.success) {
          icon.className = 'fa-solid fa-check text-green-400';
          showToast(`${provider}: ${data.message} (${data.latency}ms)`, 'success');
        } else {
          icon.className = 'fa-solid fa-times text-red-400';
          showToast(`${provider}: ${data.message}`, 'error');
        }

        // Reset icon after 3 seconds
        setTimeout(() => {
          icon.className = 'fa-solid fa-plug';
        }, 3000);
      } catch (err) {
        console.error(err);
        icon.className = 'fa-solid fa-times text-red-400';
        showToast('Erreur de connexion', 'error');
      }
    }

    // Export configuration
    async function exportConfig() {
      document.getElementById('bulkDropdown').classList.add('hidden');

      try {
        const response = await fetch('?ajax=export_config');
        const data = await response.json();

        if (data.success) {
          const blob = new Blob([JSON.stringify(data.config, null, 2)], {
            type: 'application/json'
          });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `nxtgenai_config_${new Date().toISOString().split('T')[0]}.json`;
          a.click();
          URL.revokeObjectURL(url);
          showToast('Configuration exportée', 'success');
        }
      } catch (err) {
        console.error(err);
        showToast('Erreur lors de l\'export', 'error');
      }
    }

    // Copy to clipboard
    function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
        showToast('ID copié', 'success');
      });
    }

    // Update statistics dynamically
    function updateStats() {
      const sections = document.querySelectorAll('.provider-section');
      let totalProviders = sections.length;
      let activeProviders = 0;
      let totalModels = 0;
      let activeModels = 0;

      sections.forEach(section => {
        const providerToggle = section.querySelector('[id^="providerToggle_"]');
        const isProviderActive = providerToggle ? providerToggle.checked : false;

        if (isProviderActive) {
          activeProviders++;
        }

        const modelToggles = section.querySelectorAll('.model-item input[type="checkbox"]');
        totalModels += modelToggles.length;

        modelToggles.forEach(toggle => {
          if (toggle.checked && isProviderActive) {
            activeModels++;
          }
        });
      });

      // Update DOM with animation
      animateNumber('statTotalProviders', totalProviders);
      animateNumber('statActiveProviders', activeProviders);
      animateNumber('statTotalModels', totalModels);
      animateNumber('statActiveModels', activeModels);
    }

    // Animate number change
    function animateNumber(elementId, newValue) {
      const el = document.getElementById(elementId);
      if (!el) return;

      const currentValue = parseInt(el.textContent) || 0;
      if (currentValue === newValue) return;

      // Quick flash animation
      el.style.transform = 'scale(1.2)';
      el.style.color = newValue > currentValue ? '#22c55e' : '#ef4444';

      setTimeout(() => {
        el.textContent = newValue;
        el.style.transform = 'scale(1)';
        el.style.color = '';
      }, 150);
    }

    // Toggle provider - sauvegarde en DB
    async function toggleProvider(provider, enabled) {
      // Update visual state immediately
      const section = document.querySelector(`.provider-section[data-provider="${provider}"]`);
      if (section) {
        section.dataset.status = enabled ? '1' : '0';
      }

      try {
        const response = await fetch('../api/api_keys.php?action=toggle_provider', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            provider,
            enabled
          })
        });
        const data = await response.json();
        if (data.success) {
          showToast(enabled ? `Provider ${provider} activé` : `Provider ${provider} désactivé`, enabled ? 'success' : 'warning');
          updateStats(); // Mise à jour dynamique des stats
        } else {
          showToast(data.error || 'Erreur lors de la mise à jour', 'error');
          // Revert toggle on error
          const toggle = document.getElementById('providerToggle_' + provider);
          if (toggle) toggle.checked = !enabled;
        }
      } catch (err) {
        console.error(err);
        showToast('Erreur de connexion', 'error');
      }
    }

    // Toggle model - sauvegarde en DB
    async function toggleModel(provider, modelId, enabled) {
      try {
        const response = await fetch('../api/api_keys.php?action=toggle_model', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            provider,
            model_id: modelId,
            enabled
          })
        });
        const data = await response.json();
        if (data.success) {
          showToast(enabled ? 'Modèle activé' : 'Modèle désactivé', enabled ? 'success' : 'warning');
          updateStats(); // Mise à jour dynamique des stats
        } else {
          showToast(data.error || 'Erreur lors de la mise à jour', 'error');
        }
      } catch (err) {
        console.error(err);
        showToast('Erreur de connexion', 'error');
      }
    }

    // Show model details modal
    function showModelDetails(model, provider) {
      currentModalModel = model;
      const modal = document.getElementById('modelModal');
      const icon = providerIcons[provider] || 'openai.svg';

      document.getElementById('modalProviderIcon').src = `../assets/images/providers/${icon}`;
      document.getElementById('modalTitle').textContent = model.name || model.id || 'Modèle';
      document.getElementById('modalName').textContent = model.name || model.id || '-';
      document.getElementById('modalId').textContent = model.id || '-';

      // Description
      const descContainer = document.getElementById('modalDescContainer');
      if (model.description) {
        document.getElementById('modalDesc').textContent = model.description;
        descContainer.classList.remove('hidden');
      } else {
        descContainer.classList.add('hidden');
      }

      // Extra info
      const extraInfo = document.getElementById('modalExtraInfo');
      extraInfo.innerHTML = '';

      const excludeKeys = ['id', 'name', 'description'];
      for (const [key, value] of Object.entries(model)) {
        if (!excludeKeys.includes(key) && value !== null && value !== undefined) {
          const div = document.createElement('div');
          let displayValue = value;
          if (typeof value === 'object') {
            displayValue = JSON.stringify(value, null, 2);
          }
          div.innerHTML = `
            <label class="text-xs text-neutral-500 uppercase tracking-wide">${key.replace(/_/g, ' ')}</label>
            <p class="text-neutral-300 text-sm mt-1 bg-neutral-800 px-3 py-2 rounded-lg break-all">${escapeHtml(String(displayValue))}</p>
          `;
          extraInfo.appendChild(div);
        }
      }

      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }

    // Close modal
    function closeModal() {
      document.getElementById('modelModal').classList.add('hidden');
      document.body.style.overflow = '';
      currentModalModel = null;
    }

    // Copy model ID
    function copyModelId() {
      if (currentModalModel && currentModalModel.id) {
        navigator.clipboard.writeText(currentModalModel.id).then(() => {
          showToast('ID copié dans le presse-papiers', 'success');
        });
      }
    }

    // Refresh models
    function refreshModels() {
      const icon = document.getElementById('refreshIcon');
      icon.classList.add('animate-spin');
      location.reload();
    }

    // Test API from key input
    async function testApiFromKey(provider) {
      const icon = document.getElementById('testKeyIcon_' + provider);
      icon.className = 'fa-solid fa-spinner animate-spin';

      const formData = new FormData();
      formData.append('provider', provider);

      try {
        const response = await fetch('?ajax=test_api', {
          method: 'POST',
          body: formData
        });
        const data = await response.json();

        if (data.success) {
          icon.className = 'fa-solid fa-check';
          showToast(`${provider}: Connexion réussie (${data.latency}ms)`, 'success');
        } else {
          icon.className = 'fa-solid fa-times';
          showToast(`${provider}: ${data.message}`, 'error');
        }

        setTimeout(() => {
          icon.className = 'fa-solid fa-plug';
        }, 3000);
      } catch (err) {
        console.error(err);
        icon.className = 'fa-solid fa-times';
        showToast('Erreur de test', 'error');
      }
    }

    // Toggle password visibility
    function togglePasswordVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      const icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-solid fa-eye-slash';
      } else {
        input.type = 'password';
        icon.className = 'fa-solid fa-eye';
      }
    }

    // Save API key - sauvegarde en DB avec chiffrement
    async function saveApiKey(provider) {
      const input = document.getElementById(`api_${provider}`);
      const keyName = input.dataset.keyName;
      const keyValue = input.value.trim();

      // Collecter les settings supplémentaires (ex: OLLAMA_API_URL)
      const settings = {};
      const extraInputs = document.querySelectorAll(`[id^="api_${provider}_"][data-setting-key]`);
      extraInputs.forEach(extraInput => {
        const settingKey = extraInput.dataset.settingKey;
        if (settingKey && extraInput.value.trim()) {
          settings[settingKey] = extraInput.value.trim();
        }
      });

      try {
        const response = await fetch('../api/api_keys.php?action=save&scope=global', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            provider: provider,
            key_name: keyName,
            key_value: keyValue,
            settings: settings
          })
        });

        const data = await response.json();

        if (data.success) {
          showToast(`Clé API ${provider} sauvegardée`, 'success');
          // Actualiser après un court délai
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(data.error || 'Erreur lors de la sauvegarde', 'error');
        }
      } catch (err) {
        console.error(err);
        showToast('Erreur de connexion au serveur', 'error');
      }
    }

    // Importer les clés depuis config.php vers la DB
    async function importFromConfig() {
      if (!confirm('Voulez-vous importer les clés API depuis config.php vers la base de données ?')) {
        return;
      }

      try {
        const response = await fetch('../api/api_keys.php?action=init_from_config', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          }
        });

        const data = await response.json();

        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(data.error || 'Erreur lors de l\'import', 'error');
        }
      } catch (err) {
        console.error(err);
        showToast('Erreur de connexion', 'error');
      }
    }

    // Show toast notification
    function showToast(message, type = 'success') {
      const toast = document.getElementById('toast');
      const icon = document.getElementById('toastIcon');
      const msg = document.getElementById('toastMessage');

      msg.textContent = message;

      switch (type) {
        case 'success':
          icon.className = 'fa-solid fa-check-circle text-green-400';
          break;
        case 'warning':
          icon.className = 'fa-solid fa-warning text-amber-400';
          break;
        case 'error':
          icon.className = 'fa-solid fa-times-circle text-red-400';
          break;
        case 'info':
          icon.className = 'fa-solid fa-info-circle text-blue-400';
          break;
      }

      toast.classList.remove('translate-y-20', 'opacity-0');
      toast.classList.add('translate-y-0', 'opacity-100');

      setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
        toast.classList.remove('translate-y-0', 'opacity-100');
      }, 3000);
    }

    // Escape HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeModal();
        closeNavMenu();
      }
    });

    // Navigation mobile menu
    function toggleNavMenu() {
      const menu = document.getElementById('navMenu');
      menu.classList.toggle('hidden');
    }

    function closeNavMenu() {
      const menu = document.getElementById('navMenu');
      if (menu) menu.classList.add('hidden');
    }

    // Fermer les menus au clic extérieur
    document.addEventListener('click', function(e) {
      if (!e.target.closest('[onclick*="toggleNavMenu"]') && !e.target.closest('#navMenu')) {
        closeNavMenu();
      }
    });

    // Initialize toggle states from localStorage
    document.addEventListener('DOMContentLoaded', () => {
      const providerState = JSON.parse(localStorage.getItem('nxtgenai_providers') || '{}');
      const modelState = JSON.parse(localStorage.getItem('nxtgenai_models') || '{}');

      // Apply saved provider states
      // (States are initialized on page load based on API key presence)
    });
  </script>
</body>

</html>