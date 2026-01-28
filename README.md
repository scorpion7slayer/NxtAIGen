<p align="center">
  <img src="assets/images/logo.svg" alt="NxtAIGen" width="200">
</p>

<h1 align="center">NxtAIGen</h1>

<p align="center">
  <strong>Multi-provider conversational AI platform</strong><br>
  One interface. 12 AI providers. Real-time streaming.
</p>

<p align="center">
  <a href="#features">Features</a> &middot;
  <a href="#providers">Providers</a> &middot;
  <a href="#quick-start">Quick Start</a> &middot;
  <a href="#license">License</a>
</p>

---

## Features

- **12 AI providers** &mdash; OpenAI, Anthropic, Gemini, DeepSeek, Mistral, xAI, Perplexity, OpenRouter, Hugging Face, Moonshot, GitHub Copilot, Ollama
- **Real-time streaming** &mdash; Server-Sent Events, token by token
- **Multi-format input** &mdash; Text, images, PDF, DOCX (up to 10 MB)
- **Guest access** &mdash; Try without an account (5 messages / 24h)
- **Subscription plans** &mdash; Free, Basic, Premium, Ultra via Stripe
- **Encrypted API keys** &mdash; AES-256-CBC, never stored in plaintext
- **Admin panel** &mdash; Manage providers, models, keys and rate limits

## Tech Stack

| Layer    | Technology                               |
| -------- | ---------------------------------------- |
| Backend  | PHP 8.5, MySQL 9.5                       |
| Frontend | Vanilla JS, TailwindCSS v4, Marked.js    |
| Icons    | Font Awesome 7.0.1, Highlight.js 11.11.1 |

## Providers

| Provider        | Format             |
| --------------- | ------------------ |
| OpenAI          | OpenAI             |
| Anthropic       | Custom             |
| Google Gemini   | Custom (inlineData)|
| DeepSeek        | OpenAI-compatible  |
| Mistral         | OpenAI-compatible  |
| xAI (Grok)      | OpenAI-compatible  |
| Perplexity      | OpenAI-compatible  |
| OpenRouter      | OpenAI-compatible  |
| Hugging Face    | OpenAI-compatible  |
| Moonshot (Kimi) | OpenAI-compatible  |
| GitHub Copilot  | OpenAI + OAuth     |
| Ollama          | Custom             |

## Quick Start

```bash
# Clone
git clone https://github.com/scorpion7slayer/NxtAIGen.git
cd NxtAIGen

# Install dependencies
npm install

# Import database
mysql -u root -p < database/nxtgenai.schema.sql

# Configure API keys
cp api/config.php.example api/config.php
# Edit api/config.php with your provider keys

# Build CSS (dev)
npx @tailwindcss/cli -i ./src/input.css -o ./src/output.css --watch
```

Then create an admin user with `is_admin = 1` in the database and manage everything from `/admin/settings.php`.

## Plans

| Plan    | Hourly | Daily     | Monthly   | Price   |
| ------- | ------ | --------- | --------- | ------- |
| Free    | 10     | 30        | 150       | $0      |
| Basic   | 20     | 50        | 1,000     | $5/mo   |
| Premium | 50     | 200       | 5,000     | $15/mo  |
| Ultra   | 100    | Unlimited | Unlimited | $29/mo  |

## License

This project is licensed under the [MIT License](LICENSE).
