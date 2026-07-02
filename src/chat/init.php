<?php

/**
 * Módulo Chat — inicialização.
 *
 * Registra as rotas HTTP e WebSocket do módulo.
 *
 * Uso em swoole.php:
 *   require __DIR__ . '/src/chat/init.php';
 *
 * Rotas HTTP registradas:
 *   GET  /chat              → interface web do chat
 *   GET  /api/chat/status   → JSON com total de conexões abertas
 *
 * Rotas WebSocket registradas:
 *   ws://<host>:<port>/ws/chat               → canal público de chat
 *   ws://<host>:<port>/ws/notify/:channel    → canal de notificações
 */

require __DIR__ . '/routes/chatRoutes.php';
require __DIR__ . '/routes/chatWsRoutes.php';
