# NxtAIGen

Plateforme web multi-fournisseurs pour l'IA conversationnelle. Interagissez avec plus de 12 modeles d'IA via une interface unifiee avec streaming en temps reel.

## Fonctionnalites

- **12 fournisseurs d'IA** -- OpenAI, Anthropic (Claude), Google Gemini, DeepSeek, Mistral, xAI (Grok), Perplexity, OpenRouter, Hugging Face, Moonshot (Kimi), GitHub Copilot, Ollama (local)
- **Streaming en temps reel** -- Server-Sent Events (SSE) pour des reponses instantanees, token par token
- **Entree multi-format** -- Texte, images (vision), PDF, DOCX (jusqu'a 10 Mo)
- **Acces invite** -- Essayez sans compte (5 messages / 24h)
- **Abonnements** -- Free, Basic (5$), Premium (15$), Ultra (29$) via Stripe
- **Historique des conversations** -- Stockage persistant par utilisateur
- **Panneau d'administration** -- Gestion des fournisseurs, modeles, cles API et limites d'utilisation
- **Cles API chiffrees** -- Chiffrement AES-256-CBC, jamais stockees en clair
- **GitHub OAuth** -- Connexion via GitHub, integration GitHub Copilot

## Stack technique

| Couche           | Technologie                              |
| ---------------- | ---------------------------------------- |
| Backend          | PHP 8.5                                  |
| Base de donnees  | MySQL 9.5                                |
| Frontend         | Vanilla JavaScript, TailwindCSS v4 (CLI) |
| Markdown         | Marked.js 15.0.4                         |
| Coloration       | Highlight.js 11.11.1 (github-dark)       |
| Icones           | Font Awesome 7.0.1                       |

## Fournisseurs d'IA

| Fournisseur     | Endpoint                          | Format             |
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

### Prerequis

- PHP 8.5+
- MySQL 9.5+
- Serveur web Apache ou Nginx (WAMP/LAMP/MAMP)
- Node.js (pour TailwindCSS CLI)
- Composer (optionnel, pour le parsing PDF avec smalot/pdfparser)

### Mise en place

1. **Cloner le depot**

```bash
git clone https://github.com/scorpion7slayer/NxtAIGen.git
cd NxtAIGen
```

2. **Installer TailwindCSS**

```bash
npm install
```

3. **Importer le schema de la base de donnees**

```bash
mysql -u root -p < database/nxtgenai.schema.sql
```

4. **Configurer la connexion a la base de donnees**

Creer et editer le fichier de configuration :

```php
// zone_membres/db.php
$host = 'localhost';
$dbname = 'nxtgenai';
$username = 'root';
$password = '';
```

5. **Configurer les cles API**

Creer `api/config.php` avec vos cles de fournisseurs :

```php
<?php
return [
    'OPENAI_API_KEY' => 'sk-...',
    'ANTHROPIC_API_KEY' => 'sk-ant-...',
    'GEMINI_API_KEY' => 'AIza...',
    // Ajoutez les cles des fournisseurs que vous souhaitez utiliser
];
```

6. **Creer un utilisateur administrateur**

Inserer un utilisateur dans la base de donnees avec `is_admin = 1`, puis utiliser `/admin/settings.php` pour gerer les cles API depuis l'interface.

7. **Compiler le CSS** (developpement)

```bash
npx @tailwindcss/cli -i ./src/input.css -o ./src/output.css --watch
```

8. **Configurer Stripe** (optionnel)

Copier `shop/stripe_config.php.example` vers `shop/stripe_config.php` et renseigner vos cles Stripe.

## Structure du projet

```
NxtAIGen/
├── admin/              # Panneau d'administration (parametres, modeles, limites)
├── api/                # APIs backend
│   ├── streamApi.php   # Point d'entree universel de streaming (tous les fournisseurs)
│   ├── helpers.php     # Formatage des messages multi-fournisseurs
│   ├── models.php      # Detection automatique des modeles
│   ├── rate_limiter.php# Application des limites d'utilisation
│   ├── document_parser.php # Parsing PDF et DOCX
│   ├── github/         # OAuth GitHub Copilot
│   └── *Api.php        # Liste des modeles par fournisseur
├── assets/
│   ├── js/             # Logique frontend
│   ├── fonts/          # TikTok Sans, Noto Color Emoji
│   └── images/         # Logo et icones des fournisseurs (SVG)
├── database/           # Schema SQL
├── shop/               # Integration des abonnements Stripe
├── zone_membres/       # Authentification (connexion, inscription, tableau de bord)
├── src/                # Source TailwindCSS et CSS compile
└── index.php           # Point d'entree principal et interface
```

## Limites d'utilisation

| Plan    | Par heure | Par jour  | Par mois  | Prix   |
| ------- | --------- | --------- | --------- | ------ |
| Free    | 10        | 30        | 150       | 0$     |
| Basic   | 20        | 50        | 1 000     | 5$/mo  |
| Premium | 50        | 200       | 5 000     | 15$/mo |
| Ultra   | 100       | Illimite  | Illimite  | 29$/mo |

## Architecture

### Streaming

Tous les fournisseurs passent par un point d'entree unique (`api/streamApi.php`) via les Server-Sent Events. Le format de message de chaque fournisseur est gere par des fonctions de transformation dediees dans `api/helpers.php`.

### Securite

- Chiffrement AES-256-CBC pour toutes les cles API stockees
- Protection CSRF sur tous les formulaires
- Authentification basee sur les sessions PHP
- Limitation d'utilisation par plan (horaire/journalier/mensuel)
- En-tetes de securite (X-Content-Type-Options, X-Frame-Options, Referrer-Policy)
- Cles API jamais stockees en clair -- chaine de fallback automatique : BDD > config.php > erreur

### Flux de donnees

```
Requete utilisateur
  → streamApi.php (SSE)
    → Dechiffrement de la cle API (AES-256-CBC)
    → Formatage du message pour le fournisseur
    → Stream cURL vers l'API du fournisseur
    → Evenements SSE renvoyes au client
```

## Ajouter un nouveau fournisseur

1. Ajouter la cle API dans `api/config.php`
2. Ajouter la configuration de streaming dans `api/streamApi.php` (tableau `$streamConfigs`)
3. Creer `api/nouveaufournisseurApi.php` pour la liste des modeles
4. Ajouter l'icone du fournisseur dans `assets/images/providers/nouveaufournisseur.svg`
5. Enregistrer le fournisseur dans `assets/js/models.js`

## Licence

Tous droits reserves.
