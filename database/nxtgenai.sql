-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 04 jan. 2026 à 10:42
-- Version du serveur : 9.5.0
-- Version de PHP : 8.4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `nxtgenai`
--

-- --------------------------------------------------------

--
-- Structure de la table `api_keys_global`
--

DROP TABLE IF EXISTS `api_keys_global`;
CREATE TABLE IF NOT EXISTS `api_keys_global` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom du provider (openai, anthropic, etc.)',
  `key_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom de la clé (OPENAI_API_KEY, etc.)',
  `key_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Valeur chiffrée de la clé API',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Clé active ou non',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin qui a créé la clé',
  `updated_by` int UNSIGNED DEFAULT NULL COMMENT 'Admin qui a mis à jour la clé',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_provider_key` (`provider`,`key_name`),
  KEY `idx_provider` (`provider`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `api_keys_global`
--

INSERT INTO `api_keys_global` (`id`, `provider`, `key_name`, `key_value`, `is_active`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'ollama', 'OLLAMA_API_KEY', 'KzuCOPCa52bLZzD+gHUImjhsb89Jb+SK6HVrNPTTLfrvlVn/W6yIcMBlwNK+HUGE8f19KyggFeIBcTMJ14Rmx7UDtnlLMPS66as8b8r3ykE=', 1, '2025-12-18 09:51:56', '2025-12-18 13:41:43', 1, 1),
(2, 'openai', 'OPENAI_API_KEY', '70klhy6L3BTPU+aWQoUoxu8DN3ZeXnD1qstlwqpLaIRmNmVe1QvaOr4PPff8PKkCFNqqhLKd9Ofc/gkhQqc/oC0JtpQcprI4Ttz4/82sS1Euo7Wjbv14gg0foviLpk/8TuSiwTRlFjU1hVlf3xbjBmOZHZbWQ8U67DjzEUWKjCDqpOtxQGD9Ld2QvJkIvTNH2viXMl8Pu8RO8gTYxEPx28HDEMKcUiei97NOb3OhN7rb1LsTIdr0D+tMMXmmPrL3', 1, '2025-12-18 09:51:56', '2025-12-18 09:51:56', 1, 1),
(3, 'anthropic', 'ANTHROPIC_API_KEY', '43oY/bfwwVSmKZu4JewP/tQfY7SOVtUEXvVTA5hlNhJbpbE3atffj4rU3zl7XJ5k/D6bhTqXoYGqLPMbIakX2uBToXBY3+q+EV3mYPSBDOIQxAeL0tOD0jTefJW6fr05zNJHCXQ4ap9I4ctZzwy3qi4yMzRfY2T02XA3MdsKZrk=', 1, '2025-12-18 09:51:56', '2025-12-18 09:51:56', 1, 1),
(4, 'deepseek', 'DEEPSEEK_API_KEY', 'CMEATXks1KappTi4R/Ckocpx0wxqy9zA1S3APgFi79v2LS22eXA6DiWtvRbwIATSeoilPrSqUxnqlVURP0CKNQ==', 1, '2025-12-18 09:51:56', '2025-12-18 09:51:56', 1, 1),
(5, 'mistral', 'MISTRAL_API_KEY', '7FQhGrwuD3Y9Zsgw7UJbRahqCZIcBMR7284FJapc5E/do3CSH3hLCixvOGgCmCrTfmnVsw6LuSXxX6Rul8R2ag==', 1, '2025-12-18 09:51:56', '2025-12-18 09:51:56', 1, 1),
(6, 'huggingface', 'HUGGINGFACE_API_KEY', 'RDOnYJQopYU25nH/D0ciobi7XpAaw/VIH5es33+fkGZ2HXOUB28slwQVSkrtkpjLgJuf62Fk5XXOBD1Mo5kgog==', 1, '2025-12-18 09:51:56', '2025-12-18 09:51:56', 1, 1),
(7, 'openrouter', 'OPENROUTER_API_KEY', 'v1UDdoKEIj7qaxUpT0JDjlb6BRVXAXpSO0qYe+QfwFtZ3ZjvvSUfiQ6YxAGnneaJ0KQIA2eCDkDP9PnghtvV4cXEFCg26ZUJ2IpbaUOtSZYrbx7EonM4+3KRi2qPW5Nx', 1, '2025-12-18 09:51:56', '2025-12-18 09:51:56', 1, 1),
(8, 'xai', 'XAI_API_KEY', '', 1, '2025-12-18 09:51:56', '2025-12-18 13:57:38', 1, 1),
(9, 'moonshot', 'MOONSHOT_API_KEY', 'lmGQCN6j7XFlMLWr6tg4uTozt1S96F6xiL4F1w7Ozybs+lsCuhCTTI0mDO/cWIsaqPxlq7eCbHMsh7p6LRYYHcsUOvUg6L62ZTKIfOe8hHA=', 1, '2025-12-18 09:51:56', '2025-12-18 09:51:56', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `api_keys_user`
--

DROP TABLE IF EXISTS `api_keys_user`;
CREATE TABLE IF NOT EXISTS `api_keys_user` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL COMMENT 'ID de l''utilisateur',
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom du provider',
  `key_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom de la clé',
  `key_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Valeur chiffrée de la clé API',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Clé active ou non',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_provider_key` (`user_id`,`provider`,`key_name`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_provider` (`provider`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
--

DROP TABLE IF EXISTS `encryption_config`;
CREATE TABLE IF NOT EXISTS `encryption_config` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `encryption_config`
--

INSERT INTO `encryption_config` (`id`, `config_key`, `config_value`, `created_at`) VALUES
(1, 'encryption_key', '6LpzFt4h2uedXhmz/KWmJwwsPaCszE/wB9+2szF7oag=', '2025-12-18 09:51:26');

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `models_status`
--

INSERT INTO `models_status` (`id`, `provider`, `model_id`, `is_enabled`, `updated_at`, `updated_by`) VALUES
(1, 'anthropic', 'claude-sonnet-4-5-20250929', 1, '2025-12-18 14:02:21', 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `provider_settings`
--

INSERT INTO `provider_settings` (`id`, `provider`, `setting_key`, `setting_value`, `is_global`, `user_id`, `created_at`, `updated_at`) VALUES
(8, 'ollama', 'OLLAMA_API_URL', 'https://ia-api-oollama.serverscorpion1601.site', 1, NULL, '2025-12-18 13:41:43', '2025-12-18 13:41:43');

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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `provider_status`
--

INSERT INTO `provider_status` (`id`, `provider`, `is_enabled`, `updated_at`, `updated_by`) VALUES
(1, 'xai', 0, '2025-12-18 13:57:10', 1),
(2, 'moonshot', 0, '2025-12-18 13:56:26', 1),
(3, 'huggingface', 0, '2025-12-18 13:56:38', 1),
(6, 'anthropic', 1, '2025-12-18 14:16:59', 1);

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
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Provider utilisé (openai, anthropic, etc.)',
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Modèle utilisé (gpt-4, claude-3, etc.)',
  `tokens_used` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Tokens consommés',
  `cost_estimate` decimal(10,6) NOT NULL DEFAULT '0.000000' COMMENT 'Coût estimé en USD',
  `response_time` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Temps de réponse en ms',
  `status` enum('success','error','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success' COMMENT 'Statut de la requête',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Message d''erreur si échec',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Adresse IP du client',
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User-Agent du navigateur',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_tracking` (`user_id`,`created_at`),
  KEY `idx_provider_model` (`provider`,`model`),
  KEY `idx_date_range` (`created_at`),
  KEY `idx_status` (`status`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracking détaillé de toutes les utilisations API';

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
  `user_plan` enum('free','basic','premium','custom') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free' COMMENT 'Plan de l''utilisateur',
  `daily_limit` int NOT NULL DEFAULT '10' COMMENT 'Limite de messages par jour (-1 = illimité)',
  `hourly_limit` int NOT NULL DEFAULT '3' COMMENT 'Limite de messages par heure (-1 = illimité)',
  `monthly_limit` int NOT NULL DEFAULT '200' COMMENT 'Limite de messages par mois (-1 = illimité)',
  `current_daily_count` int NOT NULL DEFAULT '0' COMMENT 'Compteur actuel journalier',
  `current_hourly_count` int NOT NULL DEFAULT '0' COMMENT 'Compteur actuel horaire',
  `current_monthly_count` int NOT NULL DEFAULT '0' COMMENT 'Compteur actuel mensuel',
  `last_daily_reset` timestamp NULL DEFAULT NULL COMMENT 'Dernière réinitialisation journalière',
  `last_hourly_reset` timestamp NULL DEFAULT NULL COMMENT 'Dernière réinitialisation horaire',
  `last_monthly_reset` timestamp NULL DEFAULT NULL COMMENT 'Dernière réinitialisation mensuelle',
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
  KEY `idx_github_token_expiry` (`github_token_expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `is_admin`, `user_plan`, `daily_limit`, `hourly_limit`, `monthly_limit`, `current_daily_count`, `current_hourly_count`, `current_monthly_count`, `last_daily_reset`, `last_hourly_reset`, `last_monthly_reset`, `github_id`, `github_username`, `github_token`, `github_refresh_token`, `github_token_expires_at`, `github_connected_at`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'test', 'test@mail.com', '$2y$12$45P2ElKK6YBzynz0wJhyPeHuvVh1VjDOfVuQMrD8xC14HfAvgiyF2', 1, 'premium', -1, -1, -1, 0, 0, 0, '2026-01-04 10:41:16', '2026-01-04 10:41:16', '2026-01-04 10:41:16', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-11 09:50:14', '2025-12-11 09:50:59', NULL);

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `api_keys_user`
--
ALTER TABLE `api_keys_user`
  ADD CONSTRAINT `fk_api_keys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
