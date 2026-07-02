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
 * Registra as mesmas rotas HTTP de app.php + rotas WebSocket,
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
$app->use(Logger::handle(options: ['echo' => true])); // imprime no console em Swoole

// ─── Rotas HTTP ───────────────────────────────────────────────────────────────

$app->get('/', function ($ctx) {
    return $ctx->json([
        'framework' => 'CrescentPHP',
        'mode'      => 'swoole',
        'status'    => 'running',
        'env'       => \Crescent\Utils\Env::get('APP_ENV', 'development'),
    ]);
});

// Módulos
require __DIR__ . '/src/users/routes/usersRoutes.php';
require __DIR__ . '/src/auth/init.php';

// ─── Rotas WebSocket ──────────────────────────────────────────────────────────

use Crescent\Core\WsContext;

/**
 * Canal de chat em tempo real — ws://<host>:<port>/ws/chat
 *
 * Protocolo de mensagens (JSON):
 *   Cliente → servidor: {"event": "message", "text": "Olá!"}
 *   Servidor → cliente: {"event": "message", "from": 3, "text": "Olá!"}
 *   Servidor → cliente: {"event": "user_joined", "fd": 3, "online": 2}
 *   Servidor → cliente: {"event": "user_left",   "fd": 3, "online": 1}
 */
$app->ws(
    path: '/ws/chat',

    onOpen: function (WsContext $ctx): void {
        $online = $ctx->connectionCount();

        // Confirma conexão ao cliente que chegou
        $ctx->push([
            'event'   => 'connected',
            'fd'      => $ctx->fd,
            'message' => 'Bem-vindo ao chat!',
            'online'  => $online,
        ]);

        // Notifica os demais
        $ctx->broadcast(
            data:       ['event' => 'user_joined', 'fd' => $ctx->fd, 'online' => $online],
            excludeFds: [$ctx->fd],
        );

        echo "[WS] #{$ctx->fd} conectou  | online: {$online}\n";
    },

    onMessage: function (WsContext $ctx, string $rawData): void {
        $payload = json_decode($rawData, true);

        if (!is_array($payload)) {
            $ctx->push(['event' => 'error', 'message' => 'JSON inválido']);
            return;
        }

        $event = $payload['event'] ?? 'message';

        match ($event) {
            'message' => $ctx->broadcast([
                'event' => 'message',
                'from'  => $ctx->fd,
                'text'  => htmlspecialchars((string)($payload['text'] ?? ''), ENT_QUOTES),
            ]),

            'ping' => $ctx->push(['event' => 'pong']),

            default => $ctx->push(['event' => 'error', 'message' => "Evento '{$event}' desconhecido"]),
        };
    },

    onClose: function (WsContext $ctx): void {
        $online = $ctx->connectionCount() - 1; // subtrai o próprio fd (já fechou)

        $ctx->broadcast(
            data: ['event' => 'user_left', 'fd' => $ctx->fd, 'online' => max(0, $online)],
        );

        echo "[WS] #{$ctx->fd} desconectou | online: {$online}\n";
    },
);

/**
 * Canal de notificações — ws://<host>:<port>/ws/notify/:channel
 *
 * Uso: conectar em /ws/notify/orders para receber notificações do canal "orders".
 */
$app->ws(
    path: '/ws/notify/:channel',

    onOpen: function (WsContext $ctx): void {
        $channel = $ctx->params['channel'];
        $ctx->state['channel'] = $channel;

        $ctx->push([
            'event'   => 'subscribed',
            'channel' => $channel,
        ]);

        echo "[WS] #{$ctx->fd} inscrito no canal '{$channel}'\n";
    },

    onMessage: function (WsContext $ctx, string $rawData): void {
        // Canal de notificações é somente leitura para clientes
        $ctx->push(['event' => 'error', 'message' => 'Canal somente leitura']);
    },

    onClose: function (WsContext $ctx): void {
        echo "[WS] #{$ctx->fd} saiu do canal '{$ctx->state['channel']}'\n";
    },
);

// ─── Start ────────────────────────────────────────────────────────────────────

$host = \Crescent\Utils\Env::get('SWOOLE_HOST', '0.0.0.0');
$port = (int)\Crescent\Utils\Env::get('SWOOLE_PORT', '9501');

$app->runWithSwoole($host, $port);
