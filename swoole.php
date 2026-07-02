<?php

/**
 * swoole.php — Entry point do CrescentPHP em modo Swoole (Docker).
 *
 * Uso:
 *   php swoole.php
 *
 * Ou via Docker:
 *   docker compose up
 *
 * Registra as mesmas rotas HTTP de app.php + rotas WebSocket dos módulos,
 * e inicia o servidor Swoole em vez do servidor built-in do PHP.
 *
 * Para hospedagem tradicional (Apache/Nginx), continue usando app.php.
 */

declare(strict_types=1);

// ─── Bootstrap ────────────────────────────────────────────────────────────────

/** @var \Crescent\App $app */
$app = require __DIR__ . '/crescent/init.php';

// ─── Middlewares globais ───────────────────────────────────────────────────────

use Crescent\Middleware\Cors;
use Crescent\Middleware\Security;
use Crescent\Middleware\Logger;

$app->use(Security::handle());
$app->use(Cors::handle());
$app->use(Logger::handle(options: ['echo' => true]));

// ─── Rotas HTTP ───────────────────────────────────────────────────────────────

$app->get('/', function ($ctx) {
    return $ctx->json([
        'framework' => 'CrescentPHP',
        'mode'      => 'swoole',
        'status'    => 'running',
        'env'       => \Crescent\Utils\Env::get('APP_ENV', 'development'),
    ]);
});

// ─── Módulos (HTTP + WebSocket) ───────────────────────────────────────────────

require __DIR__ . '/src/users/routes/usersRoutes.php';
require __DIR__ . '/src/auth/init.php';
require __DIR__ . '/src/chat/init.php';   // rotas HTTP /chat e WS /ws/chat + /ws/notify/:channel

// ─── Start ────────────────────────────────────────────────────────────────────

$host = \Crescent\Utils\Env::get('SWOOLE_HOST', '0.0.0.0');
$port = (int)\Crescent\Utils\Env::get('SWOOLE_PORT', '9501');

$app->runWithSwoole($host, $port);
