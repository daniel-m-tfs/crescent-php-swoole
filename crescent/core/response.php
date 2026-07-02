<?php

namespace Crescent\Core;

/**
 * Constrói e envia a resposta HTTP.
 *
 * Uso via Context:
 *   $ctx->json(['ok' => true]);
 *   $ctx->view('users/users_all.php', compact('users'));
 *   $ctx->redirect('/login');
 */
class Response
{
    private int    $statusCode = 200;
    private array  $headers    = [];
    private bool   $sent       = false;

    // ─── Fluent setters ──────────────────────────────────────────────────────

    public function status(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    // ─── Response types ──────────────────────────────────────────────────────

    /** Envia JSON. */
    public function json(mixed $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->header('Content-Type', 'application/json; charset=utf-8');
        $this->send(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    /** Renderiza uma view PHP e envia como HTML. */
    public function view(string $template, array $data = [], int $status = 200): void
    {
        $this->statusCode = $status;
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $content = $this->renderView($template, $data);
        $this->send($content);
    }

    /** Envia texto puro. */
    public function text(string $content, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->header('Content-Type', 'text/plain; charset=utf-8');
        $this->send($content);
    }

    /** Envia HTML sem carregar arquivo de view. */
    public function html(string $content, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->send($content);
    }

    /** Redireciona para outra URL. */
    public function redirect(string $url, int $status = 302): void
    {
        $this->statusCode = $status;
        $this->header('Location', $url);
        $this->send('');
    }

    /** Resposta vazia (204 No Content). */
    public function noContent(): void
    {
        $this->statusCode = 204;
        $this->send('');
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    private function renderView(string $template, array $data = []): string
    {
        // O template pode ser relativo a /src ou absoluto
        $viewPath = $this->resolveViewPath($template);

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View não encontrada: {$template}");
        }

        // Expõe variáveis para o template
        extract($data, EXTR_SKIP);

        ob_start();
        include $viewPath;
        return ob_get_clean();
    }

    private function resolveViewPath(string $template): string
    {
        // Caminho absoluto passado diretamente
        if (str_starts_with($template, '/')) {
            return $template;
        }

        $root = defined('APP_ROOT') ? APP_ROOT : dirname(dirname(__DIR__));

        // Tenta em /src/<template>
        $inSrc = $root . '/src/' . $template;
        if (file_exists($inSrc)) {
            return $inSrc;
        }

        // Tenta relativo à raiz
        return $root . '/' . $template;
    }

    private function send(string $body): void
    {
        if ($this->sent) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $body;
        $this->sent = true;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }
}
