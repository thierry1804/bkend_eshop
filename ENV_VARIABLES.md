# Variables d'environnement

Copiez ce fichier en `.env.local` et configurez les valeurs suivantes :

## Variables requises

### APP_ENV
Environnement d'exécution (`dev`, `prod`, `test`)
```
APP_ENV=dev
```

### APP_SECRET
Clé secrète pour la sécurité Symfony (générer avec `php bin/console secrets:generate`)
```
APP_SECRET=your-secret-key-change-this-in-production
```

### MAILER_DSN
Configuration SMTP pour l'envoi d'emails
```
MAILER_DSN=smtp://USER:PASSWORD@smtp.yourdomain.com:587?encryption=tls
```

### MESSENGER_TRANSPORT_DSN
Transport pour Messenger (utilise Doctrine par défaut)
```
MESSENGER_TRANSPORT_DSN=doctrine://default
```

### DATABASE_URL
URL de connexion à la base de données (requis pour Messenger avec Doctrine en production)
```
DATABASE_URL="mysql://user:password@127.0.0.1:3306/dbname?serverVersion=8.0.32&charset=utf8mb4"
```

**Note** : Pour les tests sans base de données, vous pouvez utiliser le transport `sync://` dans `config/packages/test/messenger.yaml`

## Variables optionnelles (mais recommandées)

### CORS_ALLOW_ORIGIN
Domaine React autorisé pour les requêtes CORS. **Requis si CORS est activé.**
```
CORS_ALLOW_ORIGIN=http://localhost:3000
```

⚠️ **Note** : Si cette variable n'est pas définie, vous devez soit la définir dans votre `.env.local`, soit désactiver CORS dans `config/packages/cors.yaml`.

## Exemple de fichier .env.local

```env
APP_ENV=dev
APP_SECRET=change-this-secret-key-in-production
MAILER_DSN=smtp://user:password@smtp.example.com:587?encryption=tls
MESSENGER_TRANSPORT_DSN=doctrine://default
DATABASE_URL="mysql://root:password@127.0.0.1:3306/symfony_messenger?serverVersion=8.0.32&charset=utf8mb4"
CORS_ALLOW_ORIGIN=http://localhost:3000
```

