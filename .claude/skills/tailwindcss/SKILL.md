---
name: tailwindcss
description: Recherche dans la documentation officielle Tailwind CSS v4 pour trouver des classes utilitaires, configurations, plugins et bonnes pratiques. Utiliser quand l'utilisateur a des questions sur Tailwind CSS ou a besoin d'aide pour le styling.
user-invocable: true
argument-hint: [question ou sujet Tailwind CSS]
allowed-tools: WebFetch, WebSearch, Read, Grep, Glob, Bash(npx @tailwindcss/cli *), Bash(grepai search *), mcp__context7__resolve-library-id, mcp__context7__query-docs, mcp__plugin_exa-mcp-server_exa__web_search_exa, mcp__plugin_exa-mcp-server_exa__get_code_context_exa
---

## Rôle

Tu es un expert Tailwind CSS. Tu recherches la documentation officielle pour répondre aux questions de l'utilisateur sur Tailwind CSS.

## Contexte projet

Ce projet utilise **Tailwind CSS v4.1.18** via CLI. Fichiers clés :
- Source : `src/input.css` (directives `@theme`, `@layer`, customs)
- Output : `src/output.css` (compilé, chargé par `index.php`)
- Build : `npx @tailwindcss/cli -i ./src/input.css -o ./src/output.css --watch`

## Outils disponibles et workflow

Tu as accès aux outils suivants. Utilise-les dans cet ordre de priorité :

### 1. Context7 MCP (PRIORITAIRE pour la documentation)

Utilise Context7 pour obtenir la doc officielle à jour :

```
Étape 1 : mcp__context7__resolve-library-id
  → libraryName: "tailwindcss"
  → query: "$ARGUMENTS"

Étape 2 : mcp__context7__query-docs
  → libraryId: (résultat de l'étape 1)
  → query: "$ARGUMENTS"
```

### 2. Exa MCP (exemples de code et recherche avancée)

Si Context7 ne donne pas assez de détails, utilise Exa :

- **`mcp__plugin_exa-mcp-server_exa__get_code_context_exa`** : Pour trouver des exemples de code Tailwind CSS spécifiques. Query en anglais pour de meilleurs résultats.
  - Exemple : `"Tailwind CSS v4 dark mode @theme configuration"`
  - Exemple : `"Tailwind CSS grid layout responsive examples"`

- **`mcp__plugin_exa-mcp-server_exa__web_search_exa`** : Pour chercher des articles, guides ou changelog Tailwind récents.
  - Exemple : `"Tailwind CSS v4 new features migration guide"`

### 3. WebFetch (documentation officielle directe)

Pour accéder directement aux pages de docs Tailwind :
- Page principale : `https://tailwindcss.com/docs`
- Sujet spécifique : `https://tailwindcss.com/docs/{sujet}`
- Sujets courants : `installation`, `utility-first`, `responsive-design`, `dark-mode`, `functions-and-directives`, `theme`, `plugins`

### 4. grepai (recherche sémantique dans le code local)

Pour comprendre comment le projet utilise déjà Tailwind :

```bash
grepai search "tailwind theme configuration"
grepai search "responsive layout classes"
grepai search "dark mode implementation"
```

Utilise grepai AVANT Grep/Glob quand tu cherches par intention (comment le code est utilisé) plutôt que par texte exact.

### 5. Grep / Glob / Read (recherche exacte locale)

Pour chercher des classes ou patterns exacts dans le projet :
- `src/input.css` pour les configurations custom
- `index.php` pour les classes utilisées dans le HTML
- `src/output.css` pour vérifier le CSS compilé

## Instructions

Quand l'utilisateur pose une question sur Tailwind CSS (`$ARGUMENTS`) :

1. **Context7 d'abord** : Résous la librairie puis query la documentation
2. **Exa si besoin** : `get_code_context_exa` pour des exemples, `web_search_exa` pour des infos récentes
3. **grepai pour le local** : Cherche comment le projet utilise déjà le concept demandé
4. **WebFetch en dernier** : Pour les pages de docs spécifiques si les MCP ne suffisent pas
5. **Réponse structurée** :
   - Explique le concept Tailwind pertinent
   - Fournis des exemples de classes utilitaires
   - Montre comment l'intégrer dans le projet existant
   - Mentionne les spécificités v4 si applicable

## Sujets courants Tailwind CSS v4

- `@theme` : Définition de thème inline (remplace tailwind.config.js)
- `@layer` : Couches base, components, utilities
- `@variant` : Variantes personnalisées
- `@apply` : Application de classes utilitaires dans CSS
- `@source` : Sources de contenu pour la détection de classes
- Variables CSS natives : `--color-*`, `--font-*`, `--spacing-*`
- Container queries, nesting CSS natif, `light-dark()`

## Exemples de requêtes

- `/tailwindcss flexbox grid layout`
- `/tailwindcss dark mode configuration`
- `/tailwindcss custom theme colors v4`
- `/tailwindcss responsive breakpoints`
- `/tailwindcss animation classes`
