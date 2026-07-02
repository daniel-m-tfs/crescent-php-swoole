<?php

namespace Crescent\Core;

/**
 * Contexto da requisição – passado para todos os handlers e middlewares.
 *
 * Atalhos de uso:
 *   $ctx->params->id          — parâmetros de rota (/:id)
 *   $ctx->query->page         — query string (?page=1)
 *   $ctx->body->name          — dados do body
 *   $ctx->json([...])         — responde JSON
 *   $ctx->view('tpl', $data)  — renderiza view
 *   $ctx->state['user']       — compartilha dados entre middlewares
 */
class Context
{
    public Request  $request;
    public Response $response;

    /** Parâmetros de rota capturados (/:id → $ctx->params['id']). */
    public array $params = [];

    /** Query string (?foo=bar → $ctx->query['foo']). */
    public array $query  = [];

    /** Body da requisição (JSON ou form). */
    public mixed $body   = [];

    /**
     * Bag de estado livre para compartilhar dados entre middlewares
     * (ex.: $ctx->state['user'] = $payload).
     */
    public array $state  = [];

    public function __construct(Request $request, Response $response, array $params = [])
    {
        $this->request  = $request;
        $this->response = $response;
        $this->params   = $params;
        $this->query    = $request->query;
        $this->body     = $request->body;
    }

    // ─── Atalhos de resposta ──────────────────────────────────────────────────

    public function json(mixed $data, int $status = 200): void
    {
        $this->response->json($data, $status);
    }

    public function view(string $template, array $data = [], int $status = 200): void
    {
        $this->response->view($template, $data, $status);
    }

    public function text(string $content, int $status = 200): void
    {
        $this->response->text($content, $status);
    }

    public function html(string $content, int $status = 200): void
    {
        $this->response->html($content, $status);
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->response->redirect($url, $status);
    }

    public function noContent(): void
    {
        $this->response->noContent();
    }

    // ─── Fluent setters ──────────────────────────────────────────────────────

    public function status(int $code): static
    {
        $this->response->status($code);
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->response->header($name, $value);
        return $this;
    }

    // ─── Helpers de requisição ────────────────────────────────────────────────

    public function method(): string
    {
        return $this->request->method;
    }

    public function path(): string
    {
        return $this->request->path;
    }

    public function ip(): string
    {
        return $this->request->ip;
    }

    public function bearerToken(): ?string
    {
        return $this->request->bearerToken();
    }

    public function requestHeader(string $name): ?string
    {
        return $this->request->header($name);
    }
}
