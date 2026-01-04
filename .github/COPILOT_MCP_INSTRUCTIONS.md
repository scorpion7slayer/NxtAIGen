# Instructions pour GitHub / Copilot MCP

Ce document fournit des directives concises pour l'utilisation des outils MCP dans ce dépôt (Context7 / Exa, Chrome DevTools, Sequential Thinking). Rédigé pour Copilot/Copilot-coding-agent et revues automatiques.

## Objectif

- Fournir des instructions claires pour choisir et appeler les outils MCP appropriés.

## Règles générales

- Toujours évaluer la nature de la requête et **choisir l'outil MCP approprié** :
    - 📚 Documentation officielle structurée → **Context7**
    - 🔍 Recherche large/temps réel/API frais → **Exa**
    - 🌐 Exécution code côté navigateur/inspection DOM → **Chrome DevTools**
    - 🧠 Raisonnement séquentiel complexe → **Sequential Thinking**
- Respecter la confidentialité des secrets : ne pas inclure de clés API, tokens ou données sensibles dans les appels ou logs.

## Sélection des outils MCP

### Context7 — Documentation officielle ciblée

- **Quand** : besoin d'extraire docs officielles précises d'une librairie (ex: `/vercel/next.js`, `/react/react`)
- **Outils** : `mcp_io_github_ups_resolve-library-id` + `mcp_io_github_ups_get-library-docs`
- **Avantages** : docs structurées, exemples vérifiés, versions précises
- **Exemple** :

```json
{
    "libraryName": "Next.js routing"
}
```

### Exa — Recherche en temps réel & contexte API

- **Quand** : recherche large, contexte d'API frais, exemples multiples sources, web search
- **Outils** : `mcp_exa_get_code_context_exa`, `mcp_exa_web_search_exa`, `mcp_exa_crawling_exa`
- **Avantages** : données fraîches, websearch, multi-source
- **Exemple** :

```json
{
    "query": "Express.js middleware patterns 2026"
}
```

### Chrome DevTools — Interaction navigateur & inspection DOM

- **Quand** : exécuter code JS, inspecter page, récupérer données du DOM, tester UI
- **Outils** : `mcp_io_github_chr_evaluate_script`, `mcp_io_github_chr_click`, `mcp_io_github_chr_navigate_page`
- **Bonnes pratiques** :
    - Fournir fonction courte et idempotente
    - Ne pas exfiltrer données sensibles
    - Utiliser `uid` des éléments retournés par snapshot
- **Exemple** :

```json
{
    "function": "() => { return document.querySelector('h1').textContent; }"
}
```

## Sequential Thinking — Raisonnement explicite multi-étapes

- **Usage** : tâches complexes nécessitant étapes de raisonnement explicites (planification, debugging multi-étapes, hypothèses à vérifier)
- **Outil** : `mcp_sequentialthi_sequentialthinking`
- **Structure recommandée** :
    - Démarrer avec nombre estimé de pensées (`totalThoughts`)
    - Fournir premier `thought` clair, poser si besoin des questions de clarification
    - Marquer `nextThoughtNeeded`=true si autres étapes nécessaires
- **Exemple minimal** :

```json
{
    "thought": "Analyser l'erreur X et proposer correction",
    "nextThoughtNeeded": true,
    "thoughtNumber": 1,
    "totalThoughts": 4
}
```

## Agents recommandés

- `Context7-Expert` : pour résolution liée aux dernières versions de librairies.
- `debug` : pour diagnostic et correction.
- `Plan` : pour générer étapes/plans structurés.

## Sécurité et limites

- Ne pas exécuter d'actions destructrices sans confirmation humaine (delete, force-push).
- Limiter les requêtes qui exfiltrent des données utilisateur.

## Checklist rapide avant exécution

1. **Identifier le type de tâche** : doc, code, navigateur, raisonnement séquentiel.
2. **Sélectionner l'outil MCP approprié** :
    - Doc officielle précise ? → Context7 (`mcp_io_github_ups_*`)
    - Recherche large/API frais ? → Exa (`mcp_exa_*`)
    - Interaction navigateur/DOM ? → Chrome DevTools (`mcp_io_github_chr_*`)
    - Raisonnement complexe ? → Sequential Thinking (`mcp_sequentialthi_*`)
3. **Vérifier les bonnes pratiques** (sécurité, format d'appel)
4. **Exécuter l'outil avec paramètres explicites**

---

Fichier auto-généré — adapte ces instructions selon les besoins du projet. (l'ia peut ajuster pour elle mais doit notifier toute modification et mettre a jour dernière mise à jour) (Dernière mise à jour : 4 janvier 2026 à 09h15)
