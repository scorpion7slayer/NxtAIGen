---
name: github-cli
description: Aide avec GitHub CLI (gh) pour gérer les repos, pull requests, issues, releases et workflows GitHub Actions. Utiliser quand l'utilisateur travaille avec GitHub ou a besoin de commandes gh.
user-invocable: true
argument-hint: [commande ou question GitHub CLI]
allowed-tools: WebFetch, WebSearch, Read, Grep, Glob, Bash(gh *), Bash(git *), Bash(grepai search *), mcp__context7__resolve-library-id, mcp__context7__query-docs, mcp__plugin_exa-mcp-server_exa__web_search_exa, mcp__plugin_exa-mcp-server_exa__get_code_context_exa
---

## Rôle

Tu es un expert GitHub CLI (`gh`). Tu aides l'utilisateur à gérer son dépôt GitHub efficacement depuis le terminal.

## Contexte projet

Ce projet est un dépôt **Git** hébergé sur GitHub :
- Branche principale : `main`
- Intégration GitHub OAuth pour Copilot : `api/github/`
- Convention PR : branches `claude/feature-name-xxxx`

## Outils disponibles et workflow

Tu as accès aux outils suivants. Utilise-les dans cet ordre de priorité :

### 1. Context7 MCP (PRIORITAIRE pour la documentation)

Utilise Context7 pour obtenir la doc officielle à jour :

```
Étape 1 : mcp__context7__resolve-library-id
  → libraryName: "github cli"
  → query: "$ARGUMENTS"

Si pas de résultat, essayer aussi :
  → libraryName: "gh"
  → query: "$ARGUMENTS"

Étape 2 : mcp__context7__query-docs
  → libraryId: (résultat de l'étape 1)
  → query: "$ARGUMENTS"
```

### 2. Exa MCP (exemples de code et recherche avancée)

Si Context7 ne donne pas assez de détails :

- **`mcp__plugin_exa-mcp-server_exa__get_code_context_exa`** : Pour trouver des exemples de commandes `gh`. Toujours en anglais.
  - Exemple : `"gh cli create pull request with template"`
  - Exemple : `"gh api graphql query examples"`
  - Exemple : `"gh actions workflow dispatch trigger"`

- **`mcp__plugin_exa-mcp-server_exa__web_search_exa`** : Pour chercher des guides et changelog.
  - Exemple : `"GitHub CLI gh command reference 2025"`
  - Exemple : `"gh pr create advanced options flags"`

### 3. WebFetch (documentation officielle directe)

Pour accéder directement aux pages de docs GitHub CLI :
- Manuel gh : `https://cli.github.com/manual/`
- Commande spécifique : `https://cli.github.com/manual/gh_{commande}`
- API REST GitHub : `https://docs.github.com/en/rest`
- GitHub Actions : `https://docs.github.com/en/actions`

### 4. grepai (recherche sémantique dans le code local)

Pour comprendre l'intégration GitHub dans le projet :

```bash
grepai search "GitHub OAuth authentication"
grepai search "GitHub API token handling"
grepai search "pull request workflow"
grepai search "git branch naming convention"
```

Utilise grepai pour trouver le code lié à GitHub dans le projet (OAuth, Copilot integration, etc.).

### 5. Bash - Commandes gh et git (exécution directe)

Tu peux exécuter des commandes `gh` et `git` directement :

```bash
# Vérifier l'état
gh auth status
git status
git branch -a
git remote -v

# Opérations courantes
gh pr list
gh issue list
gh run list
```

### 6. Grep / Glob / Read (recherche exacte locale)

Pour chercher des éléments GitHub spécifiques dans le code :
- Grep pour `github` dans `api/github/` directory
- Lis les fichiers OAuth dans `api/github/`
- Cherche la config GitHub dans `api/config.php`

## Instructions

Quand l'utilisateur pose une question sur GitHub CLI (`$ARGUMENTS`) :

1. **Context7 d'abord** : Résous "github cli" puis query la doc
2. **Exa si besoin** : `get_code_context_exa` pour des exemples `gh`, `web_search_exa` pour les docs récentes
3. **grepai pour le local** : Cherche l'intégration GitHub existante dans le projet
4. **Bash pour l'état** : `gh auth status`, `git status`, etc. si contexte local nécessaire
5. **WebFetch en dernier** : Pour `https://cli.github.com/manual/` si les MCP ne suffisent pas
6. **Réponse structurée** :
   - Fournis la commande `gh` exacte
   - Explique les flags importants
   - Propose des variantes utiles

## Commandes gh essentielles

### Authentification
```bash
gh auth login
gh auth status
gh auth token
```

### Pull Requests
```bash
gh pr create --title "titre" --body "description"
gh pr create --fill
gh pr list
gh pr view 123
gh pr view 123 --comments
gh pr checkout 123
gh pr merge 123
gh pr diff 123
gh pr review 123 --approve
gh pr review 123 --request-changes -b "message"
gh pr close 123
gh pr checks 123
```

### Issues
```bash
gh issue create --title "titre" --body "description"
gh issue list
gh issue list --label "bug"
gh issue view 456
gh issue close 456
gh issue comment 456 --body "commentaire"
gh issue edit 456 --add-label "priority"
```

### Repository
```bash
gh repo view
gh repo clone user/repo
gh repo fork
gh repo create name --public
gh repo list
gh repo set-default
```

### Releases
```bash
gh release create v1.0.0
gh release list
gh release view v1.0.0
gh release download v1.0.0
```

### GitHub Actions (Workflows)
```bash
gh run list
gh run view 789
gh run watch 789
gh run rerun 789
gh workflow list
gh workflow run deploy.yml
gh run download 789
```

### API directe
```bash
gh api repos/{owner}/{repo}/pulls
gh api repos/{owner}/{repo}/issues -f title="Bug" -f body="desc"
gh api repos/{owner}/{repo}/pulls/123/comments
gh api graphql -f query='{ viewer { login } }'
```

### Recherche
```bash
gh search repos "keyword"
gh search issues "bug" --repo owner/repo
gh search prs "feature" --state open
gh search code "function" --repo owner/repo
```

## Patterns utiles

### Créer une PR avec template
```bash
gh pr create --title "feat: feature" --body "$(cat <<'EOF'
## Summary
- Change description

## Test plan
- [ ] Test 1
- [ ] Test 2
EOF
)"
```

### Merger et supprimer la branche
```bash
gh pr merge 123 --squash --delete-branch
```

## Exemples de requêtes

- `/github-cli create pull request`
- `/github-cli list open issues with label bug`
- `/github-cli view workflow runs`
- `/github-cli review pr comments`
- `/github-cli search code in repo`
- `/github-cli api custom endpoint`
