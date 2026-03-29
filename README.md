# Backend Symfony - API E-shop

Backend Symfony pour l'envoi d'emails de contact et la gestion d'images produits (upload, conversion WebP, stockage, suppression).

## Installation

1. Installer les dépendances :
```bash
composer install
```

2. Configurer les variables d'environnement :
```bash
cp .env.example .env
```

Puis éditer `.env` et configurer :
- `MAILER_DSN` : Votre configuration SMTP
- `MESSENGER_TRANSPORT_DSN` : Transport pour Messenger (doctrine://default)
- `CORS_ALLOW_ORIGIN` : Domaine React autorisé (ex: http://localhost:3000)
- `IMAGE_API_KEY` : Clé secrète pour sécuriser les endpoints images
- `IMAGE_UPLOAD_DIR` : Dossier de stockage (défaut: `var/uploads/images`)
- `IMAGE_MAX_SIZE` : Taille max en octets (défaut: 5242880 = 5 Mo)

3. Configurer la base de données (si nécessaire pour Messenger) :
```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
```

4. Vérifier que l'extension PHP **GD** est activée avec le support WebP :
```bash
php -m | grep gd
```

## Utilisation

### Lancer le serveur Symfony
```bash
symfony server:start
# ou
php -S localhost:8000 -t public
```

### Lancer le worker Messenger (obligatoire pour l'envoi d'emails)
```bash
php bin/console messenger:consume async
```

## Endpoints API

### Emails de contact

**POST** `/api/mail/contact`

```json
{
    "email": "user@example.com",
    "message": "Votre message ici (10-2000 caractères)"
}
```

| Réponse | Description |
|---------|-------------|
| `202` | Email accepté et mis en queue |
| `400` | Erreur de validation |
| `429` | Rate limit dépassé (5 req/min) |

### Images produits

Tous les endpoints sauf `GET /api/images/{filename}` nécessitent le header `X-API-Key`.

#### Upload (une ou plusieurs images)

**POST** `/api/images`

```bash
# Une image
curl -X POST -H "X-API-Key: your-key" \
  -F "image=@photo.jpg" \
  http://localhost:8000/api/images

# Plusieurs images
curl -X POST -H "X-API-Key: your-key" \
  -F "images[]=@photo1.jpg" \
  -F "images[]=@photo2.png" \
  http://localhost:8000/api/images
```

Réponse `201` :
```json
{
    "urls": [
        "/api/images/1711234567890-k3x9m2.webp",
        "/api/images/1711234567891-abc123.webp"
    ]
}
```

Les images sont automatiquement converties en **WebP** (qualité 80). Formats acceptés : JPEG, PNG, GIF, WebP, BMP.

#### Afficher une image (public)

**GET** `/api/images/{filename}`

Pas d'authentification requise. Cache HTTP d'1 an. URL directement utilisable dans un `<img>` ou stockable en BDD.

#### Lister les images

**GET** `/api/images`

#### Remplacer une image

**POST** `/api/images/{filename}`

```bash
curl -X POST -H "X-API-Key: your-key" \
  -F "image=@new-photo.jpg" \
  http://localhost:8000/api/images/1711234567890-k3x9m2.webp
```

#### Supprimer une image

**DELETE** `/api/images/{filename}`

```bash
curl -X DELETE -H "X-API-Key: your-key" \
  http://localhost:8000/api/images/1711234567890-k3x9m2.webp
```

## Configuration

### Rate Limiting
Configuré dans `config/packages/rate_limiter.yaml` :
- Emails de contact : 5 req/min par IP
- Upload d'images : 20 req/min par IP

### CORS
Géré par `CorsSubscriber` : autorise uniquement le domaine spécifié dans `CORS_ALLOW_ORIGIN`.

### SMTP
```
MAILER_DSN=smtp://USER:PASSWORD@smtp.yourdomain.com:587?encryption=tls
```

## Structure du projet

```
src/
├── Controller/
│   ├── ContactMailController.php    # Endpoint email de contact
│   └── ImageController.php          # Endpoints gestion d'images
├── DTO/
│   └── ContactMailDTO.php           # Validation données contact
├── EventSubscriber/
│   └── CorsSubscriber.php           # Headers CORS
├── Service/
│   ├── MailService.php              # Envoi d'emails
│   └── ImageService.php             # Upload, conversion WebP, stockage
├── Message/
│   └── SendContactMailMessage.php   # Message Messenger
└── MessageHandler/
    └── SendContactMailHandler.php   # Handler asynchrone

templates/
└── emails/
    └── contact.html.twig            # Template email
```

## Sécurité

- Authentification par API Key (`X-API-Key`) sur les endpoints d'écriture images
- Images servies publiquement (GET) pour usage direct en frontend
- Validation MIME type réelle (magic bytes, pas juste l'extension)
- Double vérification via `getimagesize()`
- Protection path traversal sur les noms de fichiers
- Rate limiting par IP
- CORS configuré
- Credentials SMTP et API Key côté backend uniquement

## Tests

Voir le guide complet de test dans [TESTING.md](TESTING.md)

```bash
./test-api.sh http://localhost:8000
```

## Production

1. Modifier `APP_ENV=prod` dans `.env`
2. Générer un `APP_SECRET` unique
3. Configurer le SMTP de production
4. Configurer `CORS_ALLOW_ORIGIN` avec le domaine de production
5. Définir une `IMAGE_API_KEY` forte
6. Lancer le worker Messenger en daemon (supervisor/systemd)
