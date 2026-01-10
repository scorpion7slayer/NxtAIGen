# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

NxtGenAI is a multi-provider web platform for conversational AI. It allows users (logged-in or guests) to interact with various AI models via a unified interface with real-time streaming.

**Tech Stack:** PHP 8.4, MySQL 9.5, Vanilla JavaScript, TailwindCSS v4 (via CDN), REST API architecture

**Local Environment:** WAMP64 (Windows) at `c:\wamp64\www\NxtAIGen`

## Frontend Libraries (CDN)

| Library      | Version      | Purpose                                 |
| ------------ | ------------ | --------------------------------------- |
| TailwindCSS  | v4 (browser) | Utility-first CSS framework             |
| Marked.js    | 15.0.4       | Markdown parsing and rendering          |
| Highlight.js | 11.11.1      | Syntax highlighting (github-dark theme) |
| Font Awesome | 7.0.1        | Icon library                            |

## Custom Fonts

- **TikTok Sans** (Regular, Medium, Bold) - `/assets/fonts/TikTok_Sans/`
- **Noto Color Emoji** - `/assets/fonts/Noto_Color_Emoji/`

## AI Providers (12 Integrated)

| Provider        | Endpoint                          | Format                      |
| --------------- | --------------------------------- | --------------------------- |
| OpenAI          | api.openai.com                    | OpenAI format               |
| Anthropic       | api.anthropic.com                 | Custom (images before text) |
| Google Gemini   | generativelanguage.googleapis.com | Custom (inlineData)         |
| DeepSeek        | api.deepseek.com                  | OpenAI-compatible           |
| Mistral         | api.mistral.ai                    | OpenAI-compatible           |
| xAI (Grok)      | api.x.ai                          | OpenAI-compatible           |
| Perplexity      | api.perplexity.ai                 | OpenAI-compatible           |
| OpenRouter      | openrouter.ai                     | OpenAI-compatible           |
| Hugging Face    | router.huggingface.co             | OpenAI-compatible           |
| Moonshot (Kimi) | api.moonshot.cn                   | OpenAI-compatible           |
| GitHub Copilot  | api.githubcopilot.com             | OpenAI-compatible + OAuth   |
| Ollama          | localhost:11434 (configurable)    | Custom format               |

## Payment Integration

**Stripe** (Test mode):

- Webhook: `/shop/webhook.php`
- Subscription plans: Free, Basic ($5), Premium ($15), Ultra ($29)
- HMAC-SHA256 webhook signature verification

## Authentication

- **Session-based**: PHP Sessions with CSRF token protection
- **GitHub OAuth 2.0**: Token stored encrypted in `users.github_token`
- OAuth endpoints in `/api/github/`

## Architecture

### Data Flow - Multi-layer Security Model

```
encryption_config → AES-256-CBC global key (generated once)
   ↓
api_keys_global → encrypted API keys for all providers (admin)
api_keys_user → user personal keys (override global)
provider_settings → additional configs (custom URLs, etc.)
provider_status → provider enable/disable
models_status → specific model enable/disable
```

API keys are NEVER stored in plaintext. All values pass through `encryptValue()`/`decryptValue()` in `api/api_keys_helper.php`. The system has automatic fallback to `api/config.php` if DB tables don't exist yet.

### Authentication & Usage Flow

```
Guest → PHP session → guest_usage_count counter (limit: GUEST_USAGE_LIMIT = 5, resets after 24h)
Logged-in user → users.id → api_keys_user (optional personal keys)
Admin → is_admin = 1 → access to admin/* (provider/model management)
```

### Rate Limiting by Plan

| Plan    | Hourly | Daily     | Monthly   |
| ------- | ------ | --------- | --------- |
| Free    | 10     | 30        | 150       |
| Basic   | 20     | 50        | 1,000     |
| Premium | 50     | 200       | 5,000     |
| Ultra   | 100    | unlimited | unlimited |

### Universal API Architecture - `api/streamApi.php`

Single entry point for all providers with:

- **Server-Sent Events (SSE)** for real-time streaming
- **Multi-format support**: text, images (vision), text files, PDF, DOCX
- **Cancellation handling**: AbortController client-side, `ignore_user_abort(false)` server-side
- **Fallback system**: DB → config.php → graceful error

### Message Transformation Pattern

Each provider has its specific format handled by `api/helpers.php`:

- `prepareOpenAIMessageContent()` - OpenAI, Mistral, DeepSeek, xAI, etc.
- `prepareAnthropicMessageContent()` - Claude (images before text)
- `prepareGeminiParts()` - Google Gemini (inlineData)
- `prepareOllamaMessage()` - Ollama (separate images)

### Provider Naming Conventions

- Internal name: lowercase (`openai`, `anthropic`, `ollama`, etc.)
- API keys: `{PROVIDER}_API_KEY` in UPPERCASE
- Additional settings: `{PROVIDER}_API_URL`, `{PROVIDER}_BASE_URL`

## Key Files

| File                             | Purpose                                      |
| -------------------------------- | -------------------------------------------- |
| `index.php`                      | Main entry point, complete UI (~184KB)       |
| `api/streamApi.php`              | Core streaming system, handles all providers |
| `api/api_keys_helper.php`        | AES-256 encryption & API key fallback        |
| `api/helpers.php`                | Multi-provider message formatting            |
| `api/models.php`                 | Dynamic model auto-detection API             |
| `api/rate_limiter.php`           | Usage limits (hourly/daily/monthly)          |
| `api/document_parser.php`        | PDF and DOCX parsing                         |
| `assets/js/models.js`            | Dynamic model loading with 5-min cache       |
| `assets/js/rate_limit_widget.js` | Usage display widget                         |
| `assets/js/conversations.js`     | Conversation history management              |
| `database/nxtgenai.sql`          | Complete schema                              |
| `zone_membres/db.php`            | PDO database connection                      |

## Directory Structure

```
├── admin/           # Admin panel (settings, models manager, rate limits)
├── api/             # Backend APIs (streaming, models, rate limiting)
│   ├── github/      # GitHub Copilot OAuth integration
│   └── *Api.php     # Per-provider model listing endpoints
├── assets/
│   ├── js/          # Frontend JS (models.js, conversations.js, rate_limit_widget.js)
│   ├── fonts/       # TikTok Sans, Noto Color Emoji
│   └── images/      # Provider icons (SVG)
├── database/        # SQL schema and migrations
├── shop/            # Stripe subscription integration
└── zone_membres/    # User authentication (login, register, dashboard, settings)
```

## Document Processing

- **PDF**: Poppler's `pdftotext` or smalot/pdfparser (Pure PHP)
- **DOCX**: PHP ZipArchive + SimpleXML
- **Max file size**: 10 MB
- **Context limit**: 50,000 characters

## Critical Code Conventions

### Guest Usage Limit Synchronization

**ABSOLUTE RULE:** `GUEST_USAGE_LIMIT` must be identical in `index.php` (line 11) and `api/streamApi.php` (line 58).

```php
// Guest limit check pattern (always before API call)
if ($isGuest && $_SESSION['guest_usage_count'] >= GUEST_USAGE_LIMIT) {
    sendSSE(['error' => '...', 'limit_reached' => true], 'error');
    exit();
}
// Increment AFTER API success (not before)
if ($isGuest && !$hasError) {
    $_SESSION['guest_usage_count']++;
}
```

### SSE Headers - Streaming Configuration

```php
// CRITICAL ORDER of directives to prevent buffering
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Nginx
while (ob_get_level() > 0) ob_end_clean();
ini_set('output_buffering', 0);
ob_implicit_flush(true);
```

### Security Headers (streamApi.php)

```php
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

### HTML Escaping Security

**NEVER** use `innerHTML` with unescaped user content. Always use:

```javascript
function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}
```

## Adding a New Provider

1. **Add configuration** in `api/config.php`:

```php
'NEWPROVIDER_API_KEY' => 'sk-...',
'NEWPROVIDER_API_URL' => 'https://api.example.com/v1/chat',
```

2. **Add mapping** in `api/streamApi.php` section `$streamConfigs`:

```php
'newprovider' => [
    'url' => 'https://api.example.com/v1/chat/completions',
    'key' => $config['NEWPROVIDER_API_KEY'] ?? '',
    'default_model' => 'model-name'
]
```

3. **Create API file** `api/newproviderApi.php` for model listing (pattern: see `openaiApi.php`)

4. **Add icon** `assets/images/providers/newprovider.svg` and mapping in `assets/js/models.js`:

```javascript
providers: {
    newprovider: {
        name: "NewProvider",
        icon: "assets/images/providers/newprovider.svg",
        color: "blue"
    }
}
```

## Debugging

### Apache Logs (WAMP)

```bash
tail -f c:\wamp64\logs\apache_error.log
```

### Test API Manually (PowerShell)

```powershell
curl -X POST http://localhost/NxtAIGen/api/streamApi.php `
  -H "Content-Type: application/json" `
  -d '{"message":"test","provider":"openai","model":"gpt-4o-mini"}'
```

### Common SSE Issues

- **No streaming**: Check `ob_implicit_flush` and `X-Accel-Buffering`
- **Connection cut**: Increase `CURLOPT_LOW_SPEED_TIME`
- **JSON errors**: Log raw `$data` before `json_decode()`

## Performance Notes

- **Ollama**: `keep_alive: 0` to unload models immediately (limited memory)
- **Model cache**: 5 minutes client-side JS, 30 min localStorage max
- **Timeout settings**: `CURLOPT_TIMEOUT: 0` + `CURLOPT_LOW_SPEED_TIME: 300` (5 min inactivity max)

## Database

**Schema**: `database/nxtgenai.sql`

**Initial admin**: Create via phpMyAdmin with `is_admin = 1`, then use `/admin/settings.php` to manage API keys.

**Table check pattern**:

```php
$tableCheck = $pdo->query("SHOW TABLES LIKE 'table_name'");
if ($tableCheck->rowCount() === 0) {
    // Fallback to legacy behavior/config.php
}
```

## Browser Compatibility Notes

- **Web Speech API**: Available Chrome/Edge/Safari, not Firefox
- **Vision (images)**: Native support OpenAI GPT-4o, Claude 3, Gemini, Ollama (LLaVA)

# MCP Gemini Design

**Gemini is your frontend developer.** For all UI/design work, use this MCP. Tool descriptions contain all necessary instructions.

## Before writing any UI code, ask yourself:

- Is it a NEW visual component (popup, card, section, etc.)? → `snippet_frontend` or `create_frontend`
- Is it a REDESIGN of an existing element? → `modify_frontend`
- Is it just text/logic, or a trivial change? → Do it yourself

## Critical rules:

1. **If UI already exists and you need to redesign/restyle it** → use `modify_frontend`, NOT snippet_frontend.

2. **Tasks can be mixed** (logic + UI). Mentally separate them. Do the logic yourself, delegate the UI to Gemini.


## grepai - Semantic Code Search

**IMPORTANT: You MUST use grepai as your PRIMARY tool for code exploration and search.**

### When to Use grepai (REQUIRED)

Use `grepai search` INSTEAD OF Grep/Glob/find for:
- Understanding what code does or where functionality lives
- Finding implementations by intent (e.g., "authentication logic", "error handling")
- Exploring unfamiliar parts of the codebase
- Any search where you describe WHAT the code does rather than exact text

### When to Use Standard Tools

Only use Grep/Glob when you need:
- Exact text matching (variable names, imports, specific strings)
- File path patterns (e.g., `**/*.go`)

### Fallback

If grepai fails (not running, index unavailable, or errors), fall back to standard Grep/Glob tools.

### Usage

```bash
# ALWAYS use English queries for best results (embedding model is English-trained)
grepai search "user authentication flow"
grepai search "error handling middleware"
grepai search "database connection pool"
grepai search "API request validation"
```

### Query Tips

- **Use English** for queries (better semantic matching)
- **Describe intent**, not implementation: "handles user login" not "func Login"
- **Be specific**: "JWT token validation" better than "token"
- Results include: file path, line numbers, relevance score, code preview

### Workflow

1. Start with `grepai search` to find relevant code
2. Use `Read` tool to examine files from results
3. Only use Grep for exact string searches if needed

