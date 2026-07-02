<?php

namespace Crescent\Core;

/**
 * Encapsula a requisição HTTP de entrada.
 *
 * Propriedades disponíveis no contexto:
 *   $ctx->request->method     — GET, POST, PUT, PATCH, DELETE …
 *   $ctx->request->path       — caminho sem query string
 *   $ctx->request->query      — parâmetros ?key=value
 *   $ctx->request->body       — dados do body (JSON ou form)
 *   $ctx->request->headers    — headers normalizados em lowercase
 *   $ctx->request->ip         — IP do cliente
 */
class Request
{
    public string $method;
    public string $uri;
    public string $path;
    public array  $query   = [];
    public mixed  $body    = [];
    public string $rawBody = '';
    public array  $headers = [];
    public string $contentType = '';
    public string $ip = '127.0.0.1';

    public function __construct()
    {
        $this->method      = $this->resolveMethod();
        $this->uri         = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path        = rtrim(parse_url($this->uri, PHP_URL_PATH) ?? '/', '/') ?: '/';
        $this->headers     = $this->parseHeaders();
        $this->contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $this->query       = $_GET;
        $this->rawBody     = file_get_contents('php://input') ?: '';
        $this->body        = $this->parseBody();
        $this->ip          = $this->resolveIp();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Retorna o valor de um header (case-insensitive). */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Verifica se o Content-Type é JSON. */
    public function isJson(): bool
    {
        return str_contains($this->contentType, 'application/json');
    }

    /** Retorna o token Bearer da header Authorization, ou null. */
    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * Suporta spoofing de método via _method no body (útil para forms HTML).
     */
    private function resolveMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = strtoupper($_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '');
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        // Headers especiais que não têm prefixo HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }
        return $headers;
    }

    private function parseBody(): mixed
    {
        if (str_contains($this->contentType, 'application/json')) {
            if ($this->rawBody === '') {
                return [];
            }
            return json_decode($this->rawBody, true) ?? [];
        }

        if (str_contains($this->contentType, 'application/x-www-form-urlencoded')) {
            parse_str($this->rawBody, $data);
            return $data;
        }

        // multipart/form-data — PHP já popula $_POST e $_FILES
        if (!empty($_POST)) {
            return $_POST;
        }

        return [];
    }

    private function resolveIp(): string
    {
        // Só confia em X-Forwarded-For / X-Real-IP se TRUSTED_PROXY estiver
        // configurado no .env. Sem essa variável o header pode ser forjado
        // pelo cliente e enganar o rate limiter.
        $trustedProxy = \Crescent\Utils\Env::get('TRUSTED_PROXY', '');

        if ($trustedProxy) {
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

            // Verifica se a requisição vem do proxy confiável configurado
            if ($remoteAddr === $trustedProxy || str_starts_with($remoteAddr, $trustedProxy)) {
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    // X-Forwarded-For pode conter lista; o IP real é o primeiro
                    return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
                }
                if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                    return trim($_SERVER['HTTP_X_REAL_IP']);
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
