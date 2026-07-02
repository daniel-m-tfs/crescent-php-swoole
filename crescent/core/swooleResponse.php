<?php

namespace Crescent\Core;

/**
 * Adapta \Swoole\Http\Response para Crescent\Core\Response.
 *
 * Sobrescreve o método send() para que os headers e o body
 * sejam enviados via API do Swoole em vez de header()/echo.
 */
class SwooleResponse extends Response
{
    public function __construct(private readonly \Swoole\Http\Response $swoole) {}

    /**
     * Envia a resposta usando a API do Swoole.
     * Chamado internamente por json(), view(), text(), html(), redirect() e noContent().
     */
    protected function send(string $body): void
    {
        if ($this->sent) {
            return;
        }

        $this->swoole->status($this->statusCode);

        foreach ($this->headers as $name => $value) {
            $this->swoole->header($name, $value);
        }

        $this->swoole->end($body);
        $this->sent = true;
    }
}
