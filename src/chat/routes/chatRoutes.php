<?php

/**
 * Rotas HTTP do módulo Chat.
 *
 * Incluído por src/chat/init.php, tem acesso à variável $app.
 */

use App\Chat\Controllers\ChatController;

// Interface web do chat
$app->get('/chat', fn($ctx) => ChatController::index($ctx));

// Status da sala (útil para monitoramento)
$app->get('/api/chat/status', fn($ctx) => ChatController::status($ctx));
