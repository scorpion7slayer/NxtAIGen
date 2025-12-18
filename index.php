<?php
session_start();
require_once 'zone_membres/db.php';

// Récupérer les informations utilisateur si connecté
$user = null;
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
  <script src="assets/js/models.js" defer></script>
  <title>NxtGenAI</title>
  <style>
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

    * {
      font-family: 'TikTok Sans', system-ui, sans-serif;
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

    .ai-message em {
      font-style: italic;
    }

    /* Curseur de streaming : utiliser un <span class="streaming-cursor"> injecté dans le DOM */
    .streaming-cursor {
      display: inline-block;
      animation: blink 1s step-end infinite;
      color: #10b981;
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
  </style>
</head>

<body
  class="min-h-screen text-gray-100 flex items-center justify-center p-4 flex-col">

  <!-- Logo NxtGenAI -->
  <div id="logoContainer" class="mb-8">
    <img src="assets/images/logo.svg" alt="NxtGenAI Logo" class="h-20 w-auto" id="siteLogo">
  </div>

  <!-- Zone de conversation -->
  <div id="chatContainer" class="w-full max-w-2xl mb-6 max-h-[60vh] overflow-y-auto space-y-4 hidden">
  </div>

  <!-- Message d'accueil -->
  <div id="welcomeMessage" class="justify-center mb-6 text-center max-w-2xl text-gray-400/90">
    Posez-moi une question pour commencer.
  </div>
  <div class="w-full max-w-2xl">
    <!-- Zone de saisie -->
    <div
      class="bg-gray-800/50 rounded-2xl p-4 border border-gray-700/50 mb-50">
      <!-- Zone de prévisualisation des fichiers -->
      <div id="filePreviewContainer" class="hidden mb-3 flex flex-wrap gap-2">
      </div>
      <input
        type="text"
        id="messageInput"
        placeholder="Posez une question. Tapez @ pour mentions et / pour raccourcis."
        class="w-full bg-transparent text-gray-300 placeholder:text-gray-500 outline-none text-base mb-4" />

      <!-- Barre d'outils -->
      <div class="flex items-center justify-between">
        <!-- Icônes gauche -->
        <div class="flex items-center gap-2">
          <!-- Bouton ampoule -->
          <button
            class="p-2 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-700/50 transition-colors cursor-pointer">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-5 h-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
          </button>
        </div>

        <!-- Icônes droite -->
        <div class="flex items-center gap-2">
          <!-- Sélecteur de modèle -->
          <div class="relative">
            <button
              id="modelSelectorBtn"
              class="inline-flex items-center justify-center gap-x-2 rounded-lg bg-gray-700/50 border border-gray-600/50 px-3 py-2 text-sm text-gray-300 hover:bg-gray-600/50 hover:text-white transition-colors cursor-pointer">
              <img id="modelIcon" src="assets/images/providers/openai.svg" alt="Provider" class="w-4 h-4 rounded">
              <span id="modelName">GPT-4o Mini</span>
              <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-gray-400 transition-transform duration-200" id="modelChevron">
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
            class="p-2 rounded-lg text-gray-600 cursor-not-allowed" title="recherche web non disponible actuellement">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-5 h-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
            </svg>
          </button>

          <!-- Trombone -->
          <button
            id="attachButton"
            class="p-2 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-700/50 transition-colors cursor-pointer"
            title="Joindre un fichier">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-5 h-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
            </svg>
          </button>
          <!-- Input file caché -->
          <input
            type="file"
            id="fileInput"
            class="hidden"
            accept="image/*,.pdf,.doc,.docx,.txt,.csv,.json,.xml,.md"
            multiple />
          <!-- Micro -->
          <button
            id="micButton"
            class="p-2 rounded-lg text-gray-500 hover:text-gray-300 hover:bg-gray-700/50 transition-colors cursor-pointer"
            title="Cliquer pour dicter">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-5 h-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
            </svg>
          </button>
          <!-- Bouton envoyer -->
          <button
            id="sendButton"
            disabled
            class="p-2 rounded-lg bg-gray-700 text-gray-400 transition-all duration-300 ease-in-out ml-1 cursor-not-allowed disabled:opacity-50">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-5 h-5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
  <div>
    <!-- profil -->
    <div class="absolute bottom-2 left-2">
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
        <div
          id="profileButton"
          class="flex items-center gap-2 rounded-full border border-gray-700/50 px-3 py-2 hover:bg-gray-700/50 cursor-pointer transition-colors">
          <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white font-medium">
            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
          </div>
          <i
            id="profileIcon"
            class="fa-solid fa-angle-up text-gray-400 transition-transform duration-300 ease-in-out rotate-180"></i>
        </div>
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
      <?php endif; ?>
    </div>
  </div>
  <script>
    // État du modèle sélectionné (global pour models.js)
    let selectedModel = {
      provider: 'openai',
      model: 'gpt-4o-mini',
      display: 'GPT-4o Mini'
    };

    const messageInput = document.getElementById("messageInput");
    const sendButton = document.getElementById("sendButton");

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
        if (streamingEl.textContent) {
          streamingEl.innerHTML = renderMarkdown(streamingEl.textContent) + '<span class="text-gray-500 italic text-xs ml-2">(annulé)</span>';
          streamingEl.querySelectorAll('pre code').forEach((block) => {
            if (!block.dataset.highlighted) {
              hljs.highlightElement(block);
              block.dataset.highlighted = 'true';
            }
          });
        }
      }
    }

    // Fonction pour envoyer le message
    async function sendMessage() {
      const message = messageInput.value.trim();
      const hasFiles = attachedFiles.length > 0;

      if (!message && !hasFiles) return;

      // Masquer le message de bienvenue, le logo et afficher le chat
      document.getElementById('welcomeMessage').classList.add('hidden');
      document.getElementById('logoContainer').classList.add('hidden');
      const chatContainer = document.getElementById('chatContainer');
      chatContainer.classList.remove('hidden');

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
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
              <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
              <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
            </div>
          </div>
        </div>
      `;
      chatContainer.scrollTop = chatContainer.scrollHeight;

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

        // Supprimer l'indicateur de chargement
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
          chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Utiliser fetch avec streaming
        const response = await fetch('api/streamApi.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
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
                  break;
                }

                if (data.content) {
                  fullResponse += data.content;
                  // Rendre le markdown en temps réel avec debounce
                  if (renderTimeout) clearTimeout(renderTimeout);
                  renderTimeout = setTimeout(renderStreaming, 50);
                }

                if (data.done) {
                  // Annuler le timeout en attente
                  if (renderTimeout) clearTimeout(renderTimeout);
                  // Réinitialiser le bouton et le curseur
                  setButtonCancelMode(false);
                  currentAbortController = null;
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
        html += `<div class="code-block-wrapper"><div class="code-header"><span>${langDisplay}</span><span class="text-xs text-green-400 animate-pulse">● En cours...</span></div><pre><code class="language-${lang}">${escapeHtml(codeContent)}</code></pre></div>`;

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
      if (mimeType === 'application/pdf') return '📄';
      if (mimeType.includes('word') || filename.endsWith('.doc') || filename.endsWith('.docx')) return '📝';
      if (mimeType === 'text/plain' || filename.endsWith('.txt')) return '📃';
      if (mimeType === 'text/csv' || filename.endsWith('.csv')) return '📊';
      if (mimeType === 'application/json' || filename.endsWith('.json')) return '{ }';
      if (mimeType === 'text/xml' || filename.endsWith('.xml')) return '📋';
      if (filename.endsWith('.md')) return '📖';
      return '📎';
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
      cursor.textContent = '▋';

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
  </script>
</body>

</html>