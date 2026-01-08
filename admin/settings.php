<?php
session_start();

// Vérification que l'utilisateur est admin
require_once '../zone_membres/db.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../zone_membres/login.php');
  exit();
}

try {
  $checkAdmin = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
  $checkAdmin->execute([$_SESSION['user_id']]);
  $userData = $checkAdmin->fetch();

  if (!$userData || $userData['is_admin'] != 1) {
    header('Location: ../index.php');
    exit();
  }
} catch (PDOException $ex) {
  header('Location: ../index.php');
  exit();
}

// Traitement des actions admin
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Promouvoir/rétrograder un utilisateur
  if ($action === 'toggle_admin') {
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId > 0 && $userId != $_SESSION['user_id']) {
      try {
        $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 - is_admin WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "Statut administrateur modifié avec succès.";
      } catch (PDOException $ex) {
        $error = "Erreur lors de la modification: " . $ex->getMessage();
      }
    } else {
      $error = "Vous ne pouvez pas modifier votre propre statut administrateur.";
    }
  }

  // Supprimer un utilisateur
  if ($action === 'delete_user') {
    $userId = intval($_POST['user_id'] ?? 0);
    if ($userId > 0 && $userId != $_SESSION['user_id']) {
      try {
        // Supprimer d'abord les données de l'utilisateur si nécessaire
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $message = "Utilisateur supprimé avec succès.";
      } catch (PDOException $ex) {
        $error = "Erreur lors de la suppression: " . $ex->getMessage();
      }
    } else {
      $error = "Vous ne pouvez pas supprimer votre propre compte.";
    }
  }

  // Réinitialiser le mot de passe d'un utilisateur
  if ($action === 'reset_password') {
    $userId = intval($_POST['user_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';

    if ($userId > 0 && !empty($newPassword)) {
      try {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        $message = "Mot de passe réinitialisé avec succès.";
      } catch (PDOException $ex) {
        $error = "Erreur lors de la réinitialisation: " . $ex->getMessage();
      }
    } else {
      $error = "Données invalides.";
    }
  }
}

// Récupérer la liste des utilisateurs
$users = [];
try {
  $stmt = $pdo->query("SELECT id, username, email, is_admin, created_at FROM users ORDER BY created_at DESC");
  $users = $stmt->fetchAll();
} catch (PDOException $ex) {
  $error = "Erreur lors de la récupération des utilisateurs: " . $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr" class="dark">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <title>Paramètres Admin - NxtGenAI</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    @font-face {
      font-family: 'TikTok Sans';
      src: url('../assets/fonts/TikTok_Sans/static/TikTokSans-Light.ttf') format('truetype');
      font-weight: 300;
    }

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

    /* Light mode background */
    body {
      background-color: #f8fafc;
    }

    /* Dark mode background */
    .dark body {
      background-color: oklch(21% 0.006 285.885);
    }

    ::selection {
      background: #d1d5db;
    }

    .dark ::selection {
      background: #404040;
    }

    /* Scrollbar fine et discrète */
    * {
      scrollbar-width: thin;
      scrollbar-color: rgba(75, 85, 99, 0.5) transparent;
    }

    *::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }

    *::-webkit-scrollbar-track {
      background: transparent;
    }

    *::-webkit-scrollbar-thumb {
      background: rgba(75, 85, 99, 0.5);
      border-radius: 3px;
    }

    *::-webkit-scrollbar-thumb:hover {
      background: rgba(75, 85, 99, 0.8);
    }

    /* Menu dropdown z-index élevé */
    [id^="userActionsMenu-"] {
      z-index: 100 !important;
    }

    /* Bouton retour en haut */
    #scrollToTopBtn {
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease;
    }

    #scrollToTopBtn.visible {
      opacity: 1;
      visibility: visible;
    }

    #scrollToTopBtn:hover {
      transform: translateY(-2px);
    }
  </style>
</head>

<body class="min-h-screen text-gray-600 dark:text-neutral-400 overflow-x-hidden">
  <header class="fixed top-0 left-0 right-0 z-50 bg-white/90 dark:bg-[oklch(21%_0.006_285.885)]/90 backdrop-blur-md border-b border-gray-200 dark:border-neutral-700/50">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
      <a href="../index.php" class="flex items-center gap-2.5 text-sm font-medium text-gray-800 dark:text-neutral-200 hover:text-gray-900 dark:hover:text-white transition-colors">
        <img src="../assets/images/logo.svg" alt="NxtGenAI" class="w-7 h-7" />
        <span class="hidden sm:inline">NxtGenAI</span>
      </a>
      <!-- Navigation Desktop -->
      <nav class="hidden md:flex items-center gap-4">
        <span class="text-sm text-gray-500 dark:text-neutral-400"><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></span>
        <div class="w-px h-4 bg-gray-300 dark:bg-neutral-700"></div>
        <a href="../zone_membres/dashboard.php" class="text-sm text-gray-500 dark:text-neutral-400 hover:text-green-600 dark:hover:text-green-400 transition-colors">
          <i class="fa-solid fa-user mr-1.5"></i>Compte
        </a>
        <a href="../zone_membres/logout.php" class="text-sm text-gray-400 dark:text-neutral-500 hover:text-red-500 dark:hover:text-red-400 transition-colors">
          <i class="fa-solid fa-sign-out-alt"></i>
        </a>
      </nav>
      <!-- Navigation Mobile - Menu hamburger -->
      <div class="md:hidden relative">
        <button onclick="toggleNavMenu()" class="p-2.5 bg-gray-100 dark:bg-neutral-700 hover:bg-gray-200 dark:hover:bg-neutral-600 border border-gray-200 dark:border-neutral-600 rounded-lg text-gray-600 dark:text-neutral-300 transition-colors">
          <i class="fa-solid fa-bars text-lg"></i>
        </button>
        <div id="navMenu" class="hidden absolute right-0 top-full mt-2 bg-white dark:bg-neutral-800 border border-gray-200 dark:border-neutral-700 rounded-lg shadow-xl z-50 min-w-[180px] py-1">
          <div class="px-4 py-2 border-b border-gray-100 dark:border-neutral-700">
            <span class="text-sm font-medium text-gray-700 dark:text-neutral-300"><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <a href="models_manager.php" class="block px-4 py-3 text-sm text-gray-600 dark:text-neutral-300 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
            <i class="fa-solid fa-robot text-purple-500 dark:text-purple-400 w-5 mr-2"></i>Modèles
          </a>
          <a href="rate_limits.php" class="block px-4 py-3 text-sm text-gray-600 dark:text-neutral-300 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
            <i class="fa-solid fa-gauge-high text-amber-500 dark:text-amber-400 w-5 mr-2"></i>Rate Limits
          </a>
          <a href="../zone_membres/dashboard.php" class="block px-4 py-3 text-sm text-gray-600 dark:text-neutral-300 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
            <i class="fa-solid fa-user text-green-500 w-5 mr-2"></i>Compte
          </a>
          <a href="../index.php" class="block px-4 py-3 text-sm text-gray-600 dark:text-neutral-300 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
            <i class="fa-solid fa-home text-blue-500 w-5 mr-2"></i>Accueil
          </a>
          <div class="border-t border-gray-100 dark:border-neutral-700 mt-1 pt-1">
            <a href="../zone_membres/logout.php" class="block px-4 py-3 text-sm text-red-500 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
              <i class="fa-solid fa-sign-out-alt w-5 mr-2"></i>Déconnexion
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="pt-20 pb-10 min-h-screen px-4">
    <div class="max-w-4xl mx-auto">
      <div class="mb-8">
        <a href="../zone_membres/dashboard.php" class="text-sm text-gray-500 dark:text-neutral-400 hover:text-green-600 dark:hover:text-green-400 transition-colors">
          <i class="fa-solid fa-chevron-left mr-1"></i>Retour au tableau de bord
        </a>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mt-4 mb-2">
          <i class="fa-solid fa-shield-halved text-purple-500 dark:text-purple-400 mr-2"></i>Paramètres administrateur
        </h1>
        <p class="text-gray-500 dark:text-neutral-400">Gérez les utilisateurs et les paramètres de l'application</p>
      </div>

      <?php if (!empty($message)): ?>
        <div class="mb-6 px-4 py-3 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center gap-3">
          <i class="fa-solid fa-check-circle text-green-400"></i>
          <span class="text-sm text-green-400"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="mb-6 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center gap-3">
          <i class="fa-solid fa-exclamation-circle text-red-400"></i>
          <span class="text-sm text-red-400"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      <?php endif; ?>
      <!-- option admin supplémentaires -->
      <div class="bg-white dark:bg-neutral-800/50 border border-gray-200 dark:border-neutral-700/50 rounded-2xl p-6 mb-8 shadow-sm dark:shadow-none">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-6">
          <i class="fa-solid fa-gear text-purple-500 dark:text-purple-400 mr-2"></i>Options supplémentaires
        </h2>
        <div class="flex flex-wrap gap-3">
          <a href="models_manager.php" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-500/20 hover:bg-purple-500/30 border border-purple-500/20 rounded-lg text-sm text-purple-600 dark:text-purple-400 font-medium transition-colors">
            <i class="fa-solid fa-robot text-purple-500 dark:text-purple-400"></i>Gestion des modèles
          </a>
          <a href="rate_limits.php" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/20 rounded-lg text-sm text-amber-600 dark:text-amber-400 font-medium transition-colors">
            <i class="fa-solid fa-gauge-high text-amber-500 dark:text-amber-400"></i>Rate Limiting
          </a>
        </div>
      </div>
      <!-- Tableau des utilisateurs -->
      <div class="bg-white dark:bg-neutral-800/50 border border-gray-200 dark:border-neutral-700/50 rounded-2xl p-6 shadow-sm dark:shadow-none">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-6">
          <i class="fa-solid fa-users text-purple-500 dark:text-purple-400 mr-2"></i>Gestion des utilisateurs
        </h2>

        <?php if (empty($users)): ?>
          <p class="text-gray-500 dark:text-neutral-400 text-center py-8">Aucun utilisateur trouvé.</p>
        <?php else: ?>
          <div class="overflow-visible">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-neutral-700/50">
                  <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-neutral-300">Utilisateur</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-neutral-300">Email</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-neutral-300">Statut</th>
                  <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-neutral-300">Inscrit</th>
                  <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-neutral-300">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <tr class="border-b border-gray-100 dark:border-neutral-700/30 hover:bg-gray-50 dark:hover:bg-neutral-700/20 transition-colors">
                    <td class="py-3 px-4">
                      <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center text-xs font-semibold text-purple-500 dark:text-purple-400">
                          <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <span class="text-gray-700 dark:text-neutral-200 font-medium"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                          <span class="text-xs px-2 py-1 bg-green-500/20 text-green-600 dark:text-green-400 rounded">Vous</span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="py-3 px-4 text-gray-500 dark:text-neutral-400">
                      <?php echo htmlspecialchars($user['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td class="py-3 px-4">
                      <?php if ($user['is_admin']): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-500/20 border border-purple-500/30 rounded-lg text-xs text-purple-600 dark:text-purple-400 font-medium">
                          <i class="fa-solid fa-crown"></i>Admin
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-200 dark:bg-neutral-700/50 border border-gray-300 dark:border-neutral-600/30 rounded-lg text-xs text-gray-600 dark:text-neutral-400 font-medium">
                          <i class="fa-solid fa-user"></i>Utilisateur
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-gray-400 dark:text-neutral-500 text-xs">
                      <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                    </td>
                    <td class="py-3 px-4 text-right">
                      <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <!-- Desktop: boutons visibles -->
                        <div class="hidden lg:flex items-center justify-end gap-2">
                          <button type="button" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-xs text-neutral-300 hover:text-white transition-colors cursor-pointer"
                            onclick="openConfirmModal('toggle_admin', <?php echo $user['id']; ?>, '<?php echo $user['is_admin'] ? 'Rétrograder' : 'Promouvoir'; ?> cet utilisateur ?', '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')">
                            <i class="fa-solid fa-<?php echo $user['is_admin'] ? 'arrow-down' : 'arrow-up'; ?> mr-1.5 text-purple-400"></i><?php echo $user['is_admin'] ? 'Rétrograder' : 'Promouvoir'; ?>
                          </button>
                          <button type="button" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-xs text-neutral-300 hover:text-white transition-colors cursor-pointer"
                            onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')">
                            <i class="fa-solid fa-key mr-1.5 text-amber-400"></i>MDP
                          </button>
                          <button type="button" class="px-3 py-1.5 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-xs text-neutral-300 hover:text-white transition-colors cursor-pointer"
                            onclick="openConfirmModal('delete_user', <?php echo $user['id']; ?>, 'Êtes-vous sûr ? Cette action est irréversible.', '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')">
                            <i class="fa-solid fa-trash mr-1.5 text-red-400"></i>Supprimer
                          </button>
                        </div>
                        <!-- Mobile: menu 3 points -->
                        <div class="lg:hidden relative">
                          <button onclick="toggleUserActionsMenu(<?php echo $user['id']; ?>)" class="p-2 bg-neutral-700 hover:bg-neutral-600 border border-neutral-600 rounded-lg text-neutral-300 hover:text-white transition-colors">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                          </button>
                          <div id="userActionsMenu-<?php echo $user['id']; ?>" class="hidden absolute right-0 top-full mt-1 bg-white dark:bg-neutral-800 border border-gray-200 dark:border-neutral-700 rounded-lg shadow-xl z-50 min-w-[160px] py-1">
                            <button onclick="closeAllUserMenus(); openConfirmModal('toggle_admin', <?php echo $user['id']; ?>, '<?php echo $user['is_admin'] ? 'Rétrograder' : 'Promouvoir'; ?> cet utilisateur ?', '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')" class="w-full text-left px-4 py-3 text-sm text-gray-600 dark:text-neutral-300 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
                              <i class="fa-solid fa-<?php echo $user['is_admin'] ? 'arrow-down' : 'arrow-up'; ?> text-purple-400 w-5 mr-2"></i><?php echo $user['is_admin'] ? 'Rétrograder' : 'Promouvoir'; ?>
                            </button>
                            <button onclick="closeAllUserMenus(); openResetModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')" class="w-full text-left px-4 py-3 text-sm text-gray-600 dark:text-neutral-300 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
                              <i class="fa-solid fa-key text-amber-400 w-5 mr-2"></i>Réinitialiser MDP
                            </button>
                            <button onclick="closeAllUserMenus(); openConfirmModal('delete_user', <?php echo $user['id']; ?>, 'Êtes-vous sûr ? Cette action est irréversible.', '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>')" class="w-full text-left px-4 py-3 text-sm text-red-500 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
                              <i class="fa-solid fa-trash w-5 mr-2"></i>Supprimer
                            </button>
                          </div>
                        </div>
                      <?php else: ?>
                        <span class="text-xs text-gray-400 dark:text-neutral-500">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="text-xs text-gray-400 dark:text-neutral-500 mt-4">Total: <?php echo count($users); ?> utilisateur(s)</p>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Modal de confirmation -->
  <div id="confirmModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-neutral-800/95 border border-gray-200 dark:border-neutral-700/50 rounded-3xl p-8 w-full max-w-sm mx-4 shadow-2xl">
      <div class="flex items-center justify-center w-12 h-12 rounded-full bg-amber-500/20 mb-6">
        <i class="fa-solid fa-exclamation text-amber-500 dark:text-amber-400 text-lg"></i>
      </div>
      <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-2" id="confirmTitle">Confirmation</h3>
      <p class="text-gray-500 dark:text-neutral-400 mb-8" id="confirmMessage">Êtes-vous sûr ?</p>

      <form method="POST" id="confirmForm" style="display: none;">
        <input type="hidden" name="action" id="confirmAction">
        <input type="hidden" name="user_id" id="confirmUserId">
      </form>

      <div class="flex gap-3">
        <button type="button" class="flex-1 px-4 py-2.5 bg-gray-200 dark:bg-neutral-700/50 hover:bg-gray-300 dark:hover:bg-neutral-700 border border-gray-300 dark:border-neutral-600/30 rounded-xl text-sm text-gray-700 dark:text-neutral-300 font-medium transition-colors cursor-pointer" onclick="closeConfirmModal()">
          <i class="fa-solid fa-times mr-2"></i>Annuler
        </button>
        <button type="button" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 rounded-xl text-sm text-white font-medium transition-colors shadow-lg cursor-pointer" onclick="submitConfirm()">
          <i class="fa-solid fa-check mr-2"></i>Confirmer
        </button>
      </div>
    </div>
  </div>

  <!-- Modal de réinitialisation du mot de passe -->
  <div id="resetModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white dark:bg-neutral-800/95 border border-gray-200 dark:border-neutral-700/50 rounded-3xl p-8 w-full max-w-sm mx-4 shadow-2xl">
      <div class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-500/20 mb-6">
        <i class="fa-solid fa-key text-blue-500 dark:text-blue-400 text-lg"></i>
      </div>
      <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-2">Réinitialiser le mot de passe</h3>
      <p class="text-gray-500 dark:text-neutral-400 mb-6">Utilisateur: <span id="resetUsername" class="font-semibold text-gray-700 dark:text-neutral-200"></span></p>

      <form method="POST" id="resetForm">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="resetUserId">

        <div class="mb-6">
          <label for="newPassword" class="block text-sm font-medium text-gray-700 dark:text-neutral-300 mb-2">Nouveau mot de passe</label>
          <input type="password" id="newPassword" name="new_password" required
            class="w-full px-4 py-2.5 bg-gray-50 dark:bg-neutral-700/50 border border-gray-300 dark:border-neutral-600/30 rounded-xl text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-neutral-500 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/20 transition-all"
            placeholder="Entrez le nouveau mot de passe">
        </div>

        <div class="flex gap-3">
          <button type="button" class="flex-1 px-4 py-2.5 bg-gray-200 dark:bg-neutral-700/50 hover:bg-gray-300 dark:hover:bg-neutral-700 border border-gray-300 dark:border-neutral-600/30 rounded-xl text-sm text-gray-700 dark:text-neutral-300 font-medium transition-colors cursor-pointer" onclick="closeResetModal()">
            <i class="fa-solid fa-times mr-2"></i>Annuler
          </button>
          <button type="submit" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 rounded-xl text-sm text-white font-medium transition-colors shadow-lg cursor-pointer">
            <i class="fa-solid fa-check mr-2"></i>Réinitialiser
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Navigation mobile menu
    function toggleNavMenu() {
      const menu = document.getElementById('navMenu');
      menu.classList.toggle('hidden');
    }

    function closeNavMenu() {
      const menu = document.getElementById('navMenu');
      if (menu) menu.classList.add('hidden');
    }

    // User actions menu (mobile)
    function toggleUserActionsMenu(userId) {
      closeAllUserMenus();
      const menu = document.getElementById('userActionsMenu-' + userId);
      if (menu) menu.classList.toggle('hidden');
    }

    function closeAllUserMenus() {
      document.querySelectorAll('[id^="userActionsMenu-"]').forEach(menu => {
        menu.classList.add('hidden');
      });
    }

    // Fermer les menus au clic extérieur
    document.addEventListener('click', function(e) {
      if (!e.target.closest('[onclick*="toggleNavMenu"]') && !e.target.closest('#navMenu')) {
        closeNavMenu();
      }
      if (!e.target.closest('[onclick*="toggleUserActionsMenu"]') && !e.target.closest('[id^="userActionsMenu-"]')) {
        closeAllUserMenus();
      }
    });

    // Modal de confirmation
    function openConfirmModal(action, userId, message, username) {
      document.getElementById('confirmAction').value = action;
      document.getElementById('confirmUserId').value = userId;
      document.getElementById('confirmTitle').textContent = action === 'delete_user' ? 'Supprimer l\'utilisateur' : 'Modifier le statut';
      document.getElementById('confirmMessage').textContent = message;

      // Changer les couleurs en fonction de l'action
      const confirmBtn = document.querySelector('#confirmModal button:last-of-type');
      if (action === 'delete_user') {
        confirmBtn.className = 'flex-1 px-4 py-2.5 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 rounded-xl text-sm text-white font-medium transition-colors shadow-lg';
        confirmBtn.innerHTML = '<i class="fa-solid fa-trash mr-2"></i>Supprimer';
      } else {
        confirmBtn.className = 'flex-1 px-4 py-2.5 bg-gradient-to-r from-purple-600 to-purple-500 hover:from-purple-500 hover:to-purple-400 rounded-xl text-sm text-white font-medium transition-colors shadow-lg';
        confirmBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i>Confirmer';
      }

      document.getElementById('confirmModal').classList.remove('hidden');
    }

    function closeConfirmModal() {
      document.getElementById('confirmModal').classList.add('hidden');
    }

    function submitConfirm() {
      const form = document.getElementById('confirmForm');
      form.submit();
    }

    // Modal de réinitialisation du mot de passe
    function openResetModal(userId, username) {
      document.getElementById('resetUserId').value = userId;
      document.getElementById('resetUsername').textContent = username;
      document.getElementById('newPassword').value = '';
      document.getElementById('resetModal').classList.remove('hidden');
    }

    function closeResetModal() {
      document.getElementById('resetModal').classList.add('hidden');
    }

    // Fermer les modals en cliquant en dehors
    document.getElementById('confirmModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeConfirmModal();
      }
    });

    document.getElementById('resetModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeResetModal();
      }
    });

    // Fermer les modals avec la touche Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeConfirmModal();
        closeResetModal();
      }
    });

    // Bouton retour en haut
    const scrollToTopBtn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', function() {
      if (window.scrollY > 200) {
        scrollToTopBtn.classList.add('visible');
      } else {
        scrollToTopBtn.classList.remove('visible');
      }
    });

    scrollToTopBtn.addEventListener('click', function() {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  </script>

  <!-- Bouton retour en haut -->
  <button id="scrollToTopBtn" class="fixed bottom-6 right-6 z-50 w-12 h-12 rounded-full bg-green-500/15 hover:bg-green-500/25 border border-green-500/30 text-green-400 shadow-lg backdrop-blur-sm transition-all duration-300 cursor-pointer" aria-label="Retour en haut">
    <i class="fa-solid fa-chevron-up"></i>
  </button>
</body>

</html>