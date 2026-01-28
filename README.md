# NxtAIGen

A multi-provider web platform for conversational AI. Interact with 12+ AI models through a unified interface with real-time streaming.

## Features

- **12 AI Providers** -- OpenAI, Anthropic (Claude), Google Gemini, DeepSeek, Mistral, xAI (Grok), Perplexity, OpenRouter, Hugging Face, Moonshot (Kimi), GitHub Copilot, Ollama (local)
- **Real-time Streaming** -- Server-Sent Events (SSE) for instant, token-by-token responses
- **Multi-format Input** -- Text, images (vision), PDF, DOCX (up to 10 MB)
- **Guest Access** -- Try without an account (5 messages / 24h)
- **Subscription Plans** -- Free, Basic ($5), Premium ($15), Ultra ($29) via Stripe
- **Conversation History** -- Persistent chat storage per user
- **Admin Panel** -- Manage providers, models, API keys, and rate limits
- **Encrypted API Keys** -- AES-256-CBC encryption, keys never stored in plaintext
- **GitHub OAuth** -- Login with GitHub, GitHub Copilot integration

## Tech Stack

| Layer    | Technology                               |
| -------- | ---------------------------------------- |
| Backend  | PHP 8.5                                  |
| Database | MySQL 9.5                                |
| Frontend | Vanilla JavaScript, TailwindCSS v4 (CLI) |
| Markdown | Marked.js 15.0.4                         |
| Syntax   | Highlight.js 11.11.1 (github-dark)       |
| Icons    | Font Awesome 7.0.1                       |

## AI Providers

| Provider        | Endpoint                          | Format             |
| --------------- | --------------------------------- | ------------------ |
| OpenAI          | api.openai.com                    | OpenAI             |
| Anthropic       | api.anthropic.com                 | Custom             |
| Google Gemini   | generativelanguage.googleapis.com | Custom (inlineData)|
| DeepSeek        | api.deepseek.com                  | OpenAI-compatible  |
| Mistral         | api.mistral.ai                    | OpenAI-compatible  |
| xAI (Grok)      | api.x.ai                          | OpenAI-compatible  |
| Perplexity      | api.perplexity.ai                 | OpenAI-compatible  |
| OpenRouter      | openrouter.ai                     | OpenAI-compatible  |
| Hugging Face    | router.huggingface.co             | OpenAI-compatible  |
| Moonshot (Kimi) | api.moonshot.cn                   | OpenAI-compatible  |
| GitHub Copilot  | api.githubcopilot.com             | OpenAI + OAuth     |
| Ollama          | localhost:11434 (configurable)    | Custom             |

## Installation

### Prerequisites

- PHP 8.5+
- MySQL 9.5+
- Apache or Nginx web server (WAMP/LAMP/MAMP)
- Node.js (for TailwindCSS CLI)
- Composer (optional, for PDF parsing with smalot/pdfparser)

### Setup

1. **Clone the repository**

```bash
git clone https://github.com/scorpion7slayer/NxtAIGen.git
cd NxtAIGen
```

2. **Install TailwindCSS**

```bash
npm install
```

3. **Import the database schema**

```bash
mysql -u root -p < database/nxtgenai.schema.sql
```

4. **Configure database connection**

Copy and edit the database config:

```php
// zone_membres/db.php
$host = 'localhost';
$dbname = 'nxtgenai';
$username = 'root';
$password = '';
```

5. **Configure API keys**

Create `api/config.php` with your provider keys:

```php
<?php
return [
    'OPENAI_API_KEY' => 'sk-...',
    'ANTHROPIC_API_KEY' => 'sk-ant-...',
    'GEMINI_API_KEY' => 'AIza...',
    // Add keys for any providers you want to use
];
```

6. **Create an admin user**

Insert a user in the database with `is_admin = 1`, then use `/admin/settings.php` to manage API keys from the UI.

7. **Build CSS** (development)

```bash
npx @tailwindcss/cli -i ./src/input.css -o ./src/output.css --watch
```

8. **Configure Stripe** (optional)

Copy `shop/stripe_config.php.example` to `shop/stripe_config.php` and fill in your Stripe keys.

## Project Structure

```
NxtAIGen/
├── admin/              # Admin panel (settings, models, rate limits)
├── api/                # Backend APIs
│   ├── streamApi.php   # Universal streaming endpoint (all providers)
│   ├── helpers.php     # Multi-provider message formatting
│   ├── models.php      # Dynamic model auto-detection
│   ├── rate_limiter.php# Usage limit enforcement
│   ├── document_parser.php # PDF & DOCX parsing
│   ├── github/         # GitHub Copilot OAuth
│   └── *Api.php        # Per-provider model listing
├── assets/
│   ├── js/             # Frontend logic
│   ├── fonts/          # TikTok Sans, Noto Color Emoji
│   └── images/         # Logo & provider icons (SVG)
├── database/           # SQL schema
├── shop/               # Stripe subscription integration
├── zone_membres/       # Authentication (login, register, dashboard)
├── src/                # TailwindCSS source & compiled output
└── index.php           # Main entry point & UI
```

## Rate Limits

| Plan    | Hourly | Daily     | Monthly   | Price  |
| ------- | ------ | --------- | --------- | ------ |
| Free    | 10     | 30        | 150       | $0     |
| Basic   | 20     | 50        | 1,000     | $5/mo  |
| Premium | 50     | 200       | 5,000     | $15/mo |
| Ultra   | 100    | Unlimited | Unlimited | $29/mo |

## Architecture

### Streaming

All providers route through a single endpoint (`api/streamApi.php`) using Server-Sent Events. Each provider's message format is handled by dedicated transformation functions in `api/helpers.php`.

### Security

- AES-256-CBC encryption for all stored API keys
- CSRF token protection on all forms
- Session-based authentication
- Rate limiting per user plan (hourly/daily/monthly)
- Security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy)
- API keys never stored in plaintext -- automatic fallback chain: DB > config.php > error

### Data Flow

```
User Request
  → streamApi.php (SSE)
    → Decrypt API key (AES-256-CBC)
    → Format message for provider
    → cURL stream to provider API
    → SSE events back to client
```

## Adding a New Provider

1. Add API key in `api/config.php`
2. Add stream config in `api/streamApi.php` (`$streamConfigs` array)
3. Create `api/newproviderApi.php` for model listing
4. Add provider icon at `assets/images/providers/newprovider.svg`
5. Register provider in `assets/js/models.js`

## License

All rights reserved.
