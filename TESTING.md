# Guide de test du backend Symfony

## Prérequis

1. **Installer les dépendances** :
```bash
composer install
```

2. **Configurer les variables d'environnement** :
Créez un fichier `.env.local` avec vos configurations (voir `ENV_VARIABLES.md`)

3. **Initialiser la base de données** (pour Messenger) :
```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
```

## Tests manuels

### 1. Vérifier que le serveur démarre

```bash
# Option 1 : Symfony CLI
symfony server:start

# Option 2 : PHP built-in server
php -S localhost:8000 -t public
```

### 2. Tester l'endpoint avec curl

#### Test basique (succès)
```bash
curl -X POST http://localhost:8000/api/mail/contact \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "message": "Ceci est un message de test pour vérifier que l endpoint fonctionne correctement."
  }'
```

**Réponse attendue** :
```json
{
  "status": "accepted"
}
```
Avec le code HTTP `202 Accepted`

#### Test de validation - Email invalide
```bash
curl -X POST http://localhost:8000/api/mail/contact \
  -H "Content-Type: application/json" \
  -d '{
    "email": "email-invalide",
    "message": "Message de test"
  }'
```

**Réponse attendue** :
```json
{
  "errors": {
    "email": "This value is not a valid email address."
  }
}
```
Avec le code HTTP `400 Bad Request`

#### Test de validation - Message trop court
```bash
curl -X POST http://localhost:8000/api/mail/contact \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "message": "Court"
  }'
```

**Réponse attendue** :
```json
{
  "errors": {
    "message": "This value is too short. It should have 10 characters or more."
  }
}
```

#### Test de validation - Message trop long
```bash
curl -X POST http://localhost:8000/api/mail/contact \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "message": "'$(python3 -c "print('a' * 2001)")'"
  }'
```

#### Test de validation - Champs manquants
```bash
curl -X POST http://localhost:8000/api/mail/contact \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com"
  }'
```

#### Test de rate limiting
Exécutez la même requête 6 fois rapidement :
```bash
for i in {1..6}; do
  echo "Requête $i:"
  curl -X POST http://localhost:8000/api/mail/contact \
    -H "Content-Type: application/json" \
    -d '{
      "email": "test@example.com",
      "message": "Message de test pour vérifier le rate limiting."
    }'
  echo -e "\n"
done
```

La 6ème requête devrait retourner :
```json
{
  "error": "Too many requests. Please try again later."
}
```
Avec le code HTTP `429 Too Many Requests`

#### Test CORS (depuis un navigateur ou avec curl)
```bash
curl -X OPTIONS http://localhost:8000/api/mail/contact \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -v
```

Vous devriez voir les en-têtes CORS dans la réponse :
```
Access-Control-Allow-Origin: http://localhost:3000
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

### 3. Vérifier le traitement asynchrone

#### Lancer le worker Messenger
Dans un terminal séparé :
```bash
php bin/console messenger:consume async -vv
```

Le flag `-vv` affiche les logs détaillés. Vous devriez voir les messages être traités.

#### Tester l'envoi d'email
1. Assurez-vous que `MAILER_DSN` est correctement configuré dans `.env.local`
2. Envoyez une requête valide (voir test basique ci-dessus)
3. Vérifiez les logs du worker pour voir si l'email est envoyé

**Note** : Si vous n'avez pas de serveur SMTP configuré, vous pouvez utiliser Mailtrap ou un service similaire pour les tests.

### 4. Test avec un client HTTP (Postman, Insomnia, etc.)

1. **Méthode** : POST
2. **URL** : `http://localhost:8000/api/mail/contact`
3. **Headers** :
   - `Content-Type: application/json`
4. **Body** (raw JSON) :
```json
{
  "email": "test@example.com",
  "message": "Message de test depuis Postman/Insomnia"
}
```

## Tests automatisés (optionnel)

### Créer un test PHPUnit

Créez `tests/Controller/ContactMailControllerTest.php` :

```php
<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContactMailControllerTest extends WebTestCase
{
    public function testContactMailSuccess(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/mail/contact', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'message' => 'Message de test pour PHPUnit'
        ]));

        $this->assertResponseStatusCodeSame(202);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('accepted', $response['status']);
    }

    public function testContactMailValidationError(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/mail/contact', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'invalid-email',
            'message' => 'Test'
        ]));

        $this->assertResponseStatusCodeSame(400);
    }
}
```

Lancer les tests :
```bash
php bin/phpunit
```

## Vérification des logs

### Logs Symfony
```bash
tail -f var/log/dev.log
```

### Logs du worker Messenger
Le worker affiche les logs directement dans le terminal avec `-vv`.

## Dépannage

### Erreur : "Rate limiter requires Lock component"
```bash
composer require symfony/lock
```

### Erreur : "Database connection"
Vérifiez que `DATABASE_URL` est correctement configuré dans `.env.local`.

### Erreur : "Mailer DSN"
Vérifiez que `MAILER_DSN` est correctement configuré. Pour les tests, vous pouvez utiliser :
```
MAILER_DSN=null://null
```
Cela désactive l'envoi réel d'emails (utile pour les tests).

### Erreur : "Too many requests" pendant les tests
Si vous atteignez la limite du rate limiter pendant les tests :

1. **Réinitialiser le cache du rate limiter** :
```bash
php bin/console cache:pool:clear cache.rate_limiter
```

2. **Utiliser la configuration de développement** :
Une configuration avec une limite plus élevée (100 req/min) est disponible dans `config/packages/dev/rate_limiter.yaml` pour l'environnement de développement.

3. **Attendre 1 minute** pour que la limite se réinitialise automatiquement.

### Les emails ne sont pas envoyés
1. Vérifiez que le worker Messenger est lancé
2. Vérifiez les logs du worker
3. Vérifiez la configuration SMTP

## Checklist de test complète

- [ ] Le serveur démarre sans erreur
- [ ] L'endpoint répond avec 202 pour une requête valide
- [ ] La validation fonctionne (email invalide, message trop court/long)
- [ ] Le rate limiting fonctionne (429 après 5 requêtes)
- [ ] Les en-têtes CORS sont présents
- [ ] Le worker Messenger traite les messages
- [ ] Les emails sont envoyés (si SMTP configuré)

