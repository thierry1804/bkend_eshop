# Backend Symfony - Service d'envoi d'emails

Backend Symfony complet pour l'envoi d'emails via SMTP avec validation, traitement asynchrone, templates Twig, et sécurité (rate limiting, CORS).

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

3. Configurer la base de données (si nécessaire pour Messenger) :
```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
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

### Endpoint API

**POST** `/api/mail/contact`

**Body (JSON)** :
```json
{
    "email": "user@example.com",
    "message": "Votre message ici (10-2000 caractères)"
}
```

**Réponses** :
- `202 Accepted` : Email accepté et en cours de traitement
- `400 Bad Request` : Erreur de validation
- `429 Too Many Requests` : Rate limit dépassé (5 requêtes/minute)

## Configuration

### Rate Limiting
Configuré dans `config/packages/rate_limiter.yaml` :
- 5 requêtes par minute par IP

### CORS
Configuré dans `config/packages/cors.yaml` :
- Autorise uniquement le domaine spécifié dans `CORS_ALLOW_ORIGIN`

### SMTP
Les credentials SMTP sont configurés dans `.env` via `MAILER_DSN` :
```
MAILER_DSN=smtp://USER:PASSWORD@smtp.yourdomain.com:587?encryption=tls
```

⚠️ **Important** : Ne jamais exposer les credentials SMTP au frontend.

## Structure du projet

```
src/
├── Controller/
│   └── ContactMailController.php    # Endpoint API
├── DTO/
│   └── ContactMailDTO.php          # Validation des données
├── Service/
│   └── MailService.php              # Logique d'envoi d'emails
├── Message/
│   └── SendContactMailMessage.php   # Message Messenger
└── MessageHandler/
    └── SendContactMailHandler.php   # Handler asynchrone

templates/
└── emails/
    └── contact.html.twig            # Template email
```

## Sécurité

- ✅ Validation stricte des données d'entrée
- ✅ Rate limiting (5 req/min)
- ✅ CORS configuré
- ✅ Credentials SMTP côté backend uniquement
- ✅ Traitement asynchrone pour éviter les timeouts

## Tests

Voir le guide complet de test dans [TESTING.md](TESTING.md)

### Test rapide avec le script
```bash
./test-api.sh http://localhost:8000
```

### Test manuel avec curl
```bash
curl -X POST http://localhost:8000/api/mail/contact \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "message": "Message de test"
  }'
```

## Production

Pour la production :
1. Modifier `APP_ENV=prod` dans `.env`
2. Générer un `APP_SECRET` unique
3. Configurer le SMTP de production
4. Configurer CORS avec le domaine de production
5. Lancer le worker Messenger en daemon (supervisor/systemd)

