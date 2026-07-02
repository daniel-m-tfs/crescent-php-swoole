<?php

namespace Crescent\Core;

class Router
{
    private array $routes = [];
    private array $groupMiddlewares = [];
    private string $prefix = '';

    // ─── Route registration ───────────────────────────────────────────────────

    public function add(string $method, string $path, callable $handler, array $middlewares = []): void
    {
        $fullPath = $this->prefix . $path;

        $this->routes[] = [
            'method'      => strtoupper($method),
            'path'        => $fullPath,
            'handler'     => $handler,
            'middlewares' => array_merge($this->groupMiddlewares, $middlewares),
            'pattern'     => $this->buildPattern($fullPath),
            'paramNames'  => $this->extractParamNames($fullPath),
        ];
    }

    // ─── Group / prefix ───────────────────────────────────────────────────────

    /**
     * Agrupa rotas com um prefixo e/ou middlewares compartilhados.
     *
     * @param string        $prefix
     * @param callable      $callback   function(Router $router): void
     * @param callable[]    $middlewares
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $prevPrefix      = $this->prefix;
        $prevMiddlewares = $this->groupMiddlewares;

        $this->prefix           = $prevPrefix . $prefix;
        $this->groupMiddlewares = array_merge($prevMiddlewares, $middlewares);

        $callback($this);

        $this->prefix           = $prevPrefix;
        $this->groupMiddlewares = $prevMiddlewares;
    }

    // ─── Matching ─────────────────────────────────────────────────────────────

    public function match(string $method, string $path): ?array
    {
        // Normalize trailing slash
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                $params = [];
                foreach ($route['paramNames'] as $name) {
                    if (isset($matches[$name])) {
                        $params[$name] = urldecode($matches[$name]);
                    }
                }

                return [
                    'handler'     => $route['handler'],
                    'middlewares' => $route['middlewares'],
                    'params'      => $params,
                ];
            }
        }

        return null;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Converte "/users/:id/posts/:postId" em regex com grupos nomeados.
     */
    private function buildPattern(string $path): string
    {
        $path    = rtrim($path, '/') ?: '/';
        $pattern = preg_replace_callback('/:([A-Za-z_][A-Za-z0-9_]*)/', function ($m) {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);

        $pattern = str_replace('/', '\\/', $pattern);

        return '/^' . $pattern . '\/?$/u';
    }

    /**
     * Extrai os nomes dos parâmetros de uma rota. Ex: "/users/:id" → ["id"]
     */
    private function extractParamNames(string $path): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $path, $matches);
        return $matches[1];
    }

    // ─── Debug ────────────────────────────────────────────────────────────────

    /** Retorna todas as rotas registradas (útil para debug/CLI). */
    public function getRoutes(): array
    {
        return array_map(fn ($r) => [
            'method' => $r['method'],
            'path'   => $r['path'],
        ], $this->routes);
    }
}
