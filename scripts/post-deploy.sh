#!/bin/bash

# Script de post-déploiement pour Symfony
# À exécuter manuellement sur le serveur après le déploiement FTP

set -e

echo "🚀 Démarrage du post-déploiement..."

# Définir le répertoire de l'application (ajuster selon votre configuration)
APP_DIR="${1:-$(pwd)}"
cd "$APP_DIR"

echo "📁 Répertoire de l'application: $APP_DIR"

# Vérifier que nous sommes dans un projet Symfony
if [ ! -f "bin/console" ]; then
    echo "❌ Erreur: bin/console introuvable. Êtes-vous dans le bon répertoire?"
    exit 1
fi

# S'assurer que l'environnement est en production
if [ ! -f ".env.local" ]; then
    echo "📝 Création du fichier .env.local..."
    echo "APP_ENV=prod" > .env.local
    echo "APP_DEBUG=0" >> .env.local
    echo "⚠️  ATTENTION: Vous devez configurer les autres variables dans .env.local"
else
    echo "📝 Mise à jour de .env.local..."
    # Mettre à jour APP_ENV et APP_DEBUG si nécessaire
    if ! grep -q "^APP_ENV=prod" .env.local; then
        sed -i 's/^APP_ENV=.*/APP_ENV=prod/' .env.local || echo "APP_ENV=prod" >> .env.local
    fi
    if ! grep -q "^APP_DEBUG=0" .env.local; then
        sed -i 's/^APP_DEBUG=.*/APP_DEBUG=0/' .env.local || echo "APP_DEBUG=0" >> .env.local
    fi
fi

# Installer les dépendances si nécessaire
if [ -f "composer.json" ] && [ ! -d "vendor" ]; then
    echo "📦 Installation des dépendances Composer..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# Configurer les permissions
echo "🔐 Configuration des permissions..."
mkdir -p var/cache var/log
chmod -R 775 var
chown -R www-data:www-data var || chmod -R 777 var

# Mettre à jour la base de données
echo "🗄️  Mise à jour de la base de données..."
export APP_ENV=prod
export APP_DEBUG=0

# Migrations (si disponibles)
if php bin/console doctrine:migrations:status --no-interaction 2>/dev/null; then
    echo "  → Exécution des migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --env=prod || true
else
    echo "  → Mise à jour du schéma..."
    php bin/console doctrine:schema:update --force --no-interaction --env=prod || true
fi

# Vider et réchauffer le cache
echo "🗑️  Nettoyage du cache..."
php bin/console cache:clear --env=prod --no-debug

echo "🔥 Réchauffage du cache..."
php bin/console cache:warmup --env=prod --no-debug

echo "✅ Post-déploiement terminé avec succès!"
echo ""
echo "📋 Prochaines étapes:"
echo "   1. Vérifier que le fichier .env.local contient toutes les variables nécessaires"
echo "   2. Redémarrer le worker Messenger si nécessaire:"
echo "      php bin/console messenger:consume async"
echo "   3. Tester l'endpoint API"
echo "   4. Vérifier les logs dans var/log/prod.log"

