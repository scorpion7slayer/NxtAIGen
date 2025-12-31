# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Use MCP in this order

1. claude mem mcp
2. context7
3. (only if context7 doesn't have or isn't made for) exa
4. chrome mcp for testing the web app

## my Instructions

- speak French all the time except when you are talking to yourself so that you are more efficient

## Project Overview

NxtGenAI is a multi-provider AI chat application built with vanilla PHP, MySQL, and JavaScript. It provides a unified interface for interacting with multiple AI providers (OpenAI, Anthropic, Google Gemini, DeepSeek, Mistral, Hugging Face, OpenRouter, Perplexity, xAI, Moonshot, GitHub Copilot, and Ollama).

## Development Environment

**WAMP Stack (Windows, Apache, MySQL, PHP)**

- Database: MySQL 9.5.0
- PHP: 8.4.14
- Database name: `nxtgenai`
- Database connection: `localhost:3306` (root user, no password by default)

## Frameworks & Libraries

- Tailwind CSS (via CDN)
- Marked.js (Markdown rendering)
- Highlight.js (Code syntax highlighting)
- TikTok Sans font (custom font)

### Database Setup

1. Import the database schema:

```bash
mysql -u root -p nxtgenai < database/nxtgenai.sql
```

2. The database includes tables for:
    - `users` - User accounts with admin flag
    - `api_keys_global` - Admin-managed API keys (encrypted)
    - `api_keys_user` - User-specific API keys (encrypted)
    - `conversations` - Chat conversation metadata
    - `messages` - Chat message history
    - `models_status` - Provider/model availability status
    - `encryption_config` - AES-256-CBC encryption key storage

### Running the Application

This is a WAMP-based PHP application:

- Start WAMP services (Apache + MySQL)
- Navigate to `http://localhost/NxtAIGen/` in browser
- No build step required - PHP files are executed directly by Apache

## Core Architecture

### Authentication & Session Management

- Session-based authentication using `$_SESSION['user_id']`
- Admin users have `is_admin = 1` in the `users` table
- Database connection established in `zone_membres/db.php`
- All authenticated pages check `$_SESSION['user_id']` at the top

### API Key Management System

**Three-tier configuration hierarchy** (priority order):

1. User-specific keys (`api_keys_user` table) - highest priority
2. Global admin keys (`api_keys_global` table)
3. Hardcoded fallback keys (`api/config.php`) - legacy/development only

**Key Features:**

- AES-256-CBC encryption for all stored API keys
- Encryption key stored in `encryption_config` table
- Helper functions in `api/api_keys_helper.php`:
    - `getApiConfig($pdo, $provider, $userId)` - Get config for a provider
    - `getAllApiConfigs($pdo, $userId)` - Get all provider configs
    - `encryptValue($plaintext, $pdo)` - Encrypt sensitive data
    - `decryptValue($ciphertext, $pdo)` - Decrypt sensitive data

### Directory Structure

```
/
├── index.php                 # Main chat interface
├── zone_membres/             # User authentication & settings
│   ├── db.php               # Database connection
│   ├── login.php            # Login page
│   ├── register.php         # Registration
│   ├── dashboard.php        # User dashboard
│   └── settings.php         # User settings & API keys
├── admin/                    # Admin-only pages
│   ├── models_manager.php   # Provider management & testing
│   └── settings.php         # Global API key configuration
├── api/                      # Backend API handlers
│   ├── streamApi.php        # Universal SSE streaming endpoint
│   ├── models.php           # Model auto-detection API
│   ├── config.php           # Legacy API key storage (being phased out)
│   ├── api_keys_helper.php  # Encryption & config loading
│   ├── helpers.php          # File upload helpers
│   ├── {provider}Api.php    # Individual provider implementations
│   └── github/              # GitHub OAuth integration
├── assets/
│   ├── js/models.js         # Frontend model manager
│   ├── images/providers/    # Provider logos
│   └── fonts/               # TikTok Sans font files
└── database/
    └── nxtgenai.sql         # Database schema
```

### Streaming Architecture

**Universal Streaming System** (`api/streamApi.php`):

- Uses Server-Sent Events (SSE) for real-time streaming
- Single endpoint handles all providers
- Provider-specific formatting in individual `{provider}Api.php` files

**Request Flow:**

1. Frontend sends POST to `api/streamApi.php` with:
    - `message` - User message text
    - `files` - Base64-encoded file attachments
    - `provider` - Target AI provider
    - `model` - Specific model name
2. Backend loads API keys (user → global → fallback)
3. Streams response via SSE events:
    - `message` - Content chunks
    - `done` - Stream completion
    - `error` - Error messages

**Provider Implementation Pattern:**
Each provider API file follows this structure:

```php
// Handle streaming with provider-specific API format
// Transform provider response to SSE events
// Handle errors and connection issues
```

### Model Auto-Detection

**Frontend (`assets/js/models.js`):**

- `ModelManager` object handles model loading
- Caches models for 5 minutes per provider
- Dynamically populates model dropdowns

**Backend (`api/models.php`):**

- Fetches available models from each provider's API
- Provider-specific functions: `getOpenAIModels()`, `getAnthropicModels()`, etc.
- Filters out deprecated/unavailable models
- Returns JSON array of model objects

### File Upload Support

**Helper functions** (`api/helpers.php`):

- `prepareOpenAIMessageContent($userMessage, $files)` - OpenAI-compatible format
- `prepareAnthropicMessageContent($userMessage, $files)` - Anthropic format
- `prepareGeminiMessageContent($userMessage, $files)` - Google format

**Supported file types:**

- Images: JPEG, PNG, GIF, WebP (base64-encoded)
- Text files: TXT, JSON, XML (extracted and included as text)

## Provider-Specific Notes

### Ollama

- Configurable base URL via `OLLAMA_API_URL`
- Default: `http://localhost:11434`
- Model list endpoint: `/api/tags`

### GitHub Copilot

- OAuth-based authentication (no API key)
- Token stored in `users.github_token` column
- OAuth flow in `api/github/` directory

### Anthropic (Claude)

- Requires special headers: `x-api-key` and `anthropic-version`
- Message format uses `content` array with `text` and `image` objects

### Google Gemini

- API key in URL parameter: `?key={GEMINI_API_KEY}`
- Uses `generativelanguage.googleapis.com` domain

## Security Considerations

### API Key Encryption

- Never commit `api/config.php` with real keys
- All keys in database are AES-256-CBC encrypted
- Encryption key rotates automatically if regenerated in DB

### CSRF Protection

- Session tokens verify user actions
- Admin actions require `is_admin = 1` check

### Input Validation

- All user inputs sanitized before database insertion
- PDO prepared statements prevent SQL injection
- File uploads validated by MIME type

## Common Development Tasks

### Adding a New AI Provider

1. Create `api/{provider}Api.php` with streaming handler
2. Add provider config to `api/config.php`:
    ```php
    '{PROVIDER}_API_KEY' => '',
    ```
3. Add to `ModelManager.providers` in `assets/js/models.js`:
    ```javascript
    provider_name: {
        name: "Provider Name",
        icon: "assets/images/providers/provider.svg",
        color: "tailwind-color",
    }
    ```
4. Add model fetching function in `api/models.php`:
    ```php
    function get{Provider}Models($apiKey) {
        // Fetch and return models array
    }
    ```
5. Add to `$streamConfigs` array in `api/streamApi.php`

### Testing API Connections

Admin panel (`admin/models_manager.php`) provides:

- Connection testing for each provider
- Model availability checking
- API key validation
- Bulk enable/disable providers

### Modifying the Chat UI

Main interface in `index.php`:

- Uses Tailwind CSS (CDN via `@tailwindcss/browser@4`)
- Markdown rendering via Marked.js
- Syntax highlighting via Highlight.js
- Custom font: TikTok Sans

## Database Schema Notes

### Encryption Config

- Single encryption key shared across all encrypted values
- Key regeneration requires re-encrypting all existing keys
- Stored as base64-encoded binary data

### Conversation Storage

- Messages linked to conversations via `conversation_id`
- `tokens_used` tracks usage per message
- `model` field stores which model generated the response

### User Permissions

- `is_admin = 1` grants access to `/admin/` pages
- Regular users can only manage their own API keys
- Global keys are admin-only

## Code Style

- PHP: PSR-style formatting, no framework
- JavaScript: ES6+, no build tools
- CSS: Tailwind utility classes, custom `@font-face`
- Database: Prepared statements, no ORM
- Comments: French language (legacy), new code should use English

## Important File Paths

- Database connection: `zone_membres/db.php`
- API key helpers: `api/api_keys_helper.php`
- Main streaming endpoint: `api/streamApi.php`
- Model detection: `api/models.php`
- Frontend model manager: `assets/js/models.js`
- Admin panel: `admin/models_manager.php`
