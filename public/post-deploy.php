<?php
/**
 * Script de post-déploiement autonome — aucune dépendance Symfony/vendor.
 * Peut s'exécuter même si vendor/ est absent ou incomplet.
 *
 * Usage : GET /post-deploy.php?token=<POST_DEPLOY_TOKEN>
 *
 * Pré-requis serveur : ajouter POST_DEPLOY_TOKEN=<valeur> dans .env.local
 */

declare(strict_types=1);

set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$appDir = dirname(__DIR__);

// ── 1. Vérification du token ─────────────────────────────────────────────────
// $_ENV / $_SERVER ne lisent PAS .env.local (c'est Symfony qui le fait).
// On parse le fichier manuellement pour récupérer POST_DEPLOY_TOKEN.

function readEnvLocal(string $dir): array
{
    $vars = [];
    $file = $dir . '/.env.local';
    if (!is_readable($file)) {
        return $vars;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $vars[trim($key)] = trim($val, " \t\"'");
        }
    }
    return $vars;
}

$env           = readEnvLocal($appDir);
$expectedToken = $env['POST_DEPLOY_TOKEN'] ?? '';
$providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

// Toujours exiger un token non vide (refuse si non configuré sur le serveur)
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Token invalide ou manquant']);
    exit;
}

// ── 2. Extraction de vendor.tar.gz ───────────────────────────────────────────

$log    = [];
$errors = [];

$archive = $appDir . '/vendor.tar.gz';

if (file_exists($archive)) {
    $log[] = '📦 Extraction de vendor.tar.gz…';
    try {
        // PharData : extraction en place (overwrite = true), sans exec().
        // Les fichiers existants sont écrasés file par file → aucune fenêtre
        // où vendor/ est vide, pas de risque de Class not found pendant l'opération.
        $phar = new PharData($archive);
        $phar->extractTo($appDir, null, true);
        unlink($archive);
        $log[] = '✓ vendor extrait.';
    } catch (Throwable $e) {
        $errors[] = 'Erreur extraction : ' . $e->getMessage();
    }
} else {
    $log[] = 'ℹ️  vendor.tar.gz absent — extraction ignorée.';
}

// ── 3. Suppression du cache prod (PHP pur, sans exec) ────────────────────────

function deleteRecursive(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (new FilesystemIterator($path) as $item) {
        deleteRecursive((string) $item);
    }
    @rmdir($path);
}

$cacheDir = $appDir . '/var/cache/prod';
if (is_dir($cacheDir)) {
    $log[] = '🗑️  Suppression de var/cache/prod…';
    deleteRecursive($cacheDir);
    $log[] = '✓ Cache supprimé.';
}

// ── 4. Schéma DB + cache:warmup (via exec si disponible) ─────────────────────

if (function_exists('exec')) {
    $commands = [
        "cd {$appDir} && php bin/console doctrine:schema:update --force --no-interaction --env=prod",
        "cd {$appDir} && php bin/console cache:clear --env=prod --no-debug",
        "cd {$appDir} && php bin/console cache:warmup --env=prod --no-debug",
    ];
    foreach ($commands as $cmd) {
        $out  = [];
        $code = 0;
        exec("{$cmd} 2>&1", $out, $code);
        $log[] = implode("\n", $out);
        if ($code !== 0) {
            $errors[] = "Erreur (code {$code}) : {$cmd}";
        }
    }
} else {
    $log[] = 'ℹ️  exec() désactivé — cache:warmup ignoré (généré à la première requête).';
}

// ── 5. Réponse ───────────────────────────────────────────────────────────────

http_response_code(empty($errors) ? 200 : 500);
header('Content-Type: application/json');
echo json_encode(
    [
        'success' => empty($errors),
        'message' => empty($errors)
            ? 'Post-déploiement terminé avec succès'
            : 'Post-déploiement terminé avec des erreurs',
        'errors'  => $errors,
        'output'  => $log,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);
