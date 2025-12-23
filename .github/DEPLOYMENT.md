# Guide de déploiement avec GitHub Actions

Ce projet utilise GitHub Actions pour automatiser les tests et le déploiement.

## Workflows disponibles

### 1. CI (Tests et Validation)
**Fichier**: `.github/workflows/ci.yml`

Ce workflow s'exécute automatiquement sur :
- Push vers `main`, `master`, ou `develop`
- Pull requests vers ces branches

**Actions** :
- Vérification de la syntaxe PHP
- Installation des dépendances
- Tests avec base de données MySQL
- Validation du code

### 2. Déploiement Production
**Fichier**: `.github/workflows/deploy.yml`

Ce workflow s'exécute sur :
- Push vers `main` ou `master`
- Déclenchement manuel via `workflow_dispatch`

## Configuration du déploiement

### Déploiement via FTP

Le workflow de déploiement utilise FTP pour transférer les fichiers vers votre serveur.

#### Étape 1: Configurer les secrets GitHub

Allez dans **Settings > Secrets and variables > Actions** de votre repository GitHub et ajoutez :

**Secrets requis** :
- `FTP_SERVER` : Adresse du serveur FTP (ex: `ftp.example.com` ou `192.168.1.100`)
- `FTP_USERNAME` : Nom d'utilisateur FTP
- `FTP_PASSWORD` : Mot de passe FTP
- `FTP_PORT` : Port FTP (optionnel, défaut: `21`)
- `FTP_PROTOCOL` : Protocole à utiliser (optionnel, `ftp` ou `ftps`, défaut: `ftp`)
- `FTP_SERVER_DIR` : Répertoire de destination sur le serveur (optionnel, défaut: `/`)

**Exemple** :
- `FTP_SERVER` : `ftp.monserveur.com`
- `FTP_USERNAME` : `monuser`
- `FTP_PASSWORD` : `monmotdepasse`
- `FTP_PORT` : `21`
- `FTP_PROTOCOL` : `ftp`
- `FTP_SERVER_DIR` : `/public_html` ou `/www` (selon votre hébergeur)

#### Étape 2: Préparer le serveur

Assurez-vous que :
1. Le serveur FTP est accessible et fonctionnel
2. Les permissions d'écriture sont configurées sur le répertoire de destination
3. PHP et Composer sont installés sur le serveur (si vous devez exécuter des commandes)
4. **Configuration du serveur web** : Le document root doit pointer vers le dossier `public` de l'application

**Configuration du serveur web (Apache/Nginx)** :

Pour avoir des URLs propres comme `https://api.eshopbyvalsue.mg/api/mail/contact` au lieu de `https://api.eshopbyvalsue.mg/public/index.php/api/mail/contact`, vous devez :

**Pour Apache** :
- Configurer le document root pour pointer vers le dossier `public` de votre application
- Le fichier `.htaccess` est déjà inclus dans le déploiement et gère automatiquement la réécriture d'URL
- Assurez-vous que le module `mod_rewrite` est activé sur votre serveur Apache

**Pour Nginx** :
```nginx
server {
    listen 80;
    server_name api.eshopbyvalsue.mg;
    root /chemin/vers/votre/application/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

**Via cPanel/Plesk** :
- Dans les paramètres du domaine, configurez le document root pour pointer vers le dossier `public` de votre application
- Exemple : Si votre application est dans `/home/user/public_html/app`, le document root doit être `/home/user/public_html/app/public`

#### Étape 3: Configuration automatique du fichier .env.local

✅ **Automatisé** : Le workflow de déploiement crée automatiquement un fichier `.env.local` avec les valeurs de base :
- `APP_ENV=prod`
- `APP_DEBUG=0`

**Vous devez compléter manuellement** les variables sensibles dans `.env.local` sur le serveur :
```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=votre-secret-production
MAILER_DSN=smtp://user:password@smtp.example.com:587?encryption=tls
MESSENGER_TRANSPORT_DSN=doctrine://default
DATABASE_URL="mysql://user:password@127.0.0.1:3306/dbname?serverVersion=8.0.32&charset=utf8mb4"
CORS_ALLOW_ORIGIN=https://votre-frontend.com
```

⚠️ **Note** : Le fichier `.env.local` est créé automatiquement lors du premier déploiement. Vous devez ensuite y ajouter vos secrets (APP_SECRET, MAILER_DSN, DATABASE_URL, etc.) une seule fois.

#### Étape 4: Post-déploiement automatique (recommandé)

✅ **Automatisé** : Le workflow peut appeler automatiquement le script de post-déploiement après le déploiement FTP.

**Configuration optionnelle** (pour automatiser complètement) :

Ajoutez ces secrets GitHub pour activer le post-déploiement automatique :
- `POST_DEPLOY_WEBHOOK_URL` : URL complète vers `post-deploy.php` (ex: `https://votre-domaine.com/post-deploy.php`)
- `POST_DEPLOY_TOKEN` : Token de sécurité (optionnel mais recommandé)

**Exemple** :
- `POST_DEPLOY_WEBHOOK_URL` : `https://monsite.com/post-deploy.php`
- `POST_DEPLOY_TOKEN` : `mon-token-secret-12345`

Ensuite, ajoutez le token dans votre `.env.local` sur le serveur :
```env
POST_DEPLOY_TOKEN=mon-token-secret-12345
```

**Si vous ne configurez pas le webhook**, vous pouvez exécuter le post-déploiement manuellement :

**Option A : Via HTTP (recommandé)**
```bash
# Avec token
curl "https://votre-domaine.com/post-deploy.php?token=votre-token"

# Ou sans token (si non configuré)
curl "https://votre-domaine.com/post-deploy.php"
```

**Option B : Via SSH (si disponible)**
```bash
cd /chemin/vers/votre/application
bash scripts/post-deploy.sh
```

**Option C : Via le panneau d'administration de votre hébergeur**
1. Connectez-vous à votre serveur via SSH ou le terminal de votre hébergeur
2. Naviguez vers le répertoire de l'application
3. Exécutez le script : `bash scripts/post-deploy.sh`

#### Étape 5: Configurer le worker Messenger (optionnel)

Si vous utilisez Supervisor pour gérer le worker Messenger :

```ini
# /etc/supervisor/conf.d/messenger-worker.conf
[program:messenger-worker]
command=php /var/www/app/current/bin/console messenger:consume async --time-limit=3600
directory=/var/www/app/current
user=www-data
numprocs=1
startsecs=0
autorestart=true
startretries=10
process_name=%(program_name)s_%(process_num)02d
```

Puis :
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start messenger-worker:*
```

### Script de post-déploiement

Le script `scripts/post-deploy.sh` automatise les tâches suivantes après un déploiement FTP :

- ✅ Configuration de l'environnement de production (`APP_ENV=prod`, `APP_DEBUG=0`)
- ✅ Installation des dépendances Composer (si nécessaire)
- ✅ Configuration des permissions des répertoires `var/cache` et `var/log`
- ✅ Mise à jour de la base de données (migrations ou schéma)
- ✅ Nettoyage et réchauffage du cache Symfony

**Utilisation** :
```bash
cd /chemin/vers/votre/application
chmod +x scripts/post-deploy.sh
./scripts/post-deploy.sh
```

Ou en spécifiant le répertoire :
```bash
./scripts/post-deploy.sh /chemin/vers/votre/application
```

## Variables d'environnement requises en production

Assurez-vous que ces variables sont configurées sur votre serveur :

- `APP_ENV=prod`
- `APP_SECRET` (générer avec `php bin/console secrets:generate`)
- `MAILER_DSN`
- `MESSENGER_TRANSPORT_DSN`
- `DATABASE_URL`
- `CORS_ALLOW_ORIGIN` (si CORS est activé)

## Déploiement manuel

Pour déclencher un déploiement manuellement :

1. Allez dans l'onglet **Actions** de votre repository GitHub
2. Sélectionnez le workflow **Déploiement Production**
3. Cliquez sur **Run workflow**
4. Sélectionnez la branche et cliquez sur **Run workflow**

## Rollback

En cas de problème avec un déploiement FTP :

1. **Via votre hébergeur** : Utilisez la fonctionnalité de restauration de fichiers de votre hébergeur (souvent disponible dans le panneau d'administration)

2. **Via FTP manuel** : Téléchargez une version précédente depuis votre sauvegarde

3. **Via Git** : Si vous avez accès SSH, vous pouvez revenir à un commit précédent :
   ```bash
   cd /chemin/vers/votre/application
   git checkout <commit-hash>
   bash scripts/post-deploy.sh
   ```

## Monitoring

Après le déploiement, vérifiez :

1. **Logs de l'application** : `var/log/prod.log` dans votre répertoire d'application
2. **Logs du serveur web** : Consultez les logs de votre hébergeur (cPanel, Plesk, etc.)
3. **Status du worker Messenger** : Vérifiez que le worker est actif (si configuré)
4. **Test de l'endpoint** :
   ```bash
   curl -X POST https://votre-domaine.com/api/mail/contact \
     -H "Content-Type: application/json" \
     -d '{"email":"test@example.com","message":"Test de déploiement"}'
   ```
5. **Vérifier les permissions** : Assurez-vous que `var/cache` et `var/log` sont accessibles en écriture

## Sécurité

⚠️ **Important** :
- Ne jamais commiter les fichiers `.env` ou `.env.local`
- Utiliser des secrets GitHub pour toutes les informations sensibles (FTP credentials)
- Utiliser FTPS (FTP over SSL) si possible pour sécuriser les transferts
- Ne jamais exposer les credentials FTP dans le code ou les commits
- Configurer un firewall sur le serveur
- Utiliser HTTPS en production
- Configurer correctement CORS pour limiter les origines autorisées
- Exécuter toujours le script de post-déploiement après un déploiement FTP

