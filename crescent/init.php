<?php

/**
 * Bootstrap do CrescentPHP.
 *
 * Deve ser o primeiro require de app.php:
 *
 *   $app = require __DIR__ . '/crescent/init.php';
 *
 * O que faz:
 *  1. Define constantes APP_ROOT e CRESCENT_ROOT
 *  2. Registra o autoloader PSR-4 (sem Composer)
 *  3. Carrega o arquivo .env
 *  4. Carrega o arquivo de config do ambiente (config/development.php etc.)
 *  5. Configura error reporting de acordo com o ambiente
 *  6. Retorna a instância de Crescent\App
 */

// ─── Constantes ───────────────────────────────────────────────────────────────

defined('APP_ROOT')      || define('APP_ROOT',      dirname(__DIR__));
defined('CRESCENT_ROOT') || define('CRESCENT_ROOT', __DIR__);
defined('APP_START')     || define('APP_START',     microtime(true));

// ─── Autoloader PSR-4 (sem Composer) ──────────────────────────────────────────

spl_autoload_register(static function (string $class): void {
    /**
     * Mapa de namespaces → diretórios.
     * A ordem importa: mais específico primeiro.
     */
    $map = [
        'Crescent\\Middleware\\' => CRESCENT_ROOT . '/middleware/',
        'Crescent\\Utils\\'      => CRESCENT_ROOT . '/utils/',
        'Crescent\\Core\\'       => CRESCENT_ROOT . '/core/',
        'Crescent\\'             => CRESCENT_ROOT . '/',
        'App\\'                  => APP_ROOT . '/src/',
    ];

    foreach ($map as $prefix => $dir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative  = substr($class, strlen($prefix));
        $parts     = explode('\\', $relative);
        $className = lcfirst(array_pop($parts));        // PascalCase → camelCase filename
        $dirParts  = array_map('strtolower', $parts);   // Namespace parts → lowercase dirs
        $file      = $dir . implode(DIRECTORY_SEPARATOR, [...$dirParts, $className]) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ─── Variáveis de ambiente ────────────────────────────────────────────────────

require_once CRESCENT_ROOT . '/utils/env.php';

\Crescent\Utils\Env::load(APP_ROOT . '/.env');

// ─── Error reporting ──────────────────────────────────────────────────────────

if (\Crescent\Utils\Env::isDevelopment()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// ─── Config do ambiente ───────────────────────────────────────────────────────

$env        = strtolower(\Crescent\Utils\Env::get('APP_ENV', 'development'));
$configFile = APP_ROOT . "/config/{$env}.php";

if (file_exists($configFile)) {
    require_once $configFile;
}

// ─── Timezone ─────────────────────────────────────────────────────────────────

$tz = \Crescent\Utils\Env::get('APP_TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set($tz);

// ─── Helpers de template (component, e, asset) ──────────────────────────────
// Carregado aqui pois exporta funções globais (não são autoloadáveis).
require_once CRESCENT_ROOT . '/utils/view.php';

// ─── Retorna instância da aplicação ───────────────────────────────────────────

require_once CRESCENT_ROOT . '/server.php';

return new \Crescent\App();
