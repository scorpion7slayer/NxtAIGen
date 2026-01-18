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
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer" />
  <!-- Highlight.js pour la coloration syntaxique -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>
  <!-- Marked.js pour le parsing Markdown -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/15.0.4/marked.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <!-- Variables globales pour les scripts externes -->
  <script>
    window.isGuest = <?php echo $isGuest ? 'true' : 'false'; ?>;
    window.guestUsageLimit = <?php echo GUEST_USAGE_LIMIT; ?>;
  </script>
  <script src="assets/js/models.js" defer></script>
  <script src="assets/js/rate_limit_widget.js" defer></script>
  <title>NxtGenAI</title>
  <style>
    /* Fix pour le scroll - empêcher le body de s'étendre */
    html,
    body {
      height: 100%;
      overflow: hidden;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('assets/fonts/TikTok_Sans/static/TikTokSans-Regular.ttf') format('truetype');
      font-weight: 400;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('assets/fonts/TikTok_Sans/static/TikTokSans-Medium.ttf') format('truetype');
      font-weight: 500;
    }

    @font-face {
      font-family: 'TikTok Sans';
      src: url('assets/fonts/TikTok_Sans/static/TikTokSans-Bold.ttf') format('truetype');
      font-weight: 700;
    }

    /* Police Noto Color Emoji pour afficher tous les emojis (drapeaux, etc.) */
    @font-face {
      font-family: 'Noto Color Emoji';
      src: url('assets/fonts/Noto_Color_Emoji/NotoColorEmoji-Regular.ttf') format('truetype');
      font-display: swap;
    }

    * {
      font-family: 'TikTok Sans', 'Noto Color Emoji', system-ui, sans-serif;
    }

    body {
      background-color: oklch(21% 0.006 285.885);
    }

    ::selection {
      background: #404040;
    }

    /* Styles pour le rendu Markdown */
    .ai-message h1,
    .ai-message h2,
    .ai-message h3 {
      font-weight: 600;
      margin-top: 1rem;
      margin-bottom: 0.5rem;
    }

    .ai-message h1 {
      font-size: 1.5rem;
    }

    .ai-message h2 {
      font-size: 1.25rem;
    }

    .ai-message h3 {
      font-size: 1.1rem;
    }

    .ai-message p {
      margin-bottom: 0.75rem;
    }

    .ai-message ul,
    .ai-message ol {
      margin-left: 1.5rem;
      margin-bottom: 0.75rem;
    }

    .ai-message li {
      margin-bottom: 0.25rem;
    }

    .ai-message a {
      color: #60a5fa;
      text-decoration: underline;
    }

    .ai-message strong {
      font-weight: 600;
    }

    /* Modales personnalisées */
    .modal-backdrop {
      backdrop-filter: blur(4px);
      animation: fadeIn 0.2s ease-out;
    }

    .modal-content {
      animation: slideUp 0.3s ease-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    /* Input focus ring custom */
    .modal-input:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgb(59 130 246);
      border-color: rgb(59 130 246);
    }

    .ai-message em {
      font-style: italic;
    }

    /* Curseur de streaming */
    .streaming-cursor {
      display: inline-block;
      width: 0.6em;
      height: 1.2em;
      background-color: #10b981;
      animation: blink 1s step-end infinite;
      margin-left: 0.15rem;
      vertical-align: text-bottom;
      pointer-events: none;
    }

    .streaming-response.done .streaming-cursor {
      display: none;
    }

    @keyframes blink {
      50% {
        opacity: 0;
      }
    }

    /* Styles pour les blocs de code */
    .ai-message .code-block-wrapper {
      position: relative;
      background-color: #0d1117;
      border-radius: 0.5rem;
      margin: 1rem 0;
      overflow: hidden;
    }

    .ai-message .code-block-wrapper pre {
      margin: 0;
      background: transparent;
    }

    .ai-message pre {
      position: relative;
      background-color: #0d1117;
      border-radius: 0.5rem;
      margin: 1rem 0;
      overflow: hidden;
    }

    .ai-message pre code {
      display: block;
      padding: 1rem;
      overflow-x: auto;
      font-family: 'Fira Code', 'JetBrains Mono', 'Consolas', monospace;
      font-size: 0.875rem;
      line-height: 1.5;
    }

    .ai-message code:not(pre code) {
      background-color: rgba(110, 118, 129, 0.4);
      padding: 0.2rem 0.4rem;
      border-radius: 0.25rem;
      font-family: 'Fira Code', 'JetBrains Mono', 'Consolas', monospace;
      font-size: 0.875em;
    }

    /* Header du bloc de code avec langage et bouton copier */
    .code-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background-color: #161b22;
      padding: 0.5rem 1rem;
      border-bottom: 1px solid #30363d;
      font-size: 0.75rem;
      color: #8b949e;
    }

    .copy-btn {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      background-color: transparent;
      color: #8b949e;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
    }

    .copy-btn:hover {
      background-color: #30363d;
      color: #c9d1d9;
    }

    .copy-btn.copied {
      color: #3fb950;
    }

    /* Animation de chargement - 3 points */
    .typing-indicator {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .typing-indicator .dot {
      width: 8px;
      height: 8px;
      background-color: #10b981;
      border-radius: 50%;
      animation: typing 1.4s infinite ease-in-out both;
    }

    .typing-indicator .dot:nth-child(1) {
      animation-delay: 0s;
    }

    .typing-indicator .dot:nth-child(2) {
      animation-delay: 0.2s;
    }

    .typing-indicator .dot:nth-child(3) {
      animation-delay: 0.4s;
    }

    @keyframes typing {

      0%,
      80%,
      100% {
        transform: scale(0.6);
        opacity: 0.4;
      }

      40% {
        transform: scale(1);
        opacity: 1;
      }
    }

    /* Texte "..." animé */
    .thinking-text {
      color: #9ca3af;
      font-size: 0.875rem;
      margin-left: 8px;
    }

    .thinking-text::after {
      content: '';
      animation: ellipsis 1.5s infinite;
    }

    @keyframes ellipsis {
      0% {
        content: '';
      }

      25% {
        content: '.';
      }

      50% {
        content: '..';
      }

      75% {
        content: '...';
      }

      100% {
        content: '';
      }
    }

    /* Scrollbar personnalisée */
    * {
      scrollbar-width: thin;
      scrollbar-color: #4b5563 #1f2937;
    }

    *::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    *::-webkit-scrollbar-track {
      background: #1f2937;
      border-radius: 4px;
    }

    *::-webkit-scrollbar-thumb {
      background: #4b5563;
      border-radius: 4px;
      transition: background 0.3s ease;
    }

    *::-webkit-scrollbar-thumb:hover {
      background: #6b7280;
    }

    *::-webkit-scrollbar-thumb:active {
      background: #10b981;
    }

    /* Scrollbar pour les blocs de code */
    pre::-webkit-scrollbar-thumb {
      background: #374151;
    }

    pre::-webkit-scrollbar-thumb:hover {
      background: #4b5563;
    }

    /* ===== SIDEBAR HISTORIQUE ===== */
    .conversation-sidebar {
      width: 280px;
      min-width: 280px;
      z-index: 40;
      position: relative;
    }

    .conversation-sidebar[data-collapsed="true"] {
      width: 0;
      min-width: 0;
      padding: 0;
      overflow: hidden;
      border: none;
    }

    .conversation-sidebar[data-collapsed="true"] .sidebar-header,
    .conversation-sidebar[data-collapsed="true"] .sidebar-search,
    .conversation-sidebar[data-collapsed="true"] .sidebar-footer,
    .conversation-sidebar[data-collapsed="true"] #conversationList {
      opacity: 0;
      visibility: hidden;
    }

    /* Scrollbar personnalisée sidebar */
    #conversationList {
      scrollbar-width: thin;
      scrollbar-color: rgba(75, 85, 99, 0.5) transparent;
    }

    #conversationList::-webkit-scrollbar {
      width: 6px;
    }

    #conversationList::-webkit-scrollbar-track {
      background: transparent;
    }

    #conversationList::-webkit-scrollbar-thumb {
      background-color: rgba(75, 85, 99, 0.5);
      border-radius: 3px;
    }

    #conversationList::-webkit-scrollbar-thumb:hover {
      background-color: rgba(75, 85, 99, 0.8);
    }

    /* Item conversation */
    .conversation-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      border-radius: 0.5rem;
      cursor: pointer;
      transition: all 0.15s ease;
      border-left: 2px solid transparent;
      position: relative;
    }

    .conversation-item:hover {
      background-color: rgba(75, 85, 99, 0.2);
    }

    .conversation-item.active {
      background-color: rgba(16, 185, 129, 0.08);
      border-left-color: #10b981;
    }

    .conversation-item.active .conv-icon {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    .conversation-item .conv-icon {
      width: 32px;
      height: 32px;
      border-radius: 0.5rem;
      background-color: rgba(75, 85, 99, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: #9ca3af;
      transition: all 0.15s ease;
    }

    .conversation-item:hover .conv-icon {
      background-color: rgba(16, 185, 129, 0.15);
      color: #10b981;
    }

    .conversation-item .conv-content {
      flex: 1;
      min-width: 0;
    }

    .conversation-item .conv-title {
      font-size: 0.875rem;
      font-weight: 500;
      color: #d1d5db;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      transition: color 0.15s ease;
    }

    .conversation-item:hover .conv-title,
    .conversation-item.active .conv-title {
      color: #f3f4f6;
    }

    .conversation-item .conv-meta {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 0.25rem;
    }

    .conversation-item .conv-date {
      font-size: 0.75rem;
      color: #6b7280;
    }

    .conversation-item .conv-badge {
      font-size: 0.65rem;
      padding: 0.125rem 0.375rem;
      border-radius: 9999px;
      background-color: rgba(16, 185, 129, 0.15);
      color: #34d399;
      font-weight: 500;
    }

    .conversation-item .conv-actions {
      display: flex;
      gap: 0.25rem;
      opacity: 0;
      visibility: hidden;
      transition: all 0.15s ease;
      position: absolute;
      right: 0.5rem;
      background: linear-gradient(to right, transparent, rgba(17, 24, 39, 0.9) 20%);
      padding-left: 1rem;
    }

    .conversation-item:hover .conv-actions {
      opacity: 1;
      visibility: visible;
    }

    .conversation-item .conv-action-btn {
      padding: 0.375rem;
      border-radius: 0.375rem;
      color: #9ca3af;
      transition: all 0.15s ease;
    }

    .conversation-item .conv-action-btn:hover {
      background-color: rgba(55, 65, 81, 0.8);
      color: #f3f4f6;
    }

    .conversation-item .conv-action-btn.delete:hover {
      background-color: rgba(239, 68, 68, 0.2);
      color: #f87171;
    }

    /* Toggle icon animation */
    .sidebar-toggle-icon {
      transition: transform 0.3s ease;
    }

    .conversation-sidebar[data-collapsed="true"]~#openSidebarBtn .sidebar-toggle-icon,
    [data-collapsed="true"] .sidebar-toggle-icon {
      transform: rotate(180deg);
    }

    /* Position par défaut du profil (desktop) - reste en bas à gauche, derrière la sidebar */
    .profile-desktop-position {
      left: 0.5rem;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
      .conversation-sidebar {
        position: fixed;
        left: 0;
        top: 0;
        z-index: 40;
        box-shadow: 4px 0 15px rgba(0, 0, 0, 0.3);
      }

      .conversation-sidebar[data-collapsed="true"] {
        transform: translateX(-100%);
      }
    }

    /* Confirmation modal */
    .confirm-modal {
      position: fixed;
      inset: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
    }

    .confirm-modal-content {
      background-color: #1f2937;
      border: 1px solid rgba(75, 85, 99, 0.5);
      border-radius: 1rem;
      padding: 1.5rem;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    /* ===== ANIMATION ZONE DE SAISIE ===== */
    /* État initial: centré */
    #mainContent.centered {
      justify-content: center;
    }

    /* État actif: chat visible, input en bas */
    #mainContent.chat-active {
      justify-content: flex-start;
      padding-bottom: 0;
    }

    #mainContent.chat-active #chatContainer {
      flex: 1;
      max-height: none;
      margin-bottom: 1rem;
      min-height: 0;
    }

    #mainContent.chat-active #inputWrapper {
      position: sticky;
      bottom: 0;
      background: linear-gradient(to top, oklch(21% 0.006 285.885) 85%, transparent);
      padding-top: 1rem;
      padding-bottom: 1rem;
      margin-top: auto;
    }

    /* Animation du bouton envoyer */
    #sendButton:not(:disabled) {
      background-color: #10b981;
      color: white;
      cursor: pointer;
    }

    #sendButton:not(:disabled):hover {
      background-color: #059669;
    }

    /* ===== ACCESSIBILITÉ ===== */

    /* Classe pour éléments visibles uniquement aux screen readers */
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border-width: 0;
    }

    /* Focus visible amélioré pour tous les éléments interactifs */
    button:focus-visible,
    a:focus-visible,
    input:focus-visible,
    [tabindex]:focus-visible {
      outline: 2px solid #10b981;
      outline-offset: 2px;
    }

    /* Amélioration contraste des placeholders */
    ::placeholder {
      color: #9ca3af;
      opacity: 1;
    }

    /* États disabled plus visibles */
    [disabled],
    [aria-disabled="true"] {
      opacity: 0.6;
      cursor: not-allowed;
    }

    /* Respecter les préférences de mouvement réduit */
    @media (prefers-reduced-motion: reduce) {

      *,
      *::before,
      *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }

      .streaming-cursor {
        animation: none;
        opacity: 1;
      }

      .typing-indicator .dot {
        animation: none;
        opacity: 0.8;
      }
    }

    /* ===== INPUT FOCUS STYLE - Subtil et élégant ===== */
    #inputContainer {
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    #inputContainer:has(#messageInput:focus) {
      border-color: rgba(34, 197, 94, 0.3);
      box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.1);
    }

    /* Supprimer l'outline natif du navigateur sur l'input */
    #messageInput:focus,
    #mobileMessageInput:focus {
      outline: none !important;
      box-shadow: none !important;
    }

    /* ===== MOBILE INPUT - VERSION DISTINCTE ===== */

    /* Par défaut: mobile caché, desktop visible */
    #mobileInputContainer {
      display: none;
    }

    #inputWrapper {
      display: block;
    }

    /* Mobile: afficher version mobile, cacher desktop */
    @media (max-width: 640px) {

      /* Cacher la version desktop mais garder le menu modèles accessible */
      #inputWrapper {
        visibility: hidden !important;
        height: 0 !important;
        overflow: visible !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      /* Le menu modèles reste visible en position fixe */
      #inputWrapper #modelMenu {
        visibility: visible !important;
      }

      #inputWrapper #modelMenu.opacity-100 {
        position: fixed !important;
        bottom: 5.5rem !important;
        left: 1rem !important;
        right: 1rem !important;
        top: auto !important;
        width: auto !important;
        max-width: none !important;
        max-height: 50vh !important;
        z-index: 100 !important;
        visibility: visible !important;
        opacity: 1 !important;
        transform: none !important;
      }

      /* Afficher la version mobile */
      #mobileInputContainer {
        display: flex !important;
        flex-direction: column;
        width: 100%;
        padding: 0 1rem;
      }

      /* Style container mobile - similaire au desktop */
      #mobileInputBox {
        background: rgba(31, 41, 55, 0.5);
        border: 1px solid rgba(75, 85, 99, 0.5);
        border-radius: 1rem;
        padding: 1rem;
        width: 100%;
      }

      /* Input mobile - style desktop */
      #mobileMessageInput {
        width: 100%;
        background: transparent;
        border: none;
        outline: none;
        color: #d1d5db;
        font-size: 1rem;
        /* 16px évite zoom iOS */
        padding: 0;
        margin-bottom: 1rem;
      }

      #mobileMessageInput::placeholder {
        color: #9ca3af;
      }

      /* Barre d'actions mobile */
      #mobileActions {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }

      /* Groupe icônes gauche mobile */
      #mobileActionsLeft {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      /* Boutons icônes mobile */
      .mobile-icon-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.5rem;
        background: transparent;
        color: #9ca3af;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
      }

      .mobile-icon-btn:hover,
      .mobile-icon-btn:focus {
        background: rgba(75, 85, 99, 0.5);
        color: #e5e7eb;
      }

      .mobile-icon-btn svg {
        width: 1.125rem;
        height: 1.125rem;
      }

      /* Bouton envoi mobile */
      #mobileSendButton {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.75rem;
        background: #374151;
        color: #9ca3af;
        border: none;
        cursor: not-allowed;
        transition: all 0.3s;
      }

      #mobileSendButton.active {
        background: #10b981;
        color: white;
        cursor: pointer;
      }

      #mobileSendButton svg {
        width: 1.125rem;
        height: 1.125rem;
      }

      /* Badge sélecteur modèle mobile - style pill */
      #mobileModelSelector {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        background: rgba(75, 85, 99, 0.5);
        border: 1px solid rgba(107, 114, 128, 0.3);
        color: #d1d5db;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
      }

      #mobileModelSelector:hover,
      #mobileModelSelector:focus {
        background: rgba(75, 85, 99, 0.8);
        color: #f3f4f6;
      }

      #mobileModelSelector img {
        width: 0.875rem;
        height: 0.875rem;
        border-radius: 0.25rem;
      }

      /* Logo plus grand sur mobile pour l'écran d'accueil */
      #logoContainer {
        margin-bottom: 1.5rem;
      }

      #siteLogo {
        height: 4rem;
      }

      /* Message accueil - style Perplexity */
      #welcomeMessage {
        font-size: 1.125rem;
        font-weight: 500;
        padding: 0 1rem;
        margin-bottom: 1.5rem;
        color: #e5e7eb;
      }

      /* Bouton historique sur mobile - visible en haut à gauche */
      #openSidebarBtn {
        display: flex !important;
        position: fixed !important;
        top: 0.75rem !important;
        left: 0.75rem !important;
        padding: 0.5rem !important;
        width: 2.5rem !important;
        height: 2.5rem !important;
        align-items: center;
        justify-content: center;
        border-radius: 50% !important;
        background: rgba(31, 41, 55, 0.9) !important;
        -webkit-backdrop-filter: blur(8px);
        backdrop-filter: blur(8px);
      }

      /* Cacher le bouton historique quand sidebar ouverte sur mobile */
      #openSidebarBtn.hidden {
        display: none !important;
      }

      /* Repositionner les boutons connexion en haut à droite sur mobile */
      #profileContainer {
        position: fixed !important;
        bottom: auto !important;
        top: 0.75rem !important;
        left: auto !important;
        right: 0.75rem !important;
        width: auto !important;
      }

      /* Surcharger la classe desktop */
      #profileContainer.profile-desktop-position {
        left: auto !important;
      }

      #profileContainer .flex.items-center.gap-2 {
        flex-direction: row;
        gap: 0.5rem !important;
      }

      /* Boutons auth en rond sur mobile */
      #profileContainer a.rounded-full {
        width: 2.5rem !important;
        height: 2.5rem !important;
        padding: 0 !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
      }

      #profileContainer a.rounded-full span {
        display: none !important;
      }

      #profileContainer a.rounded-full i {
        margin: 0 !important;
      }

      /* Profile button (connecté) - dimensions gérées globalement */
      /* Cacher l'indicateur chevron sur mobile */
      #profileButton #profileIcon {
        display: none !important;
      }

      /* Menu profil sur mobile - s'afficher en dessous */
      #profileMenu {
        bottom: auto !important;
        top: 100% !important;
        left: auto !important;
        right: 0 !important;
        margin-top: 0.5rem !important;
        margin-bottom: 0 !important;
      }

      /* Main content - centré verticalement */
      #mainContent {
        overflow-x: hidden;
        padding: 1rem 0.5rem !important;
        justify-content: flex-start;
        padding-top: 15vh !important;
      }

      /* Quand conversation active - repositionner */
      #mainContent.chat-active {
        padding-top: 1rem !important;
        justify-content: flex-start;
      }

      #mainContent.chat-active #logoContainer {
        display: none;
      }

      #mainContent.chat-active #welcomeMessage {
        display: none;
      }

      #mainContent.chat-active #mobileInputContainer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgb(17, 24, 39) 85%, transparent);
        padding: 1rem;
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
        z-index: 40;
      }

      /* Chat container mobile */
      #mainContent.chat-active #chatContainer {
        padding-bottom: 6rem;
      }

      /* Prévisualisation fichiers mobile */
      #mobileFilePreview {
        margin-bottom: 0.75rem;
      }

      #mobileFilePreview:empty {
        display: none;
      }

      /* Profile container mobile - position gérée via right (défini plus haut) */

      /* Menu modèles - repositionné pour mobile */
      #modelMenu {
        position: fixed !important;
        bottom: 5rem !important;
        left: 1rem !important;
        right: 1rem !important;
        top: auto !important;
        width: auto !important;
        max-width: none !important;
        max-height: 50vh !important;
        transform: none !important;
        z-index: 100 !important;
      }

      #modelMenu.opacity-100 {
        transform: none !important;
      }
    }

    /* ===== TRÈS PETITS ÉCRANS (< 380px) ===== */
    @media (max-width: 380px) {
      #mobileInputBox {
        padding: 0.75rem;
        border-radius: 1.25rem;
      }

      #mobileMessageInput {
        font-size: 0.9375rem;
      }

      #mobileModelSelector span {
        max-width: 60px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      #siteLogo {
        height: 3.5rem;
      }

      #welcomeMessage {
        font-size: 1rem;
      }
    }

    /* ===== DESKTOP - garder styles existants optimisés ===== */
    @media (min-width: 641px) {

      /* Desktop garde tout comme avant */
      #inputContainer {
        padding: 1rem;
      }
    }

    /* ===== MOBILE BOTTOM SHEET - MENU MODÈLES ===== */
    .mobile-bottom-sheet {
      position: fixed;
      inset: 0;
      z-index: 200;
      pointer-events: none;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .mobile-bottom-sheet.active {
      pointer-events: auto;
      opacity: 1;
      visibility: visible;
    }

    .mobile-bottom-sheet-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
    }

    .mobile-bottom-sheet-content {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      max-height: 85vh;
      background: #1f2937;
      border-radius: 1.5rem 1.5rem 0 0;
      transform: translateY(100%);
      transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      padding-bottom: env(safe-area-inset-bottom, 0);
    }

    .mobile-bottom-sheet.active .mobile-bottom-sheet-content {
      transform: translateY(0);
    }

    /* Handle du bottom sheet */
    .mobile-sheet-handle {
      width: 36px;
      height: 4px;
      background: rgba(156, 163, 175, 0.4);
      border-radius: 9999px;
      margin: 0.75rem auto;
      flex-shrink: 0;
    }

    /* Header du bottom sheet modèles */
    .mobile-sheet-header {
      padding: 0 1rem 0.75rem;
      border-bottom: 1px solid rgba(75, 85, 99, 0.3);
      flex-shrink: 0;
    }

    .mobile-sheet-title {
      font-size: 1.125rem;
      font-weight: 600;
      color: #f3f4f6;
      margin-bottom: 0.75rem;
    }

    /* Recherche dans le bottom sheet */
    .mobile-sheet-search {
      position: relative;
    }

    .mobile-sheet-search input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.75rem;
      background: rgba(55, 65, 81, 0.5);
      border: 1px solid rgba(75, 85, 99, 0.5);
      border-radius: 0.75rem;
      color: #e5e7eb;
      font-size: 1rem;
      outline: none;
      transition: border-color 0.2s, background 0.2s;
    }

    .mobile-sheet-search input:focus {
      border-color: rgba(16, 185, 129, 0.5);
      background: rgba(55, 65, 81, 0.8);
    }

    .mobile-sheet-search input::placeholder {
      color: #9ca3af;
    }

    .mobile-sheet-search i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      pointer-events: none;
    }

    /* Liste des modèles mobile */
    .mobile-sheet-list {
      flex: 1;
      overflow-y: auto;
      padding: 0.5rem;
      overscroll-behavior: contain;
      -webkit-overflow-scrolling: touch;
    }

    /* Section provider dans la liste mobile */
    .mobile-provider-section {
      margin-bottom: 0.5rem;
    }

    .mobile-provider-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 0.75rem;
      color: #9ca3af;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .mobile-provider-header img {
      width: 1rem;
      height: 1rem;
      border-radius: 0.25rem;
    }

    /* Item modèle mobile - touch-friendly */
    .mobile-model-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem;
      border-radius: 0.75rem;
      cursor: pointer;
      transition: background 0.15s ease;
      min-height: 56px;
      /* Touch target minimum */
      -webkit-tap-highlight-color: transparent;
    }

    .mobile-model-item:hover,
    .mobile-model-item:active {
      background: rgba(55, 65, 81, 0.5);
    }

    .mobile-model-item.selected {
      background: rgba(16, 185, 129, 0.15);
      border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .mobile-model-item img {
      width: 2rem;
      height: 2rem;
      border-radius: 0.5rem;
      flex-shrink: 0;
    }

    .mobile-model-item .model-info {
      flex: 1;
      min-width: 0;
    }

    .mobile-model-item .model-name {
      font-size: 0.9375rem;
      font-weight: 500;
      color: #e5e7eb;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mobile-model-item .model-provider {
      font-size: 0.75rem;
      color: #9ca3af;
      margin-top: 0.125rem;
    }

    .mobile-model-item .model-check {
      color: #10b981;
      font-size: 1.125rem;
      flex-shrink: 0;
    }

    /* ===== MOBILE BOTTOM SHEET - MENU PROFIL ===== */
    .mobile-profile-sheet .mobile-bottom-sheet-content {
      max-height: auto;
      padding: 0 0 env(safe-area-inset-bottom, 1rem);
    }

    .mobile-profile-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid rgba(75, 85, 99, 0.3);
    }

    .mobile-profile-avatar {
      width: 3.5rem;
      height: 3.5rem;
      border-radius: 9999px;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.25rem;
      font-weight: 600;
      flex-shrink: 0;
    }

    .mobile-profile-info h3 {
      font-size: 1rem;
      font-weight: 600;
      color: #f3f4f6;
    }

    .mobile-profile-info p {
      font-size: 0.875rem;
      color: #9ca3af;
      margin-top: 0.125rem;
    }

    /* Items du menu profil mobile */
    .mobile-profile-menu {
      padding: 0.5rem;
    }

    .mobile-profile-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      border-radius: 0.75rem;
      color: #d1d5db;
      font-size: 0.9375rem;
      cursor: pointer;
      transition: background 0.15s ease;
      min-height: 56px;
      -webkit-tap-highlight-color: transparent;
      text-decoration: none;
    }

    .mobile-profile-item:hover,
    .mobile-profile-item:active {
      background: rgba(55, 65, 81, 0.5);
    }

    .mobile-profile-item i {
      width: 1.5rem;
      text-align: center;
      font-size: 1.125rem;
      color: #9ca3af;
    }

    .mobile-profile-item.danger {
      color: #f87171;
    }

    .mobile-profile-item.danger i {
      color: #f87171;
    }

    .mobile-profile-divider {
      height: 1px;
      background: rgba(75, 85, 99, 0.3);
      margin: 0.5rem 0.75rem;
    }

    /* ===== MOBILE AUTH BUTTONS SHEET ===== */
    .mobile-auth-sheet .mobile-bottom-sheet-content {
      padding: 1rem 1rem calc(env(safe-area-inset-bottom, 1rem) + 0.5rem);
    }

    .mobile-auth-buttons {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .mobile-auth-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      padding: 1rem;
      border-radius: 0.75rem;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      min-height: 56px;
      text-decoration: none;
      -webkit-tap-highlight-color: transparent;
    }

    .mobile-auth-btn.primary {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    .mobile-auth-btn.primary:hover,
    .mobile-auth-btn.primary:active {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
    }

    .mobile-auth-btn.secondary {
      background: rgba(55, 65, 81, 0.5);
      border: 1px solid rgba(75, 85, 99, 0.5);
      color: #e5e7eb;
    }

    .mobile-auth-btn.secondary:hover,
    .mobile-auth-btn.secondary:active {
      background: rgba(55, 65, 81, 0.8);
    }

    /* ===== OVERRIDE MOBILE/TABLETTE - Cacher menus desktop ===== */
    @media (max-width: 800px) {

      /* Cacher le menu modèles desktop sur mobile/tablette */
      #modelMenu {
        display: none !important;
      }

      /* Cacher le menu profil desktop sur mobile/tablette */
      #profileMenu {
        display: none !important;
      }

      /* Boutons auth mobile */
      #profileContainer>.flex.items-center.gap-2>a {
        display: none !important;
      }

      /* Afficher le bouton compact auth mobile */
      #mobileAuthTrigger {
        display: flex !important;
      }
    }

    /* Cacher le trigger auth sur desktop */
    @media (min-width: 801px) {

      #mobileAuthTrigger,
      #mobileModelSheet,
      #mobileProfileSheet,
      #mobileAuthSheet {
        display: none !important;
      }
    }

    /* ===== TABLETTE (641px - 1024px) ===== */
    @media (min-width: 641px) and (max-width: 1024px) {

      /* Sidebar plus étroite sur tablette */
      .conversation-sidebar {
        width: 240px;
        min-width: 240px;
      }

      /* Ajuster la position du profil */
      .profile-desktop-position {
        left: calc(240px + 0.5rem);
      }

      /* Zone de saisie adaptée */
      #inputWrapper {
        max-width: 90%;
      }

      /* Chat container plus large */
      #chatContainer {
        max-width: 90%;
      }
    }

    /* ===== BOUTON PROFIL - TOUJOURS ROND (toutes tailles) ===== */
    #profileButton {
      width: 3rem;
      height: 3rem;
      min-width: 3rem;
      min-height: 3rem;
      border-radius: 9999px !important;
      padding: 0 !important;
    }

    /* Sur mobile, légèrement plus petit */
    @media (max-width: 640px) {
      #profileButton {
        width: 2.5rem;
        height: 2.5rem;
        min-width: 2.5rem;
        min-height: 2.5rem;
      }
    }

    /* ===== DARK/LIGHT MODE - CSS CUSTOM (Browser CDN ne supporte pas dark:) ===== */
    body[data-theme="light"] {
      background-color: #f9fafb;
      color: #111827;
    }

    body[data-theme="light"] #conversationSidebar {
      background-color: #ffffff !important;
      border-right-color: #e5e7eb;
    }

    body[data-theme="light"] .conversation-item {
      color: #4b5563;
    }

    body[data-theme="light"] .conversation-item:hover {
      background-color: rgba(156, 163, 175, 0.1);
    }

    body[data-theme="light"] .conversation-item.active {
      background-color: rgba(16, 185, 129, 0.1);
    }

    body[data-theme="light"] .conversation-item .conv-title {
      color: #1f2937;
    }

    body[data-theme="light"] .sidebar-title {
      color: #6b7280;
    }

    body[data-theme="light"] #chatContainer .ai-message {
      background-color: #ffffff;
      border-color: #e5e7eb;
    }

    body[data-theme="light"] .ai-message p,
    body[data-theme="light"] .ai-message li,
    body[data-theme="light"] .ai-message {
      color: #374151 !important;
    }

    body[data-theme="light"] #inputContainer,
    body[data-theme="light"] #mobileInputBox {
      background: rgba(255, 255, 255, 0.9);
      border-color: #e5e7eb;
    }

    body[data-theme="light"] #messageInput,
    body[data-theme="light"] #mobileMessageInput {
      color: #1f2937;
    }

    body[data-theme="light"] #messageInput::placeholder,
    body[data-theme="light"] #mobileMessageInput::placeholder {
      color: #9ca3af;
    }

    /* Keep code blocks dark in light mode for better readability */
    body[data-theme="light"] .code-block-wrapper,
    body[data-theme="light"] pre code {
      background-color: #1e1e1e !important;
    }

    /* ===== RESPONSIVE - DARK MODE & SCROLL BUTTONS ===== */

    /* Mobile (max-width: 640px) */
    @media (max-width: 640px) {
      /* Dark mode toggle - décaler vers la gauche pour éviter le bouton profil */
      #themeToggleBtn {
        top: 0.75rem !important;
        right: 3.75rem !important; /* Laisse place au bouton profil (2.5rem + ~1.25rem gap) */
      }

      /* Scroll to bottom - position adaptée au mobile */
      #scrollToBottomBtn {
        bottom: 7rem !important; /* Au-dessus de l'input mobile (qui est à bottom: 0 avec z-index: 40) */
        right: 1rem !important;
        padding: 0.5rem 0.75rem !important;
        z-index: 45 !important; /* Au-dessus de l'input mobile (z-index: 40) */
        font-size: 0.875rem !important; /* Légèrement plus petit sur mobile */
      }
    }

    /* Tablette (641px - 768px) */
    @media (min-width: 641px) and (max-width: 768px) {
      #themeToggleBtn {
        top: 1rem;
        right: 1rem;
      }

      #scrollToBottomBtn {
        bottom: 6rem;
        right: 1.5rem;
      }
    }

    /* ===== GDPR COOKIE CONSENT BANNER ===== */
    #cookieConsentBanner {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 9999;
      background: linear-gradient(to top, rgba(33, 33, 33, 0.98), rgba(33, 33, 33, 0.95));
      border-top: 1px solid rgba(66, 66, 66, 0.8);
      padding: 1.25rem 1.5rem;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
      transform: translateY(100%);
      opacity: 0;
      transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.3s ease;
    }

    #cookieConsentBanner.visible {
      transform: translateY(0);
      opacity: 1;
    }

    #cookieConsentBanner.hiding {
      transform: translateY(100%);
      opacity: 0;
    }

    .cookie-banner-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.5rem;
      flex-wrap: wrap;
    }

    .cookie-banner-text {
      flex: 1;
      min-width: 280px;
    }

    .cookie-banner-text h3 {
      font-size: 1rem;
      font-weight: 600;
      color: #f3f4f6;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .cookie-banner-text h3 i {
      color: #10b981;
    }

    .cookie-banner-text p {
      font-size: 0.875rem;
      color: #9ca3af;
      line-height: 1.5;
      margin: 0;
    }

    .cookie-banner-text a {
      color: #10b981;
      text-decoration: underline;
      transition: color 0.2s;
    }

    .cookie-banner-text a:hover {
      color: #34d399;
    }

    .cookie-banner-actions {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .cookie-btn {
      padding: 0.625rem 1.25rem;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
      white-space: nowrap;
    }

    .cookie-btn-accept {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    .cookie-btn-accept:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .cookie-btn-reject {
      background: rgba(75, 85, 99, 0.5);
      color: #d1d5db;
    }

    .cookie-btn-reject:hover {
      background: rgba(75, 85, 99, 0.8);
    }

    .cookie-btn-settings {
      background: transparent;
      color: #9ca3af;
      border: 1px solid rgba(75, 85, 99, 0.5);
    }

    .cookie-btn-settings:hover {
      background: rgba(75, 85, 99, 0.3);
      color: #e5e7eb;
      border-color: rgba(107, 114, 128, 0.6);
    }

    /* Modal paramètres cookies */
    #cookieSettingsModal {
      position: fixed;
      inset: 0;
      z-index: 10000;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.6);
      backdrop-filter: blur(4px);
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    #cookieSettingsModal.visible {
      opacity: 1;
      visibility: visible;
    }

    .cookie-settings-content {
      background: #1f2937;
      border: 1px solid rgba(75, 85, 99, 0.5);
      border-radius: 1rem;
      max-width: 500px;
      width: 90%;
      max-height: 85vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      transform: scale(0.95) translateY(10px);
      transition: transform 0.3s ease;
    }

    #cookieSettingsModal.visible .cookie-settings-content {
      transform: scale(1) translateY(0);
    }

    .cookie-settings-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(75, 85, 99, 0.3);
    }

    .cookie-settings-header h3 {
      font-size: 1.125rem;
      font-weight: 600;
      color: #f3f4f6;
      margin: 0;
    }

    .cookie-settings-close {
      width: 2rem;
      height: 2rem;
      border-radius: 0.5rem;
      background: transparent;
      border: none;
      color: #9ca3af;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }

    .cookie-settings-close:hover {
      background: rgba(75, 85, 99, 0.5);
      color: #f3f4f6;
    }

    .cookie-settings-body {
      padding: 1.5rem;
    }

    .cookie-category {
      padding: 1rem;
      background: rgba(55, 65, 81, 0.3);
      border-radius: 0.75rem;
      margin-bottom: 1rem;
    }

    .cookie-category:last-child {
      margin-bottom: 0;
    }

    .cookie-category-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }

    .cookie-category-title {
      font-size: 0.9375rem;
      font-weight: 600;
      color: #e5e7eb;
    }

    .cookie-category-desc {
      font-size: 0.8125rem;
      color: #9ca3af;
      line-height: 1.4;
      margin: 0;
    }

    /* Toggle switch */
    .cookie-toggle {
      position: relative;
      width: 44px;
      height: 24px;
      flex-shrink: 0;
    }

    .cookie-toggle input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .cookie-toggle-slider {
      position: absolute;
      cursor: pointer;
      inset: 0;
      background: rgba(75, 85, 99, 0.5);
      border-radius: 9999px;
      transition: all 0.3s ease;
    }

    .cookie-toggle-slider::before {
      content: '';
      position: absolute;
      width: 18px;
      height: 18px;
      left: 3px;
      bottom: 3px;
      background: white;
      border-radius: 50%;
      transition: transform 0.3s ease;
    }

    .cookie-toggle input:checked+.cookie-toggle-slider {
      background: #10b981;
    }

    .cookie-toggle input:checked+.cookie-toggle-slider::before {
      transform: translateX(20px);
    }

    .cookie-toggle input:disabled+.cookie-toggle-slider {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .cookie-toggle input:disabled+.cookie-toggle-slider::before {
      background: #d1d5db;
    }

    .cookie-settings-footer {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
      padding: 1rem 1.5rem;
      border-top: 1px solid rgba(75, 85, 99, 0.3);
    }

    /* Mobile responsive cookie banner */
    @media (max-width: 640px) {
      #cookieConsentBanner {
        padding: 1rem;
      }

      .cookie-banner-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
      }

      .cookie-banner-text {
        min-width: 100%;
      }

      .cookie-banner-text h3 {
        justify-content: center;
      }

      .cookie-banner-actions {
        width: 100%;
        justify-content: center;
      }

      .cookie-btn {
        flex: 1;
        min-width: 80px;
      }
    }
  </style>
</head>

<body data-theme="dark" class="min-h-screen text-gray-100 flex overflow-hidden" style="background-color: oklch(21% 0.006 285.885);">

  <!-- Skip links pour accessibilité clavier -->
  <a href="#messageInput" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:px-4 focus:py-2 focus:bg-green-600 focus:text-white focus:rounded-lg focus:outline-none focus:shadow-lg">
    Aller à la zone de saisie
  </a>
  <a href="#chatContainer" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-52 focus:z-[100] focus:px-4 focus:py-2 focus:bg-green-600 focus:text-white focus:rounded-lg focus:outline-none focus:shadow-lg">
    Aller à la conversation
  </a>

  <!-- Dark Mode Toggle Button -->
  <button id="themeToggleBtn"
    aria-label="Changer de thème"
    title="Changer de thème"
    class="fixed top-4 right-4 z-50 p-2 rounded-lg bg-gray-800/30 border border-gray-700/30 text-gray-400 hover:text-white hover:bg-gray-700/30 transition-all duration-150 backdrop-blur-sm">
    <i class="fa-solid fa-moon" id="themeIcon"></i>
  </button>

  <!-- Scroll to Bottom Button -->
  <button id="scrollToBottomBtn"
    aria-label="Descendre en bas"
    title="Descendre en bas"
    class="fixed bottom-24 right-4 z-30 px-3 py-2 rounded-lg bg-gray-800/50 border border-gray-700/50 text-gray-400 hover:text-white hover:bg-gray-700/50 hover:border-gray-600/50 transition-all duration-200 opacity-0 invisible pointer-events-none backdrop-blur-sm shadow-lg"
    style="display: none;">
    <i class="fa-solid fa-arrow-down text-sm"></i>
  </button>

  <!-- Sidebar Historique des Conversations -->
  <aside id="conversationSidebar"
    aria-label="Historique des conversations"
    class="conversation-sidebar flex flex-col border-r border-gray-700/30 h-screen transition-all duration-300 ease-in-out"
    data-collapsed="false"
    style="background-color: oklch(21% 0.006 285.885);">
    <!-- Header Sidebar -->
    <div class="sidebar-header flex items-center justify-between px-4 py-3 border-b border-gray-700/20">
      <h2 id="sidebarTitle" class="sidebar-title text-sm font-semibold text-gray-300 uppercase tracking-wider flex items-center gap-2">
        <i class="fa-solid fa-clock-rotate-left text-green-500" aria-hidden="true"></i>
        <span class="sidebar-title-text">Historique</span>
      </h2>
      <div class="flex items-center gap-0.5">
        <!-- Bouton Nouvelle Conversation -->
        <button id="newConversationBtn"
          aria-label="Créer une nouvelle conversation"
          class="p-2 rounded-lg text-gray-400 hover:text-green-400 hover:bg-gray-700/30 transition-all duration-150 cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
        </button>
        <!-- Bouton Collapse -->
        <button id="toggleSidebarBtn"
          aria-label="Réduire la barre latérale"
          aria-expanded="true"
          aria-controls="conversationSidebar"
          class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700/30 transition-all duration-150 cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
          <i class="fa-solid fa-angles-left sidebar-toggle-icon" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    <!-- Barre de recherche -->
    <div class="sidebar-search p-3 border-b border-gray-700/20" role="search">
      <div class="relative group">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm group-focus-within:text-green-500 transition-colors" aria-hidden="true"></i>
        <label for="conversationSearch" class="sr-only">Rechercher dans les conversations</label>
        <input type="search"
          id="conversationSearch"
          placeholder="Rechercher..."
          aria-label="Rechercher dans l'historique des conversations"
          autocomplete="off"
          class="w-full bg-gray-800/30 border border-gray-700/30 rounded-lg pl-9 pr-3 py-2 text-sm text-gray-300 placeholder:text-gray-400 placeholder:italic outline-none focus:border-green-500/50 focus:ring-2 focus:ring-green-500/20 focus:bg-gray-800/50 transition-all duration-200">
      </div>
    </div>

    <!-- Liste des conversations -->
    <nav id="conversationList"
      aria-label="Liste des conversations"
      class="flex-1 overflow-y-auto px-2 py-2 space-y-0.5">
      <!-- Placeholder quand pas de conversations -->
      <div id="noConversationsPlaceholder" class="flex flex-col items-center justify-center py-12 text-gray-400">
        <div class="w-16 h-16 rounded-full bg-gray-800/50 flex items-center justify-center mb-4">
          <i class="fa-regular fa-comments text-2xl opacity-60" aria-hidden="true"></i>
        </div>
        <p class="text-sm font-medium text-gray-400">Aucune conversation</p>
        <p class="text-xs mt-1 text-gray-400">Commencez à discuter !</p>
      </div>
    </nav>

    <!-- Footer Sidebar -->
    <div class="sidebar-footer p-3 border-t border-gray-700/20 space-y-2">
      <button id="newConversationBtnFooter" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-500 text-white text-sm font-medium transition-colors duration-150 cursor-pointer">
        <i class="fa-solid fa-plus"></i>
        <span>Nouvelle conversation</span>
      </button>
      <button id="exportConversationsBtn" class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-gray-800/30 border border-gray-700/30 text-gray-400 hover:text-white hover:bg-gray-700/30 transition-all duration-150 text-sm cursor-pointer" title="Exporter les conversations">
        <i class="fa-solid fa-file-export"></i>
        <span class="sidebar-btn-text">Exporter tout</span>
      </button>
    </div>
  </aside>

  <!-- Overlay mobile -->
  <div id="sidebarOverlay" class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm opacity-0 invisible transition-all duration-300 md:hidden" data-visible="false"></div>

  <!-- ===== MOBILE BOTTOM SHEET - MENU MODÈLES ===== -->
  <div id="mobileModelSheet" class="mobile-bottom-sheet" role="dialog" aria-modal="true" aria-labelledby="mobileModelSheetTitle">
    <div class="mobile-bottom-sheet-backdrop" onclick="closeMobileModelSheet()"></div>
    <div class="mobile-bottom-sheet-content">
      <div class="mobile-sheet-handle" role="presentation"></div>
      <div class="mobile-sheet-header">
        <h2 id="mobileModelSheetTitle" class="mobile-sheet-title">Sélectionner un modèle</h2>
        <div class="mobile-sheet-search">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <input type="search" id="mobileModelSearch" placeholder="Rechercher un modèle..." autocomplete="off" aria-label="Rechercher un modèle">
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
          <span class="ml-3 text-gray-400 text-sm">Chargement des modèles...</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== MOBILE BOTTOM SHEET - MENU PROFIL (connecté) ===== -->
  <?php if ($user): ?>
    <div id="mobileProfileSheet" class="mobile-bottom-sheet mobile-profile-sheet" role="dialog" aria-modal="true" aria-labelledby="mobileProfileSheetTitle">
      <div class="mobile-bottom-sheet-backdrop" onclick="closeMobileProfileSheet()"></div>
      <div class="mobile-bottom-sheet-content">
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
              <span>Admin</span>
            </a>
          <?php endif; ?>
          <div class="mobile-profile-divider"></div>
          <a href="zone_membres/logout.php" class="mobile-profile-item danger">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span>Déconnexion</span>
          </a>
        </nav>
      </div>
    </div>
  <?php else: ?>
    <!-- ===== MOBILE BOTTOM SHEET - AUTH (non connecté) ===== -->
    <div id="mobileAuthSheet" class="mobile-bottom-sheet mobile-auth-sheet" role="dialog" aria-modal="true" aria-labelledby="mobileAuthSheetTitle">
      <div class="mobile-bottom-sheet-backdrop" onclick="closeMobileAuthSheet()"></div>
      <div class="mobile-bottom-sheet-content">
        <div class="mobile-sheet-handle" role="presentation"></div>
        <h2 id="mobileAuthSheetTitle" class="sr-only">Connexion ou inscription</h2>
        <div class="mobile-auth-buttons">
          <a href="zone_membres/register.php" class="mobile-auth-btn primary">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
            <span>Créer un compte gratuit</span>
          </a>
          <a href="zone_membres/login.php" class="mobile-auth-btn secondary">
            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
            <span>Se connecter</span>
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Bouton pour ouvrir la sidebar (visible quand collapsed) -->
  <button id="openSidebarBtn" class="fixed left-4 top-4 z-50 p-3 rounded-xl border border-gray-700/30 text-gray-400 hover:text-green-400 hover:bg-gray-700/30 hover:border-green-500/30 transition-all duration-200 cursor-pointer shadow-xl backdrop-blur-sm hidden" style="background-color: oklch(21% 0.006 285.885);" title="Ouvrir l'historique">
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
      Posez-moi une question pour commencer.
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
              <span id="resetTimer" data-reset-time="<?php echo $timeRemaining; ?>"></span> • <a href="zone_membres/register.php" class="text-green-400 hover:text-green-300 underline">Inscrivez-vous</a> pour un accès illimité
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
        class="bg-gray-800/50 rounded-2xl p-4 border border-gray-700/50">
        <!-- Zone de prévisualisation des fichiers -->
        <div id="filePreviewContainer" class="hidden mb-3 flex flex-wrap gap-2">
        </div>
        <label for="messageInput" class="sr-only">Votre message à l'assistant IA</label>
        <input
          type="text"
          id="messageInput"
          placeholder="Posez une question. Tapez @ pour mentions et / pour raccourcis."
          aria-label="Votre message à l'assistant IA"
          aria-describedby="inputHelpText"
          autocomplete="off"
          class="w-full bg-transparent text-gray-300 placeholder:text-gray-400 outline-none text-base mb-4" />
        <span id="inputHelpText" class="sr-only">Appuyez sur Entrée pour envoyer, ou utilisez le bouton d'envoi</span>

        <!-- Barre d'outils -->
        <div class="flex items-center justify-between">
          <!-- Icônes gauche -->
          <div class="flex items-center gap-2">
            <!-- Bouton ampoule -->
            <button
              class="p-2 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-700/50 transition-colors cursor-pointer">
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
                <span id="modelName">GPT-4o Mini</span>
                <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-gray-400 transition-transform duration-200" id="modelChevron" aria-hidden="true">
                  <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                </svg>
              </button>

              <!-- Menu déroulant des modèles -->
              <div
                id="modelMenu"
                role="listbox"
                aria-labelledby="modelSelectorBtn"
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
              class="p-2 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-700/50 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
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
              class="p-2 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-700/50 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
              <i class="fa-solid fa-microphone-lines"></i>
            </button>

            <!-- Bouton envoyer -->
            <button
              id="sendButton"
              disabled
              aria-label="Envoyer le message"
              aria-disabled="true"
              class="p-2 rounded-lg bg-gray-700 text-gray-400 transition-all duration-300 ease-in-out cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-green-500">
              <i class="fa-solid fa-paper-plane"></i>
            </button>
          </div>
        </div>

        <!-- Zone d'affichage des tokens -->
        <div id="tokensDisplay" class="hidden mt-3 pt-3 border-t border-gray-700/50">
          <div class="flex items-center justify-between text-xs text-gray-400">
            <div class="flex items-center gap-2">
              <div class="flex items-center gap-1.5">
                <i class="fa-solid fa-microchip text-gray-500"></i>
                <span class="font-medium">Tokens:</span>
              </div>
              <div class="flex items-center gap-2">
                <div id="tokensInputBadge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-500/10 border border-blue-500/20 text-blue-400">
                  <span id="tokensInputValue">0</span>
                </div>
                <div id="tokensOutputBadge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-green-500/10 border border-green-500/20 text-green-400">
                  <span id="tokensOutputValue">0</span>
                </div>
                <div id="tokensTotalBadge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-purple-500/10 border border-purple-500/20 text-purple-400">
                  <span id="tokensTotalValue">0</span>
                </div>
                <!-- Point d'interrogation avec tooltip -->
                <button id="tokensHelpBtn" class="ml-1 w-5 h-5 flex items-center justify-center rounded-full bg-gray-700/50 hover:bg-gray-600/50 text-gray-400 hover:text-gray-300 transition-colors relative" title="Aide tokens">
                  <i class="fa-solid fa-question text-xs"></i>
                </button>
              </div>
            </div>
          </div>
          <!-- Popover explicatif -->
          <div id="tokensTooltip" class="hidden absolute z-50 mt-2 p-3 bg-gray-800 border border-gray-700 rounded-lg shadow-xl text-xs text-gray-300 max-w-xs">
            <div class="space-y-2">
              <div class="flex items-start gap-2">
                <div class="w-3 h-3 mt-0.5 rounded bg-blue-500/20 border border-blue-500/40 flex-shrink-0"></div>
                <div>
                  <span class="font-medium text-blue-400">Entrée</span> : Tokens de votre message
                </div>
              </div>
              <div class="flex items-start gap-2">
                <div class="w-3 h-3 mt-0.5 rounded bg-green-500/20 border border-green-500/40 flex-shrink-0"></div>
                <div>
                  <span class="font-medium text-green-400">Sortie</span> : Tokens de la réponse IA
                </div>
              </div>
              <div class="flex items-start gap-2">
                <div class="w-3 h-3 mt-0.5 rounded bg-purple-500/20 border border-purple-500/40 flex-shrink-0"></div>
                <div>
                  <span class="font-medium text-purple-400">Total</span> : Somme des tokens utilisés
                </div>
              </div>
              <div class="pt-2 mt-2 border-t border-gray-700/50 text-gray-400">
                Les tokens représentent la quantité de texte traité par l'IA. ~1 token ≈ 4 caractères.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== ZONE DE SAISIE MOBILE ===== -->
    <div id="mobileInputContainer" aria-label="Zone de saisie mobile">
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
              <img id="mobileModelIcon" src="assets/images/providers/openai.svg" alt="">
              <span id="mobileModelName">GPT-4o Mini</span>
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
          class="relative flex items-center justify-center w-12 h-12 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 hover:from-green-400 hover:to-emerald-500 cursor-pointer transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-gray-900">
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
    // État utilisateur (isGuest et guestUsageLimit déjà définis dans le head)
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
    let selectedModel = {
      provider: 'openai',
      model: 'gpt-4o-mini',
      display: 'GPT-4o Mini'
    };

    const messageInput = document.getElementById("messageInput");
    const sendButton = document.getElementById("sendButton");
    const tokensDisplay = document.getElementById('tokensDisplay');
    const tokensHelpBtn = document.getElementById('tokensHelpBtn');
    const tokensTooltip = document.getElementById('tokensTooltip');

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

    // Gestion du tooltip des tokens
    let tooltipVisible = false;
    tokensHelpBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      tooltipVisible = !tooltipVisible;
      tokensTooltip.classList.toggle('hidden', !tooltipVisible);
    });

    // Fermer le tooltip en cliquant ailleurs
    document.addEventListener('click', (e) => {
      if (tooltipVisible && !tokensTooltip.contains(e.target) && e.target !== tokensHelpBtn) {
        tooltipVisible = false;
        tokensTooltip.classList.add('hidden');
      }
    });

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
      }
    });

    // Fermer le menu modèle si on clique ailleurs
    document.addEventListener('click', function() {
      if (isModelMenuOpen) {
        isModelMenuOpen = false;
        modelMenu.classList.remove('opacity-100', 'visible', 'translate-y-0');
        modelMenu.classList.add('opacity-0', 'invisible', 'translate-y-2');
        modelChevron.classList.remove('rotate-180');
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
            timerParagraph.innerHTML = '<span id="resetTimer" data-reset-time="' + timeRemaining + '"></span> • <a href="zone_membres/register.php" class="text-green-400 hover:text-green-300 underline">Inscrivez-vous</a> pour un accès illimité';

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
              <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-gift text-amber-400"></i>
              </div>
              <div>
                <p class="text-amber-300 font-medium mb-1">Limite d'essais atteinte</p>
                <p class="text-gray-300 text-sm mb-3">Vous avez utilisé vos ${guestUsageLimit} essais gratuits.</p>
                <p class="text-gray-400 text-sm mb-3">${timeInfo}, ou créez un compte gratuit pour un accès illimité !</p>
                <div class="flex gap-2">
                  <a href="zone_membres/register.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 hover:bg-green-500 text-white text-sm font-medium transition-colors">
                    <i class="fa-solid fa-user-plus"></i>
                    S'inscrire gratuitement
                  </a>
                  <a href="zone_membres/login.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm transition-colors">
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

    // ==== MODALES PERSONNALISÉES ====
    // Modal Confirm personnalisée
    function showConfirmModal(message) {
      return new Promise((resolve) => {
        const modalId = 'confirm-modal-' + Date.now();
        const modalHTML = `
          <div id="${modalId}" class="modal-backdrop fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="modal-content bg-[#1e1e1e] rounded-2xl shadow-2xl border border-gray-700/50 max-w-md w-full">
              <div class="p-6">
                <div class="flex items-start gap-4">
                  <div class="flex-shrink-0">
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
                <button data-action="cancel" class="flex-1 px-4 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 font-medium transition-colors">
                  Annuler
                </button>
                <button data-action="confirm" class="flex-1 px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-medium transition-colors">
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
                <button data-action="cancel" class="flex-1 px-4 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 font-medium transition-colors">
                  Annuler
                </button>
                <button data-action="confirm" class="flex-1 px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-medium transition-colors">
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

                  // Afficher les informations de tokens dans la zone sous la saisie
                  if (data.tokens && (data.tokens.total > 0 || data.tokens.input > 0)) {
                    const tokensDisplay = document.getElementById('tokensDisplay');
                    document.getElementById('tokensInputValue').textContent = data.tokens.input.toLocaleString();
                    document.getElementById('tokensOutputValue').textContent = data.tokens.output.toLocaleString();
                    document.getElementById('tokensTotalValue').textContent = data.tokens.total.toLocaleString();
                    tokensDisplay.classList.remove('hidden');

                    // Sauvegarder les tokens pour la conversation actuelle
                    if (currentConversationId) {
                      const convIndex = conversations.findIndex(c => c.id == currentConversationId);
                      if (convIndex !== -1) {
                        conversations[convIndex].last_tokens = data.tokens;
                        // Mettre à jour le rendu de la sidebar pour afficher les tokens
                        renderConversationList(conversationSearch?.value || '');
                      }
                    }
                  }

                  // === SAUVEGARDER LA RÉPONSE IA ===
                  <?php if (!$isGuest): ?>
                    if (currentConversationId && fullResponse && !responseSaved) {
                      responseSaved = true; // Éviter la double sauvegarde
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
      shouldAutoScroll = true;  // Always auto-scroll on new message
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
    const inputContainer = document.querySelector('.bg-gray-800\\/50.rounded-2xl');

    inputContainer.addEventListener('dragover', function(e) {
      e.preventDefault();
      this.classList.add('border-green-500', 'bg-green-500/10');
    });

    inputContainer.addEventListener('dragleave', function(e) {
      e.preventDefault();
      this.classList.remove('border-green-500', 'bg-green-500/10');
    });

    inputContainer.addEventListener('drop', function(e) {
      e.preventDefault();
      this.classList.remove('border-green-500', 'bg-green-500/10');

      const files = Array.from(e.dataTransfer.files);
      files.forEach(file => addFilePreview(file));
    });

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

      // Trouver le dernier nœud texte non vide
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

    // État de la sidebar
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

            // Mettre à jour les tokens si présents
            const hasTokens = conv.last_tokens && (conv.last_tokens.total > 0);
            const existingTokensBadge = existingEl.querySelector('.conv-badge[title="Tokens utilisés"]');
            if (hasTokens && !existingTokensBadge) {
              // Ajouter le badge tokens
              const metaDiv = existingEl.querySelector('.conv-meta');
              if (metaDiv) {
                const tokenBadge = document.createElement('span');
                tokenBadge.className = 'conv-badge';
                tokenBadge.style.cssText = 'background: rgba(168, 85, 247, 0.1); border-color: rgba(168, 85, 247, 0.2); color: rgb(192, 132, 252);';
                tokenBadge.title = 'Tokens utilisés';
                tokenBadge.innerHTML = `<i class="fa-solid fa-microchip text-[10px]"></i> ${conv.last_tokens.total.toLocaleString()}`;
                metaDiv.appendChild(tokenBadge);
              }
            } else if (hasTokens && existingTokensBadge) {
              // Mettre à jour le badge existant
              existingTokensBadge.innerHTML = `<i class="fa-solid fa-microchip text-[10px]"></i> ${conv.last_tokens.total.toLocaleString()}`;
            }
          }
          return;
        }

        const convEl = document.createElement('div');
        convEl.className = `conversation-item${conv.id == currentConversationId ? ' active' : ''}`;
        convEl.dataset.id = conv.id;

        const date = new Date(conv.updated_at);
        const dateStr = formatRelativeDate(date);
        const msgCount = conv.message_count || 0;
        const hasTokens = conv.last_tokens && (conv.last_tokens.total > 0);

        convEl.innerHTML = `
          <div class="conv-icon">
            <i class="fa-regular fa-message text-sm"></i>
          </div>
          <div class="conv-content">
            <div class="conv-title">${escapeHtml(conv.title || 'Nouvelle conversation')}</div>
            <div class="conv-meta">
              <span class="conv-date">${dateStr}</span>
              ${msgCount > 0 ? `<span class="conv-badge">${msgCount}</span>` : ''}
              ${hasTokens ? `<span class="conv-badge" style="background: rgba(168, 85, 247, 0.1); border-color: rgba(168, 85, 247, 0.2); color: rgb(192, 132, 252);" title="Tokens utilisés"><i class="fa-solid fa-microchip text-[10px]"></i> ${conv.last_tokens.total.toLocaleString()}</span>` : ''}
            </div>
          </div>
          <div class="conv-actions">
            <button class="conv-action-btn" title="Renommer" onclick="event.stopPropagation(); renameConversation(${conv.id})">
              <i class="fa-solid fa-pen text-xs"></i>
            </button>
            <button class="conv-action-btn delete" title="Supprimer" onclick="event.stopPropagation(); deleteConversation(${conv.id})">
              <i class="fa-solid fa-trash text-xs"></i>
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

      if (minutes < 1) return "À l'instant";
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

      // Masquer l'affichage des tokens de la conversation précédente
      tokensDisplay.classList.add('hidden');

      // Réafficher les tokens si la conversation en a
      const conv = conversations.find(c => c.id == id);
      if (conv && conv.last_tokens && conv.last_tokens.total > 0) {
        document.getElementById('tokensInputValue').textContent = conv.last_tokens.input?.toLocaleString() || '0';
        document.getElementById('tokensOutputValue').textContent = conv.last_tokens.output?.toLocaleString() || '0';
        document.getElementById('tokensTotalValue').textContent = conv.last_tokens.total.toLocaleString();
        tokensDisplay.classList.remove('hidden');
      }

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

      // Masquer l'affichage des tokens
      tokensDisplay.classList.add('hidden');

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
    conversationSearch.addEventListener('input', (e) => {
      renderConversationList(e.target.value);
    });

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

    // ===== DARK MODE TOGGLE =====
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeIcon');

    // Check for saved theme preference or default to dark
    const currentTheme = localStorage.getItem('theme') || 'dark';

    // Apply theme on page load
    if (currentTheme === 'light') {
      document.body.setAttribute('data-theme', 'light');
      themeIcon.classList.remove('fa-moon');
      themeIcon.classList.add('fa-sun');
    }

    // Theme toggle handler
    if (themeToggleBtn) {
      themeToggleBtn.addEventListener('click', function() {
        const isDark = document.body.getAttribute('data-theme') === 'dark';

        if (isDark) {
          // Switch to light mode
          document.body.setAttribute('data-theme', 'light');
          themeIcon.classList.remove('fa-moon');
          themeIcon.classList.add('fa-sun');
          localStorage.setItem('theme', 'light');
        } else {
          // Switch to dark mode
          document.body.setAttribute('data-theme', 'dark');
          themeIcon.classList.remove('fa-sun');
          themeIcon.classList.add('fa-moon');
          localStorage.setItem('theme', 'dark');
        }
      });
    }

    // ===== MOBILE BOTTOM SHEETS - GESTION =====

    // État des bottom sheets
    let mobileModelSheetOpen = false;
    let mobileProfileSheetOpen = false;
    let mobileAuthSheetOpen = false;

    // Cache des modèles pour le bottom sheet mobile
    let mobileModelsCache = null;

    // ===== BOTTOM SHEET MODÈLES =====
    function openMobileModelSheet() {
      const sheet = document.getElementById('mobileModelSheet');
      if (!sheet) return;

      mobileModelSheetOpen = true;
      sheet.classList.add('active');
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
      sheet.classList.remove('active');
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
                <img src="${providerIcon}" alt="">
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
    if (mobileModelSearch) {
      mobileModelSearch.addEventListener('input', (e) => {
        filterMobileModels(e.target.value);
      });
    }

    // ===== BOTTOM SHEET PROFIL =====
    function openMobileProfileSheet() {
      const sheet = document.getElementById('mobileProfileSheet');
      if (!sheet) return;

      mobileProfileSheetOpen = true;
      sheet.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeMobileProfileSheet() {
      const sheet = document.getElementById('mobileProfileSheet');
      if (!sheet) return;

      mobileProfileSheetOpen = false;
      sheet.classList.remove('active');
      document.body.style.overflow = '';
    }

    // ===== BOTTOM SHEET AUTH =====
    function openMobileAuthSheet() {
      const sheet = document.getElementById('mobileAuthSheet');
      if (!sheet) return;

      mobileAuthSheetOpen = true;
      sheet.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeMobileAuthSheet() {
      const sheet = document.getElementById('mobileAuthSheet');
      if (!sheet) return;

      mobileAuthSheetOpen = false;
      sheet.classList.remove('active');
      document.body.style.overflow = '';
    }

    // ===== GESTION DES ÉVÉNEMENTS MOBILE =====

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
          <button type="button" id="cookieRejectAll" class="cookie-btn cookie-btn-reject">
            Refuser
          </button>
          <button type="button" id="cookieOpenSettings" class="cookie-btn cookie-btn-settings">
            <i class="fa-solid fa-sliders"></i> Paramètres
          </button>
          <button type="button" id="cookieAcceptAll" class="cookie-btn cookie-btn-accept">
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
              <label class="cookie-toggle">
                <input type="checkbox" checked disabled>
                <span class="cookie-toggle-slider"></span>
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
              <label class="cookie-toggle">
                <input type="checkbox" id="cookieAnalytics">
                <span class="cookie-toggle-slider"></span>
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
              <label class="cookie-toggle">
                <input type="checkbox" id="cookieMarketing">
                <span class="cookie-toggle-slider"></span>
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