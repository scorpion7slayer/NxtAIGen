<?php

/**
 * Système de rate limiting par utilisateur
 * Gère les limites horaires, quotidiennes et mensuelles
 */

class RateLimiter
{
  private $pdo;

  // Définition des plans utilisateur
  private $plans = [
    'free' => [
      'daily_limit' => 30,
      'hourly_limit' => 10,
      'monthly_limit' => 150,
      'providers' => '*', // Tous les providers
    ],
    'basic' => [
      'daily_limit' => 50,
      'hourly_limit' => 20,
      'monthly_limit' => 1000,
      'providers' => '*', // Tous les providers
    ],
    'premium' => [
      'daily_limit' => 200,
      'hourly_limit' => 50,
      'monthly_limit' => 5000,
      'providers' => '*',
    ],
    'ultra' => [
      'daily_limit' => -1,
      'hourly_limit' => 100,
      'monthly_limit' => -1,
      'providers' => '*',
    ],
    'Admin' => [
      'daily_limit' => -1,
      'hourly_limit' => -1,
      'monthly_limit' => -1,
      'providers' => '*',
    ],
  ];

  public function __construct($pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Vérifier si l'utilisateur peut effectuer une action
   * @param int $userId ID de l'utilisateur
   * @param string $actionType Type d'action (message, image_upload, etc.)
   * @return array ['allowed' => bool, 'message' => string, 'remaining' => array]
   */
  public function checkLimit($userId, $actionType = 'message')
  {
    // Récupérer les informations de l'utilisateur
    $stmt = $this->pdo->prepare("
      SELECT user_plan, daily_limit, hourly_limit, monthly_limit,
             current_daily_count, current_hourly_count, current_monthly_count,
             last_daily_reset, last_hourly_reset, last_monthly_reset
      FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      return ['allowed' => false, 'message' => 'Utilisateur non trouvé'];
    }

    // Réinitialiser les compteurs si nécessaire
    $this->resetCountersIfNeeded($userId, $user);

    // Récupérer les limites (avec fallback sur le plan)
    $plan = $this->plans[$user['user_plan']] ?? $this->plans['free'];
    $dailyLimit = $user['daily_limit'] ?? $plan['daily_limit'];
    $hourlyLimit = $user['hourly_limit'] ?? $plan['hourly_limit'];
    $monthlyLimit = $user['monthly_limit'] ?? $plan['monthly_limit'];

    // Vérifier les limites (-1 = illimité)
    if ($hourlyLimit !== -1 && $user['current_hourly_count'] >= $hourlyLimit) {
      return [
        'allowed' => false,
        'message' => "Limite horaire atteinte ({$hourlyLimit} messages/heure)",
        'limit_type' => 'hourly',
        'reset_at' => $this->getNextResetTime('hourly', $user['last_hourly_reset']),
        'remaining' => [
          'hourly' => 0,
          'daily' => max(0, $dailyLimit - $user['current_daily_count']),
          'monthly' => max(0, $monthlyLimit - $user['current_monthly_count'])
        ]
      ];
    }

    if ($dailyLimit !== -1 && $user['current_daily_count'] >= $dailyLimit) {
      return [
        'allowed' => false,
        'message' => "Limite quotidienne atteinte ({$dailyLimit} messages/jour)",
        'limit_type' => 'daily',
        'reset_at' => $this->getNextResetTime('daily', $user['last_daily_reset']),
        'remaining' => [
          'hourly' => max(0, $hourlyLimit - $user['current_hourly_count']),
          'daily' => 0,
          'monthly' => max(0, $monthlyLimit - $user['current_monthly_count'])
        ]
      ];
    }

    if ($monthlyLimit !== -1 && $user['current_monthly_count'] >= $monthlyLimit) {
      return [
        'allowed' => false,
        'message' => "Limite mensuelle atteinte ({$monthlyLimit} messages/mois)",
        'limit_type' => 'monthly',
        'reset_at' => $this->getNextResetTime('monthly', $user['last_monthly_reset']),
        'remaining' => [
          'hourly' => max(0, $hourlyLimit - $user['current_hourly_count']),
          'daily' => max(0, $dailyLimit - $user['current_daily_count']),
          'monthly' => 0
        ]
      ];
    }

    // Limite OK
    return [
      'allowed' => true,
      'remaining' => [
        'hourly' => $hourlyLimit === -1 ? -1 : max(0, $hourlyLimit - $user['current_hourly_count']),
        'daily' => $dailyLimit === -1 ? -1 : max(0, $dailyLimit - $user['current_daily_count']),
        'monthly' => $monthlyLimit === -1 ? -1 : max(0, $monthlyLimit - $user['current_monthly_count'])
      ]
    ];
  }

  /**
   * Incrémenter les compteurs d'usage après une requête réussie
   * @param int $userId
   * @param string $actionType
   * @param int $tokensUsed
   * @param array $metadata (provider, model, cost, response_time, etc.)
   */
  public function incrementUsage($userId, $actionType, $tokensUsed = 0, $metadata = [])
  {
    // Incrémenter les compteurs utilisateur
    $stmt = $this->pdo->prepare("
      UPDATE users 
      SET 
        current_hourly_count = current_hourly_count + 1,
        current_daily_count = current_daily_count + 1,
        current_monthly_count = current_monthly_count + 1
      WHERE id = ?
    ");
    $stmt->execute([$userId]);

    // Enregistrer dans l'historique détaillé
    $stmt = $this->pdo->prepare("
      INSERT INTO usage_tracking 
      (user_id, action_type, provider, model, tokens_used, cost_estimate, 
       response_time, status, error_message, ip_address, user_agent)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
      $userId,
      $actionType,
      $metadata['provider'] ?? null,
      $metadata['model'] ?? null,
      $tokensUsed,
      $metadata['cost'] ?? 0.00,
      $metadata['response_time'] ?? 0,
      $metadata['status'] ?? 'success',
      $metadata['error'] ?? null,
      $_SERVER['REMOTE_ADDR'] ?? null,
      $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
  }

  /**
   * Réinitialiser les compteurs si les périodes sont dépassées
   */
  private function resetCountersIfNeeded($userId, $user)
  {
    $now = time();
    $needsUpdate = false;
    $updates = [];

    // Vérifier reset horaire (toutes les heures)
    if ($user['last_hourly_reset'] && (strtotime($user['last_hourly_reset']) + 3600) <= $now) {
      $updates[] = "current_hourly_count = 0";
      $updates[] = "last_hourly_reset = CURRENT_TIMESTAMP";
      $needsUpdate = true;
    }

    // Vérifier reset quotidien (minuit)
    if ($user['last_daily_reset'] && date('Y-m-d', strtotime($user['last_daily_reset'])) !== date('Y-m-d')) {
      $updates[] = "current_daily_count = 0";
      $updates[] = "last_daily_reset = CURRENT_TIMESTAMP";
      $needsUpdate = true;
    }

    // Vérifier reset mensuel (1er du mois)
    if ($user['last_monthly_reset'] && date('Y-m', strtotime($user['last_monthly_reset'])) !== date('Y-m')) {
      $updates[] = "current_monthly_count = 0";
      $updates[] = "last_monthly_reset = CURRENT_TIMESTAMP";
      $needsUpdate = true;
    }

    if ($needsUpdate) {
      $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([$userId]);
    }
  }

  /**
   * Calculer le prochain reset selon le type
   */
  private function getNextResetTime($type, $lastReset)
  {
    // Si lastReset est NULL (nouvel utilisateur), utiliser le timestamp actuel
    $time = $lastReset !== null ? strtotime($lastReset) : time();
    switch ($type) {
      case 'hourly':
        return date('Y-m-d H:i:s', $time + 3600);
      case 'daily':
        return date('Y-m-d 00:00:00', strtotime('tomorrow'));
      case 'monthly':
        return date('Y-m-01 00:00:00', strtotime('first day of next month'));
    }
    return null;
  }

  /**
   * Obtenir les statistiques d'usage d'un utilisateur
   */
  public function getUsageStats($userId, $period = 'month')
  {
    $dateFilter = match ($period) {
      'day' => "DATE(created_at) = CURDATE()",
      'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
      'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
      default => "1=1"
    };

    $stmt = $this->pdo->prepare("
      SELECT 
        COUNT(*) as total_requests,
        SUM(tokens_used) as total_tokens,
        SUM(cost_estimate) as total_cost,
        AVG(response_time) as avg_response_time,
        provider,
        model,
        DATE(created_at) as date
      FROM usage_tracking
      WHERE user_id = ? AND {$dateFilter}
      GROUP BY provider, model, DATE(created_at)
      ORDER BY date DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Changer le plan d'un utilisateur (admin uniquement)
   */
  public function changePlan($userId, $newPlan)
  {
    if (!isset($this->plans[$newPlan])) {
      return false;
    }

    $plan = $this->plans[$newPlan];
    $stmt = $this->pdo->prepare("
      UPDATE users 
      SET 
        user_plan = ?,
        daily_limit = ?,
        hourly_limit = ?,
        monthly_limit = ?
      WHERE id = ?
    ");

    return $stmt->execute([
      $newPlan,
      $plan['daily_limit'],
      $plan['hourly_limit'],
      $plan['monthly_limit'],
      $userId
    ]);
  }

  /**
   * Obtenir les limites restantes pour un utilisateur
   */
  public function getRemainingLimits($userId)
  {
    $check = $this->checkLimit($userId);
    return $check['remaining'] ?? [];
  }
}
