<p align="center">
  <img src="assets/images/logo.svg" alt="NxtAIGen" width="180">
</p>

<h1 align="center">NxtAIGen</h1>

<p align="center">
  <strong>Plateforme d'IA conversationnelle multi-fournisseurs</strong><br>
  Une interface unifiée. 12 fournisseurs d'IA. Streaming en temps réel.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.5-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.5">
  <img src="https://img.shields.io/badge/MySQL-9.5-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL 9.5">
  <img src="https://img.shields.io/badge/TailwindCSS-v4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="TailwindCSS v4">
  <img src="https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?style=flat-square&logo=javascript&logoColor=black" alt="Vanilla JS">
  <img src="https://img.shields.io/github/license/scorpion7slayer/NxtAIGen?style=flat-square&color=green" alt="Licence MIT">
</p>

<p align="center">
  <a href="#fonctionnalités">Fonctionnalités</a> &nbsp;&bull;&nbsp;
  <a href="#fournisseurs-intégrés">Fournisseurs</a> &nbsp;&bull;&nbsp;
  <a href="#démarrage-rapide">Démarrage rapide</a> &nbsp;&bull;&nbsp;
  <a href="#abonnements--limites">Abonnements</a> &nbsp;&bull;&nbsp;
  <a href="#structure-du-projet">Structure</a> &nbsp;&bull;&nbsp;
  <a href="#licence">Licence</a>
</p>

---

## Fonctionnalités

| | Fonctionnalité | Détails |
|---|---|---|
| **Multi-IA** | 12 fournisseurs d'IA | OpenAI, Anthropic, Gemini, DeepSeek, Mistral, xAI, Perplexity, OpenRouter, Hugging Face, Moonshot, GitHub Copilot, Ollama |
| **Streaming** | Temps réel | Server-Sent Events, affichage token par token |
| **Multi-format** | Entrée riche | Texte, images, PDF, DOCX (jusqu'à 10 Mo) |
| **Accès invité** | Sans compte | 5 messages par période de 24h |
| **Abonnements** | Plans flexibles | Gratuit, Basic, Premium, Ultra via Stripe |
| **Sécurité** | Clés API chiffrées | AES-256-CBC, jamais stockées en clair |
| **Administration** | Panneau complet | Gestion des fournisseurs, modèles, clés et limites |

---

## Stack technique

| Couche | Technologie |
|:---|:---|
| **Backend** | PHP 8.5, MySQL 9.5 |
| **Frontend** | Vanilla JS, TailwindCSS v4 |
| **Rendu Markdown** | Marked.js 15.0.4 |
| **Coloration syntaxique** | Highlight.js 11.11.1 (thème github-dark) |
| **Icônes** | Font Awesome 7.0.1 |
| **Polices** | TikTok Sans, Noto Color Emoji |

---

## Fournisseurs intégrés

<table>
  <thead>
    <tr>
      <th>Fournisseur</th>
      <th>Format API</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>OpenAI</strong></td>
      <td>OpenAI</td>
    </tr>
    <tr>
      <td><strong>Anthropic</strong></td>
      <td>Custom (images avant texte)</td>
    </tr>
    <tr>
      <td><strong>Google Gemini</strong></td>
      <td>Custom (inlineData)</td>
    </tr>
    <tr>
      <td><strong>DeepSeek</strong></td>
      <td>Compatible OpenAI</td>
    </tr>
    <tr>
      <td><strong>Mistral</strong></td>
      <td>Compatible OpenAI</td>
    </tr>
    <tr>
      <td><strong>xAI (Grok)</strong></td>
      <td>Compatible OpenAI</td>
    </tr>
    <tr>
      <td><strong>Perplexity</strong></td>
      <td>Compatible OpenAI</td>
    </tr>
    <tr>
      <td><strong>OpenRouter</strong></td>
      <td>Compatible OpenAI</td>
    </tr>
    <tr>
      <td><strong>Hugging Face</strong></td>
      <td>Compatible OpenAI</td>
    </tr>
    <tr>
      <td><strong>Moonshot (Kimi)</strong></td>
      <td>Compatible OpenAI</td>
    </tr>
    <tr>
      <td><strong>GitHub Copilot</strong></td>
      <td>OpenAI + OAuth</td>
    </tr>
    <tr>
      <td><strong>Ollama</strong></td>
      <td>Custom (local)</td>
    </tr>
  </tbody>
</table>

---

## Démarrage rapide

### Prérequis

- PHP 8.5+
- MySQL 9.5+
- Node.js (pour TailwindCSS)
- Serveur Apache (WAMP, XAMPP, etc.)

### Installation

```bash
# 1. Cloner le dépôt
git clone https://github.com/scorpion7slayer/NxtAIGen.git
cd NxtAIGen

# 2. Installer les dépendances front-end
npm install

# 3. Importer le schéma de base de données
mysql -u root -p < database/nxtgenai.schema.sql

# 4. Configurer les clés API
cp api/config.php.example api/config.php
# Éditez api/config.php avec vos clés de fournisseurs

# 5. Compiler le CSS (mode développement)
npx @tailwindcss/cli -i ./src/input.css -o ./src/output.css --watch
```

### Première configuration

Créez un utilisateur administrateur avec `is_admin = 1` dans la base de données, puis gérez l'ensemble de la plateforme depuis `/admin/settings.php`.

---

## Abonnements & limites

| Plan | Par heure | Par jour | Par mois | Prix |
|:---|:---:|:---:|:---:|---:|
| **Gratuit** | 10 | 30 | 150 | 0 $ |
| **Basic** | 20 | 50 | 1 000 | 5 $/mois |
| **Premium** | 50 | 200 | 5 000 | 15 $/mois |
| **Ultra** | 100 | Illimité | Illimité | 29 $/mois |

> Les paiements sont gérés via **Stripe** (mode test disponible).

---

## Structure du projet

```
NxtAIGen/
├── admin/              # Panneau d'administration
├── api/                # APIs backend
│   ├── github/         # Intégration OAuth GitHub Copilot
│   ├── streamApi.php   # Point d'entrée unique (streaming SSE)
│   ├── helpers.php     # Formatage multi-fournisseurs
│   ├── models.php      # Auto-détection dynamique des modèles
│   ├── rate_limiter.php# Limites d'utilisation
│   └── *Api.php        # Endpoints par fournisseur
├── assets/
│   ├── js/             # Scripts front-end
│   ├── fonts/          # TikTok Sans, Noto Color Emoji
│   └── images/         # Icônes des fournisseurs (SVG)
├── database/           # Schéma SQL et migrations
├── shop/               # Intégration abonnements Stripe
├── zone_membres/       # Authentification utilisateur
├── src/
│   ├── input.css       # Source TailwindCSS
│   └── output.css      # CSS compilé
└── index.php           # Point d'entrée principal
```

---

## Sécurité

- **Chiffrement AES-256-CBC** pour toutes les clés API
- **Protection CSRF** via tokens de session PHP
- **OAuth 2.0** pour l'intégration GitHub
- **En-têtes de sécurité** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`
- **Vérification HMAC-SHA256** des webhooks Stripe
- **Aucune clé en clair** dans la base de données

---

## Licence

Ce projet est distribué sous licence [MIT](LICENSE).
