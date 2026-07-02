<?php

/**
 * app.php — Ponto de entrada da aplicação CrescentPHP.
 *
 * Não exponha este arquivo diretamente; o .htaccess redireciona
 * todas as requisições para cá.
 */

declare(strict_types=1);

// ─── Servidor PHP built-in: serve arquivos estáticos de /public/ ─────────────
// Quando rodando com `php -S`, retorna false para entregar o arquivo diretamente
// sem passar pelo framework. Em produção o Apache/Nginx faz isso via .htaccess.
if (PHP_SAPI === 'cli-server') {
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $file = __DIR__ . $uri;
    if ($uri !== '/' && is_file($file)) {
        return false; // Deixa o servidor built-in servir o arquivo
    }
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

/** @var \Crescent\App $app */
$app = require __DIR__ . '/crescent/init.php';

// ─── Middlewares globais ───────────────────────────────────────────────────────

use Crescent\Middleware\Cors;
use Crescent\Middleware\Security;
use Crescent\Middleware\Logger;

$app->use(Security::handle());
$app->use(Cors::handle());
$app->use(Logger::handle());

// ─── Rotas principais ──────────────────────────────────────────────────────────

$app->get('/', function ($ctx) {
    return $ctx->json([
        'framework' => 'CrescentPHP',
        'status'    => 'running',
        'env'       => \Crescent\Utils\Env::get('APP_ENV', 'development'),
    ]);
});

// ─── Módulos ───────────────────────────────────────────────────────────────────

// Cada módulo registra suas próprias rotas recebendo $app como variável.
// Exemplo: require __DIR__ . '/src/users/routes/usersRoutes.php';

require __DIR__ . '/src/users/routes/usersRoutes.php';
require __DIR__ . '/src/auth/init.php';

// ─── Start ────────────────────────────────────────────────────────────────────

$app->run();
