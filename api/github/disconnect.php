<?php

/**
 * Déconnexion OAuth GitHub
 * Supprime le token GitHub de l'utilisateur
 */

session_start();

require_once __DIR__ . '/../../zone_membres/db.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../zone_membres/login.php');
  exit();
}

try {
  // Supprimer les informations GitHub de la base de données
  $stmt = $pdo->prepare("
        UPDATE users 
        SET github_id = NULL, 
            github_username = NULL, 
            github_token = NULL, 
            github_connected_at = NULL 
        WHERE id = ?
    ");

  $stmt->execute([$_SESSION['user_id']]);

  // Nettoyer la session
  unset($_SESSION['github_connected']);
  unset($_SESSION['github_username']);

  $_SESSION['oauth_success'] = 'Compte GitHub déconnecté avec succès.';
  header('Location: ../../zone_membres/dashboard.php?oauth_success=1');
  exit();
} catch (PDOException $e) {
  header('Location: ../../zone_membres/dashboard.php?oauth_error=' . urlencode('Erreur: ' . $e->getMessage()));
  exit();
}
