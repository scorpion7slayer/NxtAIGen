<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

require_once 'db.php';
require_once '../api/rate_limiter.php';

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Récupérer les informations du plan via RateLimiter
$rateLimiter = new RateLimiter($pdo, $_SESSION['user_id']);
$usage = $rateLimiter->checkLimit($_SESSION['user_id'], 'message');
$currentPlan = $user['user_plan'] ?? $user['plan'] ?? 'free';
$planExpires = $user['plan_expires_at'] ?? null;
$stripeSubId = $user['stripe_subscription_id'] ?? null;

// Définition des plans pour affichage
$planDetails = [
  'free' => ['name' => 'Gratuit', 'price' => 0, 'color' => 'gray', 'icon' => 'fa-gift'],
  'basic' => ['name' => 'Basic', 'price' => 5, 'color' => 'blue', 'icon' => 'fa-star'],
  'premium' => ['name' => 'Premium', 'price' => 15, 'color' => 'purple', 'icon' => 'fa-crown'],
  'ultra' => ['name' => 'Ultra', 'price' => 29, 'color' => 'amber', 'icon' => 'fa-rocket'],
  'Admin' => ['name' => 'Admin', 'price' => 0, 'color' => 'red', 'icon' => 'fa-shield']
];

$plan = $planDetails[$currentPlan] ?? $planDetails['free'];

// Traitement de l'annulation
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'cancel' && !empty($stripeSubId)) {
    // Annuler l'abonnement Stripe
    $stripeSecretKey = 'REDACTED_STRIPE_SECRET_KEY';

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => "https://api.stripe.com/v1/subscriptions/{$stripeSubId}",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => 'DELETE',
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $stripeSecretKey,
        'Content-Type: application/x-www-form-urlencoded'
      ],
      CURLOPT_SSL_VERIFYPEER => false // WAMP compatibility
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    // curl_close() supprimé - deprecated depuis PHP 8.0

    if ($curlError) {
      $message = "Erreur de connexion: " . $curlError;
      $messageType = 'error';
    } else {
      $data = json_decode($response, true);

      if ($httpCode === 200 && isset($data['status']) && $data['status'] === 'canceled') {
        // Mettre à jour l'utilisateur en base
        $updateStmt = $pdo->prepare("UPDATE users SET user_plan = 'free', stripe_subscription_id = NULL, plan_expires_at = NULL WHERE id = ?");
        $updateStmt->execute([$_SESSION['user_id']]);

        $message = "Votre abonnement a été annulé avec succès. Vous êtes maintenant sur le plan Gratuit.";
        $messageType = 'success';

        // Rafraîchir les données
        $currentPlan = 'free';
        $plan = $planDetails['free'];
        $stripeSubId = null;
        $planExpires = null;
      } else {
        $errorMsg = $data['error']['message'] ?? 'Erreur inconnue';
        $message = "Erreur lors de l'annulation: " . $errorMsg;
        $messageType = 'error';
      }
    }
  }
}

include 'header.php';
?>
<main class="pt-20 pb-10 min-h-screen px-4">
  <div class="max-w-lg mx-auto">
    <!-- Retour -->
    <div class="mb-8">
      <a href="dashboard.php" class="inline-flex items-center gap-2 text-gray-500 dark:text-neutral-500 hover:text-gray-700 dark:hover:text-neutral-300 transition-colors text-sm mb-4">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Retour au dashboard</span>
      </a>
      <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Mon abonnement</h1>
      <p class="text-sm text-gray-500 dark:text-neutral-500 mt-1">Gérez votre plan et votre facturation</p>
    </div>

    <?php if ($message): ?>
      <div class="mb-6 px-4 py-3 <?php echo $messageType === 'success' ? 'bg-green-500/10 border-green-500/20' : 'bg-red-500/10 border-red-500/20'; ?> border rounded-xl flex items-center gap-3">
        <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400'; ?>"></i>
        <span class="text-sm <?php echo $messageType === 'success' ? 'text-green-400' : 'text-red-400'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    <?php endif; ?>

    <!-- Plan actuel -->
    <div class="bg-white dark:bg-neutral-800/50 border border-gray-200 dark:border-neutral-700/50 rounded-2xl p-6 mb-6 shadow-sm dark:shadow-none">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wider">Plan actuel</h2>
        <?php if ($currentPlan !== 'free' && $currentPlan !== 'Admin'): ?>
          <span class="px-2 py-1 bg-green-500/10 border border-green-500/30 rounded-lg text-xs text-green-400">Actif</span>
        <?php endif; ?>
      </div>

      <div class="flex items-center gap-4 mb-4">
        <div class="w-14 h-14 rounded-xl bg-<?php echo $plan['color']; ?>-500/20 flex items-center justify-center">
          <i class="fa-solid <?php echo $plan['icon']; ?> text-2xl text-<?php echo $plan['color']; ?>-400"></i>
        </div>
        <div>
          <h3 class="text-lg font-semibold text-gray-800 dark:text-neutral-200"><?php echo $plan['name']; ?></h3>
          <?php if ($plan['price'] > 0): ?>
            <p class="text-sm text-gray-500 dark:text-neutral-500"><?php echo $plan['price']; ?>€ / mois</p>
          <?php else: ?>
            <p class="text-sm text-gray-500 dark:text-neutral-500">Gratuit</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Utilisation -->
      <div class="bg-gray-100 dark:bg-neutral-700/30 rounded-xl p-4 mb-4">
        <div class="flex justify-between text-sm mb-2">
          <span class="text-gray-600 dark:text-neutral-400">Messages restants aujourd'hui</span>
          <span class="text-gray-800 dark:text-neutral-200 font-medium">
            <?php
            $dailyRemaining = $usage['remaining']['daily'] ?? 0;
            echo $dailyRemaining === -1 ? '∞' : $dailyRemaining;
            ?>
          </span>
        </div>
        <?php
        $dailyLimit = $planDetails[$currentPlan]['daily_limit'] ?? 30;
        $dailyUsed = $dailyLimit - ($usage['remaining']['daily'] ?? 0);
        if ($dailyRemaining !== -1):
        ?>
          <div class="w-full bg-gray-200 dark:bg-neutral-600 rounded-full h-2">
            <div class="bg-<?php echo $plan['color']; ?>-500 h-2 rounded-full transition-all" style="width: <?php echo $dailyLimit > 0 ? min(100, ($dailyUsed / $dailyLimit) * 100) : 0; ?>%"></div>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($planExpires): ?>
        <p class="text-xs text-gray-500 dark:text-neutral-500 mb-4">
          <i class="fa-solid fa-calendar mr-1"></i>
          Renouvellement : <?php echo date('d/m/Y', strtotime($planExpires)); ?>
        </p>
      <?php endif; ?>

      <?php if ($stripeSubId): ?>
        <p class="text-xs text-gray-500 dark:text-neutral-500">
          <i class="fa-solid fa-credit-card mr-1"></i>
          ID Abonnement : <?php echo htmlspecialchars(substr($stripeSubId, 0, 20) . '...', ENT_QUOTES, 'UTF-8'); ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="space-y-3">
      <?php if ($currentPlan === 'free'): ?>
        <!-- Bouton pour upgrader -->
        <a href="../shop/abonnement-achat-index-stripe.php" class="flex items-center justify-center gap-2 w-full bg-linear-to-r from-green-600 to-green-500 hover:from-green-500 hover:to-green-400 text-white rounded-xl px-4 py-3 text-sm font-medium transition-all shadow-lg shadow-green-500/25">
          <i class="fa-solid fa-arrow-up"></i>
          <span>Passer à un plan supérieur</span>
        </a>
      <?php elseif ($currentPlan !== 'Admin'): ?>
        <!-- Bouton pour changer de plan -->
        <a href="../shop/abonnement-achat-index-stripe.php" class="flex items-center justify-center gap-2 w-full bg-gray-100 dark:bg-neutral-700/30 hover:bg-gray-200 dark:hover:bg-neutral-700/50 border border-gray-200 dark:border-neutral-600/30 text-gray-700 dark:text-neutral-200 rounded-xl px-4 py-3 text-sm transition-colors">
          <i class="fa-solid fa-exchange-alt"></i>
          <span>Changer de plan</span>
        </a>

        <?php if ($stripeSubId): ?>
          <!-- Bouton pour annuler -->
          <button onclick="confirmCancel()" class="flex items-center justify-center gap-2 w-full bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 hover:border-red-500/30 text-red-500 dark:text-red-400 rounded-xl px-4 py-3 text-sm transition-colors">
            <i class="fa-solid fa-times-circle"></i>
            <span>Annuler mon abonnement</span>
          </button>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Information -->
    <div class="mt-6 p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
      <p class="text-xs text-blue-600 dark:text-blue-300">
        <i class="fa-solid fa-info-circle mr-1"></i>
        <strong>Note :</strong> En cas d'annulation, vous conservez l'accès jusqu'à la fin de la période facturée.
      </p>
    </div>
  </div>
</main>

<!-- Modal de confirmation -->
<div id="cancelModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal()"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-neutral-800 border border-gray-200 dark:border-neutral-700 rounded-2xl p-6 max-w-sm w-full shadow-xl">
      <div class="text-center mb-6">
        <div class="w-16 h-16 mx-auto mb-4 bg-red-500/10 rounded-full flex items-center justify-center">
          <i class="fa-solid fa-exclamation-triangle text-3xl text-red-400"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-neutral-200 mb-2">Confirmer l'annulation</h3>
        <p class="text-sm text-gray-500 dark:text-neutral-400">
          Êtes-vous sûr de vouloir annuler votre abonnement <strong><?php echo $plan['name']; ?></strong> ?
          Vous passerez au plan Gratuit.
        </p>
      </div>

      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white rounded-xl px-4 py-3 text-sm font-medium transition-colors">
          Oui, annuler mon abonnement
        </button>
        <button type="button" onclick="closeModal()" class="w-full bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 text-gray-700 dark:text-neutral-200 rounded-xl px-4 py-3 text-sm transition-colors">
          Non, conserver mon abonnement
        </button>
      </form>
    </div>
  </div>
</div>

<script>
  function confirmCancel() {
    document.getElementById('cancelModal').classList.remove('hidden');
  }

  function closeModal() {
    document.getElementById('cancelModal').classList.add('hidden');
  }

  // Fermer avec Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
</script>
</body>

</html>