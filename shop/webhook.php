<?php

/**
 * Webhook Stripe pour NxtGenAI
 * Gère les événements d'abonnement (création, annulation, paiement échoué, etc.)
 * 
 * URL à configurer dans Stripe Dashboard: https://votre-domaine.com/shop/webhook.php
 */

// Désactiver l'affichage des erreurs en production
error_reporting(0);
ini_set('display_errors', 0);

// Configuration
define('STRIPE_SECRET_KEY', 'sk_test_51SnEgiBkhknMH4HPGrXmJg3zQz0kOcFWaeNjtbjx5VViPOHKG9mVgDe8xDAtaS1s8zbBbdTRkKht8bbO3neXbfcR00R88IaP6k');
define('STRIPE_WEBHOOK_SECRET', 'whsec_2c8b33690999cd03d36f40c83fff048e01223f9e78390c6cb8db3a9cebdac8bb');
// Log des webhooks (optionnel, pour debug)
function logWebhook($message, $data = [])
{
  $logFile = __DIR__ . '/webhook_logs.txt';
  $timestamp = date('Y-m-d H:i:s');
  $logEntry = "[$timestamp] $message\n";
  if (!empty($data)) {
    $logEntry .= json_encode($data, JSON_PRETTY_PRINT) . "\n";
  }
  file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Récupérer le payload
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Vérifier la signature (en production)
if (!empty(STRIPE_WEBHOOK_SECRET) && STRIPE_WEBHOOK_SECRET !== 'whsec_VOTRE_CLE_WEBHOOK') {
  $timestamp = null;
  $signature = null;

  foreach (explode(',', $sigHeader) as $part) {
    $pair = explode('=', $part, 2);
    if ($pair[0] === 't') {
      $timestamp = $pair[1];
    } elseif ($pair[0] === 'v1') {
      $signature = $pair[1];
    }
  }

  if (!$timestamp || !$signature) {
    http_response_code(400);
    logWebhook('Signature invalide - headers manquants');
    exit('Invalid signature');
  }

  $signedPayload = $timestamp . '.' . $payload;
  $expectedSignature = hash_hmac('sha256', $signedPayload, STRIPE_WEBHOOK_SECRET);

  if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(400);
    logWebhook('Signature invalide - hash mismatch');
    exit('Invalid signature');
  }

  // Vérifier que le timestamp n'est pas trop vieux (5 min)
  if (abs(time() - $timestamp) > 300) {
    http_response_code(400);
    logWebhook('Signature expirée');
    exit('Timestamp too old');
  }
}

// Parser l'événement
$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
  http_response_code(400);
  logWebhook('Payload invalide');
  exit('Invalid payload');
}

logWebhook('Événement reçu: ' . $event['type'], ['event_id' => $event['id']]);

// Connexion à la base de données
require_once '../zone_membres/db.php';
require_once '../api/rate_limiter.php';

$rateLimiter = new RateLimiter($pdo);

// Mapping plan → données
$planLimits = [
  'basic' => ['daily' => 50, 'hourly' => 20, 'monthly' => 1000],
  'premium' => ['daily' => 200, 'hourly' => 50, 'monthly' => 5000],
  'ultra' => ['daily' => -1, 'hourly' => 100, 'monthly' => -1],
  'free' => ['daily' => 30, 'hourly' => 10, 'monthly' => 150]
];

// Fonction pour mettre à jour le plan utilisateur
function updateUserPlan($pdo, $userId, $plan, $subscriptionId = null)
{
  global $planLimits;

  if (!isset($planLimits[$plan])) {
    $plan = 'free';
  }

  $limits = $planLimits[$plan];

  $sql = "UPDATE users SET 
            user_plan = ?, 
            daily_limit = ?, 
            hourly_limit = ?, 
            monthly_limit = ?";
  $params = [$plan, $limits['daily'], $limits['hourly'], $limits['monthly']];

  if ($subscriptionId !== null) {
    $sql .= ", stripe_subscription_id = ?";
    $params[] = $subscriptionId;
  }

  $sql .= " WHERE id = ?";
  $params[] = $userId;

  $stmt = $pdo->prepare($sql);
  return $stmt->execute($params);
}

// Fonction pour récupérer l'utilisateur par subscription ID
function getUserBySubscription($pdo, $subscriptionId)
{
  $stmt = $pdo->prepare("SELECT id, user_plan FROM users WHERE stripe_subscription_id = ?");
  $stmt->execute([$subscriptionId]);
  return $stmt->fetch();
}

// Fonction pour récupérer l'utilisateur par customer ID ou email
function getUserByCustomerOrEmail($pdo, $customerId, $email = null)
{
  // D'abord essayer par customer ID si stocké
  $stmt = $pdo->prepare("SELECT id, user_plan FROM users WHERE stripe_customer_id = ?");
  $stmt->execute([$customerId]);
  $user = $stmt->fetch();

  if (!$user && $email) {
    $stmt = $pdo->prepare("SELECT id, user_plan FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
  }

  return $user;
}

try {
  switch ($event['type']) {
    case 'checkout.session.completed':
      // Paiement réussi via Checkout
      $session = $event['data']['object'];
      $userId = $session['metadata']['user_id'] ?? $session['client_reference_id'] ?? null;
      $plan = $session['metadata']['plan'] ?? 'basic';
      $subscriptionId = $session['subscription'] ?? null;

      if ($userId && $session['payment_status'] === 'paid') {
        updateUserPlan($pdo, $userId, $plan, $subscriptionId);

        // Stocker le customer ID si présent
        if (isset($session['customer'])) {
          $stmt = $pdo->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
          $stmt->execute([$session['customer'], $userId]);
        }

        logWebhook("Plan mis à jour pour user $userId: $plan");
      }
      break;

    case 'customer.subscription.updated':
      // Abonnement mis à jour (changement de plan, etc.)
      $subscription = $event['data']['object'];
      $subscriptionId = $subscription['id'];
      $status = $subscription['status'];

      $user = getUserBySubscription($pdo, $subscriptionId);

      if ($user && in_array($status, ['active', 'trialing'])) {
        // Récupérer le nouveau plan depuis les métadonnées ou les items
        $plan = $subscription['metadata']['plan'] ?? null;

        if ($plan) {
          updateUserPlan($pdo, $user['id'], $plan);
          logWebhook("Abonnement mis à jour pour user {$user['id']}: $plan");
        }
      }
      break;

    case 'customer.subscription.deleted':
      // Abonnement annulé
      $subscription = $event['data']['object'];
      $subscriptionId = $subscription['id'];

      $user = getUserBySubscription($pdo, $subscriptionId);

      if ($user) {
        // Rétrograder vers le plan gratuit
        updateUserPlan($pdo, $user['id'], 'free', null);

        // Effacer le subscription ID
        $stmt = $pdo->prepare("UPDATE users SET stripe_subscription_id = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);

        logWebhook("Abonnement annulé pour user {$user['id']}, rétrogradé en free");
      }
      break;

    case 'invoice.payment_failed':
      // Paiement échoué
      $invoice = $event['data']['object'];
      $customerId = $invoice['customer'];
      $subscriptionId = $invoice['subscription'] ?? null;

      $user = null;
      if ($subscriptionId) {
        $user = getUserBySubscription($pdo, $subscriptionId);
      }
      if (!$user) {
        $user = getUserByCustomerOrEmail($pdo, $customerId);
      }

      if ($user) {
        // Optionnel: envoyer un email de notification
        // Pour l'instant, on log juste
        logWebhook("Paiement échoué pour user {$user['id']}", [
          'invoice_id' => $invoice['id'],
          'attempt_count' => $invoice['attempt_count'] ?? 1
        ]);

        // Après plusieurs échecs, Stripe annulera automatiquement l'abonnement
        // ce qui déclenchera customer.subscription.deleted
      }
      break;

    case 'invoice.paid':
      // Paiement réussi (renouvellement)
      $invoice = $event['data']['object'];
      $subscriptionId = $invoice['subscription'] ?? null;

      if ($subscriptionId) {
        $user = getUserBySubscription($pdo, $subscriptionId);

        if ($user) {
          logWebhook("Renouvellement réussi pour user {$user['id']}");
        }
      }
      break;

    default:
      // Événement non géré
      logWebhook("Événement non géré: " . $event['type']);
  }

  // Répondre avec succès
  http_response_code(200);
  echo json_encode(['status' => 'success']);
} catch (Exception $e) {
  logWebhook('Erreur webhook: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
