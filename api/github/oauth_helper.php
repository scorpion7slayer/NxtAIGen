<?php

/**
 * GitHub Copilot OAuth Helper
 * Gestion avancée des tokens OAuth GitHub Copilot avec refresh automatique
 */

class GitHubCopilotOAuth
{
  private $pdo;
  private $clientId;
  private $clientSecret;

  public function __construct($pdo, $clientId = null, $clientSecret = null)
  {
    $this->pdo = $pdo;

    // Charger les credentials OAuth depuis config
    $oauthConfig = require __DIR__ . '/github/config.php';
    $this->clientId = $clientId ?? $oauthConfig['GITHUB_CLIENT_ID'] ?? '';
    $this->clientSecret = $clientSecret ?? $oauthConfig['GITHUB_CLIENT_SECRET'] ?? '';
  }

  /**
   * Vérifier si l'utilisateur a un token valide
   */
  public function hasValidToken($userId): bool
  {
    $stmt = $this->pdo->prepare("
      SELECT github_token, github_token_expires_at 
      FROM users 
      WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['github_token'])) {
      return false;
    }

    // Vérifier l'expiration si disponible
    if (!empty($user['github_token_expires_at'])) {
      $expiresAt = strtotime($user['github_token_expires_at']);
      $now = time();

      // Si le token expire dans moins de 5 minutes, le considérer comme expiré
      if ($expiresAt - $now < 300) {
        return false;
      }
    }

    return true;
  }

  /**
   * Récupérer le token de l'utilisateur (avec refresh automatique si expiré)
   */
  public function getToken($userId): ?string
  {
    // Vérifier le token actuel
    if ($this->hasValidToken($userId)) {
      $stmt = $this->pdo->prepare("SELECT github_token FROM users WHERE id = ?");
      $stmt->execute([$userId]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      return $user['github_token'] ?? null;
    }

    // Token expiré ou inexistant, tenter de le rafraîchir
    $stmt = $this->pdo->prepare("
      SELECT github_refresh_token 
      FROM users 
      WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $refreshToken = $user['github_refresh_token'] ?? null;

    if (empty($refreshToken)) {
      return null; // Pas de refresh token disponible
    }

    // Tenter le refresh
    return $this->refreshAccessToken($userId, $refreshToken);
  }

  /**
   * Rafraîchir le token OAuth en utilisant le refresh token
   */
  private function refreshAccessToken($userId, $refreshToken): ?string
  {
    if (empty($this->clientId) || empty($this->clientSecret)) {
      error_log("GitHub OAuth: Client credentials not configured");
      return null;
    }

    $ch = curl_init('https://github.com/login/oauth/access_token');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json'
      ],
      CURLOPT_POSTFIELDS => json_encode([
        'client_id' => $this->clientId,
        'client_secret' => $this->clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token'
      ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() supprimé - deprecated depuis PHP 8.0

    if ($httpCode !== 200) {
      error_log("GitHub OAuth refresh failed: HTTP $httpCode - $response");
      return null;
    }

    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
      error_log("GitHub OAuth refresh failed: No access_token in response");
      return null;
    }

    // Sauvegarder le nouveau token
    $newToken = $data['access_token'];
    $newRefreshToken = $data['refresh_token'] ?? $refreshToken; // Certaines APIs renvoient un nouveau refresh token
    $expiresIn = $data['expires_in'] ?? 28800; // Par défaut 8h
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

    $stmt = $this->pdo->prepare("
      UPDATE users 
      SET 
        github_token = ?,
        github_refresh_token = ?,
        github_token_expires_at = ?
      WHERE id = ?
    ");
    $stmt->execute([$newToken, $newRefreshToken, $expiresAt, $userId]);

    return $newToken;
  }

  /**
   * Vérifier si l'utilisateur a un abonnement GitHub Copilot actif
   */
  public function hasCopilotSubscription($token): array
  {
    // Endpoint pour vérifier l'accès Copilot
    // Note: Cette API nécessite le scope "copilot" dans l'OAuth
    $ch = curl_init('https://api.github.com/user/copilot_seats');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Accept: application/vnd.github+json',
        'Authorization: Bearer ' . $token,
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: NxtGenAI/1.0'
      ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() supprimé - deprecated depuis PHP 8.0

    if ($httpCode === 200) {
      $data = json_decode($response, true);
      return [
        'has_subscription' => true,
        'plan' => $data['plan'] ?? 'unknown',
        'seats' => $data
      ];
    }

    // Alternative: Tenter d'appeler directement l'API Copilot
    $ch = curl_init('https://api.githubcopilot.com/models');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Editor-Version: vscode/1.95.0',
        'User-Agent: NxtGenAI/1.0'
      ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() supprimé - deprecated depuis PHP 8.0

    if ($httpCode === 200) {
      return [
        'has_subscription' => true,
        'plan' => 'copilot_pro', // Inféré par l'accès à l'API
        'verified_via' => 'api_access'
      ];
    }

    return [
      'has_subscription' => false,
      'error' => 'No active Copilot subscription detected',
      'http_code' => $httpCode
    ];
  }

  /**
   * Sauvegarder le token initial lors de la connexion OAuth
   */
  public function saveToken($userId, $accessToken, $refreshToken = null, $expiresIn = 28800): bool
  {
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

    $stmt = $this->pdo->prepare("
      UPDATE users 
      SET 
        github_token = ?,
        github_refresh_token = ?,
        github_token_expires_at = ?
      WHERE id = ?
    ");

    return $stmt->execute([$accessToken, $refreshToken, $expiresAt, $userId]);
  }

  /**
   * Révoquer le token (déconnexion)
   */
  public function revokeToken($userId): bool
  {
    $stmt = $this->pdo->prepare("
      UPDATE users 
      SET 
        github_token = NULL,
        github_refresh_token = NULL,
        github_token_expires_at = NULL
      WHERE id = ?
    ");

    return $stmt->execute([$userId]);
  }
}
