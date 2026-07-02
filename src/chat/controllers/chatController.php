<?php

namespace App\Chat\Controllers;

use Crescent\Core\Context;

/**
 * Controller HTTP do módulo Chat.
 *
 * Responsável pela interface web e pelas rotas de API REST do chat.
 */
class ChatController
{
    /**
     * GET /chat
     * Renderiza a interface web do chat.
     */
    public static function index(Context $ctx): void
    {
        $ctx->view('chat/views/chat.php', [
            'title'     => 'Chat',
            'wsUrl'     => self::wsUrl('/ws/chat'),
            'statusUrl' => '/api/chat/status',
        ]);
    }

    /**
     * GET /api/chat/status
     * Retorna informações sobre o servidor de chat (útil para monitoramento).
     */
    public static function status(Context $ctx): void
    {
        $ctx->json([
            'status'  => 'ok',
            'channel' => '/ws/chat',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Monta a URL do WebSocket com base no HOST da requisição.
     * Em produção HTTPS usa wss://, em dev usa ws://.
     */
    private static function wsUrl(string $path): string
    {
        $proto = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'wss' : 'ws';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost:9501';
        return "{$proto}://{$host}{$path}";
    }
}
