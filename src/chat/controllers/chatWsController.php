<?php

namespace App\Chat\Controllers;

use Crescent\Core\WsContext;

/**
 * Controller WebSocket do módulo Chat.
 *
 * Cada método público corresponde a um evento de uma rota WebSocket registrada
 * em src/chat/routes/chatWsRoutes.php.
 *
 * ─── Canal /ws/chat ───────────────────────────────────────────────────────────
 *   onOpen($ctx)              — nova conexão
 *   onMessage($ctx, $raw)     — mensagem recebida
 *   onClose($ctx)             — conexão encerrada
 *
 * ─── Canal /ws/notify/:channel ───────────────────────────────────────────────
 *   onNotifyOpen($ctx)        — inscrição no canal
 *   onNotifyMessage($ctx, $r) — somente leitura; devolve erro
 *   onNotifyClose($ctx)       — saída do canal
 */
class ChatWsController
{
    /**
     * Mapa de fd → número amigável de usuário.
     * Mantido em memória no worker do Swoole durante toda a vida do processo.
     *
     * @var array<int, int>  [fd => userNumber]
     */
    private static array $users   = [];
    private static int   $counter = 0;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Registra uma nova conexão e retorna o número amigável atribuído. */
    private static function registerUser(int $fd): int
    {
        self::$counter++;
        self::$users[$fd] = self::$counter;
        return self::$counter;
    }

    /** Remove a conexão e retorna o número amigável que ela tinha. */
    private static function removeUser(int $fd): int
    {
        $num = self::$users[$fd] ?? $fd;
        unset(self::$users[$fd]);
        return $num;
    }

    /** Retorna o número amigável de um fd já registrado. */
    private static function userNum(int $fd): int
    {
        return self::$users[$fd] ?? $fd;
    }
    // ─── /ws/chat ─────────────────────────────────────────────────────────────

    public static function onOpen(WsContext $ctx): void
    {
        $num    = self::registerUser($ctx->fd);
        $online = $ctx->connectionCount();

        // Confirma a conexão somente para o cliente recém-chegado
        $ctx->push([
            'event'   => 'connected',
            'user'    => $num,
            'message' => 'Bem-vindo ao chat!',
            'online'  => $online,
        ]);

        // Avisa os outros usuários
        $ctx->broadcast(
            data:       ['event' => 'user_joined', 'user' => $num, 'online' => $online],
            excludeFds: [$ctx->fd],
        );

        echo "[Chat] usuário #{$num} (fd:{$ctx->fd}) entrou | online: {$online}\n";
    }

    public static function onMessage(WsContext $ctx, string $raw): void
    {
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            $ctx->push(['event' => 'error', 'message' => 'Payload deve ser JSON válido']);
            return;
        }

        $event = $payload['event'] ?? 'message';
        $num   = self::userNum($ctx->fd);

        match ($event) {
            // Mensagem de texto: faz broadcast para todos
            'message' => $ctx->broadcast([
                'event' => 'message',
                'from'  => $num,
                'text'  => htmlspecialchars((string)($payload['text'] ?? ''), ENT_QUOTES),
            ]),

            // Heartbeat
            'ping' => $ctx->push(['event' => 'pong']),

            default => $ctx->push([
                'event'   => 'error',
                'message' => "Evento '{$event}' não reconhecido",
            ]),
        };
    }

    public static function onClose(WsContext $ctx): void
    {
        $num    = self::removeUser($ctx->fd);
        $online = max(0, $ctx->connectionCount() - 1);

        $ctx->broadcast(
            data: ['event' => 'user_left', 'user' => $num, 'online' => $online],
        );

        echo "[Chat] usuário #{$num} (fd:{$ctx->fd}) saiu | online: {$online}\n";
    }

    // ─── /ws/notify/:channel ─────────────────────────────────────────────────

    public static function onNotifyOpen(WsContext $ctx): void
    {
        $channel = $ctx->params['channel'];

        // Armazena o canal no estado da conexão para uso no onClose
        $ctx->state['channel'] = $channel;

        $ctx->push([
            'event'   => 'subscribed',
            'channel' => $channel,
        ]);

        echo "[Notify] #{$ctx->fd} inscrito em '{$channel}'\n";
    }

    public static function onNotifyMessage(WsContext $ctx, string $raw): void
    {
        // Canal de notificações é somente push (servidor → cliente)
        $ctx->push(['event' => 'error', 'message' => 'Canal somente leitura']);
    }

    public static function onNotifyClose(WsContext $ctx): void
    {
        $channel = $ctx->state['channel'] ?? '?';
        echo "[Notify] #{$ctx->fd} saiu de '{$channel}'\n";
    }
}
