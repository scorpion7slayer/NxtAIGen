<?php
session_start();

/**
 * Page d'achat d'abonnement Stripe
 * Intègre les plans définis dans rate_limiter.php
 */

// Configuration Stripe (utiliser les clés de test pour le dev)
define('STRIPE_SECRET_KEY', 'sk_test_51SnEgiBkhknMH4HPGrXmJg3zQz0kOcFWaeNjtbjx5VViPOHKG9mVgDe8xDAtaS1s8zbBbdTRkKht8bbO3neXbfcR00R88IaP6k');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51SnEgiBkhknMH4HPwNb2eAiWB1h3tKs3CvQ2lUAx8lZqJpQu9C0VIjcfjHAqDC2W8IZqFEpGpfKXEE6C5fU1VYnG00Yb5YD5Za');

// Mapping des plans avec les Price IDs Stripe
$stripePlans = [
  'basic' => [
    'name' => 'Basic',
    'price_id' => 'price_1SnEqEBkhknMH4HP4i9Idvf0',
    'price' => 5.00,
    'currency' => 'EUR',
    'interval' => 'mois',
    'features' => [
      '20 messages/heure',
      '50 messages/jour',
      '1 000 messages/mois',
      'Tous les providers IA',
      'Support par email'
    ],
    'color' => 'blue',
    'icon' => 'fa-bolt',
    'popular' => false
  ],
  'premium' => [
    'name' => 'Premium',
    'price_id' => 'price_1SnErEBkhknMH4HP2mfOIG6A',
    'price' => 15.00,
    'currency' => 'EUR',
    'interval' => 'mois',
    'features' => [
      '50 messages/heure',
      '200 messages/jour',
      '5 000 messages/mois',
      'Tous les providers IA',
      'Support prioritaire',
      'Modèles avancés'
    ],
    'color' => 'green',
    'icon' => 'fa-star',
    'popular' => true
  ],
  'ultra' => [
    'name' => 'Ultra',
    'price_id' => 'price_1SnFH8BkhknMH4HPqblZbMMP',
    'price' => 29.00,
    'currency' => 'EUR',
    'interval' => 'mois',
    'features' => [
      '100 messages/heure',
      'Messages illimités/jour',
      'Messages illimités/mois',
      'Tous les providers IA',
      'Support prioritaire 24/7',
      'Accès anticipé nouvelles fonctionnalités',
      'API dédiée'
    ],
    'color' => 'purple',
    'icon' => 'fa-crown',
    'popular' => false
  ]
];

// Plan gratuit (pour comparaison)
$freePlan = [
  'name' => 'Free',
  'price' => 0,
  'currency' => 'EUR',
  'interval' => 'mois',
  'features' => [
    '10 messages/heure',
    '30 messages/jour',
    '150 messages/mois',
    'Providers de base'
  ],
  'color' => 'gray',
  'icon' => 'fa-user',
  'popular' => false
];

// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);
$currentPlan = 'free';
$userEmail = '';
$userName = '';

if ($isLoggedIn) {
  require_once '../zone_membres/db.php';

  $stmt = $pdo->prepare("SELECT username, email, user_plan FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch();

  if ($user) {
    $currentPlan = $user['user_plan'] ?? 'free';
    $userEmail = $user['email'] ?? '';
    $userName = $user['username'] ?? '';
  }
}

// Traitement de la création de session Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_checkout'])) {
  header('Content-Type: application/json');

  if (!$isLoggedIn) {
    echo json_encode(['error' => 'Vous devez être connecté pour souscrire à un abonnement']);
    exit;
  }

  $planKey = $_POST['plan'] ?? '';

  if (!isset($stripePlans[$planKey])) {
    echo json_encode(['error' => 'Plan invalide']);
    exit;
  }

  $plan = $stripePlans[$planKey];

  try {
    // Initialiser Stripe (sans composer, utilisation de l'API REST directement)
    $checkoutData = [
      'payment_method_types' => ['card'],
      'line_items' => [[
        'price' => $plan['price_id'],
        'quantity' => 1
      ]],
      'mode' => 'subscription',
      'success_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/success.php?session_id={CHECKOUT_SESSION_ID}',
      'cancel_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
      'customer_email' => $userEmail,
      'client_reference_id' => (string)$_SESSION['user_id'],
      'metadata' => [
        'user_id' => (string)$_SESSION['user_id'],
        'plan' => $planKey
      ],
      'subscription_data' => [
        'metadata' => [
          'user_id' => (string)$_SESSION['user_id'],
          'plan' => $planKey
        ]
      ]
    ];

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($checkoutData));
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    // Résolution problème certificats SSL sur Windows/WAMP
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $caPath = 'C:/wamp64/ssl/cacert.pem';
    if (file_exists($caPath)) {
      curl_setopt($ch, CURLOPT_CAINFO, $caPath);
    } else {
      // Fallback: désactiver temporairement la vérification SSL (dev uniquement)
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Debug: vérifier erreur cURL
    if (!$response || $curlError) {
      echo json_encode(['error' => 'Erreur de connexion à Stripe: ' . ($curlError ?: 'Réponse vide')]);
      exit;
    }

    $session = json_decode($response, true);

    // Vérifier si le JSON est valide
    if ($session === null) {
      echo json_encode(['error' => 'Réponse Stripe invalide: ' . substr($response, 0, 200)]);
      exit;
    }

    if ($httpCode >= 400 || isset($session['error'])) {
      echo json_encode(['error' => $session['error']['message'] ?? 'Erreur Stripe (HTTP ' . $httpCode . ')']);
      exit;
    }

    // Vérifier que l'URL existe
    if (empty($session['url'])) {
      echo json_encode(['error' => 'URL de checkout non reçue de Stripe']);
      exit;
    }

    echo json_encode(['checkout_url' => $session['url']]);
    exit;
  } catch (Exception $e) {
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <title>Abonnements - NxtGenAI</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-Regular.ttf') format('truetype');
      font-weight: 400;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-Medium.ttf') format('truetype');
      font-weight: 500;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-SemiBold.ttf') format('truetype');
      font-weight: 600;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-Bold.ttf') format('truetype');
      font-weight: 700;
    }

    * {
      font-family: 'TikTok Sans', system-ui, sans-serif;
    }

    body {
      background-color: oklch(21% 0.006 285.885);
    }

    ::selection {
      background: #404040;
    }

    .plan-card {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .plan-card:hover {
      transform: translateY(-8px);
    }

    .popular-badge {
      animation: pulse 2s infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.7;
      }
    }

    .gradient-border {
      position: relative;
      background: linear-gradient(135deg, #22c55e, #10b981, #059669);
      padding: 2px;
      border-radius: 1.5rem;
    }

    .gradient-border>div {
      background: oklch(21% 0.006 285.885);
      border-radius: calc(1.5rem - 2px);
    }
  </style>
</head>

<body class="min-h-screen text-neutral-400">
  <!-- Header -->
  <header class="fixed top-0 left-0 right-0 z-50 bg-[oklch(21%_0.006_285.885)]/90 backdrop-blur-md border-b border-neutral-700/50">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="../index.php" class="flex items-center gap-2.5 text-sm font-medium text-neutral-200 hover:text-white transition-colors">
        <img src="../assets/images/logo.svg" alt="NxtGenAI" class="w-7 h-7">
        <span>NxtGenAI</span>
      </a>
      <nav class="flex items-center gap-4">
        <?php if ($isLoggedIn): ?>
          <span class="text-sm text-neutral-400 hidden sm:inline"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></span>
          <a href="../zone_membres/dashboard.php" class="text-sm text-neutral-400 hover:text-green-400 transition-colors">
            <i class="fa-solid fa-user mr-1.5"></i><span class="hidden sm:inline">Compte</span>
          </a>
          <a href="../zone_membres/logout.php" class="text-sm text-neutral-500 hover:text-red-400 transition-colors">
            <i class="fa-solid fa-sign-out-alt"></i>
          </a>
        <?php else: ?>
          <a href="../zone_membres/login.php" class="text-sm text-neutral-400 hover:text-green-400 transition-colors">Connexion</a>
          <a href="../zone_membres/register.php" class="px-3 py-1.5 text-sm bg-green-600 hover:bg-green-500 text-white rounded-lg transition-colors">Inscription</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="pt-24 pb-16 px-4">
    <div class="max-w-6xl mx-auto">
      <!-- Titre -->
      <div class="text-center mb-12">
        <h1 class="text-3xl sm:text-4xl font-bold text-neutral-100 mb-4">
          Choisissez votre plan
        </h1>
        <p class="text-neutral-400 max-w-xl mx-auto text-sm sm:text-base">
          Débloquez tout le potentiel de l'IA avec nos abonnements. Annulez à tout moment.
        </p>
        <?php if ($isLoggedIn && $currentPlan !== 'free'): ?>
          <div class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-green-500/10 border border-green-500/30 rounded-xl">
            <i class="fa-solid fa-check-circle text-green-400"></i>
            <span class="text-sm text-green-400">Plan actuel : <strong><?php echo ucfirst($currentPlan); ?></strong></span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Grille des plans -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
        <!-- Plan Free -->
        <div class="plan-card bg-neutral-800/50 border border-neutral-700/50 rounded-2xl p-5 lg:p-6 flex flex-col">
          <div class="mb-4">
            <div class="w-12 h-12 rounded-xl bg-gray-500/10 flex items-center justify-center mb-4">
              <i class="fa-solid fa-user text-xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-neutral-200">Free</h3>
            <p class="text-sm text-neutral-500 mt-1">Pour découvrir</p>
          </div>

          <div class="mb-6">
            <span class="text-3xl font-bold text-neutral-100">0€</span>
            <span class="text-neutral-500">/mois</span>
          </div>

          <ul class="space-y-3 mb-6 flex-grow">
            <?php foreach ($freePlan['features'] as $feature): ?>
              <li class="flex items-start gap-2 text-sm">
                <i class="fa-solid fa-check text-gray-500 mt-0.5"></i>
                <span class="text-neutral-400"><?php echo htmlspecialchars($feature); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>

          <?php if ($currentPlan === 'free'): ?>
            <button disabled class="w-full py-3 px-4 bg-neutral-700/50 text-neutral-500 rounded-xl text-sm font-medium cursor-not-allowed">
              Plan actuel
            </button>
          <?php else: ?>
            <a href="../zone_membres/register.php" class="block w-full py-3 px-4 bg-neutral-700 hover:bg-neutral-600 text-neutral-200 rounded-xl text-sm font-medium text-center transition-colors">
              Commencer gratuitement
            </a>
          <?php endif; ?>
        </div>

        <!-- Plans payants -->
        <?php foreach ($stripePlans as $key => $plan): ?>
          <div class="<?php echo $plan['popular'] ? 'gradient-border' : ''; ?>">
            <div class="plan-card bg-neutral-800/50 border <?php echo $plan['popular'] ? 'border-transparent' : 'border-neutral-700/50'; ?> rounded-2xl p-5 lg:p-6 flex flex-col h-full relative">
              <?php if ($plan['popular']): ?>
                <div class="popular-badge absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-green-500 text-white text-xs font-semibold rounded-full">
                  Populaire
                </div>
              <?php endif; ?>

              <div class="mb-4">
                <div class="w-12 h-12 rounded-xl bg-<?php echo $plan['color']; ?>-500/10 flex items-center justify-center mb-4">
                  <i class="fa-solid <?php echo $plan['icon']; ?> text-xl text-<?php echo $plan['color']; ?>-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-neutral-200"><?php echo $plan['name']; ?></h3>
                <p class="text-sm text-neutral-500 mt-1">
                  <?php
                  switch ($key) {
                    case 'basic':
                      echo 'Pour les utilisateurs réguliers';
                      break;
                    case 'premium':
                      echo 'Pour les professionnels';
                      break;
                    case 'ultra':
                      echo 'Pour les power users';
                      break;
                  }
                  ?>
                </p>
              </div>

              <div class="mb-6">
                <span class="text-3xl font-bold text-neutral-100"><?php echo number_format($plan['price'], 0); ?>€</span>
                <span class="text-neutral-500">/<?php echo $plan['interval']; ?></span>
              </div>

              <ul class="space-y-3 mb-6 flex-grow">
                <?php foreach ($plan['features'] as $feature): ?>
                  <li class="flex items-start gap-2 text-sm">
                    <i class="fa-solid fa-check text-<?php echo $plan['color']; ?>-400 mt-0.5"></i>
                    <span class="text-neutral-400"><?php echo htmlspecialchars($feature); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>

              <?php if ($currentPlan === $key): ?>
                <button disabled class="w-full py-3 px-4 bg-<?php echo $plan['color']; ?>-500/20 text-<?php echo $plan['color']; ?>-400 rounded-xl text-sm font-medium cursor-not-allowed border border-<?php echo $plan['color']; ?>-500/30">
                  <i class="fa-solid fa-check mr-2"></i>Plan actuel
                </button>
              <?php elseif (!$isLoggedIn): ?>
                <a href="../zone_membres/register.php" class="block w-full py-3 px-4 bg-<?php echo $plan['color']; ?>-500 hover:bg-<?php echo $plan['color']; ?>-600 text-white rounded-xl text-sm font-medium text-center transition-colors">
                  S'inscrire
                </a>
              <?php else: ?>
                <button onclick="subscribe('<?php echo $key; ?>')" class="subscribe-btn w-full py-3 px-4 bg-<?php echo $plan['color']; ?>-500 hover:bg-<?php echo $plan['color']; ?>-600 text-white rounded-xl text-sm font-medium transition-colors flex items-center justify-center gap-2">
                  <span>Choisir ce plan</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- FAQ Section -->
      <div class="mt-16 max-w-2xl mx-auto">
        <h2 class="text-2xl font-bold text-neutral-100 text-center mb-8">Questions fréquentes</h2>

        <div class="space-y-4">
          <details class="bg-neutral-800/50 border border-neutral-700/50 rounded-xl p-4 group">
            <summary class="flex items-center justify-between cursor-pointer text-neutral-200 font-medium">
              <span>Puis-je changer de plan à tout moment ?</span>
              <i class="fa-solid fa-chevron-down text-neutral-500 group-open:rotate-180 transition-transform"></i>
            </summary>
            <p class="mt-3 text-sm text-neutral-400">
              Oui, vous pouvez upgrader ou downgrader votre plan à tout moment. Les changements prennent effet immédiatement et sont proratisés.
            </p>
          </details>

          <details class="bg-neutral-800/50 border border-neutral-700/50 rounded-xl p-4 group">
            <summary class="flex items-center justify-between cursor-pointer text-neutral-200 font-medium">
              <span>Comment fonctionne la facturation ?</span>
              <i class="fa-solid fa-chevron-down text-neutral-500 group-open:rotate-180 transition-transform"></i>
            </summary>
            <p class="mt-3 text-sm text-neutral-400">
              Vous êtes facturé mensuellement. Le paiement est sécurisé via Stripe. Vous recevez une facture par email à chaque renouvellement.
            </p>
          </details>

          <details class="bg-neutral-800/50 border border-neutral-700/50 rounded-xl p-4 group">
            <summary class="flex items-center justify-between cursor-pointer text-neutral-200 font-medium">
              <span>Puis-je annuler mon abonnement ?</span>
              <i class="fa-solid fa-chevron-down text-neutral-500 group-open:rotate-180 transition-transform"></i>
            </summary>
            <p class="mt-3 text-sm text-neutral-400">
              Oui, vous pouvez annuler à tout moment depuis vos paramètres. Votre accès reste actif jusqu'à la fin de la période payée.
            </p>
          </details>

          <details class="bg-neutral-800/50 border border-neutral-700/50 rounded-xl p-4 group">
            <summary class="flex items-center justify-between cursor-pointer text-neutral-200 font-medium">
              <span>Quels moyens de paiement acceptez-vous ?</span>
              <i class="fa-solid fa-chevron-down text-neutral-500 group-open:rotate-180 transition-transform"></i>
            </summary>
            <p class="mt-3 text-sm text-neutral-400">
              Nous acceptons les cartes bancaires (Visa, Mastercard, American Express) via notre partenaire de paiement sécurisé Stripe.
            </p>
          </details>
        </div>
      </div>

      <!-- Badges de confiance -->
      <div class="mt-12 flex flex-wrap items-center justify-center gap-6 text-neutral-500">
        <div class="flex items-center gap-2 text-sm">
          <i class="fa-solid fa-lock"></i>
          <span>Paiement sécurisé</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
          <i class="fa-brands fa-stripe text-lg"></i>
          <span>Propulsé par Stripe</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
          <i class="fa-solid fa-shield-check"></i>
          <span>Données protégées</span>
        </div>
      </div>
    </div>
  </main>

  <!-- Toast notification -->
  <div id="toast" class="fixed bottom-4 right-4 transform translate-y-20 opacity-0 transition-all duration-300 z-50">
    <div class="bg-red-500/90 backdrop-blur-sm text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3">
      <i class="fa-solid fa-exclamation-circle"></i>
      <span id="toast-message">Erreur</span>
    </div>
  </div>

  <script>
    function showToast(message, type = 'error') {
      const toast = document.getElementById('toast');
      const toastMessage = document.getElementById('toast-message');

      toastMessage.textContent = message;
      toast.querySelector('div').className = `bg-${type === 'error' ? 'red' : 'green'}-500/90 backdrop-blur-sm text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3`;

      toast.classList.remove('translate-y-20', 'opacity-0');
      toast.classList.add('translate-y-0', 'opacity-100');

      setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
        toast.classList.remove('translate-y-0', 'opacity-100');
      }, 4000);
    }

    async function subscribe(plan) {
      const buttons = document.querySelectorAll('.subscribe-btn');
      buttons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Chargement...';
      });

      try {
        const formData = new FormData();
        formData.append('create_checkout', '1');
        formData.append('plan', plan);

        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.error) {
          showToast(data.error);
          buttons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = '<span>Choisir ce plan</span><i class="fa-solid fa-arrow-right"></i>';
          });
          return;
        }

        if (data.checkout_url) {
          window.location.href = data.checkout_url;
        }
      } catch (error) {
        showToast('Une erreur est survenue. Veuillez réessayer.');
        buttons.forEach(btn => {
          btn.disabled = false;
          btn.innerHTML = '<span>Choisir ce plan</span><i class="fa-solid fa-arrow-right"></i>';
        });
      }
    }
  </script>
</body>

</html>