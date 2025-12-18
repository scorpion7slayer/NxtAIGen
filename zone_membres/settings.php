<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

require_once 'db.php';
require_once '../api/api_keys_helper.php';

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Vérifier si GitHub est connecté
$githubConnected = !empty($user['github_token']);
$githubUsername = $user['github_username'] ?? null;

// Vérifier si les tables existent
$tablesExist = false;
try {
  $tableCheck = $pdo->query("SHOW TABLES LIKE 'api_keys_user'");
  $tablesExist = $tableCheck->rowCount() > 0;
} catch (PDOException $e) {
  $tablesExist = false;
}

// Charger les clés API personnelles de l'utilisateur
$userApiKeys = [];
$userSettings = [];
if ($tablesExist) {
  try {
    $stmt = $pdo->prepare("SELECT provider, key_name, key_value, is_active FROM api_keys_user WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    while ($row = $stmt->fetch()) {
      if (!isset($userApiKeys[$row['provider']])) {
        $userApiKeys[$row['provider']] = [];
      }
      $userApiKeys[$row['provider']][$row['key_name']] = [
        'value' => decryptValue($row['key_value'], $pdo),
        'active' => $row['is_active']
      ];
    }

    // Charger les settings utilisateur (ex: OLLAMA_API_URL)
    $settingsStmt = $pdo->prepare("SELECT provider, setting_key, setting_value FROM provider_settings WHERE is_global = 0 AND user_id = ?");
    $settingsStmt->execute([$_SESSION['user_id']]);
    while ($row = $settingsStmt->fetch()) {
      if (!isset($userSettings[$row['provider']])) {
        $userSettings[$row['provider']] = [];
      }
      $userSettings[$row['provider']][$row['setting_key']] = $row['setting_value'];
    }
  } catch (PDOException $e) {
    // Ignorer
  }
}

// Liste des providers disponibles
$providers = [
  'ollama' => [
    'key' => 'OLLAMA_API_KEY',
    'label' => 'Ollama',
    'icon' => 'ollama.svg',
    'url' => 'https://ollama.com/settings/keys',
    'extra' => ['OLLAMA_API_URL' => ['label' => 'URL Ollama', 'placeholder' => 'http://localhost:11434', 'required' => true]]
  ],
  'openai' => ['key' => 'OPENAI_API_KEY', 'label' => 'OpenAI', 'icon' => 'openai.svg', 'url' => 'https://platform.openai.com/api-keys'],
  'anthropic' => ['key' => 'ANTHROPIC_API_KEY', 'label' => 'Anthropic (Claude)', 'icon' => 'anthropic.svg', 'url' => 'https://console.anthropic.com/settings/keys'],
  'gemini' => ['key' => 'GEMINI_API_KEY', 'label' => 'Google Gemini', 'icon' => 'gemini.svg', 'url' => 'https://makersuite.google.com/app/apikey'],
  'deepseek' => ['key' => 'DEEPSEEK_API_KEY', 'label' => 'DeepSeek', 'icon' => 'deepseek.svg', 'url' => 'https://platform.deepseek.com/api_keys'],
  'mistral' => ['key' => 'MISTRAL_API_KEY', 'label' => 'Mistral AI', 'icon' => 'mistral.svg', 'url' => 'https://console.mistral.ai/home?workspace_dialog=apiKeys'],
  'openrouter' => ['key' => 'OPENROUTER_API_KEY', 'label' => 'OpenRouter', 'icon' => 'openrouter.svg', 'url' => 'https://openrouter.ai/keys'],
  'perplexity' => ['key' => 'PERPLEXITY_API_KEY', 'label' => 'Perplexity', 'icon' => 'perplexity.svg', 'url' => 'https://www.perplexity.ai/account/api/keys'],
  'xai' => ['key' => 'XAI_API_KEY', 'label' => 'xAI (Grok)', 'icon' => 'xai.svg', 'url' => 'https://console.x.ai/'],
];

// Helper pour masquer une clé
function maskKey($key)
{
  if (empty($key)) return '';
  $len = strlen($key);
  if ($len <= 8) return str_repeat('•', $len);
  return substr($key, 0, 4) . str_repeat('•', min($len - 8, 16)) . substr($key, -4);
}

include 'header.php';
?>
<main class="pt-20 pb-10 min-h-screen px-4">
  <div class="max-w-lg mx-auto">
    <!-- Titre -->
    <div class="mb-8">
      <a href="dashboard.php" class="inline-flex items-center gap-2 text-gray-500 dark:text-neutral-500 hover:text-gray-700 dark:hover:text-neutral-300 transition-colors text-sm mb-4">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Retour au dashboard</span>
      </a>
      <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Mes paramètres</h1>
      <p class="text-sm text-gray-500 dark:text-neutral-500 mt-1">Gérez vos connexions et préférences</p>
    </div>

    <?php if (isset($_GET['oauth_success'])): ?>
      <div class="mb-6 px-4 py-3 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center gap-3">
        <i class="fa-solid fa-check-circle text-green-400"></i>
        <span class="text-sm text-green-400"><?php echo htmlspecialchars($_SESSION['oauth_success'] ?? 'Opération réussie!', ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <?php unset($_SESSION['oauth_success']); ?>
    <?php endif; ?>

    <?php if (isset($_GET['oauth_error'])): ?>
      <div class="mb-6 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center gap-3">
        <i class="fa-solid fa-exclamation-circle text-red-400"></i>
        <span class="text-sm text-red-400"><?php echo htmlspecialchars($_GET['oauth_error'], ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    <?php endif; ?>

    <!-- Section Connexions -->
    <div class="bg-white dark:bg-neutral-800/50 border border-gray-200 dark:border-neutral-700/50 rounded-2xl p-4 mb-6 shadow-sm dark:shadow-none">
      <h2 class="text-sm font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wider mb-4 flex items-center gap-2">
        <i class="fa-solid fa-link"></i>
        Comptes connectés
      </h2>

      <!-- GitHub -->
      <div class="bg-gray-100 dark:bg-neutral-700/30 border border-gray-200 dark:border-neutral-600/30 rounded-xl p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gray-200 dark:bg-neutral-800 flex items-center justify-center">
              <img src="../assets/images/providers/githubcopilot.svg" alt="GitHub" class="w-6 h-6">
            </div>
            <div>
              <p class="text-sm font-medium text-gray-700 dark:text-neutral-200">GitHub</p>
              <?php if ($githubConnected): ?>
                <p class="text-xs text-green-500 dark:text-green-400 flex items-center gap-1">
                  <i class="fa-solid fa-check-circle"></i>
                  Connecté : @<?php echo htmlspecialchars($githubUsername, ENT_QUOTES, 'UTF-8'); ?>
                </p>
              <?php else: ?>
                <p class="text-xs text-gray-500 dark:text-neutral-500">Non connecté</p>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($githubConnected): ?>
            <a href="../api/github/disconnect.php"
              class="px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 rounded-lg text-xs text-red-500 dark:text-red-400 transition-colors"
              onclick="return confirm('Voulez-vous vraiment déconnecter votre compte GitHub ?');">
              <i class="fa-solid fa-unlink mr-1"></i>
              Déconnecter
            </a>
          <?php else: ?>
            <a href="../api/github/connect.php"
              class="px-3 py-1.5 bg-green-500/10 hover:bg-green-500/20 border border-green-500/30 rounded-lg text-xs text-green-600 dark:text-green-400 transition-colors">
              <i class="fa-brands fa-github mr-1"></i>
              Connecter
            </a>
          <?php endif; ?>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-neutral-500">
          Connectez votre compte GitHub pour utiliser l'API GitHub Models avec les modèles GPT-4o, Claude, et plus.
        </p>
      </div>
    </div>

    <!-- Section Clés API -->
    <div class="bg-white dark:bg-neutral-800/50 border border-gray-200 dark:border-neutral-700/50 rounded-2xl p-4 shadow-sm dark:shadow-none">
      <h2 class="text-sm font-medium text-gray-500 dark:text-neutral-400 uppercase tracking-wider mb-4 flex items-center gap-2">
        <i class="fa-solid fa-key"></i>
        Clés API personnelles
      </h2>

      <p class="text-xs text-gray-500 dark:text-neutral-500 mb-4">
        Ajoutez vos propres clés API pour utiliser les différents providers.
        Vos clés personnelles ont priorité sur les clés globales.
      </p>

      <?php if (!$tablesExist): ?>
        <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 mb-4">
          <p class="text-xs text-amber-500 dark:text-amber-400">
            <i class="fa-solid fa-warning mr-1"></i>
            Les tables de base de données n'ont pas été créées. Contactez l'administrateur.
          </p>
        </div>
      <?php endif; ?>

      <div class="space-y-3">
        <?php foreach ($providers as $provKey => $provConfig):
          $hasPersonalKey = isset($userApiKeys[$provKey][$provConfig['key']]['value']) && !empty($userApiKeys[$provKey][$provConfig['key']]['value']);
          $hasPersonalSettings = isset($userSettings[$provKey]) && !empty($userSettings[$provKey]);
          $hasPersonalConfig = $hasPersonalKey || $hasPersonalSettings;
          $maskedKey = $hasPersonalKey ? maskKey($userApiKeys[$provKey][$provConfig['key']]['value']) : '';
        ?>
          <div class="bg-gray-100 dark:bg-neutral-700/20 rounded-xl p-3 border border-gray-200 dark:border-neutral-600/20">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <img src="../assets/images/providers/<?php echo $provConfig['icon']; ?>" alt="<?php echo $provConfig['label']; ?>" class="w-5 h-5">
                <span class="text-sm text-gray-700 dark:text-neutral-200"><?php echo htmlspecialchars($provConfig['label'], ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <a href="<?php echo htmlspecialchars($provConfig['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="text-xs text-blue-500 dark:text-blue-400 hover:text-blue-400 dark:hover:text-blue-300">
                <i class="fa-solid fa-external-link"></i>
              </a>
            </div>

            <div class="flex gap-2">
              <div class="relative flex-1">
                <input
                  type="password"
                  id="api_<?php echo $provKey; ?>"
                  data-provider="<?php echo $provKey; ?>"
                  data-key-name="<?php echo $provConfig['key']; ?>"
                  placeholder="<?php echo $hasPersonalKey ? $maskedKey : 'Entrez votre clé API...'; ?>"
                  class="w-full px-3 py-2 pr-10 text-sm bg-white dark:bg-neutral-800 border border-gray-300 dark:border-neutral-700 rounded-lg text-gray-800 dark:text-neutral-200 placeholder-gray-400 dark:placeholder-neutral-500 focus:outline-none focus:border-green-500/50"
                  <?php echo !$tablesExist ? 'disabled' : ''; ?> />
                <button onclick="toggleApiVisibility('api_<?php echo $provKey; ?>', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-neutral-500 hover:text-gray-600 dark:hover:text-neutral-300 cursor-pointer">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
              <button
                onclick="saveUserApiKey('<?php echo $provKey; ?>')"
                class="px-3 py-2 text-sm bg-green-600 hover:bg-green-500 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                <?php echo !$tablesExist ? 'disabled' : ''; ?>>
                <i class="fa-solid fa-save"></i>
              </button>
              <?php if ($hasPersonalConfig): ?>
                <button
                  onclick="deleteUserApiKey('<?php echo $provKey; ?>')"
                  class="px-3 py-2 text-sm bg-red-500/20 hover:bg-red-500/30 text-red-500 dark:text-red-400 rounded-lg transition-colors cursor-pointer"
                  title="Supprimer ma configuration">
                  <i class="fa-solid fa-trash"></i>
                </button>
              <?php endif; ?>
            </div>

            <?php
            // Afficher les champs extra (ex: OLLAMA_API_URL)
            if (isset($provConfig['extra'])):
              foreach ($provConfig['extra'] as $extraKey => $extraConfig):
                $extraValue = $userSettings[$provKey][$extraKey] ?? '';
            ?>
                <div class="mt-2">
                  <label class="text-xs text-gray-500 dark:text-neutral-400 mb-1 block">
                    <?php echo htmlspecialchars($extraConfig['label'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($extraConfig['required'] ?? false): ?><span class="text-red-400">*</span><?php endif; ?>
                  </label>
                  <input
                    type="text"
                    id="api_<?php echo $provKey; ?>_<?php echo $extraKey; ?>"
                    data-setting-key="<?php echo $extraKey; ?>"
                    placeholder="<?php echo htmlspecialchars($extraConfig['placeholder'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    value="<?php echo htmlspecialchars($extraValue, ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full px-3 py-2 text-sm bg-white dark:bg-neutral-800 border border-gray-300 dark:border-neutral-700 rounded-lg text-gray-800 dark:text-neutral-200 placeholder-gray-400 dark:placeholder-neutral-500 focus:outline-none focus:border-green-500/50"
                    <?php echo !$tablesExist ? 'disabled' : ''; ?> />
                </div>
            <?php
              endforeach;
            endif;
            ?>

            <?php if ($hasPersonalConfig): ?>
              <p class="mt-2 text-xs text-green-500 dark:text-green-400">
                <i class="fa-solid fa-check-circle mr-1"></i>
                <?php if ($hasPersonalKey && $hasPersonalSettings): ?>
                  Clé et URL personnelles configurées
                <?php elseif ($hasPersonalKey): ?>
                  Clé personnelle configurée
                <?php else: ?>
                  URL personnelle configurée
                <?php endif; ?>
              </p>
            <?php else: ?>
              <p class="mt-2 text-xs text-gray-500 dark:text-neutral-500">
                <i class="fa-solid fa-info-circle mr-1"></i>Utilise la configuration globale si disponible
              </p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-4 p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
        <p class="text-xs text-blue-600 dark:text-blue-300">
          <i class="fa-solid fa-lightbulb mr-1"></i>
          <strong>Astuce :</strong> Vos clés personnelles vous permettent d'utiliser vos propres crédits API au lieu de ceux partagés.
        </p>
      </div>
    </div>
  </div>
</main>

<script>
  // Toggle API key visibility
  function toggleApiVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'fa-solid fa-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'fa-solid fa-eye';
    }
  }

  // Save user API key
  async function saveUserApiKey(provider) {
    const input = document.getElementById(`api_${provider}`);
    const keyName = input.dataset.keyName;
    const keyValue = input.value.trim();

    // Collecter les settings supplémentaires (ex: OLLAMA_API_URL)
    const settings = {};
    const extraInputs = document.querySelectorAll(`[id^="api_${provider}_"][data-setting-key]`);
    extraInputs.forEach(extraInput => {
      const settingKey = extraInput.dataset.settingKey;
      if (settingKey && extraInput.value.trim()) {
        settings[settingKey] = extraInput.value.trim();
      }
    });

    // Pour Ollama, l'URL est plus importante que la clé API
    if (provider === 'ollama' && !keyValue && Object.keys(settings).length > 0) {
      // Permettre de sauvegarder uniquement l'URL pour Ollama
    } else if (!keyValue && Object.keys(settings).length === 0) {
      showNotification('Veuillez entrer une clé API ou une URL', 'error');
      return;
    }

    try {
      const response = await fetch('../api/api_keys.php?action=save&scope=user', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          provider: provider,
          key_name: keyName,
          key_value: keyValue,
          settings: settings
        })
      });

      const data = await response.json();

      if (data.success) {
        showNotification('Clé API sauvegardée avec succès', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showNotification(data.error || 'Erreur lors de la sauvegarde', 'error');
      }
    } catch (err) {
      console.error(err);
      showNotification('Erreur de connexion', 'error');
    }
  }

  // Delete user API key
  async function deleteUserApiKey(provider) {
    if (!confirm('Voulez-vous vraiment supprimer cette configuration ?')) {
      return;
    }

    const input = document.getElementById(`api_${provider}`);
    const keyName = input.dataset.keyName;

    try {
      const response = await fetch('../api/api_keys.php?action=delete&scope=user', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          provider: provider,
          key_name: keyName,
          delete_settings: true
        })
      });

      const data = await response.json();

      if (data.success) {
        showNotification('Clé API supprimée', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showNotification(data.error || 'Erreur lors de la suppression', 'error');
      }
    } catch (err) {
      console.error(err);
      showNotification('Erreur de connexion', 'error');
    }
  }

  // Show notification
  function showNotification(message, type = 'info') {
    // Create toast if doesn't exist
    let toast = document.getElementById('settingsToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'settingsToast';
      toast.className = 'fixed bottom-4 right-4 z-50 transform translate-y-20 opacity-0 transition-all duration-300';
      toast.innerHTML = `
        <div class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-neutral-800 border border-gray-200 dark:border-neutral-700 rounded-xl shadow-lg">
          <i id="toastIcon" class="fa-solid fa-info-circle"></i>
          <span id="toastMsg" class="text-sm text-gray-700 dark:text-neutral-200"></span>
        </div>
      `;
      document.body.appendChild(toast);
    }

    const icon = document.getElementById('toastIcon');
    const msg = document.getElementById('toastMsg');

    msg.textContent = message;

    switch (type) {
      case 'success':
        icon.className = 'fa-solid fa-check-circle text-green-400';
        break;
      case 'error':
        icon.className = 'fa-solid fa-times-circle text-red-400';
        break;
      default:
        icon.className = 'fa-solid fa-info-circle text-blue-400';
    }

    toast.classList.remove('translate-y-20', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');

    setTimeout(() => {
      toast.classList.add('translate-y-20', 'opacity-0');
      toast.classList.remove('translate-y-0', 'opacity-100');
    }, 3000);
  }
</script>
</body>

</html>