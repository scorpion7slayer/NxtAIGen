<?php

/**
 * API Endpoint pour la gestion des clés API
 * 
 * Endpoints:
 * - GET    ?action=list&scope=global         Liste les clés API globales (admin)
 * - GET    ?action=list&scope=user           Liste les clés API de l'utilisateur
 * - POST   ?action=save&scope=global         Sauvegarde une clé API globale (admin)
 * - POST   ?action=save&scope=user           Sauvegarde une clé API utilisateur
 * - POST   ?action=delete&scope=global       Supprime une clé API globale (admin)
 * - POST   ?action=delete&scope=user         Supprime une clé API utilisateur
 * - POST   ?action=toggle_provider           Active/désactive un provider (admin)
 * - POST   ?action=toggle_model              Active/désactive un modèle (admin)
 * - GET    ?action=get_config                Récupère la config pour un provider
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../zone_membres/db.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Non authentifié']);
  exit();
}

$userId = $_SESSION['user_id'];

// Vérifier si l'utilisateur est admin
function isAdmin($pdo, $userId)
{
  $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch();
  return ($user && $user['is_admin'] == 1);
}

$isAdminUser = isAdmin($pdo, $userId);

// =====================================================
// Fonctions de chiffrement AES-256-CBC
// =====================================================

function getEncryptionKey($pdo)
{
  static $key = null;
  if ($key === null) {
    $stmt = $pdo->query("SELECT config_value FROM encryption_config WHERE config_key = 'encryption_key'");
    $row = $stmt->fetch();
    if ($row) {
      $key = base64_decode($row['config_value']);
    } else {
      // Générer une nouvelle clé si elle n'existe pas
      $key = random_bytes(32);
      $stmt = $pdo->prepare("INSERT INTO encryption_config (config_key, config_value) VALUES ('encryption_key', ?)");
      $stmt->execute([base64_encode($key)]);
    }
  }
  return $key;
}

function encryptApiKey($plaintext, $pdo)
{
  $key = getEncryptionKey($pdo);
  $iv = random_bytes(16);
  $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  return base64_encode($iv . $encrypted);
}

function decryptApiKey($ciphertext, $pdo)
{
  $key = getEncryptionKey($pdo);
  $data = base64_decode($ciphertext);
  if (strlen($data) < 16) return '';
  $iv = substr($data, 0, 16);
  $encrypted = substr($data, 16);
  $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  return $decrypted !== false ? $decrypted : '';
}

function maskApiKey($key)
{
  if (empty($key)) return '';
  $len = strlen($key);
  if ($len <= 8) return str_repeat('•', $len);
  return substr($key, 0, 4) . str_repeat('•', min($len - 8, 20)) . substr($key, -4);
}

// =====================================================
// Actions
// =====================================================

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$scope = $_GET['scope'] ?? $_POST['scope'] ?? 'global';

try {
  switch ($action) {
    case 'list':
      handleList($pdo, $userId, $isAdminUser, $scope);
      break;

    case 'save':
      handleSave($pdo, $userId, $isAdminUser, $scope);
      break;

    case 'delete':
      handleDelete($pdo, $userId, $isAdminUser, $scope);
      break;

    case 'toggle_provider':
      handleToggleProvider($pdo, $userId, $isAdminUser);
      break;

    case 'toggle_model':
      handleToggleModel($pdo, $userId, $isAdminUser);
      break;

    case 'get_config':
      handleGetConfig($pdo, $userId, $isAdminUser);
      break;

    case 'init_from_config':
      handleInitFromConfig($pdo, $userId, $isAdminUser);
      break;

    default:
      http_response_code(400);
      echo json_encode(['error' => 'Action non reconnue']);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}

// =====================================================
// Handlers
// =====================================================

function handleList($pdo, $userId, $isAdminUser, $scope)
{
  if ($scope === 'global') {
    if (!$isAdminUser) {
      http_response_code(403);
      echo json_encode(['error' => 'Accès réservé aux administrateurs']);
      return;
    }

    $stmt = $pdo->query("SELECT id, provider, key_name, key_value, is_active, created_at, updated_at FROM api_keys_global ORDER BY provider, key_name");
    $keys = $stmt->fetchAll();

    // Masquer les clés
    foreach ($keys as &$key) {
      $decrypted = decryptApiKey($key['key_value'], $pdo);
      $key['key_masked'] = maskApiKey($decrypted);
      $key['has_value'] = !empty($decrypted);
      unset($key['key_value']); // Ne jamais renvoyer la valeur chiffrée
    }

    // Récupérer les settings supplémentaires
    $settingsStmt = $pdo->query("SELECT provider, setting_key, setting_value FROM provider_settings WHERE is_global = 1");
    $settings = [];
    while ($row = $settingsStmt->fetch()) {
      if (!isset($settings[$row['provider']])) {
        $settings[$row['provider']] = [];
      }
      $settings[$row['provider']][$row['setting_key']] = $row['setting_value'];
    }

    echo json_encode(['success' => true, 'keys' => $keys, 'settings' => $settings]);
  } else {
    // Clés utilisateur
    $stmt = $pdo->prepare("SELECT id, provider, key_name, key_value, is_active, created_at, updated_at FROM api_keys_user WHERE user_id = ? ORDER BY provider, key_name");
    $stmt->execute([$userId]);
    $keys = $stmt->fetchAll();

    foreach ($keys as &$key) {
      $decrypted = decryptApiKey($key['key_value'], $pdo);
      $key['key_masked'] = maskApiKey($decrypted);
      $key['has_value'] = !empty($decrypted);
      unset($key['key_value']);
    }

    // Récupérer les settings utilisateur
    $settingsStmt = $pdo->prepare("SELECT provider, setting_key, setting_value FROM provider_settings WHERE is_global = 0 AND user_id = ?");
    $settingsStmt->execute([$userId]);
    $settings = [];
    while ($row = $settingsStmt->fetch()) {
      if (!isset($settings[$row['provider']])) {
        $settings[$row['provider']] = [];
      }
      $settings[$row['provider']][$row['setting_key']] = $row['setting_value'];
    }

    echo json_encode(['success' => true, 'keys' => $keys, 'settings' => $settings]);
  }
}

function handleSave($pdo, $userId, $isAdminUser, $scope)
{
  $input = json_decode(file_get_contents('php://input'), true);

  $provider = $input['provider'] ?? '';
  $keyName = $input['key_name'] ?? '';
  $keyValue = $input['key_value'] ?? '';
  $extraSettings = $input['settings'] ?? [];

  if (empty($provider) || empty($keyName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provider et key_name requis']);
    return;
  }

  if ($scope === 'global') {
    if (!$isAdminUser) {
      http_response_code(403);
      echo json_encode(['error' => 'Accès réservé aux administrateurs']);
      return;
    }

    // Chiffrer la clé
    $encryptedKey = !empty($keyValue) ? encryptApiKey($keyValue, $pdo) : '';

    // Upsert la clé API
    $stmt = $pdo->prepare("
            INSERT INTO api_keys_global (provider, key_name, key_value, is_active, created_by, updated_by)
            VALUES (?, ?, ?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE 
                key_value = VALUES(key_value),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP
        ");
    $stmt->execute([$provider, $keyName, $encryptedKey, $userId, $userId]);

    // Sauvegarder les settings supplémentaires (DELETE + INSERT car NULL != NULL en MySQL)
    foreach ($extraSettings as $settingKey => $settingValue) {
      // Supprimer l'ancien setting global s'il existe
      $deleteStmt = $pdo->prepare("DELETE FROM provider_settings WHERE provider = ? AND setting_key = ? AND is_global = 1");
      $deleteStmt->execute([$provider, $settingKey]);

      // Insérer le nouveau
      $settingStmt = $pdo->prepare("
                INSERT INTO provider_settings (provider, setting_key, setting_value, is_global, user_id)
                VALUES (?, ?, ?, 1, NULL)
            ");
      $settingStmt->execute([$provider, $settingKey, $settingValue]);
    }

    echo json_encode(['success' => true, 'message' => 'Clé API sauvegardée', 'masked' => maskApiKey($keyValue)]);
  } else {
    // Clé utilisateur
    $encryptedKey = !empty($keyValue) ? encryptApiKey($keyValue, $pdo) : '';

    $stmt = $pdo->prepare("
            INSERT INTO api_keys_user (user_id, provider, key_name, key_value, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                key_value = VALUES(key_value),
                updated_at = CURRENT_TIMESTAMP
        ");
    $stmt->execute([$userId, $provider, $keyName, $encryptedKey]);

    // Sauvegarder les settings utilisateur
    foreach ($extraSettings as $settingKey => $settingValue) {
      $settingStmt = $pdo->prepare("
                INSERT INTO provider_settings (provider, setting_key, setting_value, is_global, user_id)
                VALUES (?, ?, ?, 0, ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP
            ");
      $settingStmt->execute([$provider, $settingKey, $settingValue, $userId]);
    }

    echo json_encode(['success' => true, 'message' => 'Clé API personnelle sauvegardée', 'masked' => maskApiKey($keyValue)]);
  }
}

function handleDelete($pdo, $userId, $isAdminUser, $scope)
{
  $input = json_decode(file_get_contents('php://input'), true);

  $provider = $input['provider'] ?? '';
  $keyName = $input['key_name'] ?? '';
  $deleteSettings = $input['delete_settings'] ?? false;

  if (empty($provider) || empty($keyName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provider et key_name requis']);
    return;
  }

  if ($scope === 'global') {
    if (!$isAdminUser) {
      http_response_code(403);
      echo json_encode(['error' => 'Accès réservé aux administrateurs']);
      return;
    }

    $stmt = $pdo->prepare("DELETE FROM api_keys_global WHERE provider = ? AND key_name = ?");
    $stmt->execute([$provider, $keyName]);

    // Supprimer aussi les settings si demandé
    if ($deleteSettings) {
      $settingsStmt = $pdo->prepare("DELETE FROM provider_settings WHERE provider = ? AND is_global = 1");
      $settingsStmt->execute([$provider]);
    }

    echo json_encode(['success' => true, 'message' => 'Clé API supprimée']);
  } else {
    $stmt = $pdo->prepare("DELETE FROM api_keys_user WHERE user_id = ? AND provider = ? AND key_name = ?");
    $stmt->execute([$userId, $provider, $keyName]);

    // Supprimer aussi les settings utilisateur si demandé
    if ($deleteSettings) {
      $settingsStmt = $pdo->prepare("DELETE FROM provider_settings WHERE provider = ? AND is_global = 0 AND user_id = ?");
      $settingsStmt->execute([$provider, $userId]);
    }

    echo json_encode(['success' => true, 'message' => 'Configuration personnelle supprimée']);
  }
}

function handleToggleProvider($pdo, $userId, $isAdminUser)
{
  if (!$isAdminUser) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès réservé aux administrateurs']);
    return;
  }

  $input = json_decode(file_get_contents('php://input'), true);

  $provider = $input['provider'] ?? '';
  $enabled = isset($input['enabled']) ? ($input['enabled'] ? 1 : 0) : 1;

  if (empty($provider)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provider requis']);
    return;
  }

  $stmt = $pdo->prepare("
        INSERT INTO provider_status (provider, is_enabled, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            is_enabled = VALUES(is_enabled),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP
    ");
  $stmt->execute([$provider, $enabled, $userId]);

  echo json_encode(['success' => true, 'message' => $enabled ? 'Provider activé' : 'Provider désactivé']);
}

function handleToggleModel($pdo, $userId, $isAdminUser)
{
  if (!$isAdminUser) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès réservé aux administrateurs']);
    return;
  }

  $input = json_decode(file_get_contents('php://input'), true);

  $provider = $input['provider'] ?? '';
  $modelId = $input['model_id'] ?? '';
  $enabled = isset($input['enabled']) ? ($input['enabled'] ? 1 : 0) : 1;

  if (empty($provider) || empty($modelId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provider et model_id requis']);
    return;
  }

  $stmt = $pdo->prepare("
        INSERT INTO models_status (provider, model_id, is_enabled, updated_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            is_enabled = VALUES(is_enabled),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP
    ");
  $stmt->execute([$provider, $modelId, $enabled, $userId]);

  echo json_encode(['success' => true, 'message' => $enabled ? 'Modèle activé' : 'Modèle désactivé']);
}

function handleGetConfig($pdo, $userId, $isAdminUser)
{
  $provider = $_GET['provider'] ?? '';

  if (empty($provider)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provider requis']);
    return;
  }

  // Priorité: clé utilisateur > clé globale
  $config = [];

  // Clé globale
  $globalStmt = $pdo->prepare("SELECT key_name, key_value, is_active FROM api_keys_global WHERE provider = ?");
  $globalStmt->execute([$provider]);
  while ($row = $globalStmt->fetch()) {
    if ($row['is_active']) {
      $config[$row['key_name']] = [
        'value' => decryptApiKey($row['key_value'], $pdo),
        'source' => 'global'
      ];
    }
  }

  // Clé utilisateur (override)
  $userStmt = $pdo->prepare("SELECT key_name, key_value, is_active FROM api_keys_user WHERE user_id = ? AND provider = ?");
  $userStmt->execute([$userId, $provider]);
  while ($row = $userStmt->fetch()) {
    if ($row['is_active']) {
      $decrypted = decryptApiKey($row['key_value'], $pdo);
      if (!empty($decrypted)) {
        $config[$row['key_name']] = [
          'value' => $decrypted,
          'source' => 'user'
        ];
      }
    }
  }

  // Settings globaux
  $settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM provider_settings WHERE provider = ? AND is_global = 1");
  $settingsStmt->execute([$provider]);
  while ($row = $settingsStmt->fetch()) {
    $config[$row['setting_key']] = [
      'value' => $row['setting_value'],
      'source' => 'global'
    ];
  }

  // Settings utilisateur (override)
  $userSettingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM provider_settings WHERE provider = ? AND is_global = 0 AND user_id = ?");
  $userSettingsStmt->execute([$provider, $userId]);
  while ($row = $userSettingsStmt->fetch()) {
    if (!empty($row['setting_value'])) {
      $config[$row['setting_key']] = [
        'value' => $row['setting_value'],
        'source' => 'user'
      ];
    }
  }

  echo json_encode(['success' => true, 'config' => $config]);
}

function handleInitFromConfig($pdo, $userId, $isAdminUser)
{
  if (!$isAdminUser) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès réservé aux administrateurs']);
    return;
  }

  // Charger les clés depuis le fichier config.php existant
  $configFile = __DIR__ . '/config.php';
  if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'error' => 'Fichier config.php non trouvé']);
    return;
  }

  $config = require $configFile;
  if (!is_array($config)) {
    echo json_encode(['success' => false, 'error' => 'Format de config.php invalide']);
    return;
  }

  // Mapping des clés vers leurs providers
  $keyMapping = [
    'OPENAI_API_KEY' => 'openai',
    'ANTHROPIC_API_KEY' => 'anthropic',
    'OLLAMA_API_KEY' => 'ollama',
    'OLLAMA_API_URL' => 'ollama',
    'GEMINI_API_KEY' => 'gemini',
    'DEEPSEEK_API_KEY' => 'deepseek',
    'MISTRAL_API_KEY' => 'mistral',
    'HUGGINGFACE_API_KEY' => 'huggingface',
    'OPENROUTER_API_KEY' => 'openrouter',
    'PERPLEXITY_API_KEY' => 'perplexity',
    'XAI_API_KEY' => 'xai',
    'MOONSHOT_API_KEY' => 'moonshot',
    'GITHUB_TOKEN' => 'github',
  ];

  $imported = 0;
  $skipped = 0;

  foreach ($config as $keyName => $keyValue) {
    if (!isset($keyMapping[$keyName])) {
      continue;
    }

    if (empty($keyValue)) {
      $skipped++;
      continue;
    }

    $provider = $keyMapping[$keyName];

    // Vérifier si c'est une URL ou une clé API
    if (strpos($keyName, '_URL') !== false) {
      // C'est un setting, pas une clé API - DELETE + INSERT car NULL != NULL en MySQL
      $deleteStmt = $pdo->prepare("DELETE FROM provider_settings WHERE provider = ? AND setting_key = ? AND is_global = 1");
      $deleteStmt->execute([$provider, $keyName]);

      $stmt = $pdo->prepare("
                INSERT INTO provider_settings (provider, setting_key, setting_value, is_global, user_id)
                VALUES (?, ?, ?, 1, NULL)
            ");
      $stmt->execute([$provider, $keyName, $keyValue]);
    } else {
      // C'est une clé API - chiffrer
      $encryptedKey = encryptApiKey($keyValue, $pdo);

      $stmt = $pdo->prepare("
                INSERT INTO api_keys_global (provider, key_name, key_value, is_active, created_by, updated_by)
                VALUES (?, ?, ?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    key_value = VALUES(key_value),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
            ");
      $stmt->execute([$provider, $keyName, $encryptedKey, $userId, $userId]);
    }

    $imported++;
  }

  echo json_encode([
    'success' => true,
    'message' => "Import terminé: {$imported} clés importées, {$skipped} ignorées"
  ]);
}
