-- Migration pour le stockage des clés API
-- Date: 2025-12-18
-- Description: Ajoute le support pour les clés API globales (admin) et personnelles (utilisateurs)

-- =====================================================
-- Table: api_keys_global
-- Stocke les clés API globales de l'application (admin)
-- =====================================================
DROP TABLE IF EXISTS `api_keys_global`;
CREATE TABLE IF NOT EXISTS `api_keys_global` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom du provider (openai, anthropic, etc.)',
  `key_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom de la clé (OPENAI_API_KEY, etc.)',
  `key_value` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Valeur chiffrée de la clé API',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Clé active ou non',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin qui a créé la clé',
  `updated_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin qui a mis à jour la clé',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider_key` (`provider`, `key_name`),
  KEY `idx_provider` (`provider`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: api_keys_user
-- Stocke les clés API personnelles des utilisateurs
-- =====================================================
DROP TABLE IF EXISTS `api_keys_user`;
CREATE TABLE IF NOT EXISTS `api_keys_user` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL COMMENT 'ID de l\'utilisateur',
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom du provider',
  `key_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom de la clé',
  `key_value` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Valeur chiffrée de la clé API',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Clé active ou non',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_provider_key` (`user_id`, `provider`, `key_name`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_provider` (`provider`),
  CONSTRAINT `fk_api_keys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: encryption_config
-- Stocke la configuration de chiffrement (clé de chiffrement)
-- Note: En production, utilisez plutôt une variable d'environnement
-- =====================================================
DROP TABLE IF EXISTS `encryption_config`;
CREATE TABLE IF NOT EXISTS `encryption_config` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Générer une clé de chiffrement aléatoire (32 bytes en base64 pour AES-256)
-- Cette clé sera utilisée pour chiffrer toutes les clés API
INSERT INTO `encryption_config` (`config_key`, `config_value`) 
VALUES ('encryption_key', TO_BASE64(RANDOM_BYTES(32)))
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

-- =====================================================
-- Table: provider_settings
-- Stocke les paramètres supplémentaires des providers (URLs, etc.)
-- =====================================================
DROP TABLE IF EXISTS `provider_settings`;
CREATE TABLE IF NOT EXISTS `provider_settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_global` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=global, 0=user specific',
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL pour global, user_id pour user specific',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider_setting` (`provider`, `setting_key`, `is_global`, `user_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: models_status
-- Stocke l'état d'activation des modèles
-- =====================================================
DROP TABLE IF EXISTS `models_status`;
CREATE TABLE IF NOT EXISTS `models_status` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider_model` (`provider`, `model_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: provider_status
-- Stocke l'état d'activation des providers
-- =====================================================
DROP TABLE IF EXISTS `provider_status`;
CREATE TABLE IF NOT EXISTS `provider_status` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider` (`provider`),
  KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
