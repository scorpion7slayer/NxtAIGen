-- NxtGenAI - Schema de base de donnees
-- Version: MySQL 9.5+ / MariaDB 10.6+
--
-- INSTRUCTIONS :
-- 1. Creez une base de donnees nommee 'nxtgenai'
-- 2. Importez ce fichier SQL
-- 3. Creez un utilisateur admin manuellement via phpMyAdmin (is_admin = 1)
-- 4. Configurez vos cles API via /admin/settings.php

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de donnees : `nxtgenai`
--

-- --------------------------------------------------------

--
-- Structure de la table `api_keys_global`
--

DROP TABLE IF EXISTS `api_keys_global`;
CREATE TABLE IF NOT EXISTS `api_keys_global` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom du provider (openai, anthropic, etc.)',
  `key_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom de la cle (OPENAI_API_KEY, etc.)',
  `key_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Valeur chiffree de la cle API',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Cle active ou non',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin qui a cree la cle',
  `updated_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin qui a mis a jour la cle',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider_key` (`provider`,`key_name`),
  KEY `idx_provider` (`provider`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `api_keys_user`
--

DROP TABLE IF EXISTS `api_keys_user`;
CREATE TABLE IF NOT EXISTS `api_keys_user` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL COMMENT 'ID de l''utilisateur',
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom du provider',
  `key_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom de la cle',
  `key_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Valeur chiffree de la cle API',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Cle active ou non',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_provider_key` (`user_id`,`provider`,`key_name`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Nouvelle conversation',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `encryption_config`
-- Note: La cle de chiffrement sera generee automatiquement au premier lancement
--

DROP TABLE IF EXISTS `encryption_config`;
CREATE TABLE IF NOT EXISTS `encryption_config` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` int UNSIGNED NOT NULL,
  `role` enum('user','assistant','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tokens_used` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `models_status`
--

DROP TABLE IF EXISTS `models_status`;
CREATE TABLE IF NOT EXISTS `models_status` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider_model` (`provider`,`model_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `provider_settings`
--

DROP TABLE IF EXISTS `provider_settings`;
CREATE TABLE IF NOT EXISTS `provider_settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_global` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=global, 0=user specific',
  `user_id` int UNSIGNED DEFAULT NULL COMMENT 'NULL pour global, user_id pour user specific',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider_setting` (`provider`,`setting_key`,`is_global`,`user_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `provider_status`
--

DROP TABLE IF EXISTS `provider_status`;
CREATE TABLE IF NOT EXISTS `provider_status` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider` (`provider`),
  KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `session_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `usage_tracking`
--

DROP TABLE IF EXISTS `usage_tracking`;
CREATE TABLE IF NOT EXISTS `usage_tracking` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL COMMENT 'ID de l''utilisateur (0 = visiteur)',
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'message' COMMENT 'Type d''action (message, image, etc.)',
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Provider utilise (openai, anthropic, etc.)',
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Modele utilise (gpt-4, claude-3, etc.)',
  `tokens_used` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Tokens consommes',
  `cost_estimate` decimal(10,6) NOT NULL DEFAULT '0.000000' COMMENT 'Cout estime en USD',
  `response_time` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Temps de reponse en ms',
  `status` enum('success','error','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success' COMMENT 'Statut de la requete',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Message d''erreur si echec',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Adresse IP du client',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User-Agent du navigateur',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_tracking` (`user_id`,`created_at`),
  KEY `idx_provider_model` (`provider`,`model`),
  KEY `idx_date_range` (`created_at`),
  KEY `idx_status` (`status`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracking detaille de toutes les utilisations API';

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `user_plan` enum('free','basic','premium','ultra','Admin','custom') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free' COMMENT 'Plan de l''utilisateur',
  `daily_limit` int NOT NULL DEFAULT '10' COMMENT 'Limite de messages par jour (-1 = illimite)',
  `hourly_limit` int NOT NULL DEFAULT '3' COMMENT 'Limite de messages par heure (-1 = illimite)',
  `monthly_limit` int NOT NULL DEFAULT '200' COMMENT 'Limite de messages par mois (-1 = illimite)',
  `stripe_customer_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID client Stripe',
  `stripe_subscription_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID abonnement Stripe actif',
  `subscription_status` enum('active','trialing','past_due','canceled','unpaid','incomplete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Statut de l''abonnement Stripe',
  `subscription_ends_at` timestamp NULL DEFAULT NULL COMMENT 'Date de fin de l''abonnement (pour periode de grace)',
  `current_daily_count` int NOT NULL DEFAULT '0' COMMENT 'Compteur actuel journalier',
  `current_hourly_count` int NOT NULL DEFAULT '0' COMMENT 'Compteur actuel horaire',
  `current_monthly_count` int NOT NULL DEFAULT '0' COMMENT 'Compteur actuel mensuel',
  `last_daily_reset` timestamp NULL DEFAULT NULL COMMENT 'Derniere reinitialisation journaliere',
  `last_hourly_reset` timestamp NULL DEFAULT NULL COMMENT 'Derniere reinitialisation horaire',
  `last_monthly_reset` timestamp NULL DEFAULT NULL COMMENT 'Derniere reinitialisation mensuelle',
  `github_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `github_username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `github_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `github_refresh_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Refresh token GitHub OAuth',
  `github_token_expires_at` timestamp NULL DEFAULT NULL COMMENT 'Date d''expiration du token GitHub',
  `github_connected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username` (`username`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_is_admin` (`is_admin`),
  KEY `idx_github_id` (`github_id`),
  KEY `idx_user_plan` (`user_plan`),
  KEY `idx_daily_usage` (`current_daily_count`,`last_daily_reset`),
  KEY `idx_hourly_usage` (`current_hourly_count`,`last_hourly_reset`),
  KEY `idx_github_token_expiry` (`github_token_expires_at`),
  KEY `idx_stripe_customer` (`stripe_customer_id`),
  KEY `idx_stripe_subscription` (`stripe_subscription_id`),
  KEY `idx_subscription_status` (`subscription_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Contraintes pour les tables
--

ALTER TABLE `api_keys_user`
  ADD CONSTRAINT `fk_api_keys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
