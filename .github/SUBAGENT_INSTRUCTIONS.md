# Instructions pour Agents Spécialisés - NxtAIGen

Ce document définit les agents spécialisés disponibles pour travailler sur le projet NxtAIGen. Chaque agent a une expertise spécifique et utilise les outils MCP appropriés.

**Dernière mise à jour** : 4 janvier 2026 à 12h00

---

## 📁 Emplacement des agents

Les profils d'agents sont définis dans **`.github/agents/`** :

```
.github/agents/
├── docs-expert.md          # Agent 1 : Documentation
├── ui-ux-expert.md         # Agent 2 : Interface utilisateur
├── backend-expert.md       # Agent 3 : Backend PHP
├── debug-performance.md    # Agent 4 : Debug & Performance
├── tester.md               # Agent 5 : Tests automatisés
├── code-reviewer.md        # Agent 6 : Revue de code
├── database-expert.md      # Agent 7 : Base de données
├── api-integration.md      # Agent 8 : Intégration providers
├── accessibility-expert.md # Agent 9 : Accessibilité
├── documentation-writer.md # Agent 10 : Rédaction docs
├── mobile-pwa-expert.md    # Agent 11 : Mobile & PWA
└── ai-prompt-engineer.md   # Agent 12 : Prompts IA
```

---

## 📋 Vue d'ensemble des agents

| Agent                    | Fichier                   | Rôle                          | Outils MCP                           | Dépendances  |
| ------------------------ | ------------------------- | ----------------------------- | ------------------------------------ | ------------ |
| **docs-expert**          | `docs-expert.md`          | Documentation & recherche     | Context7, Exa                        | -            |
| **ui-ux-expert**         | `ui-ux-expert.md`         | Interface & UX                | Chrome DevTools                      | docs-expert  |
| **backend-expert**       | `backend-expert.md`       | Architecture & API            | Sequential Thinking                  | docs-expert  |
| **debug-performance**    | `debug-performance.md`    | Débogage & profiling          | Chrome DevTools, Sequential Thinking | -            |
| **tester**               | `tester.md`               | Tests E2E & validation        | Chrome DevTools                      | -            |
| **code-reviewer**        | `code-reviewer.md`        | Revue de code                 | Sequential Thinking                  | -            |
| **database-expert**      | `database-expert.md`      | MySQL, migrations, index      | Context7, Sequential Thinking        | docs-expert  |
| **api-integration**      | `api-integration.md`      | Intégration providers IA      | Context7, Exa                        | docs-expert  |
| **accessibility-expert** | `accessibility-expert.md` | WCAG, ARIA, a11y              | Chrome DevTools                      | ui-ux-expert |
| **documentation-writer** | `documentation-writer.md` | README, API docs, CHANGELOG   | Context7, Exa                        | -            |
| **mobile-pwa-expert**    | `mobile-pwa-expert.md`    | PWA, responsive, mobile       | Chrome DevTools                      | ui-ux-expert |
| **ai-prompt-engineer**   | `ai-prompt-engineer.md`   | Prompts système, optimisation | Sequential Thinking                  | docs-expert  |

---

## 🎯 Agent 1 : docs-expert

### Mission

Rechercher et fournir de la documentation officielle précise, des exemples de code et des patterns recommandés.

### Outils MCP utilisés

- **Context7** (`mcp_io_github_ups_resolve-library-id`, `mcp_io_github_ups_get-library-docs`)
- **Exa** (`mcp_exa_get_code_context_exa`, `mcp_exa_web_search_exa`)

### Quand l'utiliser

- Besoin de documentation officielle d'une librairie
- Recherche de patterns/best practices
- Documentation API des providers IA

### Exemple d'invocation

```
@docs-expert Recherche la documentation TailwindCSS pour les animations
@docs-expert Trouve les best practices PHP 8.4 pour SSE streaming
```

---

## 🎨 Agent 2 : ui-ux-expert

### Mission

Concevoir et implémenter l'interface utilisateur avec focus sur accessibilité, performance et UX.

### Outils MCP utilisés

- **Chrome DevTools** (`mcp_io_github_chr_*`)
- Consulte **docs-expert** pour documentation frameworks

### Dépendances

- Utilise `docs-expert` pour docs TailwindCSS, Bootstrap

### Quand l'utiliser

- Améliorer composants UI
- Implémenter animations
- Corriger problèmes responsive
- Ajouter accessibilité (ARIA, keyboard)

### Exemple d'invocation

```
@ui-ux-expert Améliore l'animation du curseur streaming
@ui-ux-expert Rends le sélecteur de modèles accessible au clavier
```

---

## ⚙️ Agent 3 : backend-expert

### Mission

Architecturer et développer la logique backend (API, streaming SSE, sécurité, sessions).

### Outils MCP utilisés

- **Sequential Thinking** (`mcp_sequentialthi_sequentialthinking`)
- Consulte **docs-expert** pour documentation PHP, cURL, MySQL

### Dépendances

- Utilise `docs-expert` pour patterns PHP, OAuth

### Quand l'utiliser

- Ajouter/modifier APIs
- Implémenter streaming SSE
- Sécuriser clés API
- Intégrer nouveaux providers

### Exemple d'invocation

```
@backend-expert Optimise le streaming SSE pour réduire la latence
@backend-expert Ajoute le support du provider Cohere
```

---

## 🐛 Agent 4 : debug-performance

### Mission

Identifier, diagnostiquer et résoudre bugs et problèmes de performance.

### Outils MCP utilisés

- **Chrome DevTools** (`mcp_io_github_chr_*`)
- **Sequential Thinking** (`mcp_sequentialthi_sequentialthinking`)

### Quand l'utiliser

- Erreurs runtime
- Problèmes de streaming (timeout, coupure)
- Performance lente
- Fuites mémoire

### Exemple d'invocation

```
@debug-performance Le streaming coupe après 30 secondes avec Anthropic
@debug-performance Analyse pourquoi la page est lente au chargement
```

---

## ✅ Agent 5 : tester

### Mission

Tester toutes les fonctionnalités via automation Chrome DevTools.

### Outils MCP utilisés

- **Chrome DevTools** (`mcp_io_github_chr_*`)
- **Sequential Thinking** (planification scénarios)

### Quand l'utiliser

- Tests E2E flux utilisateur
- Tests multi-provider
- Tests régression
- Validation corrections bugs

### Exemple d'invocation

```
@tester Teste le flux complet d'envoi de message avec OpenAI
@tester Vérifie que la limite visiteur fonctionne
```

---

## 🔍 Agent 6 : code-reviewer

### Mission

Réviser le code pour qualité, sécurité, maintenabilité et conventions.

### Outils MCP utilisés

- **Sequential Thinking** (`mcp_sequentialthi_sequentialthinking`)

### Quand l'utiliser

- Avant merge de PR
- Après modifications importantes
- Audit sécurité du code
- Vérification conventions

### Exemple d'invocation

```
@code-reviewer Révise les modifications dans api/streamApi.php
@code-reviewer Analyse la sécurité du nouveau système d'upload
```

---

## 💾 Agent 7 : database-expert

### Mission

Concevoir, migrer et optimiser le schéma MySQL, gérer les performances des requêtes.

### Outils MCP utilisés

- **Context7** (via docs-expert)
- **Sequential Thinking** (`mcp_sequentialthi_sequentialthinking`)

### Quand l'utiliser

- Créer/modifier tables
- Optimiser requêtes lentes
- Créer migrations SQL
- Ajouter index

### Exemple d'invocation

```
@database-expert Optimise les requêtes de la table conversations
@database-expert Crée une migration pour ajouter le rate limiting
```

---

## 🔌 Agent 8 : api-integration

### Mission

Intégrer de nouveaux providers IA dans l'architecture multi-provider de NxtAIGen.

### Outils MCP utilisés

- **Context7** (`mcp_io_github_ups_*`)
- **Exa** (`mcp_exa_*`)

### Dépendances

- Utilise `docs-expert` pour documentation API provider

### Quand l'utiliser

- Ajouter nouveau provider (Cohere, Groq, Together...)
- Adapter format messages
- Configurer streaming spécifique

### Exemple d'invocation

```
@api-integration Intègre le provider Cohere avec streaming
@api-integration Ajoute Together AI comme nouveau provider
```

---

## ♿ Agent 9 : accessibility-expert

### Mission

Garantir l'accessibilité WCAG 2.1 AA, navigation clavier et compatibilité screen readers.

### Outils MCP utilisés

- **Chrome DevTools** (`mcp_io_github_chr_*`)

### Dépendances

- Collabore avec `ui-ux-expert` pour implémentations

### Quand l'utiliser

- Audit accessibilité
- Ajouter attributs ARIA
- Implémenter navigation clavier
- Vérifier contraste couleurs

### Exemple d'invocation

```
@accessibility-expert Audite l'accessibilité de la page principale
@accessibility-expert Rends le chat compatible screen readers
```

---

## 📝 Agent 10 : documentation-writer

### Mission

Créer et maintenir documentation technique, guides utilisateur, API docs et CHANGELOG.

### Outils MCP utilisés

- **Context7** (`mcp_io_github_ups_*`)
- **Exa** (`mcp_exa_*`)

### Quand l'utiliser

- Créer/mettre à jour README
- Documenter API
- Écrire CHANGELOG
- Guides contributeurs

### Exemple d'invocation

```
@documentation-writer Crée le README.md principal
@documentation-writer Documente l'API streamApi.php
```

---

## 📱 Agent 11 : mobile-pwa-expert

### Mission

Optimiser pour mobile, implémenter PWA avec service workers et responsive avancé.

### Outils MCP utilisés

- **Chrome DevTools** (`mcp_io_github_chr_*`)

### Dépendances

- Collabore avec `ui-ux-expert` pour design responsive

### Quand l'utiliser

- Optimiser layout mobile
- Configurer PWA (manifest, service worker)
- Implémenter offline mode
- Gestures tactiles

### Exemple d'invocation

```
@mobile-pwa-expert Configure NxtAIGen comme PWA installable
@mobile-pwa-expert Optimise le chat pour petits écrans
```

---

## 🧠 Agent 12 : ai-prompt-engineer

### Mission

Optimiser les prompts système, améliorer qualité des réponses IA et créer templates de prompts.

### Outils MCP utilisés

- **Sequential Thinking** (`mcp_sequentialthi_sequentialthinking`)

### Dépendances

- Utilise `docs-expert` pour documentation des modèles

### Quand l'utiliser

- Optimiser system prompt par défaut
- Créer prompts spécialisés (code, créatif, technique)
- Améliorer qualité des réponses
- Templates de prompts

### Exemple d'invocation

```
@ai-prompt-engineer Optimise le system prompt par défaut
@ai-prompt-engineer Crée un prompt template pour l'analyse de code
```

---

## 📊 Diagramme des dépendances

```
                    ┌──────────────┐
                    │  docs-expert │
                    │  (Context7,  │
                    │     Exa)     │
                    └──────┬───────┘
                           │
           ┌───────────────┼───────────────┐
           │               │               │
           ▼               ▼               │
    ┌─────────────┐ ┌──────────────┐       │
    │ ui-ux-expert│ │backend-expert│       │
    │  (Chrome    │ │ (Sequential  │       │
    │  DevTools)  │ │  Thinking)   │       │
    └─────────────┘ └──────────────┘       │
                                           │
    ┌─────────────┐ ┌──────────────┐ ┌─────┴────────┐
    │   tester    │ │debug-perform.│ │code-reviewer │
    │  (Chrome    │ │   (Chrome,   │ │ (Sequential  │
    │  DevTools)  │ │  Sequential) │ │  Thinking)   │
    └─────────────┘ └──────────────┘ └──────────────┘
```

---

## 🔄 Workflow recommandé

### Développement nouvelle fonctionnalité

```
1. @docs-expert → Recherche documentation technique
2. @backend-expert ou @ui-ux-expert → Implémentation
3. @tester → Tests automatisés
4. @code-reviewer → Revue avant merge
```

### Correction de bug

```
1. @debug-performance → Diagnostic et identification cause
2. @backend-expert ou @ui-ux-expert → Correction
3. @tester → Test régression
4. @code-reviewer → Validation fix
```

### Intégration nouveau provider IA

```
1. @docs-expert → Documentation API du provider
2. @backend-expert → Implémentation dans streamApi.php
3. @ui-ux-expert → Ajout icône et menu modèles
4. @tester → Tests streaming multi-provider
```

---

## 💡 Idées d'agents supplémentaires

### Agents à implémenter (suggestions futures)

| Agent proposé        | Rôle                               | Outils MCP                    |
| -------------------- | ---------------------------------- | ----------------------------- |
| **security-expert**  | Audits sécurité, chiffrement, CSRF | Context7, Sequential Thinking |
| **i18n-expert**      | Internationalisation multi-langues | Context7                      |
| **devops-expert**    | CI/CD, Docker, déploiement         | Sequential Thinking           |
| **load-tester**      | Tests de charge, stress tests      | Chrome DevTools               |
| **analytics-expert** | Monitoring, métriques usage        | Sequential Thinking           |
| **migration-expert** | Migration vers autres stacks       | Context7, Exa                 |
| **seo-expert**       | Optimisation référencement         | Exa, Context7                 |

---

## 📚 Ressources

- [copilot-instructions.md](./copilot-instructions.md) : Architecture NxtAIGen
- [COPILOT_MCP_INSTRUCTIONS.md](./COPILOT_MCP_INSTRUCTIONS.md) : Sélection outils MCP
- [GitHub Custom Agents Docs](https://docs.github.com/en/copilot/how-tos/use-copilot-agents/coding-agent/create-custom-agents)

---

**Note** : Les agents sont définis dans `.github/agents/` et peuvent être invoqués directement dans les conversations Copilot.

_Dernière mise à jour : 4 janvier 2026_
