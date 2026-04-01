<?php
/**
 * Script de post-déploiement automatique
 * 
 * Ce script peut être appelé via HTTP après un déploiement FTP
 * pour automatiser les tâches de post-déploiement.
 * 
 * Sécurité : Utilisez un token secret pour protéger cet endpoint
 */

// Token de sécurité (à définir dans .env.local comme POST_DEPLOY_TOKEN)
$requiredToken = $_ENV['POST_DEPLOY_TOKEN'] ?? $_SERVER['POST_DEPLOY_TOKEN'] ?? null;
$providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? null;

// Vérifier le token si configuré
if ($requiredToken && $providedToken !== $requiredToken) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Token invalide ou manquant']);
    exit;
}

// Désactiver l'affichage des erreurs en production
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Définir le timeout pour les longues opérations
set_time_limit(300);

// Répertoire de l'application
$appDir = dirname(__DIR__);
chdir($appDir);

$output = [];
$errors = [];

/**
 * Exécuter une commande et capturer la sortie
 */
function runCommand($command, &$output, &$errors) {
    $output[] = "Exécution: $command";
    exec("$command 2>&1", $cmdOutput, $returnCode);
    $output = array_merge($output, $cmdOutput);
    if ($returnCode !== 0) {
        $errors[] = "Erreur lors de l'exécution de: $command";
    }
    return $returnCode === 0;
}

// Démarrer le post-déploiement
$output[] = "🚀 Démarrage du post-déploiement automatique...";
$output[] = "📁 Répertoire: $appDir";

// 1. Vérifier que nous sommes dans un projet Symfony
if (!file_exists("$appDir/bin/console")) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'bin/console introuvable',
        'output' => $output
    ]);
    exit;
}

// 2. S'assurer que l'environnement est en production
$envLocalPath = "$appDir/.env.local";
if (!file_exists($envLocalPath)) {
    $output[] = "📝 Création du fichier .env.local...";
    file_put_contents($envLocalPath, "APP_ENV=prod\nAPP_DEBUG=0\n");
} else {
    $envContent = file_get_contents($envLocalPath);
    if (strpos($envContent, 'APP_ENV=prod') === false) {
        $output[] = "📝 Mise à jour de .env.local...";
        $envContent = preg_replace('/^APP_ENV=.*/m', 'APP_ENV=prod', $envContent);
        $envContent = preg_replace('/^APP_DEBUG=.*/m', 'APP_DEBUG=0', $envContent);
        if (strpos($envContent, 'APP_ENV=prod') === false) {
            $envContent = "APP_ENV=prod\nAPP_DEBUG=0\n" . $envContent;
        }
        file_put_contents($envLocalPath, $envContent);
    }
}

// 3. Dépendances : archive CI (vendor.tar.gz) ou Composer
if (file_exists("$appDir/vendor.tar.gz")) {
    $output[] = "📦 Extraction de vendor.tar.gz…";
    if (is_dir("$appDir/vendor")) {
        runCommand("cd $appDir && rm -rf vendor", $output, $errors);
    }
    runCommand("cd $appDir && tar -xzf vendor.tar.gz && rm -f vendor.tar.gz", $output, $errors);
} elseif (file_exists("$appDir/composer.json") && !is_dir("$appDir/vendor")) {
    $output[] = "📦 Installation des dépendances Composer...";
    runCommand("cd $appDir && composer install --no-dev --optimize-autoloader --no-interaction", $output, $errors);
}

// 4. Configurer les permissions
$output[] = "🔐 Configuration des permissions...";
if (!is_dir("$appDir/var/cache")) {
    mkdir("$appDir/var/cache", 0775, true);
}
if (!is_dir("$appDir/var/log")) {
    mkdir("$appDir/var/log", 0775, true);
}
chmod("$appDir/var/cache", 0775);
chmod("$appDir/var/log", 0775);

// 5. Mettre à jour la base de données
$output[] = "🗄️  Mise à jour de la base de données...";
putenv("APP_ENV=prod");
putenv("APP_DEBUG=0");

runCommand("cd $appDir && php bin/console doctrine:schema:update --force --no-interaction --env=prod", $output, $errors);

// 6. Vider et réchauffer le cache
$output[] = "🗑️  Nettoyage du cache...";
runCommand("cd $appDir && php bin/console cache:clear --env=prod --no-debug", $output, $errors);

$output[] = "🔥 Réchauffage du cache...";
runCommand("cd $appDir && php bin/console cache:warmup --env=prod --no-debug", $output, $errors);

// Réponse JSON
header('Content-Type: application/json');

if (empty($errors)) {
    $output[] = "✅ Post-déploiement terminé avec succès!";
    echo json_encode([
        'success' => true,
        'message' => 'Post-déploiement terminé avec succès',
        'output' => $output
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Post-déploiement terminé avec des erreurs',
        'errors' => $errors,
        'output' => $output
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

