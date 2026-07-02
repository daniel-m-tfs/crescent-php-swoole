<?php

namespace Crescent\Core;

/**
 * Adapta \Swoole\Http\Request para Crescent\Core\Request.
 *
 * Substitui o construtor original (que lê $_SERVER, $_GET, etc.)
 * pelo contexto da requisição Swoole, mantendo a mesma interface
 * que middlewares e handlers esperam.
 */
class SwooleRequest extends Request
{
    /**
     * @param \Swoole\Http\Request $swoole  Requisição recebida pelo Swoole
     */
    public function __construct(\Swoole\Http\Request $swoole)
    {
        // Não chama parent::__construct() — ele leria $_SERVER vazio em CLI
        $server  = $swoole->server  ?? [];
        $headers = array_change_key_case($swoole->header ?? [], CASE_LOWER);

        $this->headers     = $headers;
        $this->contentType = $headers['content-type'] ?? '';
        $this->uri         = $server['request_uri'] ?? '/';
        $this->path        = rtrim(parse_url($this->uri, PHP_URL_PATH) ?? '/', '/') ?: '/';
        $this->query       = $swoole->get ?? [];
        $this->rawBody     = $swoole->rawContent() ?: '';
        $this->body        = $this->parseSwooleBody($swoole->post ?? []);
        $this->ip          = $this->resolveSwooleIp($server, $headers);

        // Resolve método, com suporte a spoofing via _method / X-HTTP-Method-Override
        $this->method = strtoupper($server['request_method'] ?? 'GET');

        if ($this->method === 'POST') {
            $override = strtoupper(
                ($swoole->post ?? [])['_method'] ??
                $headers['x-http-method-override'] ??
                ''
            );
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $this->method = $override;
            }
        }
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    private function parseSwooleBody(array $postData): mixed
    {
        // form-data / urlencoded já foi decodificado pelo Swoole
        if (!empty($postData)) {
            return $postData;
        }

        if (empty($this->rawBody)) {
            return [];
        }

        if (str_contains($this->contentType, 'application/json')) {
            $decoded = json_decode($this->rawBody, true);
            return is_array($decoded) ? $decoded : [];
        }

        $data = [];
        parse_str($this->rawBody, $data);
        return $data;
    }

    private function resolveSwooleIp(array $server, array $headers): string
    {
        foreach (['x-forwarded-for', 'x-real-ip'] as $h) {
            if (!empty($headers[$h])) {
                return trim(explode(',', $headers[$h])[0]);
            }
        }
        return $server['remote_addr'] ?? '127.0.0.1';
    }
}
