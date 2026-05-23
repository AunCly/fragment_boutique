# POC — E-boutique Shopify Custom Storefront

## Objectif

Valider le fonctionnement ecommerce complet avant de développer le vrai builder :
- Affichage du catalogue de produits fixes récupérés depuis Shopify
- Simulation du builder (input nom + input prix) pour un produit à prix variable
- Ajout au panier et tunnel de paiement Shopify natif

---

## Stack

| Outil | Rôle |
|---|---|
| Laravel 13 | Backend (déjà en place) |
| Shopify Storefront API (GraphQL) | Lecture des produits |
| Shopify Admin API (REST/GraphQL) | Création des Draft Orders (panier + prix variable) |
| Alpine.js | Gestion du panier côté client |
| Tailwind v4 | CSS (déjà en place) |

---

## Pourquoi Draft Orders pour le panier ?

La Storefront API Cart ne supporte pas les prix personnalisés. Les **Draft Orders** via l'Admin API permettent de mixer :
- Des produits Shopify standard (par `variant_id`)
- Des produits custom (titre libre + prix arbitraire)

Et génèrent une `invoice_url` → checkout Shopify natif, aucun développement tunnel de paiement.

---

## Architecture des appels

```
Browser                Laravel                    Shopify
  │                       │                           │
  │  GET /                │                           │
  │──────────────────────>│  Storefront API (GraphQL) │
  │                       │──────────────────────────>│
  │                       │<── products[]             │
  │<── HTML page          │                           │
  │                       │                           │
  │  (interactions Alpine │                           │
  │   → panier en mémoire)│                           │
  │                       │                           │
  │  POST /checkout       │                           │
  │  { items: [...] }     │                           │
  │──────────────────────>│  Admin API Draft Order    │
  │                       │──────────────────────────>│
  │                       │<── { invoice_url }        │
  │<── redirect           │                           │
  │         invoice_url ──────────────────────────────│
```

---

## Étapes d'implémentation

### 1. Config Shopify (`.env`)

```env
SHOPIFY_STORE_DOMAIN=yourstore.myshopify.com
SHOPIFY_STOREFRONT_TOKEN=xxxx   # Storefront API public token
SHOPIFY_ADMIN_TOKEN=xxxx        # Admin API token
```

### 2. Service Storefront API

`app/Services/ShopifyStorefrontService.php`
- Fetch produits via GraphQL (titre, prix, image, variant ID)

### 3. Service Admin API

`app/Services/ShopifyAdminService.php`
- `createDraftOrder(array $items): string` → retourne `invoice_url`
- Supporte line items standards (`variant_id`) et custom (`title` + `price`)

### 4. Routes + Controllers

```
GET  /           → listing produits (ShopifyStorefrontService)
POST /checkout   → reçoit panier JSON → Draft Order → redirect invoice_url
```

### 5. Frontend (Blade + Alpine.js)

- **Listing produits** : cards avec image, titre, prix, bouton "Ajouter au panier"
- **Builder simulé** : input `Nom du produit` + input `Prix (€)` → bouton "Ajouter au panier"
- **Panier flottant** : état géré par Alpine.js en mémoire client, affiche les items + total
- **Bouton Checkout** : POST vers `/checkout` avec le contenu du panier → redirect Shopify

---

## Ce que ce POC valide

| Fonctionnalité | Mécanisme |
|---|---|
| Fetch et affichage produits Shopify | Storefront API |
| Ajout produit fixe au panier | Draft Order line item (`variant_id`) |
| Produit à prix variable | Draft Order custom line item |
| Panier mixte (fixe + custom) | Draft Order multi-items |
| Tunnel de paiement complet | `invoice_url` Shopify natif |

---

## Prérequis côté Shopify

1. **Storefront API token** : `Apps > Develop apps > Storefront API access`
2. **Admin API token** : `Apps > Develop apps > Admin API access`
   - Scopes requis : `write_draft_orders`, `read_products`
3. Au moins un produit publié dans le store pour tester
