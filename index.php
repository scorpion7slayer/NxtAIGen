<?php
session_start();
require_once 'zone_membres/db.php';

// Génération token CSRF si absent
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Constante pour la limite de messages visiteurs (doit correspondre à streamApi.php)
define('GUEST_USAGE_LIMIT', 5);

// Récupérer les informations utilisateur si connecté
$user = null;
$isGuest = !isset($_SESSION['user_id']);

// Gestion du système de réinitialisation 24h pour les visiteurs
if ($isGuest) {
  // Initialiser le timestamp de première utilisation si nécessaire
  if (!isset($_SESSION['guest_first_usage_time'])) {
    $_SESSION['guest_first_usage_time'] = time();
    $_SESSION['guest_usage_count'] = 0;
  }

  // Vérifier si 24h se sont écoulées depuis la première utilisation
  $timeSinceFirstUsage = time() - $_SESSION['guest_first_usage_time'];
  if ($timeSinceFirstUsage >= 86400) { // 86400 secondes = 24 heures
    // Réinitialiser le compteur et le timestamp
    $_SESSION['guest_usage_count'] = 0;
    $_SESSION['guest_first_usage_time'] = time();
  }
}

$guestUsageCount = $_SESSION['guest_usage_count'] ?? 0;

// ===== GDPR Cookie Consent =====
$cookieConsentGiven = isset($_COOKIE['nxtgenai_consent']);
$cookieConsent = null;
if ($cookieConsentGiven) {
  $cookieConsent = json_decode($_COOKIE['nxtgenai_consent'], true);
}

if (isset($_SESSION['user_id'])) {
  $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch();
}
// Vérifier si l'utilisateur est admin
$isAdmin = false;
if ($user) {
  try {
    $checkAdmin = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $checkAdmin->execute([$_SESSION['user_id']]);
    $userData = $checkAdmin->fetch();
    $isAdmin = ($userData && $userData['is_admin'] == 1);
  } catch (PDOException $ex) {
    $isAdmin = false;
  }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php if (!$isGuest): ?>
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>" />
  <?php endif; ?>

  <!-- Preconnect pour les CDN externes (améliore TTFB) -->
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

  <!-- Preload CSS critique -->
  <link rel="preload" href="src/output.css" as="style">

  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link href="src/output.css" rel="stylesheet">

  <!-- Font Awesome - chargement non-bloquant -->
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
    media="print"
    onload="this.media='all'" />
  <noscript>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
  </noscript>

  <!-- Highlight.js - chargement différé -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css" media="print" onload="this.media='all'" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>

  <!-- Marked.js - chargement synchrone (requis avant le script inline) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/15.0.4/marked.min.js"></script>

  <!-- Anime.js v4 (local via npm) -->
  <script src="assets/js/anime.min.js"></script>

  <!-- Variables globales pour les scripts externes -->
  <script>
    window.isGuest = <?php echo $isGuest ? 'true' : 'false'; ?>;
    window.guestUsageLimit = <?php echo GUEST_USAGE_LIMIT; ?>;
  </script>
  <script src="assets/js/models.js" defer></script>
  <script src="assets/js/rate_limit_widget.js" defer></script>
  <script src="assets/js/animations.js" defer></script>
  <!-- Theme initialization (inline to prevent FOUC) -->
  <script>
    document.documentElement.classList.add('dark');
    document.documentElement.lang = 'fr';
  </script>
  <title>NxtAIGen</title>
</head>

<body class="min-h-screen flex overflow-hidden bg-bg-dark text-neutral-100">

  <!-- Skip links pour accessibilité clavier -->
  <a href="#messageInput" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-100 focus:px-4 focus:py-2 focus:bg-green-600 focus:text-white focus:rounded-lg focus:outline-none focus:shadow-lg" data-i18n="a11y.skip_to_input">
    Aller à la zone de saisie
  </a>
  <a href="#chatContainer" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-52 focus:z-100 focus:px-4 focus:py-2 focus:bg-green-600 focus:text-white focus:rounded-lg focus:outline-none focus:shadow-lg" data-i18n="a11y.skip_to_chat">
    Aller à la conversation
  </a>

  <!-- Suppression des boutons de thème et de langue -->

  <!-- Scroll to Bottom Button -->
  <button id="scrollToBottomBtn"
    aria-label="Descendre en bas"
    title="Descendre en bas"
    class="fixed bottom-24 right-4 z-30 px-3 py-2 rounded-lg bg-bg-dark border border-neutral-700 text-neutral-400 transition-all duration-200 opacity-0 invisible pointer-events-none backdrop-blur-sm shadow-none hidden">
    <i class="fa-solid fa-arrow-down text-sm"></i>
  </button>

  <!-- Sidebar Historique des Conversations -->
  <aside id="conversationSidebar"
    aria-label="Historique des conversations"
    class="conversation-sidebar flex flex-col border-r border-neutral-700 h-screen transition-all duration-300 ease-in-out bg-bg-dark"
    data-collapsed="false">
    <!-- Header Sidebar -->
    <div class="sidebar-header flex items-center justify-between px-4 py-3 border-b border-neutral-700/20">
      <h2 id="sidebarTitle" class="sidebar-title text-sm font-semibold text-neutral-300 uppercase tracking-wider flex items-center gap-2">
        <i class="fa-solid fa-clock-rotate-left text-green-500" aria-hidden="true"></i>
        <span class="sidebar-title-text">Historique</span>
      </h2>
      <div class="flex items-center gap-0.5">
        <!-- Bouton Nouvelle Conversation -->
        <button id="newConversationBtn"
          aria-label="Créer une nouvelle conversation"
          data-i18n-title="sidebar.new_conversation"
          class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-gray-200 dark:hover:bg-gray-700/30 transition-all duration-150 cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
        </button>
        <!-- Bouton Collapse -->
        <button id="toggleSidebarBtn"
          aria-label="Réduire la barre latérale"
          aria-expanded="true"
          aria-controls="conversationSidebar"
          data-i18n-title="sidebar.collapse"
          class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700/30 transition-all duration-150 cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
          <i class="fa-solid fa-angles-left sidebar-toggle-icon" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    <!-- Barre de recherche -->
    <div class="sidebar-search p-3 border-b border-neutral-700/20" role="search">
      <div class="relative group">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500 text-sm group-focus-within:text-green-500 transition-colors pointer-events-none" aria-hidden="true"></i>
        <label for="conversationSearch" class="sr-only">Rechercher dans les conversations</label>
        <input type="text"
          id="conversationSearch"
          placeholder="Rechercher..."
          aria-label="Rechercher dans l'historique des conversations"
          autocomplete="off"
          class="w-full bg-bg-dark border border-neutral-700 rounded-lg pl-9 pr-8 py-2 text-sm text-neutral-300 placeholder:text-neutral-500 placeholder:italic outline-none focus:border-green-500/50 focus:ring-2 focus:ring-green-500/20 focus:bg-bg-dark transition-all duration-200">
        <button type="button" id="conversationSearchClear" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-500 hover:text-neutral-300 transition-colors hidden" aria-label="Effacer la recherche">
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </div>
    </div>

    <!-- Liste des conversations -->
    <nav id="conversationList"
      aria-label="Liste des conversations"
      class="flex-1 overflow-y-auto px-2 py-2 space-y-0.5">
      <!-- Placeholder quand pas de conversations -->
      <div id="noConversationsPlaceholder" class="flex flex-col items-center justify-center py-12 text-neutral-400">
        <div class="w-16 h-16 rounded-full bg-neutral-700/50 flex items-center justify-center mb-4">
          <i class="fa-regular fa-comments text-2xl opacity-60" aria-hidden="true"></i>
        </div>
        <p class="text-sm font-medium text-neutral-400">Aucune conversation</p>
        <p class="text-xs mt-1 text-neutral-500">Commencez à discuter pour créer un historique</p>
      </div>
    </nav>

    <!-- Footer Sidebar -->
    <div class="sidebar-footer p-3 border-t border-neutral-700/20 space-y-2">
      <button id="newConversationBtnFooter" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-500 text-white text-sm font-medium transition-colors duration-150 cursor-pointer">
        <i class="fa-solid fa-plus"></i>
        <span>Nouvelle conversation</span>
      </button>
      <button id="exportConversationsBtn" class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-neutral-700/30 border border-neutral-700 text-neutral-400 hover:text-white hover:bg-neutral-600/50 transition-all duration-150 text-sm cursor-pointer" title="Exporter les conversations">
        <i class="fa-solid fa-file-export"></i>
        <span class="sidebar-btn-text">Exporter l'historique</span>
      </button>
    </div>
  </aside>

  <!-- Overlay mobile -->
  <div id="sidebarOverlay" class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm opacity-0 invisible transition-all duration-300 md:hidden" data-visible="false"></div>

  <!-- ===== MOBILE BOTTOM SHEET - MENU MODÃˆLES ===== -->
  <div id="mobileModelSheet" class="mobile-bottom-sheet fixed inset-0 z-50 pointer-events-none opacity-0 invisible transition-opacity duration-200 md:hidden" role="dialog" aria-modal="true" aria-labelledby="mobileModelSheetTitle">
    <div class="mobile-bottom-sheet-backdrop absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeMobileModelSheet()"></div>
    <div class="mobile-bottom-sheet-content absolute bottom-0 left-0 right-0 max-h-[85vh] bg-neutral-800 rounded-t-3xl transform translate-y-full transition-transform duration-300 flex flex-col overflow-hidden">
      <div class="mobile-sheet-handle bg-gray-400/30 rounded-full w-9 h-1.5 mx-auto my-3" role="presentation"></div>
      <div class="mobile-sheet-header">
        <h2 id="mobileModelSheetTitle" class="mobile-sheet-title">Sélectionner un modèle</h2>
        <div class="mobile-sheet-search relative">
          <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-base text-neutral-400 pointer-events-none" aria-hidden="true"></i>
          <input type="text" id="mobileModelSearch" placeholder="Rechercher un modèle..." autocomplete="off" aria-label="Rechercher un modèle">
          <button type="button" id="mobileModelSearchClear" class="mobile-search-clear hidden" aria-label="Effacer la recherche">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      </div>
      <div id="mobileModelList" class="mobile-sheet-list" role="listbox" aria-labelledby="mobileModelSheetTitle">
        <!-- Les modèles seront injectés ici par JS -->
        <div class="flex items-center justify-center py-8">
          <div class="typing-indicator">
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
          </div>
          <span class="ml-3 text-neutral-400 text-sm">Chargement des modèles...</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== MOBILE BOTTOM SHEET - MENU PROFIL (connecté) ===== -->
  <?php if ($user): ?>
    <div id="mobileProfileSheet" class="mobile-bottom-sheet mobile-profile-sheet fixed inset-0 z-50 pointer-events-none opacity-0 invisible transition-opacity duration-200 md:hidden" role="dialog" aria-modal="true" aria-labelledby="mobileProfileSheetTitle">
      <div class="mobile-bottom-sheet-backdrop absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeMobileProfileSheet()"></div>
      <div class="mobile-bottom-sheet-content absolute bottom-0 left-0 right-0 max-h-[85vh] bg-neutral-800 rounded-t-3xl transform translate-y-full transition-transform duration-300 flex flex-col overflow-hidden">
        <div class="mobile-sheet-handle" role="presentation"></div>
        <div class="mobile-profile-header">
          <div class="mobile-profile-avatar">
            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
          </div>
          <div class="mobile-profile-info">
            <h3 id="mobileProfileSheetTitle"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>
        <nav class="mobile-profile-menu" aria-label="Menu profil">
          <a href="zone_membres/dashboard.php" class="mobile-profile-item">
            <i class="fa-solid fa-user" aria-hidden="true"></i>
            <span>Mon Profil</span>
          </a>
          <a href="zone_membres/settings.php" class="mobile-profile-item">
            <i class="fa-solid fa-gear" aria-hidden="true"></i>
            <span>Paramètres</span>
          </a>
          <a href="zone_membres/subscription.php" class="mobile-profile-item">
            <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
            <span>Abonnements</span>
          </a>
          <?php if ($isAdmin): ?>
            <a href="admin/settings.php" class="mobile-profile-item">
              <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
              <span data-i18n="nav.admin">Admin</span>
            </a>
          <?php endif; ?>
          <div class="mobile-profile-divider"></div>
          <a href="zone_membres/logout.php" class="mobile-profile-item danger">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span data-i18n="nav.logout">Déconnexion</span>
          </a>
        </nav>
      </div>
    </div>
  <?php else: ?>
    <!-- ===== MOBILE BOTTOM SHEET - AUTH (non connecté) ===== -->
    <div id="mobileAuthSheet" class="mobile-bottom-sheet mobile-auth-sheet fixed inset-0 z-50 pointer-events-none opacity-0 invisible transition-opacity duration-200 md:hidden" role="dialog" aria-modal="true" aria-labelledby="mobileAuthSheetTitle">
      <div class="mobile-bottom-sheet-backdrop absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeMobileAuthSheet()"></div>
      <div class="mobile-bottom-sheet-content absolute bottom-0 left-0 right-0 max-h-[85vh] bg-neutral-800 rounded-t-3xl transform translate-y-full transition-transform duration-300 flex flex-col overflow-hidden">
        <div class="mobile-sheet-handle" role="presentation"></div>
        <h2 id="mobileAuthSheetTitle" class="sr-only">Connexion ou inscription</h2>
        <div class="mobile-auth-buttons">
          <a href="zone_membres/register.php" class="mobile-auth-btn primary">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
            <span data-i18n="nav.register">Créer un compte gratuit</span>
          </a>
          <a href="zone_membres/login.php" class="mobile-auth-btn secondary">
            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
            <span data-i18n="nav.login">Se connecter</span>
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Bouton pour ouvrir la sidebar (visible quand collapsed) -->
  <button id="openSidebarBtn" class="fixed left-4 top-4 z-50 p-3 rounded-xl border border-gray-700/30 text-gray-400 hover:text-green-400 hover:bg-gray-700/30 hover:border-green-500/30 transition-all duration-200 cursor-pointer shadow-xl backdrop-blur-sm hidden bg-bg-dark" title="Ouvrir l'historique">
    <i class="fa-solid fa-clock-rotate-left"></i>
  </button>

  <!-- Contenu Principal -->
  <main id="mainContent" class="flex-1 flex flex-col items-center justify-center p-4 min-w-0 relative overflow-hidden transition-all duration-500 ease-out">
    <!-- Logo NxtGenAI -->
    <div id="logoContainer" class="mb-8 transition-all duration-500 ease-out">
      <img src="assets/images/logo.svg" alt="NxtGenAI - Assistant IA conversationnel" class="h-20 w-auto" id="siteLogo">
    </div>

    <!-- Zone de conversation -->
    <div id="chatContainer"
      role="log"
      aria-live="polite"
      aria-atomic="false"
      aria-label="Conversation avec l'assistant IA"
      class="w-full max-w-2xl mb-4 flex-1 overflow-y-auto space-y-4 hidden transition-all duration-500 ease-out min-h-0">
    </div>

    <!-- Message d'accueil -->
    <div id="welcomeMessage" class="justify-center mb-6 text-center max-w-2xl text-gray-400/90 transition-all duration-500 ease-out">
      <span>Pensez à poser une question pour commencer.</span>
      <?php if ($isGuest): ?>
        <?php
        $remaining = GUEST_USAGE_LIMIT - $guestUsageCount;
        $timeRemaining = 86400 - (time() - ($_SESSION['guest_first_usage_time'] ?? time()));
        ?>
        <div id="guestUsageInfo" class="mt-3 text-sm">
          <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400">
            <i class="fa-solid fa-gift"></i>
            <span id="usageText"><?php echo $remaining; ?> essai<?php echo ($remaining > 1) ? 's' : ''; ?> gratuit<?php echo ($remaining > 1) ? 's' : ''; ?> restant<?php echo ($remaining > 1) ? 's' : ''; ?></span>
          </span>
          <?php if ($remaining === 0): ?>
            <p class="mt-2 text-xs text-gray-500">
              <span id="resetTimer" data-reset-time="<?php echo $timeRemaining; ?>"></span> â€¢ <a href="zone_membres/register.php" class="text-green-400 hover:text-green-300 underline">Inscrivez-vous</a> pour un accès illimité
            </p>
          <?php else: ?>
            <p class="mt-2 text-xs text-gray-500">
              <a href="zone_membres/register.php" class="text-green-400 hover:text-green-300 underline">Inscrivez-vous</a> pour un accès illimité
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div id="inputWrapper" class="w-full max-w-2xl transition-all duration-500 ease-out">
      <!-- Zone de saisie -->
      <div id="inputContainer"
        class="bg-gray-800/50 rounded-2xl p-4 border border-gray-700/50 shadow-none">
        <!-- Zone de prévisualisation des fichiers -->
        <div id="filePreviewContainer" class="hidden mb-3 gap-2">
        </div>
        <label for="messageInput" class="sr-only">Votre message à l'assistant IA</label>
        <input
          type="text"
          id="messageInput"
          placeholder="Posez une question..."
          aria-label="Votre message à l'assistant IA"
          aria-describedby="inputHelpText"
          autocomplete="off"
          class="w-full bg-transparent text-gray-300 placeholder:text-gray-500 outline-none text-base mb-4" />
        <span id="inputHelpText" class="sr-only">Appuyez sur Entrée pour envoyer, ou utilisez le bouton d'envoi</span>

        <!-- Barre d'outils -->
        <div class="flex items-center justify-between">
          <!-- Icônes gauche -->
          <div class="flex items-center gap-2">
            <!-- Bouton ampoule -->
            <button
              class="p-2 rounded-lg text-gray-400 hover:text-gray-300 hover:bg-gray-700/50 transition-colors cursor-pointer">
              <i class="fa-solid fa-lightbulb"></i>
            </button>
            <?php if ($isGuest): ?>
              <!-- Badge utilisations restantes pour visiteurs -->
              <?php $remainingBadge = GUEST_USAGE_LIMIT - $guestUsageCount; ?>
              <?php if ($remainingBadge === 0): ?>
                <?php $timeRemainingBadge = 86400 - (time() - ($_SESSION['guest_first_usage_time'] ?? time())); ?>
                <div id="usageBadge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-xs" title="Réinitialisation des essais gratuits">
                  <i class="fa-solid fa-clock"></i>
                  <span id="usageBadgeCount" data-timer="true" data-reset-time="<?php echo $timeRemainingBadge; ?>"></span>
                </div>
              <?php else: ?>
                <div id="usageBadge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs" title="Essais gratuits restants">
                  <i class="fa-solid fa-bolt"></i>
                  <span id="usageBadgeCount"><?php echo $remainingBadge; ?>/<?php echo GUEST_USAGE_LIMIT; ?></span>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- Icônes droite -->
          <div class="flex items-center gap-2">
            <!-- Sélecteur de modèle -->
            <div class="relative">
              <button
                id="modelSelectorBtn"
                aria-haspopup="listbox"
                aria-expanded="false"
                aria-controls="modelMenu"
                aria-label="Sélectionner un modèle IA"
                class="inline-flex items-center justify-center gap-x-2 rounded-lg bg-gray-700/50 border border-gray-600/50 px-3 py-2 text-sm text-gray-300 hover:bg-gray-600/50 hover:text-white transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
                <img id="modelIcon" src="assets/images/providers/openai.svg" alt="" aria-hidden="true" class="w-4 h-4 rounded">
                <span id="modelName">Chargement...</span>
                <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-gray-400 transition-transform duration-200" id="modelChevron" aria-hidden="true">
                  <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                </svg>
              </button>

              <!-- Menu déroulant des modèles -->
              <div
                id="modelMenu"
                class="absolute bottom-full left-0 w-72 bg-gray-800 rounded-xl border border-gray-700/50 shadow-xl overflow-hidden opacity-0 invisible translate-y-2 transition-all duration-200 ease-out z-50 mb-2">
                <div class="p-2 max-h-96 overflow-y-auto">
                  <p class="px-3 py-2 text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-2">
                    <svg class="animate-spin w-3 h-3" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Chargement des modèles...
                  </p>
                </div>
              </div>
            </div>

            <!-- Globe -->
            <button
              aria-label="Recherche web (non disponible actuellement)"
              aria-disabled="true"
              disabled
              class="p-2 rounded-lg text-gray-600 cursor-not-allowed focus:outline-none">
              <i class="fa-solid fa-globe"></i>
            </button>

            <!-- Trombone -->
            <button
              id="attachButton"
              aria-label="Joindre un fichier"
              aria-describedby="fileTypesAllowed"
              class="p-2 rounded-lg text-gray-400 hover:text-gray-300 hover:bg-gray-700/50 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
              <i class="fa-solid fa-paperclip"></i>
            </button>
            <span id="fileTypesAllowed" class="sr-only">Formats acceptés: images, PDF, Word, texte, CSV, JSON, XML, Markdown. Taille max: 10 MB</span>
            <!-- Input file caché -->
            <input
              type="file"
              id="fileInput"
              class="sr-only"
              accept="image/*,.pdf,.doc,.docx,.txt,.csv,.json,.xml,.md"
              aria-describedby="fileTypesAllowed"
              multiple />
            <!-- Micro -->
            <button
              id="micButton"
              aria-label="Dicter un message (reconnaissance vocale)"
              aria-pressed="false"
              class="p-2 rounded-lg text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
              <i class="fa-solid fa-microphone-lines"></i>
            </button>

            <!-- Bouton envoyer -->
            <button
              id="sendButton"
              disabled
              aria-label="Envoyer le message"
              data-i18n-title="chat.send"
              aria-disabled="true"
              class="p-2 rounded-lg bg-gray-700 text-gray-400 transition-all duration-300 ease-in-out cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-green-500">
              <i class="fa-solid fa-paper-plane"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== ZONE DE SAISIE MOBILE ===== -->
    <div id="mobileInputContainer" class="hidden" aria-label="Zone de saisie mobile">
      <!-- Container principal mobile -->
      <div id="mobileInputBox">
        <!-- Prévisualisation fichiers mobile -->
        <div id="mobileFilePreview" class="flex flex-wrap gap-2"></div>

        <!-- Input text mobile -->
        <label for="mobileMessageInput" class="sr-only">Votre message</label>
        <input
          type="text"
          id="mobileMessageInput"
          placeholder="Posez une question..."
          autocomplete="off"
          aria-label="Votre message à l'assistant IA" />

        <!-- Actions mobile -->
        <div id="mobileActions">
          <!-- Actions gauche -->
          <div id="mobileActionsLeft">
            <!-- Sélecteur modèle mobile -->
            <button id="mobileModelSelector" aria-label="Sélectionner un modèle" aria-haspopup="true">
              <img id="mobileModelIcon" src="assets/images/providers/openai.svg" alt="" aria-hidden="true">
              <span id="mobileModelName">Chargement...</span>
              <svg class="w-3 h-3 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" />
              </svg>
            </button>

            <!-- Bouton recherche web (désactivé) -->
            <button id="mobileWebSearchButton" class="mobile-icon-btn" aria-label="Recherche web (non disponible)" aria-disabled="true" disabled style="opacity: 0.4; cursor: not-allowed;">
              <i class="fa-solid fa-globe"></i>
            </button>

            <!-- Bouton joindre fichier -->
            <button id="mobileAttachButton" class="mobile-icon-btn" aria-label="Joindre un fichier">
              <i class="fa-solid fa-paperclip"></i>
            </button>

            <!-- Bouton micro -->
            <button id="mobileMicButton" class="mobile-icon-btn" aria-label="Dicter un message" aria-pressed="false">
              <i class="fa-solid fa-microphone-lines"></i>
            </button>

            <?php if ($isGuest): ?>
              <!-- Badge usage visiteur mobile -->
              <?php $remainingMobile = GUEST_USAGE_LIMIT - $guestUsageCount; ?>
              <span id="mobileUsageBadge" class="text-xs text-amber-400 flex items-center gap-1">
                <i class="fa-solid fa-bolt"></i>
                <span id="mobileUsageCount"><?php echo $remainingMobile; ?>/<?php echo GUEST_USAGE_LIMIT; ?></span>
              </span>
            <?php endif; ?>
          </div>

          <!-- Bouton envoyer mobile -->
          <button id="mobileSendButton" disabled aria-label="Envoyer le message" aria-disabled="true">
            <i class="fa-solid fa-paper-plane"></i>
          </button>
        </div>
      </div>
    </div>
    <!-- ===== FIN ZONE MOBILE ===== -->

    <!-- profil -->
    <div class="fixed bottom-2 z-30 profile-desktop-position" id="profileContainer">
      <?php if ($user): ?>
        <!-- Menu déroulant (utilisateur connecté) -->
        <div
          id="profileMenu"
          class="absolute bottom-full left-0 mb-2 w-48 bg-gray-800 rounded-xl border border-gray-700/50 shadow-lg overflow-hidden opacity-0 invisible translate-y-2 transition-all duration-300 ease-in-out">
          <div class="py-2">
            <div class="px-4 py-2 border-b border-gray-700/50">
              <p class="text-sm text-gray-200 font-medium"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></p>
              <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <a
              href="zone_membres/dashboard.php"
              class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:bg-gray-700/50 transition-colors">
              <i class="fa-solid fa-user w-4"></i>
              <span>Mon Profil</span>
            </a>
            <a
              href="zone_membres/settings.php"
              class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:bg-gray-700/50 transition-colors">
              <i class="fa-solid fa-gear w-4"></i>
              <span>Paramètres</span>
            </a>
            <a href="zone_membres/subscription.php" class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:bg-gray-700/50 transition-colors">
              <i class="fa-solid fa-credit-card w-4"></i>
              <span>Abonnements</span>
            </a>
            <?php if ($isAdmin): ?>
              <a
                href="admin/settings.php"
                class="flex items-center gap-3 px-4 py-2 text-gray-300 hover:bg-gray-700/50 transition-colors">
                <i class="fa-solid fa-user-shield w-4"></i>
                <span>Paramètres Admin</span>
              </a>
            <?php endif; ?>
            <div class="border-t border-gray-700/50 my-1"></div>
            <a
              href="zone_membres/logout.php"
              class="flex items-center gap-3 px-4 py-2 text-red-400 hover:bg-gray-700/50 transition-colors">
              <i class="fa-solid fa-right-from-bracket w-4"></i>
              <span>Déconnexion</span>
            </a>
          </div>
        </div>

        <!-- Bouton avatar (utilisateur connecté) -->
        <button
          id="profileButton"
          aria-label="Menu profil"
          aria-haspopup="true"
          aria-expanded="false"
          class="relative flex items-center justify-center w-12 h-12 rounded-full bg-linear-to-br from-green-500 to-emerald-600 hover:from-green-400 hover:to-emerald-500 cursor-pointer transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-gray-900">
          <span class="text-white font-semibold text-lg"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
          <span
            id="profileIcon"
            class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-gray-800 rounded-full flex items-center justify-center border-2 border-gray-700">
            <i class="fa-solid fa-chevron-down text-[8px] text-gray-400 transition-transform duration-300 ease-in-out"></i>
          </span>
        </button>
      <?php else: ?>
        <!-- Boutons connexion/inscription (non connecté) -->
        <div class="flex items-center gap-2">
          <a
            href="zone_membres/login.php"
            class="flex items-center gap-2 rounded-full border border-gray-700/50 px-4 py-2 hover:bg-gray-700/50 cursor-pointer transition-colors text-gray-400 hover:text-gray-200 text-sm">
            <i class="fa-solid fa-right-to-bracket"></i>
            <span>Connexion</span>
          </a>
          <a
            href="zone_membres/register.php"
            class="flex items-center gap-2 rounded-full bg-green-700 hover:bg-green-600 px-4 py-2 cursor-pointer transition-colors text-white text-sm">
            <i class="fa-solid fa-user-plus"></i>
            <span>Inscription</span>
          </a>
        </div>
        <!-- Bouton mobile auth trigger (visible uniquement sur mobile) -->
        <button id="mobileAuthTrigger"
          onclick="openMobileAuthSheet()"
          class="hidden items-center justify-center w-10 h-10 rounded-full bg-green-600 hover:bg-green-500 text-white transition-colors"
          aria-label="Se connecter ou créer un compte">
          <i class="fa-solid fa-user" aria-hidden="true"></i>
        </button>
      <?php endif; ?>
    </div>
  </main>

  <script>
    // Ã‰tat utilisateur (isGuest et guestUsageLimit déjà définis dans le head)
    let guestUsageCount = <?php echo $guestUsageCount; ?>;

    // Timer de réinitialisation pour les visiteurs
    if (isGuest) {
      // Timer dans le message d'accueil
      const resetTimer = document.getElementById('resetTimer');
      if (resetTimer) {
        let timeRemaining = parseInt(resetTimer.dataset.resetTime);
        let resetTimerInterval = null;

        function updateResetTimer() {
          if (timeRemaining <= 0) {
            resetTimer.innerHTML = '<i class="fa-solid fa-rotate-right text-green-400"></i> Réinitialisation disponible - rechargez la page';
            if (resetTimerInterval) clearInterval(resetTimerInterval);
            return;
          }

          const hours = Math.floor(timeRemaining / 3600);
          const minutes = Math.floor((timeRemaining % 3600) / 60);
          const seconds = timeRemaining % 60;

          let timeText = '';
          if (hours > 0) {
            timeText = `${hours}h ${minutes}min ${seconds}s`;
          } else if (minutes > 0) {
            timeText = `${minutes}min ${seconds}s`;
          } else {
            timeText = `${seconds}s`;
          }

          resetTimer.innerHTML = `<i class="fa-solid fa-clock text-amber-400"></i> Réinitialisation dans ${timeText}`;

          timeRemaining--;
        }

        updateResetTimer();
        resetTimerInterval = setInterval(updateResetTimer, 1000);
      }

      // Timer dans le badge
      const usageBadgeCount = document.getElementById('usageBadgeCount');
      if (usageBadgeCount && usageBadgeCount.dataset.timer === 'true') {
        let badgeTimeRemaining = parseInt(usageBadgeCount.dataset.resetTime);
        let badgeTimerInterval = null;

        function updateBadgeTimer() {
          if (badgeTimeRemaining <= 0) {
            usageBadgeCount.textContent = 'Rechargez';
            if (badgeTimerInterval) clearInterval(badgeTimerInterval);
            return;
          }

          const hours = Math.floor(badgeTimeRemaining / 3600);
          const minutes = Math.floor((badgeTimeRemaining % 3600) / 60);
          const seconds = badgeTimeRemaining % 60;

          let timeText = '';
          if (hours > 0) {
            timeText = `${hours}h ${minutes}m`;
          } else if (minutes > 0) {
            timeText = `${minutes}m ${seconds}s`;
          } else {
            timeText = `${seconds}s`;
          }

          usageBadgeCount.textContent = timeText;
          badgeTimeRemaining--;
        }

        updateBadgeTimer();
        badgeTimerInterval = setInterval(updateBadgeTimer, 1000);
      }
    }

    // État du modèle sélectionné (global pour models.js)
    // IMPORTANT: var est nécessaire pour l'accessibilité globale depuis models.js
    // Le modèle sera défini dynamiquement par models.js avec le premier modèle disponible
    var selectedModel = {
      provider: '',
      model: '',
      display: ''
    };

    const messageInput = document.getElementById("messageInput");
    const sendButton = document.getElementById("sendButton");

    // ===== MOBILE INPUT SYNCHRONIZATION =====
    const mobileMessageInput = document.getElementById("mobileMessageInput");
    const mobileSendButton = document.getElementById("mobileSendButton");
    const mobileModelSelector = document.getElementById("mobileModelSelector");
    const mobileModelIcon = document.getElementById("mobileModelIcon");
    const mobileModelName = document.getElementById("mobileModelName");
    const mobileAttachButton = document.getElementById("mobileAttachButton");
    const mobileMicButton = document.getElementById("mobileMicButton");
    const mobileFilePreview = document.getElementById("mobileFilePreview");
    const mainContent = document.getElementById("mainContent");

    // Fonction pour détecter si on est sur mobile
    function isMobileView() {
      return window.innerWidth <= 640;
    }

    // Synchroniser les deux inputs
    function syncInputs(source, target) {
      if (target) target.value = source.value;
      updateSendButtonState();
      updateMobileSendButtonState();
    }

    // Mettre à jour l'état du bouton d'envoi mobile
    function updateMobileSendButtonState() {
      if (!mobileSendButton || !mobileMessageInput) return;
      const hasText = mobileMessageInput.value.trim().length > 0;
      const hasFiles = (typeof attachedFiles !== 'undefined') && attachedFiles.length > 0;
      const canSend = (hasText || hasFiles) && !isStreaming;

      if (canSend) {
        mobileSendButton.disabled = false;
        mobileSendButton.classList.add('active');
        mobileSendButton.setAttribute('aria-disabled', 'false');
      } else {
        mobileSendButton.disabled = true;
        mobileSendButton.classList.remove('active');
        mobileSendButton.setAttribute('aria-disabled', 'true');
      }
    }

    // Synchroniser le modèle sélectionné entre desktop et mobile
    function syncModelDisplay() {
      if (mobileModelIcon && modelIcon) {
        mobileModelIcon.src = modelIcon.src;
      }
      if (mobileModelName && modelName) {
        mobileModelName.textContent = modelName.textContent;
      }
    }

    // Event listeners pour synchronisation mobile
    if (mobileMessageInput) {
      mobileMessageInput.addEventListener("input", function() {
        syncInputs(this, messageInput);
      });

      mobileMessageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if (!mobileSendButton.disabled) {
            sendMessage();
          }
        }
      });
    }

    if (messageInput) {
      // Observer les changements sur l'input desktop pour synchro mobile
      const originalInputHandler = messageInput.oninput;
      messageInput.addEventListener("input", function() {
        syncInputs(this, mobileMessageInput);
      });
    }

    // Clic sur bouton envoi mobile
    if (mobileSendButton) {
      mobileSendButton.addEventListener('click', function() {
        if (isStreaming) {
          cancelRequest();
        } else if (!this.disabled) {
          sendMessage();
        }
      });
    }

    // NOTE: Le handler du sélecteur modèle mobile est défini plus bas avec openMobileModelSheet()

    // Clic sur bouton attach mobile
    if (mobileAttachButton) {
      mobileAttachButton.addEventListener('click', function() {
        const fileInput = document.getElementById('fileInput');
        if (fileInput) fileInput.click();
      });
    }

    // Clic sur bouton micro mobile
    if (mobileMicButton) {
      mobileMicButton.addEventListener('click', function() {
        const micButton = document.getElementById('micButton');
        if (micButton) micButton.click();
      });
    }

    // Gérer l'activation de la classe chat-active sur mobile
    function setChatActiveMode(active) {
      if (mainContent) {
        if (active && isMobileView()) {
          mainContent.classList.add('chat-active');
        } else if (!active) {
          mainContent.classList.remove('chat-active');
        }
      }
    }

    // Observer pour détecter quand le chat devient actif
    const chatContainer = document.getElementById('chatContainer');
    if (chatContainer) {
      const chatObserver = new MutationObserver(function(mutations) {
        const hasMessages = chatContainer.children.length > 0;
        const isHidden = chatContainer.classList.contains('hidden');
        setChatActiveMode(hasMessages && !isHidden);
      });
      chatObserver.observe(chatContainer, {
        childList: true,
        attributes: true,
        attributeFilter: ['class']
      });
    }

    // Mettre à jour sur resize
    window.addEventListener('resize', function() {
      const chatHasMessages = chatContainer && chatContainer.children.length > 0 && !chatContainer.classList.contains('hidden');
      setChatActiveMode(chatHasMessages);
      syncModelDisplay();
    });
    // ===== FIN MOBILE SYNC =====

    // Sélecteur de modèle
    const modelSelectorBtn = document.getElementById('modelSelectorBtn');
    const modelMenu = document.getElementById('modelMenu');
    const modelChevron = document.getElementById('modelChevron');
    const modelIcon = document.getElementById('modelIcon');
    const modelName = document.getElementById('modelName');
    let isModelMenuOpen = false;

    modelSelectorBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      isModelMenuOpen = !isModelMenuOpen;

      if (isModelMenuOpen) {
        modelMenu.classList.remove('opacity-0', 'invisible', 'translate-y-2');
        modelMenu.classList.add('opacity-100', 'visible', 'translate-y-0');
        modelChevron.classList.add('rotate-180');
      } else {
        modelMenu.classList.remove('opacity-100', 'visible', 'translate-y-0');
        modelMenu.classList.add('opacity-0', 'invisible', 'translate-y-2');
        modelChevron.classList.remove('rotate-180');
        // Réinitialiser la recherche
        const searchInput = document.getElementById('desktopModelSearch');
        if (searchInput) {
          searchInput.value = '';
          window.modelManager?.filterDesktopModels('');
        }
      }
    });

    // Fermer le menu modèle si on clique ailleurs
    document.addEventListener('click', function() {
      if (isModelMenuOpen) {
        isModelMenuOpen = false;
        modelMenu.classList.remove('opacity-100', 'visible', 'translate-y-0');
        modelMenu.classList.add('opacity-0', 'invisible', 'translate-y-2');
        modelChevron.classList.remove('rotate-180');
        // Réinitialiser la recherche
        const searchInput = document.getElementById('desktopModelSearch');
        if (searchInput) {
          searchInput.value = '';
          window.modelManager?.filterDesktopModels('');
        }
      }
    });

    messageInput.addEventListener("input", function() {
      updateSendButtonState();
    });

    // Variables pour la gestion de l'annulation
    let currentAbortController = null;
    let isStreaming = false;
    let currentStreamingContent = ''; // Stocke le contenu brut du streaming en cours

    // Variables pour l'auto-scroll
    let isUserScrolling = false;
    let shouldAutoScroll = true;

    // Fonction pour basculer le bouton en mode annulation
    function setButtonCancelMode(cancel) {
      if (cancel) {
        isStreaming = true;
        sendButton.disabled = false;
        sendButton.innerHTML = '<i class="fa-solid fa-xmark text-lg"></i>';
        sendButton.classList.remove('bg-gray-700', 'text-gray-400', 'cursor-not-allowed', 'disabled:opacity-50', 'bg-green-600', 'hover:bg-green-500');
        sendButton.classList.add('bg-gray-700', 'text-gray-300', 'hover:bg-red-600', 'hover:text-white', 'cursor-pointer');
      } else {
        isStreaming = false;
        sendButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>`;
        updateSendButtonState();
      }
    }

    // Fonction pour annuler la requête
    function cancelRequest() {
      if (currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
      }
      setButtonCancelMode(false);

      // Marquer la réponse en cours comme terminée
      const streamingEl = document.querySelector('.streaming-response:not(.done)');
      if (streamingEl) {
        streamingEl.classList.add('done');
        // Supprimer le curseur de streaming
        removeStreamingCursors(streamingEl);

        // Remplacer "En cours..." par "Annulé" dans les blocs de code
        streamingEl.querySelectorAll('.code-header .text-green-400').forEach(el => {
          el.textContent = '';
          el.innerHTML = '<i class="fa-solid fa-stop mr-1"></i>Annulé';
          el.classList.remove('text-green-400', 'animate-pulse');
          el.classList.add('text-amber-400');
        });

        // Ajouter un indicateur d'annulation si du contenu existe
        if (currentStreamingContent) {
          const cancelBadge = document.createElement('div');
          cancelBadge.className = 'text-amber-400 italic text-xs mt-2 flex items-center gap-1';
          cancelBadge.innerHTML = '<i class="fa-solid fa-circle-stop"></i> Réponse interrompue';
          streamingEl.appendChild(cancelBadge);
        }
      }

      // Réinitialiser le contenu de streaming
      currentStreamingContent = '';
    }

    // Fonction pour mettre à jour l'affichage des utilisations restantes (visiteurs)
    function updateGuestUsageDisplay() {
      if (!isGuest) return;

      const remaining = guestUsageLimit - guestUsageCount;
      const usageText = document.getElementById('usageText');
      const guestUsageInfo = document.getElementById('guestUsageInfo');
      const usageBadge = document.getElementById('usageBadge');
      const usageBadgeCount = document.getElementById('usageBadgeCount');

      // Mettre à jour le badge dans la barre d'outils
      if (usageBadgeCount) {
        if (remaining <= 0) {
          // Transformer le badge en timer si pas déjà fait
          if (!usageBadgeCount.dataset.timer) {
            usageBadgeCount.dataset.timer = 'true';
            usageBadgeCount.dataset.resetTime = '86400'; // 24h par défaut

            // Changer l'icône
            const badgeIcon = usageBadge.querySelector('i');
            if (badgeIcon) {
              badgeIcon.className = 'fa-solid fa-clock';
            }

            // Démarrer le timer
            let badgeTimeRem = 86400;
            let dynamicBadgeInterval = null;
            const updateBadge = () => {
              if (badgeTimeRem <= 0) {
                usageBadgeCount.textContent = 'Rechargez';
                if (dynamicBadgeInterval) clearInterval(dynamicBadgeInterval);
                return;
              }
              const hours = Math.floor(badgeTimeRem / 3600);
              const minutes = Math.floor((badgeTimeRem % 3600) / 60);
              const seconds = badgeTimeRem % 60;
              let timeText = hours > 0 ? `${hours}h ${minutes}m` : (minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`);
              usageBadgeCount.textContent = timeText;
              badgeTimeRem--;
            };
            updateBadge();
            dynamicBadgeInterval = setInterval(updateBadge, 1000);
          }

          usageBadge.classList.remove('bg-amber-500/10', 'border-amber-500/20', 'text-amber-400');
          usageBadge.classList.add('bg-red-500/10', 'border-red-500/20', 'text-red-400');
        } else {
          usageBadgeCount.textContent = `${remaining}/${guestUsageLimit}`;
          if (remaining === 1) {
            usageBadge.classList.remove('bg-amber-500/10', 'border-amber-500/20', 'text-amber-400');
            usageBadge.classList.add('bg-orange-500/10', 'border-orange-500/20', 'text-orange-400');
          }
        }
      }

      if (usageText) {
        if (remaining <= 0) {
          usageText.innerHTML = `<i class="fa-solid fa-lock mr-1"></i> Plus d'essais disponibles`;
          usageText.parentElement.classList.remove('bg-amber-500/10', 'border-amber-500/30', 'text-amber-400');
          usageText.parentElement.classList.add('bg-red-500/10', 'border-red-500/30', 'text-red-400');

          // Afficher le timer si pas déjà visible
          const timerParagraph = guestUsageInfo.querySelector('p');
          const resetTimer = document.getElementById('resetTimer');
          if (!resetTimer && timerParagraph) {
            // Calculer le temps restant (approximatif)
            const timeRemaining = 86400; // Par défaut 24h, sera mis à jour par le serveur
            timerParagraph.innerHTML = '<span id="resetTimer" data-reset-time="' + timeRemaining + '"></span> â€¢ <a href="zone_membres/register.php" class="text-green-400 hover:text-green-300 underline">Inscrivez-vous</a> pour un accès illimité';

            // Réinitialiser le timer
            const newResetTimer = document.getElementById('resetTimer');
            if (newResetTimer) {
              let timeRem = parseInt(newResetTimer.dataset.resetTime);
              let dynamicResetInterval = null;
              const updateTimer = () => {
                if (timeRem <= 0) {
                  newResetTimer.innerHTML = '<i class="fa-solid fa-rotate-right text-green-400"></i> Réinitialisation disponible - rechargez la page';
                  if (dynamicResetInterval) clearInterval(dynamicResetInterval);
                  return;
                }
                const hours = Math.floor(timeRem / 3600);
                const minutes = Math.floor((timeRem % 3600) / 60);
                const seconds = timeRem % 60;
                let timeText = hours > 0 ? `${hours}h ${minutes}min ${seconds}s` : (minutes > 0 ? `${minutes}min ${seconds}s` : `${seconds}s`);
                newResetTimer.innerHTML = `<i class="fa-solid fa-clock text-amber-400"></i> Réinitialisation dans ${timeText}`;
                timeRem--;
              };
              updateTimer();
              dynamicResetInterval = setInterval(updateTimer, 1000);
            }
          }
        } else {
          usageText.textContent = `${remaining} essai${remaining > 1 ? 's' : ''} gratuit${remaining > 1 ? 's' : ''} restant${remaining > 1 ? 's' : ''}`;
        }
      }
    }

    // Fonction pour afficher le message de limite atteinte
    function showLimitReachedMessage(chatContainer) {
      const resetTimerEl = document.getElementById('resetTimer');
      const timeInfo = resetTimerEl ? resetTimerEl.textContent : 'quelques heures';

      chatContainer.innerHTML += `
        <div class="flex justify-start">
          <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl rounded-bl-md px-4 py-4 max-w-[85%]">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-gift text-amber-400"></i>
              </div>
              <div>
                <p class="text-amber-300 font-medium mb-1">Limite d'essais atteinte</p>
                <p class="text-gray-300 text-sm mb-3">Vous avez utilisé vos ${guestUsageLimit} essais gratuits.</p>
                <p class="text-gray-400 text-sm mb-3">${timeInfo}, ou créez un compte gratuit pour un accès illimité !</p>
                <div class="flex gap-2">
                  <a href="zone_membres/register.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium transition-colors">
                    <i class="fa-solid fa-user-plus"></i>
                    S'inscrire gratuitement
                  </a>
                  <a href="zone_membres/login.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-700 text-gray-300 text-sm transition-colors">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Se connecter
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
      chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // ==== MODALES PERSONNALISÃ‰ES ====
    // Modal Confirm personnalisée
    function showConfirmModal(message) {
      return new Promise((resolve) => {
        const modalId = 'confirm-modal-' + Date.now();
        const modalHTML = `
          <div id="${modalId}" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="modal-content bg-[#1e1e1e] rounded-2xl shadow-2xl border border-gray-700/50 max-w-md w-full">
              <div class="p-6">
                <div class="flex items-start gap-4">
                  <div class="shrink-0">
                    <div class="w-12 h-12 rounded-full bg-amber-500/10 flex items-center justify-center">
                      <i class="fa-solid fa-triangle-exclamation text-amber-400 text-xl"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <h3 class="text-lg font-semibold text-white mb-2">Confirmation</h3>
                    <p class="text-gray-300 text-sm">${message}</p>
                  </div>
                </div>
              </div>
              <div class="flex gap-3 px-6 pb-6">
                <button data-action="cancel" class="flex-1 px-4 py-2.5 rounded-lg bg-gray-700 text-gray-300 font-medium transition-colors">
                  Annuler
                </button>
                <button data-action="confirm" class="flex-1 px-4 py-2.5 rounded-lg bg-blue-600 text-white font-medium transition-colors">
                  OK
                </button>
              </div>
            </div>
          </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        const modal = document.getElementById(modalId);

        const handleClick = (e) => {
          const action = e.target.dataset.action;
          if (action === 'confirm') {
            modal.remove();
            resolve(true);
          } else if (action === 'cancel' || e.target === modal) {
            modal.remove();
            resolve(false);
          }
        };

        modal.addEventListener('click', handleClick);

        // Focus sur le bouton OK
        setTimeout(() => modal.querySelector('[data-action="confirm"]').focus(), 100);
      });
    }

    // Modal Prompt personnalisée
    function showPromptModal(message, defaultValue = '') {
      return new Promise((resolve) => {
        const modalId = 'prompt-modal-' + Date.now();
        const modalHTML = `
          <div id="${modalId}" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="modal-content bg-[#1e1e1e] rounded-2xl shadow-2xl border border-gray-700/50 max-w-md w-full">
              <div class="p-6">
                <h3 class="text-lg font-semibold text-white mb-4">${message}</h3>
                <input 
                  type="text" 
                  value="${escapeHtml(defaultValue)}" 
                  class="modal-input w-full px-4 py-2.5 bg-[#2a2a2a] border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                  placeholder="Entrez le texte..."
                />
              </div>
              <div class="flex gap-3 px-6 pb-6">
                <button data-action="cancel" class="flex-1 px-4 py-2.5 rounded-lg bg-gray-700 text-gray-300 font-medium transition-colors">
                  Annuler
                </button>
                <button data-action="confirm" class="flex-1 px-4 py-2.5 rounded-lg bg-blue-600 text-white font-medium transition-colors">
                  OK
                </button>
              </div>
            </div>
          </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        const modal = document.getElementById(modalId);
        const input = modal.querySelector('input');

        const handleAction = (confirmed) => {
          if (confirmed) {
            const value = input.value.trim();
            modal.remove();
            resolve(value || null);
          } else {
            modal.remove();
            resolve(null);
          }
        };

        modal.querySelector('[data-action="confirm"]').addEventListener('click', () => handleAction(true));
        modal.querySelector('[data-action="cancel"]').addEventListener('click', () => handleAction(false));

        // Fermer en cliquant sur le backdrop
        modal.addEventListener('click', (e) => {
          if (e.target === modal) handleAction(false);
        });

        // Soumettre avec Enter
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') handleAction(true);
          if (e.key === 'Escape') handleAction(false);
        });

        // Focus et sélection du texte
        setTimeout(() => {
          input.focus();
          input.select();
        }, 100);
      });
    }

    // Fonction pour envoyer le message
    async function sendMessage() {
      const message = messageInput.value.trim();
      const hasFiles = attachedFiles.length > 0;

      if (!message && !hasFiles) return;

      // Reset auto-scroll when user sends new message
      shouldAutoScroll = true;

      // Vérifier la limite pour les visiteurs avant d'envoyer
      if (isGuest && guestUsageCount >= guestUsageLimit) {
        // Masquer le message de bienvenue, le logo et afficher le chat
        document.getElementById('welcomeMessage').classList.add('hidden');
        document.getElementById('logoContainer').classList.add('hidden');
        const chatContainer = document.getElementById('chatContainer');
        chatContainer.classList.remove('hidden');
        showLimitReachedMessage(chatContainer);
        return;
      }

      // Masquer le message de bienvenue, le logo et afficher le chat
      document.getElementById('welcomeMessage').classList.add('hidden');
      document.getElementById('logoContainer').classList.add('hidden');
      const chatContainer = document.getElementById('chatContainer');
      chatContainer.classList.remove('hidden');

      // Activer l'animation de la zone de saisie vers le bas
      const mainContent = document.getElementById('mainContent');
      mainContent.classList.remove('centered');
      mainContent.classList.add('chat-active');

      // === GESTION CONVERSATION ===
      // Créer une conversation si nécessaire (utilisateurs connectés uniquement)
      let isNewConversation = false;
      let responseSaved = false; // Flag pour éviter la double sauvegarde
      <?php if (!$isGuest): ?>
        if (!currentConversationId) {
          try {
            const createResp = await fetch('api/conversations.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                action: 'create',
                title: message.substring(0, 50)
              })
            });
            const createData = await createResp.json();
            if (createData.success && createData.conversation_id) {
              currentConversationId = createData.conversation_id;
              isNewConversation = true;
              // Ajouter à la liste locale
              conversations.unshift({
                id: currentConversationId,
                title: message.substring(0, 50),
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
                message_count: 0
              });
              renderConversationList();
            }
          } catch (err) {
            console.error('Erreur création conversation:', err);
          }
        }

        // Sauvegarder le message utilisateur
        if (currentConversationId) {
          try {
            await fetch('api/conversations.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                action: 'save_message',
                conversation_id: currentConversationId,
                role: 'user',
                content: message
              })
            });
            conversationMessages.push({
              role: 'user',
              content: message
            });
          } catch (err) {
            console.error('Erreur sauvegarde message user:', err);
          }
        }
      <?php endif; ?>

      // Sauvegarder le message original pour la génération du titre
      const originalMessage = message;

      // Sauvegarder les fichiers avant de les effacer
      const filesToSend = [...attachedFiles];

      // Construire le contenu du message avec les fichiers
      let filesHtml = '';
      if (hasFiles) {
        filesHtml = '<div class="flex flex-wrap gap-2 mb-2">';
        for (const {
            file
          }
          of filesToSend) {
          if (file.type.startsWith('image/')) {
            const dataUrl = await readFileAsDataURL(file);
            filesHtml += `<img src="${dataUrl}" alt="${escapeHtml(file.name)}" class="w-16 h-16 rounded-lg object-cover border border-green-500/30" />`;
          } else {
            const icon = getFileIcon(file.type, file.name);
            filesHtml += `<div class="w-16 h-16 rounded-lg border border-green-500/30 bg-gray-700/50 flex flex-col items-center justify-center"><span class="text-lg">${icon}</span><span class="text-xs text-gray-400">${getFileExtension(file.name)}</span></div>`;
          }
        }
        filesHtml += '</div>';
      }

      // Afficher le message utilisateur
      chatContainer.innerHTML += `
        <div class="flex justify-end">
          <div class="bg-green-600/20 border border-green-500/30 rounded-2xl rounded-br-md px-4 py-3 max-w-[80%]">
            ${filesHtml}
            ${message ? `<p class="text-gray-200 text-sm">${escapeHtml(message)}</p>` : ''}
          </div>
        </div>
      `;

      // Vider l'input, les fichiers et désactiver le bouton
      messageInput.value = '';
      attachedFiles = [];
      filePreviewContainer.innerHTML = '';
      filePreviewContainer.classList.add('hidden');
      sendButton.disabled = true;
      sendButton.classList.remove('bg-green-600', 'text-white', 'hover:bg-green-500', 'cursor-pointer');
      sendButton.classList.add('bg-gray-700', 'text-gray-400', 'cursor-not-allowed', 'disabled:opacity-50');

      // Créer un AbortController pour pouvoir annuler
      currentAbortController = new AbortController();

      // Passer le bouton en mode annulation
      setButtonCancelMode(true);

      // Afficher l'indicateur de chargement
      const loadingId = 'loading-' + Date.now();
      chatContainer.innerHTML += `
        <div id="${loadingId}" class="flex justify-start">
          <div class="bg-gray-700/50 border border-gray-600/50 rounded-2xl rounded-bl-md px-4 py-3">
            <div class="flex items-center">
              <div class="typing-indicator">
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot"></span>
              </div>
            </div>
          </div>
        </div>
      `;
      // Auto-scroll to show loading indicator
      if (shouldAutoScroll) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
      }

      try {
        // Préparer les fichiers en base64 pour l'envoi
        const filesData = [];
        for (const {
            file
          }
          of filesToSend) {
          const base64 = await readFileAsDataURL(file);
          filesData.push({
            name: file.name,
            type: file.type,
            size: file.size,
            data: base64
          });
        }

        // Utiliser fetch avec streaming
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const fetchHeaders = {
          'Content-Type': 'application/json'
        };
        if (csrfToken) {
          fetchHeaders['X-CSRF-Token'] = csrfToken;
        }

        const response = await fetch('api/streamApi.php', {
          method: 'POST',
          headers: fetchHeaders,
          body: JSON.stringify({
            message: message,
            model: selectedModel.model,
            provider: selectedModel.provider,
            files: filesData
          }),
          signal: currentAbortController.signal
        });

        if (!response.ok) {
          throw new Error('Erreur de connexion');
        }

        // Supprimer l'indicateur de chargement maintenant que la connexion est établie
        document.getElementById(loadingId)?.remove();

        // Créer le conteneur de réponse pour le streaming
        const responseId = 'response-' + Date.now();
        chatContainer.innerHTML += `
          <div class="flex justify-start">
            <div class="ai-message bg-gray-700/50 border border-gray-600/50 rounded-2xl rounded-bl-md px-4 py-3 max-w-[85%]">
              <div id="${responseId}" class="text-gray-200 text-sm streaming-response"></div>
            </div>
          </div>
        `;
        const responseContainer = document.getElementById(responseId);
        let fullResponse = '';
        let renderTimeout = null;
        currentStreamingContent = ''; // Réinitialiser le contenu global

        // Fonction pour rendre le markdown avec debounce
        function renderStreaming() {
          // Rendre le markdown en temps réel
          responseContainer.innerHTML = renderMarkdownStreaming(fullResponse);
          // Appliquer highlight.js aux blocs de code complets
          responseContainer.querySelectorAll('pre code').forEach((block) => {
            if (!block.dataset.highlighted) {
              hljs.highlightElement(block);
              block.dataset.highlighted = 'true';
            }
          });
          // Ajouter le curseur après le dernier texte
          updateCursor(responseContainer);
          // Only auto-scroll if user hasn't scrolled up
          if (shouldAutoScroll) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
          }
        }

        // Batch pour accumuler les petits chunks
        let batchBuffer = '';
        let batchTimer = null;

        // Lire le stream SSE
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        while (true) {
          const {
            done,
            value
          } = await reader.read();
          if (done) break;

          const chunk = decoder.decode(value, {
            stream: true
          });
          const lines = chunk.split('\n');

          for (const line of lines) {
            if (line.startsWith('data:')) {
              try {
                const data = JSON.parse(line.slice(5).trim());

                if (data.error) {
                  responseContainer.innerHTML = `<span class="text-red-400">Erreur: ${escapeHtml(data.error)}</span>`;
                  // Si limite atteinte, afficher le message d'inscription
                  if (data.limit_reached && isGuest) {
                    guestUsageCount = data.usage_count || guestUsageLimit;
                    updateGuestUsageDisplay();
                    setTimeout(() => showLimitReachedMessage(chatContainer), 500);
                  }
                  break;
                }

                if (data.content) {
                  fullResponse += data.content;
                  batchBuffer += data.content;
                  currentStreamingContent = fullResponse; // Synchroniser avec la variable globale

                  // Batching intelligent : rendre immédiatement si buffer > 20 caractères
                  if (batchBuffer.length > 20) {
                    batchBuffer = '';
                    if (renderTimeout) clearTimeout(renderTimeout);
                    renderStreaming();
                  } else {
                    // Sinon debounce court
                    if (renderTimeout) clearTimeout(renderTimeout);
                    renderTimeout = setTimeout(() => {
                      batchBuffer = '';
                      renderStreaming();
                    }, 16); // ~60fps
                  }
                }

                if (data.done) {
                  // Annuler le timeout en attente
                  if (renderTimeout) clearTimeout(renderTimeout);
                  // Réinitialiser le bouton et le curseur
                  setButtonCancelMode(false);
                  currentAbortController = null;
                  currentStreamingContent = ''; // Réinitialiser
                  responseContainer.classList.add('done');
                  // Appliquer le rendu Markdown final
                  responseContainer.innerHTML = renderMarkdown(fullResponse);
                  // Appliquer la coloration syntaxique
                  responseContainer.querySelectorAll('pre code').forEach((block) => {
                    if (!block.dataset.highlighted) {
                      hljs.highlightElement(block);
                      block.dataset.highlighted = 'true';
                    }
                  });

                  // Mettre à jour le compteur d'utilisations pour les visiteurs
                  if (isGuest && data.usage_count !== undefined) {
                    guestUsageCount = data.usage_count;
                    updateGuestUsageDisplay();
                  }

                  // Mettre à jour les limites de rate limiting pour les utilisateurs connectés
                  if (!isGuest && data.rate_limits && typeof window.updateRateLimits === 'function') {
                    window.updateRateLimits(data.rate_limits);
                  }

                  // === SAUVEGARDER LA RÃ‰PONSE IA ===
                  <?php if (!$isGuest): ?>
                    if (currentConversationId && fullResponse && !responseSaved) {
                      responseSaved = true; // Ã‰viter la double sauvegarde
                      // Sauvegarder la réponse IA
                      fetch('api/conversations.php', {
                        method: 'POST',
                        headers: {
                          'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                          action: 'save_message',
                          conversation_id: currentConversationId,
                          role: 'assistant',
                          content: fullResponse,
                          model: selectedModel.model,
                          provider: selectedModel.provider
                        })
                      }).catch(err => console.error('Erreur sauvegarde réponse IA:', err));

                      conversationMessages.push({
                        role: 'assistant',
                        content: fullResponse
                      });

                      // Mettre à jour le nombre de messages dans la sidebar
                      const convIndex = conversations.findIndex(c => c.id == currentConversationId);
                      if (convIndex !== -1) {
                        conversations[convIndex].message_count = (conversations[convIndex].message_count || 0) + 2;
                        conversations[convIndex].updated_at = new Date().toISOString();
                        renderConversationList(conversationSearch?.value || '');
                      }

                      // Générer un titre automatique après le premier échange
                      if (isNewConversation && conversationMessages.length >= 2) {
                        generateConversationTitle(currentConversationId, originalMessage, fullResponse);
                      }
                    }
                  <?php endif; ?>
                }
              } catch (e) {
                // Ignorer les erreurs de parsing JSON
              }
            }
          }
        }
        // S'assurer que le curseur est retiré à la fin
        responseContainer?.classList.add('done');
        setButtonCancelMode(false);
        currentAbortController = null;
      } catch (error) {
        // Réinitialiser le bouton
        setButtonCancelMode(false);
        currentAbortController = null;

        // Retirer le curseur en cas d'erreur
        const streamingEl = document.querySelector('.streaming-response:not(.done)');
        if (streamingEl) streamingEl.classList.add('done');

        document.getElementById(loadingId)?.remove();

        // Ne pas afficher d'erreur si c'est une annulation volontaire
        if (error.name === 'AbortError') {
          return;
        }

        chatContainer.innerHTML += `
          <div class="flex justify-start">
            <div class="bg-red-500/10 border border-red-500/30 rounded-2xl rounded-bl-md px-4 py-3 max-w-[80%]">
              <p class="text-red-400 text-sm">Erreur de connexion. Veuillez réessayer.</p>
            </div>
          </div>
        `;
      }

      // Final scroll to bottom after send
      shouldAutoScroll = true; // Always auto-scroll on new message
      chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // Fonction pour échapper le HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Configuration de Marked avec Highlight.js
    marked.setOptions({
      highlight: function(code, lang) {
        if (lang && hljs.getLanguage(lang)) {
          try {
            return hljs.highlight(code, {
              language: lang
            }).value;
          } catch (e) {}
        }
        return hljs.highlightAuto(code).value;
      },
      breaks: true,
      gfm: true
    });

    // Fonction pour rendre le Markdown pendant le streaming (gère les blocs incomplets)
    function renderMarkdownStreaming(text) {
      // Compter les occurrences de ``` pour voir si on a un bloc incomplet
      const codeBlockMarkers = text.match(/```/g) || [];
      const hasIncompleteBlock = codeBlockMarkers.length % 2 !== 0;

      if (hasIncompleteBlock) {
        // Trouver le dernier ``` qui ouvre un bloc non fermé
        const lastOpenIndex = text.lastIndexOf('```');
        const beforeCode = text.substring(0, lastOpenIndex);
        const codeBlockPart = text.substring(lastOpenIndex + 3);

        // Extraire le langage (première ligne après ```)
        const firstNewline = codeBlockPart.indexOf('\n');
        let lang = 'code';
        let codeContent = codeBlockPart;

        if (firstNewline !== -1) {
          const possibleLang = codeBlockPart.substring(0, firstNewline).trim();
          if (possibleLang && /^[a-zA-Z0-9_+-]+$/.test(possibleLang)) {
            lang = possibleLang;
            codeContent = codeBlockPart.substring(firstNewline + 1);
          }
        } else if (codeBlockPart.trim() && /^[a-zA-Z0-9_+-]+$/.test(codeBlockPart.trim())) {
          // Seulement le langage, pas encore de contenu
          lang = codeBlockPart.trim();
          codeContent = '';
        }

        // Parser le texte avant le bloc incomplet
        let html = '';
        if (beforeCode.trim()) {
          html = marked.parse(beforeCode);
          html = addCodeHeaders(html);
        }

        // Ajouter le bloc de code incomplet avec style
        const langDisplay = lang.charAt(0).toUpperCase() + lang.slice(1);
        html += `<div class="code-block-wrapper"><div class="code-header"><span>${langDisplay}</span><span class="text-xs text-green-400 animate-pulse"><i class="fa-solid fa-circle text-[8px] mr-1"></i>En cours...</span></div><pre><code class="language-${lang}">${escapeHtml(codeContent)}</code></pre></div>`;

        return html;
      }

      // Pas de bloc incomplet, parser normalement
      let html = marked.parse(text);
      return addCodeHeaders(html);
    }

    // Fonction helper pour ajouter les headers aux blocs de code
    function addCodeHeaders(html) {
      // Ajouter le header avec langage et bouton copier pour chaque bloc de code
      html = html.replace(/<pre><code class="language-(\w+)">/g, (match, lang) => {
        const langDisplay = lang.charAt(0).toUpperCase() + lang.slice(1);
        return `<div class="code-block-wrapper"><div class="code-header"><span>${langDisplay}</span><button class="copy-btn" onclick="copyCode(this)"><i class="fa-regular fa-copy"></i> Copier</button></div><pre><code class="language-${lang}">`;
      });

      // Fermer le wrapper après </pre>
      html = html.replace(/<\/code><\/pre>/g, '</code></pre></div>');

      // Pour les blocs sans langage spécifié
      html = html.replace(/<pre><code>(?!<)/g, () => {
        return `<div class="code-block-wrapper"><div class="code-header"><span>Code</span><button class="copy-btn" onclick="copyCode(this)"><i class="fa-regular fa-copy"></i> Copier</button></div><pre><code>`;
      });

      return html;
    }

    // Fonction pour rendre le Markdown avec blocs de code stylisés
    function renderMarkdown(text) {
      // Parser le markdown
      let html = marked.parse(text);
      return addCodeHeaders(html);
    }

    // Fonction pour copier le code
    function copyCode(button) {
      const wrapper = button.closest('.code-block-wrapper');
      const code = wrapper.querySelector('code');
      const text = code.textContent;

      navigator.clipboard.writeText(text).then(() => {
        button.innerHTML = '<i class="fa-solid fa-check"></i> Copié !';
        button.classList.add('copied');
        setTimeout(() => {
          button.innerHTML = '<i class="fa-regular fa-copy"></i> Copier';
          button.classList.remove('copied');
        }, 2000);
      }).catch(err => {
        console.error('Erreur de copie:', err);
      });
    }

    // Fonction pour lire un fichier en Data URL
    function readFileAsDataURL(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
      });
    }

    // Envoyer avec le bouton ou annuler
    sendButton.addEventListener('click', function() {
      if (isStreaming) {
        cancelRequest();
      } else {
        sendMessage();
      }
    });

    // Envoyer avec Enter
    messageInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter' && !e.shiftKey && !sendButton.disabled) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Gestion des pièces jointes
    const attachButton = document.getElementById('attachButton');
    const fileInput = document.getElementById('fileInput');
    const filePreviewContainer = document.getElementById('filePreviewContainer');
    let attachedFiles = [];

    // Ouvrir le sélecteur de fichiers
    attachButton.addEventListener('click', function() {
      fileInput.click();
    });

    // Gérer la sélection de fichiers
    fileInput.addEventListener('change', function(e) {
      const files = Array.from(e.target.files);
      files.forEach(file => addFilePreview(file));
      // Réinitialiser l'input pour permettre de sélectionner le même fichier
      fileInput.value = '';
    });

    // Fonction pour ajouter une prévisualisation de fichier
    function addFilePreview(file) {
      // Vérifier la taille (max 10 MB)
      if (file.size > 10 * 1024 * 1024) {
        alert(`Le fichier "${file.name}" est trop volumineux (max 10 MB).`);
        return;
      }

      // Ajouter le fichier à la liste
      const fileId = 'file-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
      attachedFiles.push({
        id: fileId,
        file: file
      });

      // Créer l'élément de prévisualisation
      const previewEl = document.createElement('div');
      previewEl.id = fileId;
      previewEl.className = 'relative group';

      // Vérifier si c'est une image
      if (file.type.startsWith('image/')) {
        // Prévisualisation image
        const reader = new FileReader();
        reader.onload = function(e) {
          previewEl.innerHTML = `
            <div class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-600/50 bg-gray-700/50">
              <img src="${e.target.result}" alt="${escapeHtml(file.name)}" class="w-full h-full object-cover" />
              <button 
                onclick="removeFile('${fileId}')"
                class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 hover:bg-red-400 rounded-full flex items-center justify-center text-white text-xs opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <p class="text-xs text-gray-500 mt-1 truncate max-w-20" title="${escapeHtml(file.name)}">${escapeHtml(file.name.length > 12 ? file.name.substring(0, 10) + '...' : file.name)}</p>
          `;
        };
        reader.readAsDataURL(file);
      } else {
        // Prévisualisation fichier (non-image)
        const icon = getFileIcon(file.type, file.name);
        previewEl.innerHTML = `
          <div class="relative w-20 h-20 rounded-lg border border-gray-600/50 bg-gray-700/50 flex flex-col items-center justify-center">
            <div class="text-2xl">${icon}</div>
            <p class="text-xs text-gray-400 mt-1">${getFileExtension(file.name)}</p>
            <button 
              onclick="removeFile('${fileId}')"
              class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 hover:bg-red-400 rounded-full flex items-center justify-center text-white text-xs opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <p class="text-xs text-gray-500 mt-1 truncate max-w-20" title="${escapeHtml(file.name)}">${escapeHtml(file.name.length > 12 ? file.name.substring(0, 10) + '...' : file.name)}</p>
        `;
      }

      filePreviewContainer.appendChild(previewEl);
      filePreviewContainer.classList.remove('hidden');

      // Activer le bouton d'envoi si des fichiers sont attachés
      updateSendButtonState();
    }

    // Fonction pour obtenir l'icône selon le type de fichier
    function getFileIcon(mimeType, filename) {
      if (mimeType === 'application/pdf') return '<i class="fa-solid fa-file-pdf text-red-400"></i>';
      if (mimeType.includes('word') || filename.endsWith('.doc') || filename.endsWith('.docx')) return '<i class="fa-solid fa-file-word text-blue-400"></i>';
      if (mimeType === 'text/plain' || filename.endsWith('.txt')) return '<i class="fa-solid fa-file-lines text-gray-400"></i>';
      if (mimeType === 'text/csv' || filename.endsWith('.csv')) return '<i class="fa-solid fa-file-csv text-green-400"></i>';
      if (mimeType === 'application/json' || filename.endsWith('.json')) return '<i class="fa-solid fa-file-code text-yellow-400"></i>';
      if (mimeType === 'text/xml' || filename.endsWith('.xml')) return '<i class="fa-solid fa-file-code text-orange-400"></i>';
      if (filename.endsWith('.md')) return '<i class="fa-brands fa-markdown text-purple-400"></i>';
      return '<i class="fa-solid fa-paperclip text-gray-400"></i>';
    }

    // Fonction pour obtenir l'extension du fichier
    function getFileExtension(filename) {
      const ext = filename.split('.').pop();
      return ext ? ext.toUpperCase() : 'FILE';
    }

    // Fonction pour supprimer un fichier
    function removeFile(fileId) {
      // Supprimer de la liste
      attachedFiles = attachedFiles.filter(f => f.id !== fileId);

      // Supprimer l'élément DOM
      const el = document.getElementById(fileId);
      if (el) el.remove();

      // Masquer le container si plus de fichiers
      if (attachedFiles.length === 0) {
        filePreviewContainer.classList.add('hidden');
      }

      // Mettre à jour l'état du bouton d'envoi
      updateSendButtonState();
    }

    // Fonction pour mettre à jour l'état du bouton d'envoi
    function updateSendButtonState() {
      const hasText = messageInput.value.trim().length > 0;
      const hasFiles = attachedFiles.length > 0;

      if (hasText || hasFiles) {
        sendButton.disabled = false;
        sendButton.classList.remove('bg-gray-700', 'text-gray-400', 'cursor-not-allowed', 'disabled:opacity-50');
        sendButton.classList.add('bg-green-600', 'text-white', 'hover:bg-green-500', 'cursor-pointer');
      } else {
        sendButton.disabled = true;
        sendButton.classList.remove('bg-green-600', 'text-white', 'hover:bg-green-500', 'cursor-pointer');
        sendButton.classList.add('bg-gray-700', 'text-gray-400', 'cursor-not-allowed', 'disabled:opacity-50');
      }

      // Mise à jour du bouton mobile également
      if (typeof updateMobileSendButtonState === 'function') {
        updateMobileSendButtonState();
      }
    }

    // Drag & Drop sur la zone de saisie
    const inputContainerEl = document.getElementById('inputContainer');

    if (inputContainerEl) {
      inputContainerEl.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('border-green-500', 'bg-green-500/10');
      });

      inputContainerEl.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('border-green-500', 'bg-green-500/10');
      });

      inputContainerEl.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('border-green-500', 'bg-green-500/10');

        const files = Array.from(e.dataTransfer.files);
        files.forEach(file => addFilePreview(file));
      });
    }

    // Speech-to-Text avec Web Speech API
    const micButton = document.getElementById('micButton');
    let recognition = null;
    let isRecording = false;

    // Vérifier si l'API Speech Recognition est disponible
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      recognition = new SpeechRecognition();

      // Configuration de la reconnaissance vocale
      recognition.continuous = false; // Arrêt automatique après une phrase
      recognition.interimResults = true; // Résultats intermédiaires
      recognition.lang = ''; // Langue française

      // Quand la reconnaissance commence
      recognition.onstart = function() {
        isRecording = true;
        micButton.classList.remove('text-gray-500', 'hover:text-gray-300');
        micButton.classList.add('text-red-500', 'animate-pulse');
        micButton.title = 'Enregistrement en cours... Cliquez pour arrêter';
      };

      // Quand on reçoit des résultats
      recognition.onresult = function(event) {
        let interimTranscript = '';
        let finalTranscript = '';

        for (let i = event.resultIndex; i < event.results.length; i++) {
          const transcript = event.results[i][0].transcript;
          if (event.results[i].isFinal) {
            finalTranscript += transcript + ' ';
          } else {
            interimTranscript += transcript;
          }
        }

        // Mettre à jour l'input avec le texte reconnu
        if (finalTranscript) {
          messageInput.value = (messageInput.value + ' ' + finalTranscript).trim();
          // Déclencher l'événement input pour activer le bouton d'envoi
          messageInput.dispatchEvent(new Event('input'));
        } else if (interimTranscript) {
          // Afficher les résultats intermédiaires (optionnel)
          messageInput.placeholder = interimTranscript;
        }
      };

      // Quand la reconnaissance se termine
      recognition.onend = function() {
        isRecording = false;
        micButton.classList.remove('text-red-500', 'animate-pulse');
        micButton.classList.add('text-gray-500', 'hover:text-gray-300');
        micButton.title = 'Cliquer pour dicter';
        messageInput.placeholder = 'Posez une question. Tapez @ pour mentions et / pour raccourcis.';
      };

      // Gestion des erreurs
      recognition.onerror = function(event) {
        console.error('Erreur de reconnaissance vocale:', event.error);
        isRecording = false;
        micButton.classList.remove('text-red-500', 'animate-pulse');
        micButton.classList.add('text-gray-500', 'hover:text-gray-300');
        micButton.title = 'Cliquer pour dicter';

        // Afficher un message d'erreur selon le type
        let errorMessage = '';
        switch (event.error) {
          case 'no-speech':
            errorMessage = 'Aucune parole détectée. Réessayez.';
            break;
          case 'audio-capture':
            errorMessage = 'Microphone non détecté. Vérifiez vos périphériques.';
            break;
          case 'not-allowed':
            errorMessage = 'Permission microphone refusée. Autorisez l\'accès au micro.';
            break;
          default:
            errorMessage = 'Erreur de reconnaissance vocale: ' + event.error;
        }
        messageInput.placeholder = errorMessage;
        setTimeout(() => {
          messageInput.placeholder = 'Posez une question. Tapez @ pour mentions et / pour raccourcis.';
        }, 3000);
      };

      // Gérer le clic sur le bouton micro
      micButton.addEventListener('click', async function() {
        if (isRecording) {
          // Arrêter l'enregistrement
          recognition.stop();
        } else {
          // Demander d'abord la permission du microphone via le modal du navigateur
          try {
            // Cette ligne déclenche le modal de permission du navigateur
            const stream = await navigator.mediaDevices.getUserMedia({
              audio: true
            });
            // Arrêter le stream immédiatement (on n'en a pas besoin, juste la permission)
            stream.getTracks().forEach(track => track.stop());

            // Maintenant démarrer la reconnaissance vocale
            recognition.start();
          } catch (error) {
            console.error('Erreur de permission microphone:', error);

            let errorMessage = '';
            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
              errorMessage = 'Permission microphone refusée. Veuillez autoriser l\'accès au microphone.';
            } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
              errorMessage = 'Aucun microphone détecté. Vérifiez vos périphériques.';
            } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
              errorMessage = 'Le microphone est utilisé par une autre application.';
            } else {
              errorMessage = 'Erreur d\'accès au microphone: ' + error.message;
            }

            messageInput.placeholder = errorMessage;
            setTimeout(() => {
              messageInput.placeholder = 'Posez une question. Tapez @ pour mentions et / pour raccourcis.';
            }, 4000);
          }
        }
      });
    } else {
      // API non disponible
      micButton.classList.add('opacity-50', 'cursor-not-allowed');
      micButton.title = 'Reconnaissance vocale non disponible sur ce navigateur';
      micButton.addEventListener('click', function(e) {
        e.preventDefault();
        alert('La reconnaissance vocale n\'est pas disponible sur votre navigateur. Utilisez Chrome, Edge ou Safari.');
      });
    }

    // --- streaming caret helpers ---
    function removeStreamingCursors(container) {
      if (!container) return;
      container.querySelectorAll('.streaming-cursor').forEach(el => el.remove());
    }

    function updateCursor(container) {
      if (!container || container.classList.contains('done')) return;

      // Supprimer l'ancien curseur d'abord
      removeStreamingCursors(container);

      const cursor = document.createElement('span');
      cursor.className = 'streaming-cursor';

      // Trouver le dernier nÅ“ud texte non vide
      function getLastTextNode(el) {
        const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
        let last = null;
        while (walker.nextNode()) {
          if (walker.currentNode.textContent.trim()) last = walker.currentNode;
        }
        return last;
      }

      const lastText = getLastTextNode(container);
      if (lastText && lastText.parentNode) {
        lastText.parentNode.insertBefore(cursor, lastText.nextSibling);
      } else {
        container.appendChild(cursor);
      }
    }
    // --- fin helpers ---

    <?php if ($user): ?>
      // Menu profil (uniquement si connecté)
      const profileButton = document.getElementById("profileButton");
      const profileMenu = document.getElementById("profileMenu");
      const profileIcon = document.getElementById("profileIcon");
      let isMenuOpen = false;

      profileButton.addEventListener("click", function(e) {
        // Sur mobile/tablette, laisser le handler mobile gérer l'événement
        if (window.innerWidth <= 800) {
          return; // Le handler avec capture:true ouvrira le bottom sheet
        }

        e.stopPropagation();
        isMenuOpen = !isMenuOpen;

        if (isMenuOpen) {
          // Ouvrir le menu
          profileMenu.classList.remove(
            "opacity-0",
            "invisible",
            "translate-y-2"
          );
          profileMenu.classList.add(
            "opacity-100",
            "visible",
            "translate-y-0"
          );
          // Tourner l'icône vers le haut
          profileIcon.classList.remove("rotate-180");
          profileIcon.classList.add("rotate-0");
        } else {
          // Fermer le menu
          profileMenu.classList.remove(
            "opacity-100",
            "visible",
            "translate-y-0"
          );
          profileMenu.classList.add(
            "opacity-0",
            "invisible",
            "translate-y-2"
          );
          // Tourner l'icône vers le bas
          profileIcon.classList.remove("rotate-0");
          profileIcon.classList.add("rotate-180");
        }
      });

      // Fermer le menu si on clique ailleurs
      document.addEventListener("click", function() {
        if (isMenuOpen) {
          isMenuOpen = false;
          profileMenu.classList.remove(
            "opacity-100",
            "visible",
            "translate-y-0"
          );
          profileMenu.classList.add(
            "opacity-0",
            "invisible",
            "translate-y-2"
          );
          profileIcon.classList.remove("rotate-0");
          profileIcon.classList.add("rotate-180");
        }
      });
    <?php endif; ?>

    // ===== GESTION DE LA SIDEBAR HISTORIQUE =====
    const conversationSidebar = document.getElementById('conversationSidebar');
    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');
    const openSidebarBtn = document.getElementById('openSidebarBtn');
    const newConversationBtn = document.getElementById('newConversationBtn');
    const conversationSearch = document.getElementById('conversationSearch');
    const conversationList = document.getElementById('conversationList');
    const exportConversationsBtn = document.getElementById('exportConversationsBtn');
    const noConversationsPlaceholder = document.getElementById('noConversationsPlaceholder');
    const profileContainer = document.getElementById('profileContainer');

    // Ã‰tat de la sidebar
    let currentConversationId = null;
    let conversations = [];
    let conversationMessages = []; // Messages de la conversation actuelle en mémoire

    // Charger la préférence de sidebar depuis localStorage
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (sidebarCollapsed) {
      conversationSidebar.dataset.collapsed = 'true';
      openSidebarBtn.classList.remove('hidden');
      if (profileContainer && window.innerWidth >= 640) {
        profileContainer.style.left = '0.5rem';
      }
    }

    // Toggle sidebar
    function toggleSidebar() {
      const isCollapsed = conversationSidebar.dataset.collapsed === 'true';
      conversationSidebar.dataset.collapsed = isCollapsed ? 'false' : 'true';
      openSidebarBtn.classList.toggle('hidden', isCollapsed);
      localStorage.setItem('sidebarCollapsed', !isCollapsed);

      // Gérer l'overlay mobile
      const overlay = document.getElementById('sidebarOverlay');
      if (window.innerWidth < 768) {
        overlay.dataset.visible = isCollapsed ? 'true' : 'false';
        overlay.classList.toggle('opacity-0', !isCollapsed);
        overlay.classList.toggle('invisible', !isCollapsed);
        overlay.classList.toggle('opacity-100', isCollapsed);
        overlay.classList.toggle('visible', isCollapsed);
      }

      // Le profil reste fixe en bas à gauche, derrière la sidebar (z-index 30 < 40)
    }

    toggleSidebarBtn.addEventListener('click', toggleSidebar);
    openSidebarBtn.addEventListener('click', toggleSidebar);

    // Overlay mobile ferme la sidebar
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
      if (conversationSidebar.dataset.collapsed === 'false') {
        toggleSidebar();
      }
    });

    // Bouton nouvelle conversation dans le footer
    document.getElementById('newConversationBtnFooter')?.addEventListener('click', createNewConversation);

    // Charger les conversations depuis l'API
    async function loadConversations() {
      <?php if (!$isGuest): ?>
        try {
          const response = await fetch('api/conversations.php?action=list');
          const data = await response.json();

          if (data.success && data.conversations) {
            conversations = data.conversations;
            renderConversationList();
          }
        } catch (error) {
          console.error('Erreur chargement conversations:', error);
        }
      <?php else: ?>
        // Les visiteurs n'ont pas d'historique persistant
        noConversationsPlaceholder.innerHTML = `
        <div class="w-16 h-16 rounded-full bg-gray-800/50 flex items-center justify-center mb-4">
          <i class="fa-solid fa-lock text-2xl opacity-60"></i>
        </div>
        <p class="text-sm font-medium text-gray-400">Historique non disponible</p>
        <p class="text-xs mt-2 text-gray-500"><a href="zone_membres/register.php" class="text-green-400 hover:text-green-300 hover:underline transition-colors">Inscrivez-vous</a> pour sauvegarder</p>
      `;
      <?php endif; ?>
    }

    // Afficher la liste des conversations
    function renderConversationList(filter = '') {
      const filtered = filter ?
        conversations.filter(c => c.title.toLowerCase().includes(filter.toLowerCase())) :
        conversations;

      if (filtered.length === 0) {
        noConversationsPlaceholder.classList.remove('hidden');
        // Vider la liste sauf le placeholder
        conversationList.querySelectorAll('.conversation-item').forEach(el => el.remove());
        return;
      }

      noConversationsPlaceholder.classList.add('hidden');

      // Construire la liste
      const existingIds = new Set();
      conversationList.querySelectorAll('.conversation-item').forEach(el => {
        if (!filtered.find(c => c.id == el.dataset.id)) {
          el.remove();
        } else {
          existingIds.add(el.dataset.id);
        }
      });

      filtered.forEach(conv => {
        if (existingIds.has(String(conv.id))) {
          // Mettre à jour l'élément existant
          const existingEl = conversationList.querySelector(`[data-id="${conv.id}"]`);
          if (existingEl) {
            existingEl.querySelector('.conv-title').textContent = conv.title || 'Nouvelle conversation';
            existingEl.classList.toggle('active', conv.id == currentConversationId);
          }
          return;
        }

        const convEl = document.createElement('div');
        convEl.className = `conversation-item flex items-center gap-3 p-3 rounded-lg relative transition-all duration-150 group cursor-pointer ${conv.id == currentConversationId ? 'bg-green-800/10 border-l-2 border-green-500' : 'hover:bg-gray-700/20'}`;
        convEl.dataset.id = conv.id;

        const date = new Date(conv.updated_at);
        const dateStr = formatRelativeDate(date);
        const msgCount = conv.message_count || 0;

        convEl.innerHTML = `
          <div class="w-8 h-8 rounded-md bg-gray-800/50 flex items-center justify-center text-xs text-gray-300 shrink-0">
            <i class="fa-regular fa-message text-sm"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="conv-title text-sm font-medium text-gray-200 truncate">${escapeHtml(conv.title || 'Nouvelle conversation')}</div>
            <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
              <span class="text-xs">${dateStr}</span>
              ${msgCount > 0 ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-neutral-800/40 text-green-400">${msgCount}</span>` : ''}
            </div>
          </div>
          <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
            <button class="px-2 py-1 rounded-md text-neutral-300 hover:text-white hover:bg-gray-700/50" title="Renommer" onclick="event.stopPropagation(); renameConversation(${conv.id})">
              <i class="fa-solid fa-pen text-xs"></i>
            </button>
            <button class="px-2 py-1 rounded-md text-neutral-300 hover:text-white hover:bg-gray-700/50" title="Supprimer" onclick="event.stopPropagation(); deleteConversation(${conv.id})">
              <i class="fa-solid fa-trash text-xs text-red-400"></i>
            </button>
          </div>
        `;

        convEl.addEventListener('click', () => selectConversation(conv.id));
        conversationList.insertBefore(convEl, noConversationsPlaceholder);
      });
    }

    // Formater la date relative
    function formatRelativeDate(date) {
      const now = new Date();
      const diff = now - date;
      const minutes = Math.floor(diff / 60000);
      const hours = Math.floor(diff / 3600000);
      const days = Math.floor(diff / 86400000);

      if (minutes < 1) return "Ã€ l'instant";
      if (minutes < 60) return `Il y a ${minutes} min`;
      if (hours < 24) return `Il y a ${hours}h`;
      if (days < 7) return `Il y a ${days}j`;
      return date.toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short'
      });
    }

    // Sélectionner une conversation
    async function selectConversation(id) {
      if (currentConversationId === id) return;

      currentConversationId = id;

      // Mettre à jour la classe active
      conversationList.querySelectorAll('.conversation-item').forEach(el => {
        el.classList.toggle('active', el.dataset.id == id);
      });

      // Activer le mode chat
      const mainContent = document.getElementById('mainContent');
      mainContent.classList.remove('centered');
      mainContent.classList.add('chat-active');
      document.getElementById('welcomeMessage').classList.add('hidden');
      document.getElementById('logoContainer').classList.add('hidden');
      document.getElementById('chatContainer').classList.remove('hidden');

      // Charger les messages
      try {
        const response = await fetch(`api/conversations.php?action=get&id=${id}`);
        const data = await response.json();

        if (data.success && data.messages) {
          conversationMessages = data.messages;
          displayConversationMessages(data.messages);
        }
      } catch (error) {
        console.error('Erreur chargement conversation:', error);
      }
    }

    // Afficher les messages d'une conversation
    function displayConversationMessages(messages) {
      const chatContainer = document.getElementById('chatContainer');

      // Masquer welcome, afficher chat
      document.getElementById('welcomeMessage').classList.add('hidden');
      document.getElementById('logoContainer').classList.add('hidden');
      chatContainer.classList.remove('hidden');
      chatContainer.innerHTML = '';

      messages.forEach(msg => {
        if (msg.role === 'user') {
          chatContainer.innerHTML += `
            <div class="flex justify-end">
              <div class="bg-green-600/20 border border-green-500/30 rounded-2xl rounded-br-md px-4 py-3 max-w-[80%]">
                <p class="text-gray-200 text-sm">${escapeHtml(msg.content)}</p>
              </div>
            </div>
          `;
        } else {
          chatContainer.innerHTML += `
            <div class="flex justify-start">
              <div class="ai-message bg-gray-700/50 border border-gray-600/50 rounded-2xl rounded-bl-md px-4 py-3 max-w-[85%] done">
                ${renderMarkdown(msg.content)}
              </div>
            </div>
          `;
        }
      });

      // Apply syntax highlighting to all code blocks in loaded messages
      chatContainer.querySelectorAll('pre code').forEach((block) => {
        if (!block.dataset.highlighted) {
          hljs.highlightElement(block);
          block.dataset.highlighted = 'true';
        }
      });

      // Scroll to bottom and enable auto-scroll for loaded conversation
      shouldAutoScroll = true;
      chatContainer.scrollTop = chatContainer.scrollHeight;
      // updateScrollButton will be called automatically by scroll event
    }

    // Nouvelle conversation
    async function createNewConversation() {
      currentConversationId = null;
      conversationMessages = [];

      // Réinitialiser l'UI
      document.getElementById('chatContainer').classList.add('hidden');
      document.getElementById('chatContainer').innerHTML = '';
      document.getElementById('welcomeMessage').classList.remove('hidden');
      document.getElementById('logoContainer').classList.remove('hidden');

      // Désélectionner dans la sidebar
      conversationList.querySelectorAll('.conversation-item').forEach(el => {
        el.classList.remove('active');
      });
    }

    newConversationBtn.addEventListener('click', createNewConversation);

    // Renommer une conversation
    async function renameConversation(id) {
      const conv = conversations.find(c => c.id == id);
      const newTitle = await showPromptModal('Nouveau titre:', conv?.title || '');

      if (newTitle === null) return;

      try {
        const response = await fetch('api/conversations.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            action: 'update_title',
            conversation_id: id,
            title: newTitle
          })
        });

        const data = await response.json();
        if (data.success) {
          // Mettre à jour localement
          const convIndex = conversations.findIndex(c => c.id == id);
          if (convIndex !== -1) {
            conversations[convIndex].title = newTitle;
            renderConversationList(conversationSearch.value);
          }
        }
      } catch (error) {
        console.error('Erreur renommage:', error);
      }
    }

    // Supprimer une conversation
    async function deleteConversation(id) {
      const confirmed = await showConfirmModal('Supprimer cette conversation ?');
      if (!confirmed) return;

      try {
        const response = await fetch('api/conversations.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            action: 'delete',
            conversation_id: id
          })
        });

        const data = await response.json();
        if (data.success) {
          // Supprimer localement
          conversations = conversations.filter(c => c.id != id);
          renderConversationList(conversationSearch.value);

          // Si c'était la conversation active, réinitialiser
          if (currentConversationId == id) {
            createNewConversation();
          }
        }
      } catch (error) {
        console.error('Erreur suppression:', error);
      }
    }

    // Générer un titre automatique pour une conversation
    async function generateConversationTitle(conversationId, userMessage, aiResponse) {
      <?php if (!$isGuest): ?>
        try {
          const response = await fetch('api/conversations.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              action: 'generate_title',
              conversation_id: conversationId,
              user_message: userMessage,
              ai_response: aiResponse.substring(0, 500) // Limiter la taille
            })
          });

          const data = await response.json();
          if (data.success && data.title) {
            // Mettre à jour localement
            const convIndex = conversations.findIndex(c => c.id == conversationId);
            if (convIndex !== -1) {
              conversations[convIndex].title = data.title;
              renderConversationList(conversationSearch?.value || '');
            }
          }
        } catch (error) {
          console.error('Erreur génération titre:', error);
        }
      <?php endif; ?>
    }

    // Recherche
    const conversationSearchClear = document.getElementById('conversationSearchClear');

    conversationSearch.addEventListener('input', (e) => {
      const value = e.target.value;
      renderConversationList(value);
      // Afficher/masquer le bouton clear
      if (conversationSearchClear) {
        conversationSearchClear.classList.toggle('hidden', !value);
      }
    });

    // Bouton clear de la recherche conversations
    if (conversationSearchClear) {
      conversationSearchClear.addEventListener('click', (e) => {
        e.stopPropagation();
        conversationSearch.value = '';
        conversationSearch.focus();
        conversationSearchClear.classList.add('hidden');
        renderConversationList('');
      });
    }

    // Export des conversations
    exportConversationsBtn.addEventListener('click', async () => {
      <?php if (!$isGuest): ?>
        try {
          const exportData = {
            exported_at: new Date().toISOString(),
            conversations: []
          };

          // Charger tous les messages de chaque conversation
          for (const conv of conversations) {
            const response = await fetch(`api/conversations.php?action=get&id=${conv.id}`);
            const data = await response.json();

            if (data.success) {
              exportData.conversations.push({
                title: conv.title,
                created_at: conv.created_at,
                updated_at: conv.updated_at,
                messages: data.messages
              });
            }
          }

          // Télécharger le JSON
          const blob = new Blob([JSON.stringify(exportData, null, 2)], {
            type: 'application/json'
          });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `nxtgenai-conversations-${new Date().toISOString().slice(0,10)}.json`;
          a.click();
          URL.revokeObjectURL(url);
        } catch (error) {
          console.error('Erreur export:', error);
          alert('Erreur lors de l\'export');
        }
      <?php else: ?>
        alert('Fonctionnalité réservée aux utilisateurs inscrits');
      <?php endif; ?>
    });

    // Charger les conversations au démarrage
    document.addEventListener('DOMContentLoaded', loadConversations);

    // ===== PLACEHOLDER RESPONSIVE =====
    function updatePlaceholder() {
      const input = document.getElementById('messageInput');
      if (!input) return;

      if (window.innerWidth <= 480) {
        input.placeholder = "Posez une question...";
      } else if (window.innerWidth <= 640) {
        input.placeholder = "Posez une question. Tapez @ ou /";
      } else {
        input.placeholder = "Posez une question. Tapez @ pour mentions et / pour raccourcis.";
      }
    }

    // Appliquer au chargement et au resize (avec debounce)
    updatePlaceholder();
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(updatePlaceholder, 150);
    });

    // ===== AUTOFOCUS INPUT AU CHARGEMENT =====
    // Focus automatique sur la zone de texte (seulement desktop)
    if (window.innerWidth > 640) {
      const mainInput = document.getElementById('messageInput');
      if (mainInput) {
        mainInput.focus();
      }
    }

    // ===== AUTO-SCROLL AND SCROLL-TO-BOTTOM BUTTON =====
    const scrollToBottomBtn = document.getElementById('scrollToBottomBtn');

    // Function to check if user is near bottom of chat
    function isNearBottom(container, threshold = 100) {
      if (!container) return true;
      return container.scrollHeight - container.scrollTop - container.clientHeight < threshold;
    }

    // Function to scroll to bottom smoothly
    function scrollToBottom(smooth = true) {
      if (!chatContainer) return;
      if (smooth) {
        chatContainer.scrollTo({
          top: chatContainer.scrollHeight,
          behavior: 'smooth'
        });
      } else {
        chatContainer.scrollTop = chatContainer.scrollHeight;
      }
    }

    // Show/hide scroll-to-bottom button based on scroll position
    function updateScrollButton() {
      if (!chatContainer || !scrollToBottomBtn) return;

      const isAtBottom = isNearBottom(chatContainer, 150);
      if (isAtBottom) {
        scrollToBottomBtn.style.display = 'none';
        scrollToBottomBtn.classList.add('opacity-0', 'invisible');
        scrollToBottomBtn.classList.remove('opacity-100', 'visible');
      } else {
        scrollToBottomBtn.style.display = 'block';
        scrollToBottomBtn.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
        scrollToBottomBtn.classList.add('opacity-100', 'visible');
      }
    }

    // Handle scroll events
    if (chatContainer) {
      let scrollTimeout;
      chatContainer.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        isUserScrolling = true;

        // Update button visibility
        updateScrollButton();

        // Check if user scrolled near bottom
        shouldAutoScroll = isNearBottom(chatContainer, 100);

        scrollTimeout = setTimeout(() => {
          isUserScrolling = false;
        }, 150);
      });
    }

    // Scroll to bottom button click handler
    if (scrollToBottomBtn) {
      scrollToBottomBtn.addEventListener('click', function() {
        shouldAutoScroll = true;
        scrollToBottom(true);
      });
    }

    // Override the auto-scroll in sendMessage to respect user preference
    // Store the original scrollTop setter
    const originalScrollTopSetter = Object.getOwnPropertyDescriptor(Element.prototype, 'scrollTop').set;

    // ===== DARK MODE & LANGUAGE TOGGLE =====
    // Theme and language toggle are handled by assets/js/theme-lang.js
    // Event listeners are bound via .theme-toggle-btn and .lang-toggle-btn classes

    // ===== MOBILE BOTTOM SHEETS - GESTION =====

    // Ã‰tat des bottom sheets
    let mobileModelSheetOpen = false;
    let mobileProfileSheetOpen = false;
    let mobileAuthSheetOpen = false;

    // Cache des modèles pour le bottom sheet mobile
    let mobileModelsCache = null;

    // ===== BOTTOM SHEET MODÃˆLES =====
    function openMobileModelSheet() {
      const sheet = document.getElementById('mobileModelSheet');
      if (!sheet) return;

      mobileModelSheetOpen = true;
      // Retirer les classes Tailwind conflictuelles et ajouter active
      sheet.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
      sheet.classList.add('active', 'opacity-100', 'visible', 'pointer-events-auto');

      // Animer le contenu vers le haut
      const content = sheet.querySelector('.mobile-bottom-sheet-content');
      if (content) {
        content.classList.remove('translate-y-full');
        content.classList.add('translate-y-0');
      }

      document.body.style.overflow = 'hidden';

      // Charger les modèles si pas encore fait
      if (!mobileModelsCache) {
        loadMobileModels();
      }

      // Focus sur la recherche
      setTimeout(() => {
        const searchInput = document.getElementById('mobileModelSearch');
        if (searchInput) searchInput.focus();
      }, 300);
    }

    function closeMobileModelSheet() {
      const sheet = document.getElementById('mobileModelSheet');
      if (!sheet) return;

      mobileModelSheetOpen = false;

      // Animer le contenu vers le bas
      const content = sheet.querySelector('.mobile-bottom-sheet-content');
      if (content) {
        content.classList.remove('translate-y-0');
        content.classList.add('translate-y-full');
      }

      // Remettre les classes Tailwind et retirer active
      sheet.classList.remove('active', 'opacity-100', 'visible', 'pointer-events-auto');
      sheet.classList.add('opacity-0', 'invisible', 'pointer-events-none');
      document.body.style.overflow = '';

      // Vider la recherche
      const searchInput = document.getElementById('mobileModelSearch');
      if (searchInput) searchInput.value = '';

      // Réafficher tous les modèles
      filterMobileModels('');
    }

    // Charger les modèles dans le bottom sheet mobile
    async function loadMobileModels() {
      const listContainer = document.getElementById('mobileModelList');
      if (!listContainer) return;

      // Attendre que models.js charge les modèles
      // Le système models.js utilise modelManager.models
      const checkModels = () => {
        if (typeof modelManager !== 'undefined' && modelManager.models && modelManager.models.length > 0) {
          mobileModelsCache = modelManager.models;
          renderMobileModelList(mobileModelsCache);
        } else {
          setTimeout(checkModels, 100);
        }
      };
      checkModels();
    }

    // Afficher la liste des modèles mobile
    function renderMobileModelList(models, filter = '') {
      const listContainer = document.getElementById('mobileModelList');
      if (!listContainer || !models) return;

      // Filtrer si nécessaire
      const filtered = filter ?
        models.filter(m =>
          m.display.toLowerCase().includes(filter.toLowerCase()) ||
          m.provider.toLowerCase().includes(filter.toLowerCase()) ||
          m.model.toLowerCase().includes(filter.toLowerCase())
        ) :
        models;

      // Grouper par provider
      const grouped = {};
      filtered.forEach(model => {
        if (!grouped[model.provider]) {
          grouped[model.provider] = [];
        }
        grouped[model.provider].push(model);
      });

      // Générer le HTML
      let html = '';

      if (Object.keys(grouped).length === 0) {
        html = `
          <div class="flex flex-col items-center justify-center py-12 text-gray-400">
            <i class="fa-solid fa-search text-3xl mb-3 opacity-50"></i>
            <p class="text-sm">Aucun modèle trouvé</p>
          </div>
        `;
      } else {
        // Ordre de priorité des providers
        const providerOrder = ['openai', 'anthropic', 'gemini', 'deepseek', 'mistral', 'xai', 'perplexity', 'openrouter', 'huggingface', 'moonshot', 'github', 'ollama'];
        const sortedProviders = Object.keys(grouped).sort((a, b) => {
          const indexA = providerOrder.indexOf(a);
          const indexB = providerOrder.indexOf(b);
          if (indexA === -1 && indexB === -1) return a.localeCompare(b);
          if (indexA === -1) return 1;
          if (indexB === -1) return -1;
          return indexA - indexB;
        });

        sortedProviders.forEach(provider => {
          const providerModels = grouped[provider];
          const providerInfo = typeof modelManager !== 'undefined' ? modelManager.providers[provider] : null;
          const providerName = providerInfo ? providerInfo.name : provider.charAt(0).toUpperCase() + provider.slice(1);
          const providerIcon = providerInfo ? providerInfo.icon : `assets/images/providers/${provider}.svg`;

          html += `
            <div class="mobile-provider-section">
              <div class="mobile-provider-header">
                <img src="${providerIcon}" alt="${providerName}" onerror="this.style.display='none'">
                <span>${providerName}</span>
              </div>
          `;

          providerModels.forEach(model => {
            const isSelected = selectedModel.model === model.model && selectedModel.provider === model.provider;
            html += `
              <div class="mobile-model-item ${isSelected ? 'selected' : ''}" 
                   data-model="${escapeHtml(model.model)}" 
                   data-provider="${escapeHtml(model.provider)}"
                   data-display="${escapeHtml(model.display)}"
                   role="option"
                   aria-selected="${isSelected}"
                   onclick="selectMobileModel(this)">
                <img src="${providerIcon}" alt="${providerName}" aria-hidden="true">
                <div class="model-info">
                  <div class="model-name">${escapeHtml(model.display)}</div>
                  <div class="model-provider">${escapeHtml(model.model)}</div>
                </div>
                ${isSelected ? '<i class="fa-solid fa-check model-check"></i>' : ''}
              </div>
            `;
          });

          html += '</div>';
        });
      }

      listContainer.innerHTML = html;
    }

    // Filtrer les modèles mobile
    function filterMobileModels(query) {
      if (mobileModelsCache) {
        renderMobileModelList(mobileModelsCache, query);
      }
    }

    // Sélectionner un modèle depuis le bottom sheet mobile
    function selectMobileModel(element) {
      const model = element.dataset.model;
      const provider = element.dataset.provider;
      const display = element.dataset.display;

      // Mettre à jour la sélection globale
      selectedModel = {
        model,
        provider,
        display
      };

      // Mettre à jour l'affichage desktop et mobile
      const providerInfo = typeof modelManager !== 'undefined' ? modelManager.providers[provider] : null;
      const icon = providerInfo ? providerInfo.icon : `assets/images/providers/${provider}.svg`;

      // Desktop
      if (modelIcon) modelIcon.src = icon;
      if (modelName) modelName.textContent = display;

      // Mobile
      if (mobileModelIcon) mobileModelIcon.src = icon;
      if (mobileModelName) mobileModelName.textContent = display;

      // Mettre à jour la sélection visuelle
      document.querySelectorAll('.mobile-model-item').forEach(item => {
        const isThis = item.dataset.model === model && item.dataset.provider === provider;
        item.classList.toggle('selected', isThis);
        item.setAttribute('aria-selected', isThis);

        // Ajouter/retirer le check
        const existingCheck = item.querySelector('.model-check');
        if (isThis && !existingCheck) {
          item.insertAdjacentHTML('beforeend', '<i class="fa-solid fa-check model-check"></i>');
        } else if (!isThis && existingCheck) {
          existingCheck.remove();
        }
      });

      // Fermer le sheet après un court délai
      setTimeout(() => closeMobileModelSheet(), 150);
    }

    // Recherche dans le bottom sheet modèles
    const mobileModelSearch = document.getElementById('mobileModelSearch');
    const mobileModelSearchClear = document.getElementById('mobileModelSearchClear');

    if (mobileModelSearch) {
      mobileModelSearch.addEventListener('input', (e) => {
        const value = e.target.value;
        filterMobileModels(value);
        // Afficher/masquer le bouton clear
        if (mobileModelSearchClear) {
          mobileModelSearchClear.classList.toggle('hidden', !value);
        }
      });
    }

    // Bouton clear de la recherche mobile
    if (mobileModelSearchClear) {
      mobileModelSearchClear.addEventListener('click', (e) => {
        e.stopPropagation();
        if (mobileModelSearch) {
          mobileModelSearch.value = '';
          mobileModelSearch.focus();
        }
        mobileModelSearchClear.classList.add('hidden');
        filterMobileModels('');
      });
    }

    // ===== BOTTOM SHEET PROFIL =====
    function openMobileProfileSheet() {
      const sheet = document.getElementById('mobileProfileSheet');
      if (!sheet) return;

      mobileProfileSheetOpen = true;
      sheet.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
      sheet.classList.add('active', 'opacity-100', 'visible', 'pointer-events-auto');

      const content = sheet.querySelector('.mobile-bottom-sheet-content');
      if (content) {
        content.classList.remove('translate-y-full');
        content.classList.add('translate-y-0');
      }

      document.body.style.overflow = 'hidden';
    }

    function closeMobileProfileSheet() {
      const sheet = document.getElementById('mobileProfileSheet');
      if (!sheet) return;

      mobileProfileSheetOpen = false;

      const content = sheet.querySelector('.mobile-bottom-sheet-content');
      if (content) {
        content.classList.remove('translate-y-0');
        content.classList.add('translate-y-full');
      }

      sheet.classList.remove('active', 'opacity-100', 'visible', 'pointer-events-auto');
      sheet.classList.add('opacity-0', 'invisible', 'pointer-events-none');
      document.body.style.overflow = '';
    }

    // ===== BOTTOM SHEET AUTH =====
    function openMobileAuthSheet() {
      const sheet = document.getElementById('mobileAuthSheet');
      if (!sheet) return;

      mobileAuthSheetOpen = true;
      sheet.classList.remove('opacity-0', 'invisible', 'pointer-events-none');
      sheet.classList.add('active', 'opacity-100', 'visible', 'pointer-events-auto');

      const content = sheet.querySelector('.mobile-bottom-sheet-content');
      if (content) {
        content.classList.remove('translate-y-full');
        content.classList.add('translate-y-0');
      }

      document.body.style.overflow = 'hidden';
    }

    function closeMobileAuthSheet() {
      const sheet = document.getElementById('mobileAuthSheet');
      if (!sheet) return;

      mobileAuthSheetOpen = false;

      const content = sheet.querySelector('.mobile-bottom-sheet-content');
      if (content) {
        content.classList.remove('translate-y-0');
        content.classList.add('translate-y-full');
      }

      sheet.classList.remove('active', 'opacity-100', 'visible', 'pointer-events-auto');
      sheet.classList.add('opacity-0', 'invisible', 'pointer-events-none');
      document.body.style.overflow = '';
    }

    // ===== GESTION DES Ã‰VÃ‰NEMENTS MOBILE =====

    // Modifier le comportement du sélecteur modèle mobile
    if (mobileModelSelector) {
      mobileModelSelector.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (window.innerWidth <= 640) {
          openMobileModelSheet();
        } else {
          // Desktop: ouvrir le menu classique
          if (modelSelectorBtn) modelSelectorBtn.click();
        }
      });
    }

    // Modifier le comportement du bouton profil sur mobile/tablette
    <?php if ($user): ?>
      const profileButtonEl = document.getElementById('profileButton');
      if (profileButtonEl) {
        profileButtonEl.addEventListener('click', function(e) {
          if (window.innerWidth <= 800) {
            e.preventDefault();
            e.stopPropagation();
            openMobileProfileSheet();
          }
        }, true);
      }
    <?php endif; ?>

    // Fermer les sheets avec Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        if (mobileModelSheetOpen) closeMobileModelSheet();
        if (mobileProfileSheetOpen) closeMobileProfileSheet();
        if (mobileAuthSheetOpen) closeMobileAuthSheet();
      }
    });

    // Gestes tactiles pour fermer les bottom sheets (swipe down)
    function setupSwipeToClose(sheetId, closeFunction) {
      const sheet = document.getElementById(sheetId);
      if (!sheet) return;

      const content = sheet.querySelector('.mobile-bottom-sheet-content');
      if (!content) return;

      let startY = 0;
      let currentY = 0;
      let isDragging = false;

      content.addEventListener('touchstart', function(e) {
        // Ne pas démarrer si on scroll dans la liste
        const list = content.querySelector('.mobile-sheet-list');
        if (list && list.scrollTop > 0) return;

        startY = e.touches[0].clientY;
        isDragging = true;
      }, {
        passive: true
      });

      content.addEventListener('touchmove', function(e) {
        if (!isDragging) return;

        currentY = e.touches[0].clientY;
        const diff = currentY - startY;

        // Seulement vers le bas
        if (diff > 0) {
          content.style.transform = `translateY(${Math.min(diff, 200)}px)`;
        }
      }, {
        passive: true
      });

      content.addEventListener('touchend', function(e) {
        if (!isDragging) return;
        isDragging = false;

        const diff = currentY - startY;

        if (diff > 100) {
          // Fermer
          closeFunction();
        }

        // Reset
        content.style.transform = '';
        startY = 0;
        currentY = 0;
      }, {
        passive: true
      });
    }

    // Activer swipe to close sur tous les bottom sheets
    setupSwipeToClose('mobileModelSheet', closeMobileModelSheet);
    setupSwipeToClose('mobileProfileSheet', closeMobileProfileSheet);
    setupSwipeToClose('mobileAuthSheet', closeMobileAuthSheet);

    // Synchroniser l'affichage du modèle quand models.js charge
    document.addEventListener('modelsLoaded', function(e) {
      syncModelDisplay();
      // Recharger le cache mobile
      if (typeof modelManager !== 'undefined' && modelManager.models) {
        mobileModelsCache = modelManager.models;
      }
      // Pré-rendre la liste mobile si le sheet existe
      const mobileList = document.getElementById('mobileModelList');
      if (mobileList && mobileModelsCache && mobileModelsCache.length > 0) {
        renderMobileModelList(mobileModelsCache);
      }
    });

    // ===== GDPR COOKIE CONSENT MANAGEMENT =====
    const CookieConsent = {
      cookieName: 'nxtgenai_consent',
      cookieExpireDays: 365,

      // Vérifier si le consentement existe
      hasConsent: function() {
        return this.getCookie(this.cookieName) !== null;
      },

      // Lire un cookie
      getCookie: function(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
          try {
            return JSON.parse(decodeURIComponent(parts.pop().split(';').shift()));
          } catch (e) {
            return null;
          }
        }
        return null;
      },

      // Sauvegarder le consentement
      saveConsent: function(preferences) {
        const consent = {
          necessary: true, // Toujours true
          analytics: preferences.analytics || false,
          marketing: preferences.marketing || false,
          timestamp: new Date().toISOString()
        };

        const expires = new Date();
        expires.setDate(expires.getDate() + this.cookieExpireDays);

        // Déterminer si on est en HTTPS pour le flag Secure
        const isSecure = window.location.protocol === 'https:';
        const secureFlag = isSecure ? '; Secure' : '';

        document.cookie = `${this.cookieName}=${encodeURIComponent(JSON.stringify(consent))}; expires=${expires.toUTCString()}; path=/${secureFlag}; SameSite=Lax`;

        return consent;
      },

      // Accepter tous les cookies
      acceptAll: function() {
        const consent = this.saveConsent({
          analytics: true,
          marketing: true
        });
        this.hideBanner();
        this.onConsentChange(consent);
      },

      // Refuser les cookies non essentiels
      rejectAll: function() {
        const consent = this.saveConsent({
          analytics: false,
          marketing: false
        });
        this.hideBanner();
        this.onConsentChange(consent);
      },

      // Sauvegarder les préférences personnalisées
      savePreferences: function() {
        const analytics = document.getElementById('cookieAnalytics')?.checked || false;
        const marketing = document.getElementById('cookieMarketing')?.checked || false;
        const consent = this.saveConsent({
          analytics,
          marketing
        });
        this.closeSettings();
        this.hideBanner();
        this.onConsentChange(consent);
      },

      // Afficher le bandeau
      showBanner: function() {
        const banner = document.getElementById('cookieConsentBanner');
        if (banner) {
          // Petit délai pour l'animation d'entrée
          setTimeout(() => {
            banner.classList.add('visible');
          }, 300);
        }
      },

      // Masquer le bandeau
      hideBanner: function() {
        const banner = document.getElementById('cookieConsentBanner');
        if (banner) {
          banner.classList.remove('visible');
          banner.classList.add('hiding');
          setTimeout(() => {
            banner.classList.remove('hiding');
            banner.style.display = 'none';
          }, 400);
        }
      },

      // Ouvrir la modal des paramètres
      openSettings: function() {
        const modal = document.getElementById('cookieSettingsModal');
        if (modal) {
          modal.classList.add('visible');
          document.body.style.overflow = 'hidden';

          // Pré-remplir avec les préférences actuelles
          const current = this.getCookie(this.cookieName);
          if (current) {
            const analyticsCheckbox = document.getElementById('cookieAnalytics');
            const marketingCheckbox = document.getElementById('cookieMarketing');
            if (analyticsCheckbox) analyticsCheckbox.checked = current.analytics || false;
            if (marketingCheckbox) marketingCheckbox.checked = current.marketing || false;
          }
        }
      },

      // Fermer la modal des paramètres
      closeSettings: function() {
        const modal = document.getElementById('cookieSettingsModal');
        if (modal) {
          modal.classList.remove('visible');
          document.body.style.overflow = '';
        }
      },

      // Callback quand le consentement change
      onConsentChange: function(consent) {
        console.log('[GDPR] Consentement mis à jour:', consent);
        // Ici vous pouvez charger/bloquer les scripts selon le consentement
        // Exemple: if (consent.analytics) { loadGoogleAnalytics(); }
      },

      // Initialisation
      init: function() {
        if (!this.hasConsent()) {
          this.showBanner();
        }

        // Gestionnaires d'événements
        document.getElementById('cookieAcceptAll')?.addEventListener('click', () => this.acceptAll());
        document.getElementById('cookieRejectAll')?.addEventListener('click', () => this.rejectAll());
        document.getElementById('cookieOpenSettings')?.addEventListener('click', () => this.openSettings());
        document.getElementById('cookieSettingsClose')?.addEventListener('click', () => this.closeSettings());
        document.getElementById('cookieSavePreferences')?.addEventListener('click', () => this.savePreferences());

        // Fermer modal au clic sur le backdrop
        document.getElementById('cookieSettingsModal')?.addEventListener('click', (e) => {
          if (e.target.id === 'cookieSettingsModal') {
            this.closeSettings();
          }
        });

        // Fermer modal avec Escape
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            const modal = document.getElementById('cookieSettingsModal');
            if (modal?.classList.contains('visible')) {
              this.closeSettings();
            }
          }
        });
      }
    };

    // Initialiser le gestionnaire de cookies au chargement
    document.addEventListener('DOMContentLoaded', function() {
      CookieConsent.init();
    });
  </script>

  <!-- GDPR Cookie Consent Banner -->
  <?php if (!$cookieConsentGiven): ?>
    <div id="cookieConsentBanner" role="dialog" aria-labelledby="cookieConsentTitle" aria-describedby="cookieConsentDesc">
      <div class="cookie-banner-content">
        <div class="cookie-banner-text">
          <h3 id="cookieConsentTitle"><i class="fa-solid fa-cookie-bite"></i> Nous utilisons des cookies</h3>
          <p id="cookieConsentDesc">
            Ce site utilise des cookies pour améliorer votre expérience, analyser le trafic et personnaliser le contenu.
            En cliquant sur "Accepter tout", vous consentez à l'utilisation de tous les cookies.
            <a href="#" onclick="return false;">Politique de confidentialité</a>
          </p>
        </div>
        <div class="cookie-banner-actions">
          <button type="button" id="cookieRejectAll" class="px-3 py-2 rounded-lg text-neutral-300 bg-neutral-700/30 hover:bg-neutral-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
            Refuser
          </button>
          <button type="button" id="cookieOpenSettings" class="px-3 py-2 rounded-lg text-neutral-300 border border-neutral-700/30 hover:bg-neutral-700/30 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
            <i class="fa-solid fa-sliders"></i> Paramètres
          </button>
          <button type="button" id="cookieAcceptAll" class="px-3 py-2 rounded-lg text-white bg-linear-to-tr from-green-500 to-green-600 hover:from-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
            <i class="fa-solid fa-check"></i> Accepter tout
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Paramètres Cookies -->
    <div id="cookieSettingsModal" role="dialog" aria-labelledby="cookieSettingsTitle" aria-modal="true">
      <div class="cookie-settings-content">
        <div class="cookie-settings-header">
          <h3 id="cookieSettingsTitle">Paramètres des cookies</h3>
          <button type="button" id="cookieSettingsClose" class="cookie-settings-close" aria-label="Fermer">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="cookie-settings-body">
          <!-- Cookies nécessaires (toujours actifs) -->
          <div class="cookie-category">
            <div class="cookie-category-header">
              <span class="cookie-category-title">Cookies essentiels</span>
              <label class="inline-flex items-center cursor-default">
                <input type="checkbox" checked disabled class="sr-only peer">
                <div class="w-11 h-6 rounded-full bg-neutral-700/30 peer-disabled:opacity-60 relative">
                  <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full"></span>
                </div>
              </label>
            </div>
            <p class="cookie-category-desc">
              Ces cookies sont indispensables au fonctionnement du site. Ils permettent la navigation, la connexion et l'accès aux fonctionnalités de base.
            </p>
          </div>

          <!-- Cookies analytiques -->
          <div class="cookie-category">
            <div class="cookie-category-header">
              <span class="cookie-category-title">Cookies analytiques</span>
              <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" id="cookieAnalytics" class="sr-only peer">
                <div class="w-11 h-6 rounded-full bg-neutral-700/30 peer-checked:bg-green-500 relative">
                  <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></span>
                </div>
              </label>
            </div>
            <p class="cookie-category-desc">
              Ces cookies nous aident à comprendre comment les visiteurs interagissent avec le site en collectant des informations anonymes.
            </p>
          </div>

          <!-- Cookies marketing -->
          <div class="cookie-category">
            <div class="cookie-category-header">
              <span class="cookie-category-title">Cookies marketing</span>
              <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" id="cookieMarketing" class="sr-only peer">
                <div class="w-11 h-6 rounded-full bg-neutral-700/30 peer-checked:bg-green-500 relative">
                  <span class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></span>
                </div>
              </label>
            </div>
            <p class="cookie-category-desc">
              Ces cookies sont utilisés pour afficher des publicités pertinentes et mesurer l'efficacité des campagnes publicitaires.
            </p>
          </div>
        </div>
        <div class="cookie-settings-footer">
          <button type="button" id="cookieSavePreferences" class="cookie-btn cookie-btn-accept">
            <i class="fa-solid fa-check"></i> Enregistrer mes préférences
          </button>
        </div>
      </div>
    </div>
  <?php endif; ?>
</body>

</html>