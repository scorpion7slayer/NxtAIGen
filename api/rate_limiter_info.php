<?php

/**
 * API pour récupérer les informations de rate limiting de l'utilisateur
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Non authentifié']);
  exit();
}

require_once __DIR__ . '/../zone_membres/db.php';
require_once __DIR__ . '/rate_limiter.php';

$userId = $_SESSION['user_id'];
$rateLimiter = new RateLimiter($pdo);

try {
  // Récupérer les limites restantes
  $remaining = $rateLimiter->getRemainingLimits($userId);

  // Récupérer les informations du plan
  $stmt = $pdo->prepare("
    SELECT user_plan, daily_limit, hourly_limit, monthly_limit,
           current_daily_count, current_hourly_count, current_monthly_count
    FROM users WHERE id = ?
  ");
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  // Ajouter les limites max au tableau
  $remaining['hourly_limit'] = $user['hourly_limit'];
  $remaining['daily_limit'] = $user['daily_limit'];
  $remaining['monthly_limit'] = $user['monthly_limit'];
  $remaining['user_plan'] = $user['user_plan'];

  echo json_encode([
    'success' => true,
    'limits' => $remaining,
    'usage' => [
      'hourly' => $user['current_hourly_count'],
      'daily' => $user['current_daily_count'],
      'monthly' => $user['current_monthly_count']
    ]
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
