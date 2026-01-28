<p align="center">
  <img src="assets/images/logo.svg" alt="NxtAIGen" width="200">
</p>

<h1 align="center">NxtAIGen</h1>

<p align="center">
  <strong>Plateforme d'IA conversationnelle multi-fournisseurs</strong><br>
  Une interface. 12 fournisseurs d'IA. Streaming en temps reel.
</p>

<p align="center">
  <a href="#fonctionnalites">Fonctionnalites</a> &middot;
  <a href="#fournisseurs">Fournisseurs</a> &middot;
  <a href="#demarrage-rapide">Demarrage rapide</a> &middot;
  <a href="#licence">Licence</a>
</p>

---

## Fonctionnalites

- **12 fournisseurs d'IA** &mdash; OpenAI, Anthropic, Gemini, DeepSeek, Mistral, xAI, Perplexity, OpenRouter, Hugging Face, Moonshot, GitHub Copilot, Ollama
- **Streaming en temps reel** &mdash; Server-Sent Events, token par token
- **Entree multi-format** &mdash; Texte, images, PDF, DOCX (jusqu'a 10 Mo)
- **Acces invite** &mdash; Essayez sans compte (5 messages / 24h)
- **Abonnements** &mdash; Gratuit, Basic, Premium, Ultra via Stripe
- **Cles API chiffrees** &mdash; AES-256-CBC, jamais stockees en clair
- **Panneau d'administration** &mdash; Gestion des fournisseurs, modeles, cles et limites

## Stack technique

| Couche   | Technologie                              |
| -------- | ---------------------------------------- |
| Backend  | PHP 8.5, MySQL 9.5                       |
| Frontend | Vanilla JS, TailwindCSS v4, Marked.js    |
| Icones   | Font Awesome 7.0.1, Highlight.js 11.11.1 |

## Fournisseurs

| Fournisseur     | Format             |
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

## Demarrage rapide

```bash
# Cloner le depot
git clone https://github.com/scorpion7slayer/NxtAIGen.git
cd NxtAIGen

# Installer les dependances
npm install

# Importer la base de donnees
mysql -u root -p < database/nxtgenai.schema.sql

# Configurer les cles API
cp api/config.php.example api/config.php
# Editez api/config.php avec vos cles de fournisseurs

# Compiler le CSS (dev)
npx @tailwindcss/cli -i ./src/input.css -o ./src/output.css --watch
```

Creez ensuite un utilisateur admin avec `is_admin = 1` dans la base de donnees, puis gerez tout depuis `/admin/settings.php`.

## Abonnements

| Plan    | Par heure | Par jour  | Par mois  | Prix    |
| ------- | --------- | --------- | --------- | ------- |
| Gratuit | 10        | 30        | 150       | 0$      |
| Basic   | 20        | 50        | 1 000     | 5$/mois |
| Premium | 50        | 200       | 5 000     | 15$/mois|
| Ultra   | 100       | Illimite  | Illimite  | 29$/mois|

## Licence

Ce projet est sous licence [MIT](LICENSE).
