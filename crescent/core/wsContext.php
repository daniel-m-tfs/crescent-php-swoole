<?php

namespace Crescent\Core;

/**
 * Contexto de uma conexão WebSocket.
 *
 * Passado para os callbacks onOpen, onMessage e onClose.
 *
 * Uso:
 *   $app->ws('/ws/chat',
 *       onOpen:    fn(WsContext $ctx) => $ctx->push(['event' => 'connected']),
 *       onMessage: fn(WsContext $ctx, string $data) => $ctx->broadcast($data),
 *       onClose:   fn(WsContext $ctx) => null,
 *   );
 */
class WsContext
{
    /** File descriptor (ID único da conexão no Swoole). */
    public readonly int $fd;

    /** Caminho de URL da conexão (ex.: /ws/chat). */
    public readonly string $path;

    /** Parâmetros de rota capturados (/:room → params['room']). */
    public readonly array $params;

    /** Query string da URL de upgrade (?token=abc → query['token']). */
    public readonly array $query;

    /**
     * Bag de estado livre para compartilhar dados entre eventos da mesma conexão.
     * Ex.: $ctx->state['user'] = $payload;
     */
    public array $state = [];

    public function __construct(
        private readonly \Swoole\WebSocket\Server $server,
        int    $fd,
        string $path,
        array  $params = [],
        array  $query  = [],
    ) {
        $this->fd     = $fd;
        $this->path   = $path;
        $this->params = $params;
        $this->query  = $query;
    }

    // ─── Envio ────────────────────────────────────────────────────────────────

    /**
     * Envia uma mensagem para ESTA conexão.
     *
     * @param string|array $data  String raw ou array → serializado como JSON
     */
    public function push(string|array $data): void
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($this->server->isEstablished($this->fd)) {
            $this->server->push($this->fd, $data);
        }
    }

    /**
     * Faz broadcast para TODAS as conexões abertas no servidor.
     *
     * @param string|array $data        Mensagem a enviar
     * @param int[]        $excludeFds  FDs a excluir do broadcast (ex.: o remetente)
     */
    public function broadcast(string|array $data, array $excludeFds = []): void
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        foreach ($this->server->connections as $fd) {
            if (
                $this->server->isEstablished($fd) &&
                !in_array($fd, $excludeFds, true)
            ) {
                $this->server->push($fd, $data);
            }
        }
    }

    /**
     * Encerra esta conexão WebSocket.
     */
    public function close(): void
    {
        $this->server->close($this->fd);
    }

    /**
     * Retorna o número de conexões WebSocket ativas no momento.
     */
    public function connectionCount(): int
    {
        $count = 0;
        foreach ($this->server->connections as $fd) {
            if ($this->server->isEstablished($fd)) {
                $count++;
            }
        }
        return $count;
    }
}
