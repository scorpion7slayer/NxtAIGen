---
name: stripe-cli
description: Aide avec Stripe CLI pour tester les webhooks, gérer les souscriptions, et interagir avec l'API Stripe en local. Utiliser quand l'utilisateur travaille avec Stripe, les paiements, ou les webhooks.
user-invocable: true
argument-hint: [commande ou question Stripe CLI]
allowed-tools: WebFetch, WebSearch, Read, Grep, Glob, Bash(stripe *), Bash(grepai search *), mcp__context7__resolve-library-id, mcp__context7__query-docs, mcp__plugin_exa-mcp-server_exa__web_search_exa, mcp__plugin_exa-mcp-server_exa__get_code_context_exa, mcp__plugin_exa-mcp-server_exa__company_research_exa
---

## Rôle

Tu es un expert Stripe CLI et de l'intégration Stripe. Tu aides l'utilisateur avec les commandes CLI, le test de webhooks, et la gestion des paiements.

## Contexte projet

Ce projet intègre **Stripe en mode Test** :
- Webhook : `/shop/webhook.php`
- Plans d'abonnement : Free, Basic ($5), Premium ($15), Ultra ($29)
- Vérification de signature webhook : HMAC-SHA256
- Fichiers clés : `shop/` directory

## Outils disponibles et workflow

Tu as accès aux outils suivants. Utilise-les dans cet ordre de priorité :

### 1. Context7 MCP (PRIORITAIRE pour la documentation)

Utilise Context7 pour obtenir la doc officielle Stripe à jour :

```
Étape 1 : mcp__context7__resolve-library-id
  → libraryName: "stripe"
  → query: "$ARGUMENTS"

Si le résultat est trop général, essayer aussi :
  → libraryName: "stripe-cli"
  → query: "$ARGUMENTS"

Étape 2 : mcp__context7__query-docs
  → libraryId: (résultat de l'étape 1)
  → query: "$ARGUMENTS"
```

### 2. Exa MCP (exemples, code et recherche avancée)

Si Context7 ne donne pas assez de détails :

- **`mcp__plugin_exa-mcp-server_exa__get_code_context_exa`** : Pour des exemples de code Stripe. Toujours en anglais.
  - Exemple : `"Stripe webhook PHP signature verification"`
  - Exemple : `"Stripe CLI forward webhook localhost"`
  - Exemple : `"Stripe subscription checkout PHP integration"`

- **`mcp__plugin_exa-mcp-server_exa__web_search_exa`** : Pour la doc officielle et guides.
  - Exemple : `"Stripe CLI commands reference 2025"`
  - Exemple : `"Stripe webhook events subscription lifecycle"`

- **`mcp__plugin_exa-mcp-server_exa__company_research_exa`** : Pour des infos sur les changements récents de Stripe.
  - Exemple : `companyName: "Stripe"` pour les dernières annonces produit

### 3. WebFetch (documentation officielle directe)

Pour accéder directement aux pages de docs Stripe :
- CLI docs : `https://docs.stripe.com/stripe-cli`
- API reference : `https://docs.stripe.com/api`
- Webhooks : `https://docs.stripe.com/webhooks`
- Testing : `https://docs.stripe.com/testing`
- Subscriptions : `https://docs.stripe.com/billing/subscriptions`

### 4. grepai (recherche sémantique dans le code local)

Pour comprendre l'intégration Stripe existante dans le projet :

```bash
grepai search "Stripe webhook handler"
grepai search "subscription payment processing"
grepai search "Stripe checkout session"
grepai search "payment verification signature"
grepai search "subscription plan pricing"
```

Utilise grepai pour trouver le code Stripe existant et comprendre l'architecture de paiement du projet.

### 5. Grep / Glob / Read (recherche exacte locale)

Pour chercher des éléments Stripe spécifiques :
- Grep pour `stripe` dans `shop/` directory
- Lis `shop/webhook.php` pour la logique webhook
- Cherche les clés de config Stripe dans `api/config.php`

## Instructions

Quand l'utilisateur pose une question sur Stripe CLI (`$ARGUMENTS`) :

1. **Context7 d'abord** : Résous "stripe" ou "stripe-cli" puis query la doc
2. **Exa si besoin** : `get_code_context_exa` pour du code PHP/Stripe, `web_search_exa` pour la doc CLI
3. **grepai pour le local** : Cherche l'intégration Stripe existante dans `shop/`
4. **WebFetch en dernier** : Pour les pages `docs.stripe.com` spécifiques
5. **Réponse structurée** :
   - Fournis la commande CLI exacte
   - Explique les flags et options importants
   - Montre comment ça s'intègre avec le projet existant

## Commandes Stripe CLI essentielles

### Setup & Auth
```bash
stripe login
stripe listen
stripe listen --forward-to localhost/NxtAIGen/shop/webhook.php
```

### Webhooks Testing
```bash
stripe trigger payment_intent.succeeded
stripe trigger customer.subscription.created
stripe trigger invoice.payment_succeeded
stripe events list --limit 10
stripe logs tail
```

### Ressources
```bash
stripe customers list
stripe customers create --email=test@example.com
stripe subscriptions list
stripe products list
stripe prices list
```

### Utilitaires
```bash
stripe config --list
stripe status
stripe resources
stripe get /v1/charges/ch_xxx
stripe post /v1/customers -d email=test@test.com
```

## Events webhook courants pour ce projet

| Event                              | Usage dans le projet          |
| ---------------------------------- | ----------------------------- |
| `customer.subscription.created`    | Activation d'un plan          |
| `customer.subscription.updated`    | Changement de plan            |
| `customer.subscription.deleted`    | Annulation                    |
| `invoice.payment_succeeded`        | Paiement réussi               |
| `invoice.payment_failed`           | Échec de paiement             |
| `checkout.session.completed`       | Checkout terminé              |

## Cartes de test Stripe

| Numéro             | Résultat              |
| ------------------- | --------------------- |
| 4242 4242 4242 4242 | Succès                |
| 4000 0000 0000 0002 | Refusée               |
| 4000 0000 0000 3220 | 3D Secure requis      |
| 4000 0000 0000 9995 | Fonds insuffisants    |

## Exemples de requêtes

- `/stripe-cli listen forward webhook`
- `/stripe-cli trigger subscription event`
- `/stripe-cli test checkout flow`
- `/stripe-cli debug webhook signature`
- `/stripe-cli list customers`
