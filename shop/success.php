<?php
session_start();

/**
 * Page de succès après paiement Stripe
 * Met à jour le plan de l'utilisateur
 */

require_once __DIR__ . '/stripe_config.php';

$sessionId = $_GET['session_id'] ?? '';
$success = false;
$planName = '';
$error = '';

if (!empty($sessionId) && isset($_SESSION['user_id'])) {
  // Récupérer les infos de la session Stripe
  $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId) . '?expand[]=subscription');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');

  // Résolution problème certificats SSL sur Windows/WAMP
  $caPath = 'C:/wamp64/ssl/cacert.pem';
  if (file_exists($caPath)) {
    curl_setopt($ch, CURLOPT_CAINFO, $caPath);
  } else {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  }

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  // curl_close() supprimé - deprecated depuis PHP 8.0

  $session = json_decode($response, true);

  if ($httpCode === 200 && isset($session['metadata']['plan'])) {
    $plan = $session['metadata']['plan'];
    $userId = $session['metadata']['user_id'] ?? $session['client_reference_id'];

    // Vérifier que l'utilisateur correspond
    if ($userId == $_SESSION['user_id'] && $session['payment_status'] === 'paid') {
      require_once '../zone_membres/db.php';
      require_once '../api/rate_limiter.php';

      try {
        $rateLimiter = new RateLimiter($pdo);
        $result = $rateLimiter->changePlan($_SESSION['user_id'], $plan);

        if ($result) {
          $success = true;
          $planName = ucfirst($plan);

          // Stocker l'ID de l'abonnement Stripe pour la gestion future
          if (isset($session['subscription'])) {
            $subscriptionId = is_array($session['subscription'])
              ? $session['subscription']['id']
              : $session['subscription'];

            $stmt = $pdo->prepare("UPDATE users SET stripe_subscription_id = ? WHERE id = ?");
            $stmt->execute([$subscriptionId, $_SESSION['user_id']]);
          }
        } else {
          $error = 'Erreur lors de la mise à jour du plan';
        }
      } catch (Exception $e) {
        $error = 'Erreur: ' . $e->getMessage();
      }
    } else {
      $error = 'Session invalide ou paiement non confirmé';
    }
  } else {
    $error = 'Impossible de récupérer les informations de paiement';
  }
} elseif (empty($sessionId)) {
  $error = 'Session de paiement manquante';
} else {
  $error = 'Vous devez être connecté';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Preconnect CDN -->
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
  <!-- Preload CSS critique -->
  <link rel="preload" href="../src/output.css" as="style">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <!-- Font Awesome non-bloquant -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" media="print" onload="this.media='all'">
  <title><?php echo $success ? 'Paiement reussi' : 'Erreur'; ?> - NxtGenAI</title>
  <link href="../src/output.css" rel="stylesheet">
  <!-- Forçage du thème sombre et du français -->
  <script>
    document.documentElement.classList.add('dark');
    document.documentElement.lang = 'fr';
  </script>
</head>

<body class="min-h-screen bg-gray-50 dark:bg-bg-dark text-gray-900 dark:text-neutral-400 flex items-center justify-center px-4">
  <div class="max-w-md w-full text-center">
    <?php if ($success): ?>
      <div class="success-animation mb-6">
        <div class="w-24 h-24 mx-auto rounded-full bg-green-500/20 flex items-center justify-center">
          <i class="fa-solid fa-check text-4xl text-green-400"></i>
        </div>
      </div>

      <h1 class="text-2xl font-bold text-neutral-100 mb-3">Paiement réussi !</h1>
      <p class="text-neutral-400 mb-6">
        Votre abonnement <span class="text-green-400 font-semibold"><?php echo htmlspecialchars($planName); ?></span> est maintenant actif.
      </p>

      <div class="bg-neutral-800/50 border border-neutral-700/50 rounded-xl p-4 mb-6">
        <div class="flex items-center justify-between text-sm">
          <span class="text-neutral-500">Plan activé</span>
          <span class="text-neutral-200 font-medium"><?php echo htmlspecialchars($planName); ?></span>
        </div>
        <div class="flex items-center justify-between text-sm mt-2">
          <span class="text-neutral-500">Statut</span>
          <span class="text-green-400 font-medium">Actif</span>
        </div>
      </div>

      <div class="space-y-3">
        <a href="../index.php" class="block w-full py-3 px-4 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition-colors">
          <i class="fa-solid fa-comments mr-2"></i>
          Commencer à discuter
        </a>
        <a href="../zone_membres/dashboard.php" class="block w-full py-3 px-4 bg-neutral-700 hover:bg-neutral-600 text-neutral-200 rounded-xl font-medium transition-colors">
          <i class="fa-solid fa-user mr-2"></i>
          Mon compte
        </a>
      </div>

      <!-- Confetti effect -->
      <script>
        function createConfetti() {
          const colors = ['#22c55e', '#10b981', '#059669', '#34d399', '#6ee7b7'];
          for (let i = 0; i < 50; i++) {
            setTimeout(() => {
              const confetti = document.createElement('div');
              confetti.className = 'confetti';
              confetti.style.cssText = `
                                left: ${Math.random() * 100}vw;
                                top: -20px;
                                width: ${Math.random() * 10 + 5}px;
                                height: ${Math.random() * 10 + 5}px;
                                background: ${colors[Math.floor(Math.random() * colors.length)]};
                                border-radius: ${Math.random() > 0.5 ? '50%' : '0'};
                                transform: rotate(${Math.random() * 360}deg);
                                animation: fall ${Math.random() * 3 + 2}s linear forwards;
                            `;
              document.body.appendChild(confetti);
              setTimeout(() => confetti.remove(), 5000);
            }, i * 50);
          }
        }
        createConfetti();
      </script>
    <?php else: ?>
      <div class="mb-6">
        <div class="w-24 h-24 mx-auto rounded-full bg-red-500/20 flex items-center justify-center">
          <i class="fa-solid fa-xmark text-4xl text-red-400"></i>
        </div>
      </div>

      <h1 class="text-2xl font-bold text-neutral-100 mb-3">Une erreur est survenue</h1>
      <p class="text-neutral-400 mb-6">
        <?php echo htmlspecialchars($error); ?>
      </p>

      <div class="space-y-3">
        <a href="abonnement-achat-index-stripe.php" class="block w-full py-3 px-4 bg-neutral-700 hover:bg-neutral-600 text-neutral-200 rounded-xl font-medium transition-colors">
          <i class="fa-solid fa-arrow-left mr-2"></i>
          Retour aux abonnements
        </a>
        <a href="../zone_membres/login.php" class="block w-full py-3 px-4 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition-colors">
          <i class="fa-solid fa-sign-in-alt mr-2"></i>
          Se connecter
        </a>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>