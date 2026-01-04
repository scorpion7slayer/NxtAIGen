# NxtGenAI - Instructions pour Agents IA

## Vue d'ensemble du projet

NxtGenAI est une plateforme web multi-provider pour l'IA conversationnelle. Elle permet aux utilisateurs (connectés ou visiteurs) de dialoguer avec différents modèles IA via une interface unifiée avec streaming en temps réel.

**Stack technique :** PHP 8.4, MySQL, Vanilla JS, TailwindCSS (via CDN), architecture API REST

**Environnement local :** WAMP64 (Windows) à `c:\wamp64\www\NxtAIGen`

## 📌 Documentation à consulter en simultané

**⚠️ RÈGLE CRUCIALE** : Ce fichier (`copilot-instructions.md`) doit **TOUJOURS** être utilisé en conjonction avec [`COPILOT_MCP_INSTRUCTIONS.md`](./COPILOT_MCP_INSTRUCTIONS.md) et [`SUBAGENT_INSTRUCTIONS.md`](./SUBAGENT_INSTRUCTIONS.md).

### Répartition des responsabilités :

| Fichier                         | Responsabilité                                                                 |
| ------------------------------- | ------------------------------------------------------------------------------ |
| **copilot-instructions.md**     | Architecture NxtGenAI, patterns code, workflows développeur                    |
| **COPILOT_MCP_INSTRUCTIONS.md** | Sélection des outils MCP (Context7, Exa, Chrome DevTools, Sequential Thinking) |
| **SUBAGENT_INSTRUCTIONS.md**    | Sélection et utilisation des agents spécialisés (Context7-Expert, debug, Plan) |

### Comment utiliser les trois fichiers :

1. **Lire copilot-instructions.md** pour comprendre le contexte du projet et les patterns à suivre
2. **Consulter COPILOT_MCP_INSTRUCTIONS.md** pour choisir les bons outils MCP selon la tâche
3. **Consulter SUBAGENT_INSTRUCTIONS.md** pour déléguer une tâche complexe à un agent spécialisé
4. **Combiner tous les trois** : appliquer les patterns du projet avec les bons outils MCP et agents

## Architecture système

### 1. Structure des données - Modèle de sécurité multi-couches

```
encryption_config → clé AES-256-CBC globale (générée une fois)
   ↓
api_keys_global → clés API chiffrées pour tous les providers (admin)
api_keys_user → clés personnelles utilisateur (override des globales)
provider_settings → configurations additionnelles (URLs personnalisées, etc.)
provider_status → activation/désactivation des providers
models_status → activation/désactivation de modèles spécifiques
```

**Pattern de sécurité :** Les clés API ne sont JAMAIS stockées en clair. Toutes les valeurs passent par `encryptValue()`/`decryptValue()` dans `api/api_keys_helper.php`. Le système a un fallback automatique vers `api/config.php` si les tables DB n'existent pas encore.

### 2. Flux d'authentification & usage

```
Visiteur → session PHP → compteur guest_usage_count (limite: GUEST_USAGE_LIMIT = 5)
Utilisateur connecté → users.id → api_keys_user (clés personnelles optionnelles)
Admin → is_admin = 1 → accès admin/* (gestion providers/modèles)
```

**Particularité GitHub Copilot :** Nécessite OAuth. Le token est stocké dans `users.github_token` et injecté comme Bearer token dans les requêtes API.

### 3. Architecture API universelle - `api/streamApi.php`

**Point d'entrée unique** pour tous les providers avec:

- **Server-Sent Events (SSE)** pour streaming temps réel
- **Multi-format support** : texte, images (vision), fichiers texte
- **Gestion des annulations** : AbortController côté client, détection `ignore_user_abort(false)` côté serveur
- **Système de fallback** : DB → config.php → erreur gracieuse

**Pattern de transformation des messages :**

```php
// Chaque provider a son format spécifique géré par helpers.php
prepareOpenAIMessageContent()    // OpenAI, Mistral, DeepSeek, xAI, etc.
prepareAnthropicMessageContent() // Claude (images avant texte)
prepareGeminiParts()            // Google Gemini (inlineData)
prepareOllamaMessage()          // Ollama (images séparées)
```

**Conventions de nommage des providers :**

- Nom interne : lowercase (`openai`, `anthropic`, `ollama`, etc.)
- Clés API : `{PROVIDER}_API_KEY` en UPPERCASE
- Settings additionnels : `{PROVIDER}_API_URL`, `{PROVIDER}_BASE_URL`

### 4. Chargement dynamique des modèles - `assets/js/models.js` + `api/models.php`

**Pattern d'autodétection :** Chaque provider expose une API de listing de modèles. Le système interroge ces APIs au chargement et construit le menu dynamiquement.

**Gestion du cache :** Cache JavaScript (5 min) + bouton refresh manuel. Réduire les appels API coûteux.

**Déduplication :** Les modèles avec le même ID entre providers sont affichés une seule fois (ex: `gpt-4o-mini` disponible via OpenAI et OpenRouter).

```javascript
// Ordre de priorité d'affichage des providers
providerOrder = [
    "openai",
    "anthropic",
    "gemini",
    "deepseek",
    "mistral",
    "xai",
    "perplexity",
    "openrouter",
    "huggingface",
    "moonshot",
    "github",
    "ollama",
];
```

## Conventions de code critiques

### 1. Gestion des sessions visiteurs

**RÈGLE ABSOLUE :** `GUEST_USAGE_LIMIT` doit être identique dans `index.php` (ligne 11) et `api/streamApi.php` (ligne 22).

```php
// Pattern de vérification limite visiteur (toujours avant l'envoi à l'API)
if ($isGuest && $_SESSION['guest_usage_count'] >= GUEST_USAGE_LIMIT) {
    sendSSE(['error' => '...', 'limit_reached' => true], 'error');
    exit();
}
// Incrémenter APRÈS succès de l'API (pas avant)
if ($isGuest && !$hasError) {
    $_SESSION['guest_usage_count']++;
}
```

### 2. Headers SSE - Configuration streaming

```php
// ORDRE CRITIQUE des directives pour éviter le buffering
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Nginx
if (ob_get_level()) ob_end_clean();
ini_set('output_buffering', 'off');
ob_implicit_flush(true);
```

### 3. Gestion des erreurs API

**Pattern de gestion des erreurs HTTP :**

```php
// Accumuler les données d'erreur dans un buffer séparé
if ($httpCode >= 400) {
    $errorBuffer .= $data;
    return strlen($data); // Continuer à lire
}
// Après curl_exec, parser le buffer d'erreur
if (!empty($errorBuffer)) {
    $errorData = json_decode($errorBuffer, true);
    sendSSE(['error' => $errorData['error']['message'] ?? '...'], 'error');
}
```

### 4. Markdown & coloration syntaxique - Frontend

**Bibliothèques utilisées :**

- `marked.js` 15.0.4 pour parsing Markdown
- `highlight.js` 11.11.1 pour coloration code

**Pattern de rendu streaming :**

````javascript
// renderMarkdownStreaming() gère les blocs ``` incomplets
const codeBlockMarkers = text.match(/```/g) || [];
if (codeBlockMarkers.length % 2 !== 0) {
    // Bloc incomplet → afficher avec indicateur "En cours..."
}
// addCodeHeaders() injecte langage + bouton copier dans chaque <pre><code>
````

### 5. Sécurité - Échappement HTML

**JAMAIS** utiliser `innerHTML` avec du contenu utilisateur non échappé. Toujours passer par :

```javascript
function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}
```

## Workflows développeur

### Test d'un nouveau provider

1. **Ajouter la configuration** dans `api/config.php`:

```php
'NEWPROVIDER_API_KEY' => 'sk-...',
'NEWPROVIDER_API_URL' => 'https://api.example.com/v1/chat',
```

2. **Ajouter le mapping** dans `api/streamApi.php` section `$streamConfigs`:

```php
'newprovider' => [
    'url' => 'https://api.example.com/v1/chat/completions',
    'key' => $config['NEWPROVIDER_API_KEY'] ?? '',
    'default_model' => 'model-name'
]
```

3. **Créer le fichier API** `api/newproviderApi.php` pour le listing des modèles (pattern: voir `openaiApi.php`)

4. **Ajouter l'icône** `assets/images/providers/newprovider.svg` et le mapping dans `models.js`:

```javascript
providers: {
    newprovider: {
        name: "NewProvider",
        icon: "assets/images/providers/newprovider.svg",
        color: "blue"
    }
}
```

### Debug streaming SSE

**Outils essentiels :**

- Console navigateur → onglet Network → filtre sur `streamApi.php`
- Observer les événements SSE en temps réel
- Vérifier les headers de réponse (Content-Type, Cache-Control)

**Problèmes fréquents :**

- **Pas de streaming** : Vérifier `ob_implicit_flush` et `X-Accel-Buffering`
- **Connexion coupée** : Augmenter `CURLOPT_LOW_SPEED_TIME`
- **Erreurs JSON** : Loguer `$data` brut avant `json_decode()`

### Base de données - Migrations

**Schéma SQL complet** : `database/nxtgenai.sql`

**Pattern de vérification table :**

```php
$tableCheck = $pdo->query("SHOW TABLES LIKE 'table_name'");
if ($tableCheck->rowCount() === 0) {
    // Fallback vers comportement ancien/config.php
}
```

**Admin initial :** Créer via phpMyAdmin avec `is_admin = 1`, puis utiliser `/admin/settings.php` pour gérer les clés API.

## Points d'attention spécifiques

### Performance

- **Ollama** : `keep_alive: 0` pour décharger les modèles immédiatement (mémoire limitée)
- **Cache modèles** : 5 minutes côté JS pour réduire les appels API
- **Limite de timeout** : `CURLOPT_TIMEOUT: 0` + `CURLOPT_LOW_SPEED_TIME: 300` (5 min d'inactivité max)

### Sécurité

- **Clés API** : TOUJOURS chiffrées en DB via `api_keys_helper.php`
- **Sessions** : Fichiers PHP (pas de Redis/DB) - attention aux permissions `/tmp`
- **CSRF** : Non implémenté - considérer `X-CSRF-Token` pour les actions admin

### UI/UX

- **Logo animation** : Masquer `#logoContainer` dès le premier message
- **Curseur streaming** : Élément `<span class="streaming-cursor">` injecté dynamiquement, retiré avec `.done`
- **Bouton annulation** : `sendButton` bascule entre "envoyer" et "X" selon `isStreaming`

### Compatibilité navigateurs

- **Web Speech API** : Disponible Chrome/Edge/Safari, pas Firefox
- **Vision (images)** : Support natif OpenAI GPT-4o, Claude 3, Gemini, Ollama (LLaVA)

## Fichiers clés à connaître

- `index.php` : Point d'entrée principal, gestion UI complète (2200+ lignes)
- `api/streamApi.php` : Cœur du système, gère tous les providers (450+ lignes)
- `api/api_keys_helper.php` : Chiffrement & fallback clés API
- `api/helpers.php` : Formatage messages multi-provider
- `assets/js/models.js` : Chargement dynamique modèles
- `database/nxtgenai.sql` : Schéma complet (11 tables)

## Débogage rapide

```bash
# Logs Apache (WAMP)
tail -f c:\wamp64\logs\apache_error.log

# Tester une API manuellement (PowerShell)
curl -X POST http://localhost/NxtAIGen/api/streamApi.php `
  -H "Content-Type: application/json" `
  -d '{"message":"test","provider":"openai","model":"gpt-4o-mini"}'

# Vérifier les variables de session
# Ajouter dans index.php : var_dump($_SESSION);
```

## Prochaines évolutions suggérées

- Historique conversations UI
