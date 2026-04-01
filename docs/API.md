# Documentation API (backend)

Base URL : à adapter (`http://localhost:8000`, `https://api.example.com`, etc.).

Sauf indication contraire, les corps de requête et les réponses JSON utilisent **`Content-Type: application/json`**.

---

## CORS et prévol `OPTIONS`

- Variable d’environnement : `CORS_ALLOW_ORIGIN`.
  - **Origine unique** : ex. `http://localhost:5173` (Vite).
  - **Plusieurs origines** : liste séparée par des virgules.
  - **Motif PREG** (commence par `^`) : l’en-tête `Origin` du navigateur est comparé au motif ; si ça correspond, la réponse renvoie **exactement** cette origine (obligatoire pour CORS — une regex brute ne peut pas être mise dans `Access-Control-Allow-Origin`).
- Un abonné répond aux requêtes **`OPTIONS`** dont le chemin commence par **`/api`** avec **204** (corps vide).
- Les en-têtes CORS ajoutés en réponse incluent notamment :  
  `Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS`  
  et `Access-Control-Allow-Headers: Content-Type, Authorization, x-api-key`.

> **Images :** le contrôleur attend l’en-tête **`X-API-Key`** (valeur = `IMAGE_API_KEY` dans `.env`). Le préfixe peut varier selon le client HTTP ; côté navigateur, l’en-tête autorisé en CORS est noté `x-api-key`.

---

## Erreurs de validation (JSON)

Réponse **422** avec un corps du type :

```json
{
  "violations": [
    { "field": "nomDuChamp", "message": "…" }
  ]
}
```

Certaines routes renvoient **400** avec `{ "error": "…" }` ou `{ "errors": { "champ": "…" } }` (contact mail).

---

## Formats JSON communs

### Catégorie (objet complet)

| Champ     | Type   | Description        |
|-----------|--------|--------------------|
| `id`      | int    | Identifiant        |
| `code`    | string | Code unique        |
| `libelle` | string | Libellé            |
| `couleur` | string | Ex. `#6B7280`      |
| `icone`   | string\|null | Icône optionnelle |
| `ordre`   | int    | Ordre d’affichage  |

### Catégorie compacte (imbriquée)

`code`, `libelle`, `couleur`.

### Poste budgétaire (`budgetItem`)

| Champ            | Type    | Description |
|------------------|---------|-------------|
| `id`             | int     |             |
| `nom`            | string  |             |
| `categorie`      | objet compact |      |
| `periodicite`    | string  | Une de : `MOIS`, `SEMAINE`, `2_SEMAINES`, `TRIMESTRE`, `ANNEE` |
| `quantite`       | number  | > 0         |
| `unite`          | string\|null |        |
| `prixUnitaire`   | int     | ≥ 0 (unité métier, ex. Ariary) |
| `montant`        | int     | quantité × prix (occurrence) |
| `montantMensuel` | number  | équivalent mensuel |
| `actif`          | bool    | défaut `true` |

### Dépense (`depense`)

| Champ           | Type   | Description |
|-----------------|--------|-------------|
| `id`            | int    |             |
| `date`          | string | `Y-m-d`     |
| `produit`       | string |             |
| `budgetItem`    | objet\|null | Si lié : `id`, `nom`, `categorie` (compact) |
| `categorieCode` | string | Requis si pas de `budgetItem` (validation entité) |
| `quantite`      | number | > 0         |
| `unite`         | string\|null |        |
| `prixUnitaire`  | int    | ≥ 0         |
| `montant`       | int    | recalculé côté serveur |
| `note`          | string\|null |       |
| `createdAt`     | string | ISO 8601 (`ATOM`) |

---

## `/api/categories`

| Méthode | Chemin | Description |
|---------|--------|-------------|
| GET     | `/api/categories` | Liste (tri : `ordre`, puis `code`) |
| GET     | `/api/categories/{id}` | Détail |
| POST    | `/api/categories` | Création |
| PUT     | `/api/categories/{id}` | Remplacement |
| PATCH   | `/api/categories/{id}` | Mise à jour partielle |
| DELETE  | `/api/categories/{id}` | Suppression → **204** |

**Corps JSON (création / mise à jour)** — champs utilisés par le contrôleur :

- `code`, `libelle`, `couleur`, `icone` (nullable), `ordre`

**Réponses :** objet catégorie complet ; création **201**.

---

## `/api/budget-items`

| Méthode | Chemin | Description |
|---------|--------|-------------|
| GET     | `/api/budget-items` | Tableau JSON de postes |
| GET     | `/api/budget-items/{id}` | Détail |
| POST    | `/api/budget-items` | Création — **`categorieId` obligatoire** |
| PUT     | `/api/budget-items/{id}` | Remplacement |
| PATCH   | `/api/budget-items/{id}` | Partiel |
| DELETE  | `/api/budget-items/{id}` | **204** |

**Corps JSON :**

- `nom`, `categorieId` (int ; requis à la création), `periodicite`, `quantite`, `unite`, `prixUnitaire`, `actif`

Si `categorieId` manque en POST : **400** `{ "error": "categorieId est requis." }`.

---

## `/api/depenses`

| Méthode | Chemin | Description |
|---------|--------|-------------|
| GET     | `/api/depenses` | Liste paginée |
| GET     | `/api/depenses/{id}` | Détail |
| POST    | `/api/depenses` | Création **201** |
| PUT     | `/api/depenses/{id}` | Remplacement |
| PATCH   | `/api/depenses/{id}` | Partiel |
| DELETE  | `/api/depenses/{id}` | **204** |

### GET liste — paramètres de requête

| Paramètre | Description |
|-----------|----------------|
| `page` | Page (défaut `1`) |
| `itemsPerPage` | Taille (défaut `50`, max `100`) |
| `categorieCode` | Filtre sur le code catégorie |
| `produit` | Recherche partielle sur le nom produit |
| `orderDir` | `asc` ou `desc` (défaut `desc`) |
| `order[date]` | `asc` ou `desc` pour tri par date |
| `order[montant]` | idem par montant (si les deux sont présents, `date` est prioritaire dans l’implémentation actuelle) |
| `date[after]` | Date min incluse (`Y-m-d` ou chaîne acceptée par `DateTimeImmutable`) |
| `date[before]` | Date max incluse |
| `date[strictly_after]` | Date stricte &gt; |
| `date[strictly_before]` | Date stricte &lt; |

**Réponse liste :**

```json
{
  "member": [ /* objets depense */ ],
  "total": 0,
  "page": 1,
  "itemsPerPage": 50
}
```

### Corps JSON (création / mise à jour)

- `date` (string)
- `produit` (string)
- `budgetItemId` (int \| null) — si id inconnu : **404**
- `categorieCode` (string)
- `quantite` (number)
- `unite` (string \| null)
- `prixUnitaire` (int)
- `note` (string \| null)

---

## `/api/mail/contact`

| Méthode | Description |
|---------|-------------|
| POST, GET | Même handler (corps JSON attendu) |

**Corps JSON :**

```json
{
  "email": "user@example.com",
  "message": "Texte entre 10 et 2000 caractères."
}
```

- **202** : `{ "status": "accepted" }` (message mis en file via Messenger).
- **400** : JSON invalide ou erreurs de validation `{ "errors": { … } }`.
- **429** : rate limiting.

> En **GET** sans corps JSON valide, la requête échouera en pratique (JSON invalide). Utiliser **POST** pour le formulaire.

---

## `/api/images`

Toutes les routes sauf **`GET /api/images/{filename}`** exigent **`X-API-Key`** = clé configurée (`IMAGE_API_KEY`).

| Méthode | Chemin | Description |
|---------|--------|-------------|
| POST    | `/api/images` | Upload multipart : champ **`image`** (un fichier) ou **`images[]`** (plusieurs). Réponse **201** `{ "urls": [ "…" ] }`. |
| GET     | `/api/images` | Liste (JSON) — **401** sans clé. |
| GET     | `/api/images/{filename}` | Fichier **image/webp** (binaire). |
| POST    | `/api/images/{filename}` | Remplacement : multipart, champ **`image`**. `{ "url": "…" }`. |
| DELETE  | `/api/images/{filename}` | `{ "status": "deleted" }`. |

**401 / 429 / 400 / 500** : messages `{ "error": "…" }` selon les cas.

---

## Routes non exposées actuellement

Les services **stats** et **import CSV** existent dans `src/Service/`, mais il n’y a **pas** encore de contrôleurs HTTP pour :

- `GET /api/stats/dashboard`, `/api/stats/monthly`, `/api/stats/recommendations`
- `POST /api/import/depenses`, `POST /api/import/budget-items`

---

## Liste des routes (Symfony)

```bash
php bin/console debug:router
```

Filtrer par méthode, par exemple :

```bash
php bin/console debug:router | grep -E 'GET|POST|PUT|PATCH|DELETE'
```
