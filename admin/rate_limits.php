<?php

/**
 * Interface d'administration pour gérer les plans de rate limiting des utilisateurs
 * Accessible uniquement aux administrateurs
 */

session_start();
require_once __DIR__ . '/../zone_membres/db.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['user_id'])) {
  header('Location: ../zone_membres/login.php');
  exit();
}

$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['is_admin']) {
  header('Location: ../index.php');
  exit();
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_plan') {
    $userId = (int)$_POST['user_id'];
    $newPlan = $_POST['plan'];

    // Définir les limites selon le plan
    $plans = [
      'free' => ['daily' => 10, 'hourly' => 3, 'monthly' => 200],
      'basic' => ['daily' => 50, 'hourly' => 10, 'monthly' => 1000],
      'premium' => ['daily' => 200, 'hourly' => 50, 'monthly' => 5000],
      'ultra' => ['daily' => -1, 'hourly' => 100, 'monthly' => -1]
    ];

    if (isset($plans[$newPlan])) {
      $stmt = $pdo->prepare("
        UPDATE users 
        SET user_plan = ?, daily_limit = ?, hourly_limit = ?, monthly_limit = ?
        WHERE id = ?
      ");
      $stmt->execute([
        $newPlan,
        $plans[$newPlan]['daily'],
        $plans[$newPlan]['hourly'],
        $plans[$newPlan]['monthly'],
        $userId
      ]);

      $success = "Plan modifié avec succès pour l'utilisateur #$userId";
    }
  }

  if ($action === 'reset_limits') {
    $userId = (int)$_POST['user_id'];
    $stmt = $pdo->prepare("
      UPDATE users 
      SET current_hourly_count = 0, 
          current_daily_count = 0, 
          current_monthly_count = 0,
          last_hourly_reset = NOW(),
          last_daily_reset = NOW(),
          last_monthly_reset = NOW()
      WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $success = "Compteurs réinitialisés pour l'utilisateur #$userId";
  }

  if ($action === 'custom_limits') {
    $userId = (int)$_POST['user_id'];
    $hourly = (int)$_POST['hourly_limit'];
    $daily = (int)$_POST['daily_limit'];
    $monthly = (int)$_POST['monthly_limit'];

    $stmt = $pdo->prepare("
      UPDATE users 
      SET user_plan = 'custom', daily_limit = ?, hourly_limit = ?, monthly_limit = ?
      WHERE id = ?
    ");
    $stmt->execute([$daily, $hourly, $monthly, $userId]);
    $success = "Limites personnalisées définies pour l'utilisateur #$userId";
  }
}

// Récupérer tous les utilisateurs avec leurs stats
$users = $pdo->query("
  SELECT 
    id, username, email, user_plan,
    daily_limit, hourly_limit, monthly_limit,
    current_daily_count, current_hourly_count, current_monthly_count,
    last_daily_reset, last_hourly_reset, last_monthly_reset,
    created_at
  FROM users
  ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$stats = $pdo->query("
  SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN user_plan = 'free' THEN 1 ELSE 0 END) as free_users,
    SUM(CASE WHEN user_plan = 'basic' THEN 1 ELSE 0 END) as basic_users,
    SUM(CASE WHEN user_plan = 'premium' THEN 1 ELSE 0 END) as premium_users,
    SUM(CASE WHEN user_plan = 'ultra' THEN 1 ELSE 0 END) as ultra_users,
    SUM(current_daily_count) as total_daily_usage,
    SUM(current_monthly_count) as total_monthly_usage
  FROM users
")->fetch(PDO::FETCH_ASSOC);
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
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Gestion Rate Limiting - Admin NxtGenAI</title>
  <link href="../src/output.css" rel="stylesheet">
  <!-- Font Awesome non-bloquant -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" media="print" onload="this.media='all'" />
</head>

<body class="bg-neutral-900 text-gray-200 overflow-x-hidden">
  <div class="container mx-auto px-4 py-8 max-w-7xl overflow-x-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between gap-4 mb-8">
      <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-white flex items-center">
        <img src="../assets/images/rate_limit_header.svg" alt="Rate Limiting" class="w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8 mr-2" />
        <span class="hidden md:inline">Gestion Rate Limiting</span>
        <span class="md:hidden">Rate Limits</span>
      </h1>
      <!-- Navigation Desktop -->
      <div class="hidden md:flex gap-2">
        <a href="settings.php" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-sm text-neutral-300 hover:text-white transition-colors flex items-center">
          <i class="fa-solid fa-shield-halved mr-1.5 text-blue-400"></i>Admin
        </a>
        <a href="models_manager.php" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-sm text-neutral-300 hover:text-white transition-colors flex items-center">
          <i class="fa-solid fa-robot mr-1.5 text-purple-400"></i>Modèles
        </a>
        <a href="../index.php" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-sm text-neutral-300 hover:text-white transition-colors flex items-center">
          <i class="fa-solid fa-home mr-1.5 text-green-400"></i>Accueil
        </a>
      </div>
      <!-- Navigation Mobile - Menu hamburger -->
      <div class="md:hidden relative">
        <button onclick="toggleNavMenu()" class="p-2.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-neutral-300 hover:text-white transition-colors">
          <i class="fa-solid fa-bars text-lg"></i>
        </button>
        <div id="navMenu" class="hidden absolute right-0 top-full mt-2 bg-neutral-800 border border-neutral-700 rounded-lg shadow-xl z-50 min-w-40 py-1">
          <a href="settings.php" class="block px-4 py-3 text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors">
            <i class="fa-solid fa-shield-halved text-blue-400 w-5 mr-2"></i>Admin
          </a>
          <a href="models_manager.php" class="block px-4 py-3 text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors">
            <i class="fa-solid fa-robot text-purple-400 w-5 mr-2"></i>Modèles
          </a>
          <a href="../index.php" class="block px-4 py-3 text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors">
            <i class="fa-solid fa-home text-green-400 w-5 mr-2"></i>Accueil
          </a>
        </div>
      </div>
    </div>

    <?php if (isset($success)): ?>
      <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4 mb-6">
        <p class="text-green-400 flex items-center gap-2">
          <i class="fa-solid fa-check-circle"></i>
          <?= htmlspecialchars($success) ?>
        </p>
      </div>
    <?php endif; ?>

    <!-- Statistiques globales -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-8">
      <div class="bg-neutral-800 border border-neutral-700 rounded-lg p-3 sm:p-4">
        <div class="text-neutral-400 text-xs sm:text-sm mb-1">Total utilisateurs</div>
        <div class="text-xl sm:text-2xl font-bold"><?= $stats['total_users'] ?></div>
      </div>
      <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-3 sm:p-4">
        <div class="text-blue-400 text-xs sm:text-sm mb-1">Plan Free</div>
        <div class="text-xl sm:text-2xl font-bold text-blue-400"><?= $stats['free_users'] ?></div>
      </div>
      <div class="bg-purple-500/10 border border-purple-500/30 rounded-lg p-3 sm:p-4">
        <div class="text-purple-400 text-xs sm:text-sm mb-1">Plan Basic</div>
        <div class="text-xl sm:text-2xl font-bold text-purple-400"><?= $stats['basic_users'] ?></div>
      </div>
      <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3 sm:p-4">
        <div class="text-amber-400 text-xs sm:text-sm mb-1">Plan Premium</div>
        <div class="text-xl sm:text-2xl font-bold text-amber-400"><?= $stats['premium_users'] ?></div>
      </div>
      <div class="bg-rose-500/10 border border-rose-500/30 rounded-lg p-3 sm:p-4 col-span-2 sm:col-span-1">
        <div class="text-rose-400 text-xs sm:text-sm mb-1">Plan Ultra</div>
        <div class="text-xl sm:text-2xl font-bold text-rose-400"><?= $stats['ultra_users'] ?></div>
      </div>
    </div>

    <!-- Légende des plans -->
    <div class="bg-neutral-800 border border-neutral-700 rounded-lg p-4 sm:p-6 mb-8">
      <h2 class="text-lg sm:text-xl font-semibold mb-4"><i class="fa-solid fa-list-check mr-2 text-green-500"></i>Plans disponibles</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
          <h3 class="text-blue-400 font-semibold mb-2"><i class="fa-solid fa-gift mr-1"></i>Free</h3>
          <ul class="space-y-1 text-sm text-neutral-300">
            <li>• 3 messages/heure</li>
            <li>• 10 messages/jour</li>
            <li>• 200 messages/mois</li>
          </ul>
        </div>
        <div class="bg-purple-500/10 border border-purple-500/30 rounded-lg p-4">
          <h3 class="text-purple-400 font-semibold mb-2"><i class="fa-solid fa-star mr-1"></i>Basic</h3>
          <ul class="space-y-1 text-sm text-neutral-300">
            <li>• 10 messages/heure</li>
            <li>• 50 messages/jour</li>
            <li>• 1000 messages/mois</li>
          </ul>
        </div>
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4">
          <h3 class="text-amber-400 font-semibold mb-2"><i class="fa-solid fa-crown mr-1"></i>Premium</h3>
          <ul class="space-y-1 text-sm text-neutral-300">
            <li>• 50 messages/heure</li>
            <li>• 200 messages/jour</li>
            <li>• 5000 messages/mois</li>
          </ul>
        </div>
        <div class="bg-rose-500/10 border border-rose-500/30 rounded-lg p-4">
          <h3 class="text-rose-400 font-semibold mb-2"><i class="fa-solid fa-rocket mr-1"></i>Ultra</h3>
          <ul class="space-y-1 text-sm text-neutral-300">
            <li>• 100 messages/heure</li>
            <li>• ∞ Illimité/jour</li>
            <li>• ∞ Illimité/mois</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="bg-neutral-800 border border-neutral-700 rounded-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-neutral-900/50">
            <tr>
              <th class="text-left p-4 text-sm font-semibold text-neutral-400">ID</th>
              <th class="text-left p-4 text-sm font-semibold text-neutral-400">Utilisateur</th>
              <th class="text-left p-4 text-sm font-semibold text-neutral-400">Plan actuel</th>
              <th class="text-left p-4 text-sm font-semibold text-neutral-400">Usage Heure</th>
              <th class="text-left p-4 text-sm font-semibold text-neutral-400">Usage Jour</th>
              <th class="text-left p-4 text-sm font-semibold text-neutral-400">Usage Mois</th>
              <th class="text-right p-4 text-sm font-semibold text-neutral-400">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-700">
            <?php foreach ($users as $user): ?>
              <tr class="hover:bg-neutral-700/30 transition-colors">
                <td class="p-4 text-sm">#<?= $user['id'] ?></td>
                <td class="p-4">
                  <div class="font-medium"><?= htmlspecialchars($user['username']) ?></div>
                  <div class="text-xs text-neutral-400"><?= htmlspecialchars($user['email']) ?></div>
                </td>
                <td class="p-4">
                  <span class="px-3 py-1 rounded-full text-xs font-semibold <?php
                                                                            echo match ($user['user_plan']) {
                                                                              'free' => 'bg-blue-500/20 text-blue-400',
                                                                              'basic' => 'bg-purple-500/20 text-purple-400',
                                                                              'premium' => 'bg-amber-500/20 text-amber-400',
                                                                              'ultra' => 'bg-rose-500/20 text-rose-400',
                                                                              default => 'bg-neutral-500/20 text-neutral-400'
                                                                            };
                                                                            ?>">
                    <?= htmlspecialchars(ucfirst($user['user_plan'])) ?>
                  </span>
                </td>
                <td class="p-4 text-sm">
                  <span class="font-mono"><?= $user['current_hourly_count'] ?></span>
                  <span class="text-neutral-500">/</span>
                  <span class="text-neutral-400 font-mono"><?= $user['hourly_limit'] == -1 ? '∞' : $user['hourly_limit'] ?></span>
                </td>
                <td class="p-4 text-sm">
                  <span class="font-mono"><?= $user['current_daily_count'] ?></span>
                  <span class="text-neutral-500">/</span>
                  <span class="text-neutral-400 font-mono"><?= $user['daily_limit'] == -1 ? '∞' : $user['daily_limit'] ?></span>
                </td>
                <td class="p-4 text-sm">
                  <span class="font-mono"><?= $user['current_monthly_count'] ?></span>
                  <span class="text-neutral-500">/</span>
                  <span class="text-neutral-400 font-mono"><?= $user['monthly_limit'] == -1 ? '∞' : $user['monthly_limit'] ?></span>
                </td>
                <td class="p-4 text-right">
                  <!-- Version Desktop -->
                  <div class="hidden md:flex items-center justify-end gap-2">
                    <button onclick="openPlanModal(<?= $user['id'] ?>, '<?= $user['username'] ?>', '<?= $user['user_plan'] ?>')" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-sm text-neutral-300 hover:text-white transition-colors">
                      <i class="fa-solid fa-pen mr-1.5 text-blue-400"></i>Modifier
                    </button>
                    <button onclick="openCustomModal(<?= $user['id'] ?>, '<?= $user['username'] ?>', <?= $user['hourly_limit'] ?>, <?= $user['daily_limit'] ?>, <?= $user['monthly_limit'] ?>)" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-sm text-neutral-300 hover:text-white transition-colors">
                      <i class="fa-solid fa-sliders mr-1.5 text-purple-400"></i>Personnaliser
                    </button>
                    <form method="POST" class="inline" onsubmit="return confirm('Réinitialiser les compteurs pour <?= htmlspecialchars($user['username']) ?> ?')">
                      <input type="hidden" name="action" value="reset_limits">
                      <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                      <button type="submit" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-sm text-neutral-300 hover:text-white transition-colors">
                        <i class="fa-solid fa-rotate-right mr-1.5 text-amber-400"></i>Reset
                      </button>
                    </form>
                  </div>
                  <!-- Version Mobile - Menu 3 points -->
                  <div class="md:hidden relative">
                    <button onclick="toggleActionsMenu(<?= $user['id'] ?>)" class="p-2 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-neutral-300 hover:text-white transition-colors">
                      <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div id="actionsMenu-<?= $user['id'] ?>" class="hidden absolute right-0 top-full mt-1 bg-neutral-800 border border-neutral-700 rounded-lg shadow-xl z-50 min-w-40 py-1">
                      <button onclick="openPlanModal(<?= $user['id'] ?>, '<?= $user['username'] ?>', '<?= $user['user_plan'] ?>'); closeAllMenus();" class="w-full px-4 py-2.5 text-left text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-pen text-blue-400 w-4"></i>Modifier
                      </button>
                      <button onclick="openCustomModal(<?= $user['id'] ?>, '<?= $user['username'] ?>', <?= $user['hourly_limit'] ?>, <?= $user['daily_limit'] ?>, <?= $user['monthly_limit'] ?>); closeAllMenus();" class="w-full px-4 py-2.5 text-left text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-sliders text-purple-400 w-4"></i>Personnaliser
                      </button>
                      <form method="POST" onsubmit="return confirm('Réinitialiser les compteurs pour <?= htmlspecialchars($user['username']) ?> ?')">
                        <input type="hidden" name="action" value="reset_limits">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="w-full px-4 py-2.5 text-left text-sm text-neutral-300 hover:bg-neutral-700 hover:text-white transition-colors flex items-center gap-2">
                          <i class="fa-solid fa-rotate-right text-amber-400 w-4"></i>Reset
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal Changement de plan -->
  <div id="planModal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-50">
    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-neutral-800 border border-neutral-700 rounded-lg p-6 max-w-md w-full mx-4">
      <h3 class="text-xl font-bold mb-4" id="planModalTitle">Changer le plan utilisateur</h3>
      <form method="POST">
        <input type="hidden" name="action" value="update_plan">
        <input type="hidden" name="user_id" id="planUserId">

        <div class="space-y-3 mb-6">
          <label class="flex items-center p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg cursor-pointer hover:bg-blue-500/20 transition-colors">
            <input type="radio" name="plan" value="free" class="mr-3">
            <div>
              <div class="font-semibold text-blue-400"><i class="fa-solid fa-gift mr-1"></i>Free</div>
              <div class="text-xs text-neutral-400">3/h • 10/j • 200/mois</div>
            </div>
          </label>

          <label class="flex items-center p-3 bg-purple-500/10 border border-purple-500/30 rounded-lg cursor-pointer hover:bg-purple-500/20 transition-colors">
            <input type="radio" name="plan" value="basic" class="mr-3">
            <div>
              <div class="font-semibold text-purple-400"><i class="fa-solid fa-star mr-1"></i>Basic</div>
              <div class="text-xs text-neutral-400">10/h • 50/j • 1000/mois</div>
            </div>
          </label>

          <label class="flex items-center p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg cursor-pointer hover:bg-amber-500/20 transition-colors">
            <input type="radio" name="plan" value="premium" class="mr-3">
            <div>
              <div class="font-semibold text-amber-400"><i class="fa-solid fa-crown mr-1"></i>Premium</div>
              <div class="text-xs text-neutral-400">50/h • 200/j • 5000/mois</div>
            </div>
          </label>

          <label class="flex items-center p-3 bg-rose-500/10 border border-rose-500/30 rounded-lg cursor-pointer hover:bg-rose-500/20 transition-colors">
            <input type="radio" name="plan" value="ultra" class="mr-3">
            <div>
              <div class="font-semibold text-rose-400"><i class="fa-solid fa-rocket mr-1"></i>Ultra</div>
              <div class="text-xs text-neutral-400">100/h • Illimité</div>
            </div>
          </label>
        </div>

        <div class="flex gap-3">
          <button type="button" onclick="closePlanModal()" class="flex-1 px-4 py-2 bg-neutral-700 hover:bg-neutral-600 rounded-lg transition-colors">
            Annuler
          </button>
          <button type="submit" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-500 rounded-lg transition-colors">
            Confirmer
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Limites personnalisées -->
  <div id="customModal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-50">
    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-neutral-800 border border-neutral-700 rounded-lg p-6 max-w-md w-full mx-4">
      <h3 class="text-xl font-bold mb-4" id="customModalTitle">Limites personnalisées</h3>
      <form method="POST">
        <input type="hidden" name="action" value="custom_limits">
        <input type="hidden" name="user_id" id="customUserId">

        <div class="space-y-4 mb-6">
          <div>
            <label class="block text-sm font-medium text-neutral-300 mb-2">Messages par heure</label>
            <input type="number" name="hourly_limit" id="customHourly" min="-1" class="w-full bg-neutral-900 border border-neutral-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
            <p class="text-xs text-neutral-500 mt-1">-1 = illimité</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-300 mb-2">Messages par jour</label>
            <input type="number" name="daily_limit" id="customDaily" min="-1" class="w-full bg-neutral-900 border border-neutral-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
            <p class="text-xs text-neutral-500 mt-1">-1 = illimité</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-300 mb-2">Messages par mois</label>
            <input type="number" name="monthly_limit" id="customMonthly" min="-1" class="w-full bg-neutral-900 border border-neutral-700 rounded-lg px-4 py-2 focus:border-blue-500 focus:outline-none">
            <p class="text-xs text-neutral-500 mt-1">-1 = illimité</p>
          </div>
        </div>

        <div class="flex gap-3">
          <button type="button" onclick="closeCustomModal()" class="flex-1 px-4 py-2 bg-neutral-700 hover:bg-neutral-600 rounded-lg transition-colors">
            Annuler
          </button>
          <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 hover:bg-purple-500 rounded-lg transition-colors">
            Appliquer
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openPlanModal(userId, username, currentPlan) {
      const modal = document.getElementById('planModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.getElementById('planModalTitle').textContent = `Changer le plan de ${username}`;
      document.getElementById('planUserId').value = userId;

      // Sélectionner le plan actuel
      document.querySelectorAll('#planModal input[name="plan"]').forEach(input => {
        if (input.value === currentPlan) {
          input.checked = true;
        }
      });
    }

    function closePlanModal() {
      const modal = document.getElementById('planModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    function openCustomModal(userId, username, hourly, daily, monthly) {
      const modal = document.getElementById('customModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.getElementById('customModalTitle').textContent = `Limites personnalisées - ${username}`;
      document.getElementById('customUserId').value = userId;
      document.getElementById('customHourly').value = hourly;
      document.getElementById('customDaily').value = daily;
      document.getElementById('customMonthly').value = monthly;
    }

    function closeCustomModal() {
      const modal = document.getElementById('customModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    // Fermer les modals en cliquant à l'extérieur
    document.getElementById('planModal').addEventListener('click', function(e) {
      if (e.target === this) closePlanModal();
    });

    document.getElementById('customModal').addEventListener('click', function(e) {
      if (e.target === this) closeCustomModal();
    });

    // Menu actions mobile
    function toggleActionsMenu(userId) {
      closeAllMenus();
      const menu = document.getElementById(`actionsMenu-${userId}`);
      menu.classList.toggle('hidden');
    }

    function closeAllMenus() {
      document.querySelectorAll('[id^="actionsMenu-"]').forEach(menu => {
        menu.classList.add('hidden');
      });
      document.getElementById('navMenu')?.classList.add('hidden');
    }

    // Menu navigation mobile
    function toggleNavMenu() {
      closeAllMenus();
      document.getElementById('navMenu').classList.toggle('hidden');
    }

    // Fermer le menu en cliquant ailleurs
    document.addEventListener('click', function(e) {
      if (!e.target.closest('[id^="actionsMenu-"]') && !e.target.closest('button[onclick^="toggleActionsMenu"]') &&
        !e.target.closest('#navMenu') && !e.target.closest('button[onclick="toggleNavMenu()"]')) {
        closeAllMenus();
      }
    });
  </script>
</body>

</html>