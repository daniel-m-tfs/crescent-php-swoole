<?php

/**
 * Rotas WebSocket do módulo Chat.
 *
 * Incluído por src/chat/init.php, tem acesso à variável $app.
 *
 * Requer modo Swoole — inclua apenas em swoole.php.
 *
 * ─── Protocolo de mensagens ───────────────────────────────────────────────────
 *
 * /ws/chat  (canal público de chat)
 *
 *   Cliente → servidor:
 *     {"event": "message", "text": "Olá!"}
 *     {"event": "ping"}
 *
 *   Servidor → cliente:
 *     {"event": "connected",   "fd": 3, "message": "...", "online": 2}
 *     {"event": "user_joined", "fd": 3, "online": 2}
 *     {"event": "user_left",   "fd": 3, "online": 1}
 *     {"event": "message",     "from": 3, "text": "Olá!"}
 *     {"event": "pong"}
 *     {"event": "error",       "message": "..."}
 *
 * /ws/notify/:channel  (canal de notificações — somente leitura pelo cliente)
 *
 *   Servidor → cliente:
 *     {"event": "subscribed", "channel": "orders"}
 *     {"event": "notify",     "channel": "orders", "data": {...}}
 */

use Crescent\Core\WsContext;
use App\Chat\Controllers\ChatWsController;

// ─── Canal de chat público ────────────────────────────────────────────────────

$app->ws(
    path: '/ws/chat',

    onOpen:    fn(WsContext $ctx)              => ChatWsController::onOpen($ctx),
    onMessage: fn(WsContext $ctx, string $raw) => ChatWsController::onMessage($ctx, $raw),
    onClose:   fn(WsContext $ctx)              => ChatWsController::onClose($ctx),
);

// ─── Canal de notificações ────────────────────────────────────────────────────

$app->ws(
    path: '/ws/notify/:channel',

    onOpen:    fn(WsContext $ctx)              => ChatWsController::onNotifyOpen($ctx),
    onMessage: fn(WsContext $ctx, string $raw) => ChatWsController::onNotifyMessage($ctx, $raw),
    onClose:   fn(WsContext $ctx)              => ChatWsController::onNotifyClose($ctx),
);
