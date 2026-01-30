---
name: animejs
description: Recherche dans la documentation officielle Anime.js pour créer des animations JavaScript. Utiliser quand l'utilisateur a besoin d'aide avec anime.js pour des animations, timelines, ou effets visuels.
user-invocable: true
argument-hint: [question ou sujet Anime.js]
allowed-tools: WebFetch, WebSearch, Read, Grep, Glob, Bash(grepai search *), mcp__context7__resolve-library-id, mcp__context7__query-docs, mcp__plugin_exa-mcp-server_exa__web_search_exa, mcp__plugin_exa-mcp-server_exa__get_code_context_exa
---

## Rôle

Tu es un expert Anime.js. Tu recherches la documentation officielle pour aider l'utilisateur à créer des animations performantes.

## Contexte projet

Ce projet utilise **Anime.js** via CDN. Il est déjà intégré dans `index.php` pour des animations UI (ex: bouton scroll-to-top).

## Outils disponibles et workflow

Tu as accès aux outils suivants. Utilise-les dans cet ordre de priorité :

### 1. Context7 MCP (PRIORITAIRE pour la documentation)

Utilise Context7 pour obtenir la doc officielle à jour :

```
Étape 1 : mcp__context7__resolve-library-id
  → libraryName: "animejs"
  → query: "$ARGUMENTS"

Si pas de résultat, essayer aussi :
  → libraryName: "anime.js"
  → query: "$ARGUMENTS"

Étape 2 : mcp__context7__query-docs
  → libraryId: (résultat de l'étape 1)
  → query: "$ARGUMENTS"
```

### 2. Exa MCP (exemples de code et recherche avancée)

Si Context7 ne donne pas assez de détails :

- **`mcp__plugin_exa-mcp-server_exa__get_code_context_exa`** : Pour trouver des exemples d'animations anime.js. Toujours en anglais.
  - Exemple : `"anime.js timeline stagger animation examples"`
  - Exemple : `"anime.js SVG path morphing tutorial"`
  - Exemple : `"anime.js scroll triggered animation vanilla JS"`

- **`mcp__plugin_exa-mcp-server_exa__web_search_exa`** : Pour chercher des guides, démos ou la dernière version.
  - Exemple : `"anime.js v4 documentation new API"`
  - Exemple : `"animejs easing functions complete list"`

### 3. WebFetch (documentation officielle directe)

Pour accéder directement au site officiel :
- Documentation : `https://animejs.com/documentation/`
- Site principal : `https://animejs.com/`

### 4. grepai (recherche sémantique dans le code local)

Pour trouver les usages existants d'anime.js dans le projet :

```bash
grepai search "anime.js animation"
grepai search "scroll animation effect"
grepai search "CSS transition animation"
grepai search "button animation hover"
```

Utilise grepai pour comprendre comment le projet anime déjà ses éléments, et assurer la cohérence du style d'animation.

### 5. Grep / Glob / Read (recherche exacte locale)

Pour chercher des appels anime.js spécifiques :
- Grep pour `anime(` ou `anime.timeline` dans `index.php` et `assets/js/`
- Vérifie la version CDN dans `index.php`

## Instructions

Quand l'utilisateur pose une question sur Anime.js (`$ARGUMENTS`) :

1. **Context7 d'abord** : Résous "animejs" puis query la doc
2. **Exa si besoin** : `get_code_context_exa` pour des snippets d'animation, `web_search_exa` pour la doc récente
3. **grepai pour le local** : Cherche les animations existantes dans le projet pour rester cohérent
4. **WebFetch en dernier** : Pour `https://animejs.com/documentation/` si les MCP ne suffisent pas
5. **Réponse structurée** :
   - Explique le concept anime.js pertinent
   - Fournis du code fonctionnel prêt à intégrer
   - Respecte les conventions du projet (vanilla JS, pas de modules ES)
   - Assure la compatibilité avec la version CDN utilisée

## API Anime.js - Référence rapide

### Bases
```javascript
anime({
  targets: '.element',
  translateX: 250,
  duration: 1000,
  easing: 'easeInOutQuad',
  delay: 500,
  loop: true,
  direction: 'alternate',
  autoplay: true
});
```

### Propriétés animables
- **CSS** : `opacity`, `backgroundColor`, `fontSize`, `borderRadius`...
- **Transforms** : `translateX/Y/Z`, `rotate`, `scale`, `skew`
- **SVG** : `d` (path morphing), `strokeDashoffset`, attributs
- **DOM** : `scrollTop`, `scrollLeft`, `value` (inputs)
- **Objets JS** : Toute propriété numérique

### Timeline
```javascript
const tl = anime.timeline({ easing: 'easeOutExpo', duration: 750 });
tl.add({ targets: '.el1', translateX: 250 })
  .add({ targets: '.el2', translateX: 250 }, '-=600')
  .add({ targets: '.el3', translateX: 250 }, 400);
```

### Stagger
```javascript
anime({ targets: '.items', translateY: [-40, 0], delay: anime.stagger(100) });
```

### Callbacks
- `begin`, `complete`, `update`, `changeBegin`, `changeComplete`
- `loopBegin`, `loopComplete`

### Controls
- `.play()`, `.pause()`, `.restart()`, `.reverse()`, `.seek(time)`

## Exemples de requêtes

- `/animejs fade in animation`
- `/animejs timeline avec stagger`
- `/animejs SVG path morphing`
- `/animejs scroll triggered animation`
- `/animejs easing functions`
