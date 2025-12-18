<?php

/**
 * Fonctions utilitaires pour la gestion des clés API chiffrées
 * 
 * Ce fichier fournit les fonctions pour:
 * - Récupérer la clé de chiffrement
 * - Chiffrer/déchiffrer les clés API
 * - Charger les configurations depuis la DB
 */

// =====================================================
// Fonctions de chiffrement
// =====================================================

function getEncryptionKeyFromDb($pdo)
{
  static $key = null;
  if ($key === null) {
    try {
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
    } catch (PDOException $e) {
      // Table n'existe pas encore - retourner null
      return null;
    }
  }
  return $key;
}

function encryptValue($plaintext, $pdo)
{
  $key = getEncryptionKeyFromDb($pdo);
  if ($key === null) return $plaintext;
  $iv = random_bytes(16);
  $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  return base64_encode($iv . $encrypted);
}

function decryptValue($ciphertext, $pdo)
{
  $key = getEncryptionKeyFromDb($pdo);
  if ($key === null) return $ciphertext;
  $data = base64_decode($ciphertext);
  if (strlen($data) < 16) return '';
  $iv = substr($data, 0, 16);
  $encrypted = substr($data, 16);
  $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  return $decrypted !== false ? $decrypted : '';
}

// =====================================================
// Chargement des configurations API
// =====================================================

/**
 * Charge la configuration API pour un provider donné
 * Priorité: clé utilisateur > clé globale > fichier config.php (fallback)
 * 
 * @param PDO $pdo Instance PDO
 * @param string $provider Nom du provider
 * @param int|null $userId ID de l'utilisateur (null pour global uniquement)
 * @return array Configuration avec les clés API
 */
function getApiConfig($pdo, $provider, $userId = null)
{
  $config = [];

  try {
    // Vérifier si la table existe
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'api_keys_global'");
    if ($tableCheck->rowCount() === 0) {
      // Tables pas encore créées, utiliser le fichier config.php
      return getApiConfigFromFile($provider);
    }

    // Charger les clés globales
    $globalStmt = $pdo->prepare("SELECT key_name, key_value, is_active FROM api_keys_global WHERE provider = ?");
    $globalStmt->execute([$provider]);
    while ($row = $globalStmt->fetch()) {
      if ($row['is_active']) {
        $config[$row['key_name']] = decryptValue($row['key_value'], $pdo);
      }
    }

    // Charger les settings globaux
    $settingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM provider_settings WHERE provider = ? AND is_global = 1");
    $settingsStmt->execute([$provider]);
    while ($row = $settingsStmt->fetch()) {
      $config[$row['setting_key']] = $row['setting_value'];
    }

    // Si un utilisateur est spécifié, charger ses clés personnelles (override)
    if ($userId !== null) {
      $userStmt = $pdo->prepare("SELECT key_name, key_value, is_active FROM api_keys_user WHERE user_id = ? AND provider = ?");
      $userStmt->execute([$userId, $provider]);
      while ($row = $userStmt->fetch()) {
        if ($row['is_active']) {
          $decrypted = decryptValue($row['key_value'], $pdo);
          if (!empty($decrypted)) {
            $config[$row['key_name']] = $decrypted;
          }
        }
      }

      // Settings utilisateur
      $userSettingsStmt = $pdo->prepare("SELECT setting_key, setting_value FROM provider_settings WHERE provider = ? AND is_global = 0 AND user_id = ?");
      $userSettingsStmt->execute([$provider, $userId]);
      while ($row = $userSettingsStmt->fetch()) {
        if (!empty($row['setting_value'])) {
          $config[$row['setting_key']] = $row['setting_value'];
        }
      }
    }

    // Fallback vers le fichier config.php si aucune clé en DB
    if (empty($config)) {
      return getApiConfigFromFile($provider);
    }
  } catch (PDOException $e) {
    // En cas d'erreur, utiliser le fichier config.php
    return getApiConfigFromFile($provider);
  }

  return $config;
}

/**
 * Charge la configuration depuis le fichier config.php (fallback)
 */
function getApiConfigFromFile($provider)
{
  static $fileConfig = null;

  if ($fileConfig === null) {
    $configFile = __DIR__ . '/config.php';
    if (file_exists($configFile)) {
      $fileConfig = require $configFile;
    } else {
      $fileConfig = [];
    }
  }

  // Mapping provider -> clés
  $mapping = [
    'openai' => ['OPENAI_API_KEY'],
    'anthropic' => ['ANTHROPIC_API_KEY'],
    'ollama' => ['OLLAMA_API_KEY', 'OLLAMA_API_URL'],
    'gemini' => ['GEMINI_API_KEY'],
    'deepseek' => ['DEEPSEEK_API_KEY'],
    'mistral' => ['MISTRAL_API_KEY'],
    'huggingface' => ['HUGGINGFACE_API_KEY'],
    'openrouter' => ['OPENROUTER_API_KEY'],
    'perplexity' => ['PERPLEXITY_API_KEY'],
    'xai' => ['XAI_API_KEY'],
    'moonshot' => ['MOONSHOT_API_KEY'],
    'github' => ['GITHUB_TOKEN'],
  ];

  $config = [];
  if (isset($mapping[$provider])) {
    foreach ($mapping[$provider] as $key) {
      if (isset($fileConfig[$key])) {
        $config[$key] = $fileConfig[$key];
      }
    }
  }

  return $config;
}

/**
 * Charge toutes les configurations API pour tous les providers
 */
function getAllApiConfigs($pdo, $userId = null)
{
  $providers = ['openai', 'anthropic', 'ollama', 'gemini', 'deepseek', 'mistral', 'huggingface', 'openrouter', 'perplexity', 'xai', 'moonshot', 'github'];
  $configs = [];

  foreach ($providers as $provider) {
    $configs[$provider] = getApiConfig($pdo, $provider, $userId);
  }

  return $configs;
}

/**
 * Récupère une clé API spécifique avec fallback automatique
 */
function getApiKey($pdo, $keyName, $userId = null)
{
  // Mapping clé -> provider
  $keyToProvider = [
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

  if (!isset($keyToProvider[$keyName])) {
    return '';
  }

  $provider = $keyToProvider[$keyName];
  $config = getApiConfig($pdo, $provider, $userId);

  return $config[$keyName] ?? '';
}

/**
 * Vérifie si un provider est activé
 */
function isProviderEnabled($pdo, $provider)
{
  try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'provider_status'");
    if ($tableCheck->rowCount() === 0) {
      return true; // Par défaut activé si la table n'existe pas
    }

    $stmt = $pdo->prepare("SELECT is_enabled FROM provider_status WHERE provider = ?");
    $stmt->execute([$provider]);
    $row = $stmt->fetch();

    return $row ? (bool)$row['is_enabled'] : true;
  } catch (PDOException $e) {
    return true;
  }
}

/**
 * Vérifie si un modèle est activé
 */
function isModelEnabled($pdo, $provider, $modelId)
{
  try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'models_status'");
    if ($tableCheck->rowCount() === 0) {
      return true;
    }

    $stmt = $pdo->prepare("SELECT is_enabled FROM models_status WHERE provider = ? AND model_id = ?");
    $stmt->execute([$provider, $modelId]);
    $row = $stmt->fetch();

    return $row ? (bool)$row['is_enabled'] : true;
  } catch (PDOException $e) {
    return true;
  }
}

/**
 * Récupère la liste des providers activés
 */
function getEnabledProviders($pdo)
{
  $allProviders = ['openai', 'anthropic', 'ollama', 'gemini', 'deepseek', 'mistral', 'huggingface', 'openrouter', 'perplexity', 'xai', 'moonshot', 'github'];
  $enabled = [];

  foreach ($allProviders as $provider) {
    if (isProviderEnabled($pdo, $provider)) {
      $enabled[] = $provider;
    }
  }

  return $enabled;
}
