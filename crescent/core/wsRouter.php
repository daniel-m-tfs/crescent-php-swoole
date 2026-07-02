<?php

namespace Crescent\Core;

/**
 * Roteador de conexões WebSocket.
 *
 * Mapeia caminhos de URL para conjuntos de callbacks de eventos.
 * Suporta rotas estáticas e dinâmicas com parâmetros (/:param).
 *
 * Uso em App:
 *   $app->ws('/ws/chat', onOpen: ..., onMessage: ..., onClose: ...);
 */
class WsRouter
{
    private array $routes = [];

    // ─── Registro ─────────────────────────────────────────────────────────────

    public function add(
        string    $path,
        ?callable $onOpen    = null,
        ?callable $onMessage = null,
        ?callable $onClose   = null,
    ): void {
        $this->routes[] = [
            'path'      => $path,
            'pattern'   => $this->buildPattern($path),
            'paramNames'=> $this->extractParamNames($path),
            'open'      => $onOpen,
            'message'   => $onMessage,
            'close'     => $onClose,
        ];
    }

    // ─── Matching ─────────────────────────────────────────────────────────────

    /**
     * Retorna o conjunto de callbacks para o caminho dado, ou null se não encontrado.
     *
     * @return array{open: ?callable, message: ?callable, close: ?callable, params: array}|null
     */
    public function match(string $path): ?array
    {
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = [];
                foreach ($route['paramNames'] as $name) {
                    if (isset($matches[$name])) {
                        $params[$name] = urldecode($matches[$name]);
                    }
                }

                return [
                    'open'    => $route['open'],
                    'message' => $route['message'],
                    'close'   => $route['close'],
                    'params'  => $params,
                ];
            }
        }

        return null;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildPattern(string $path): string
    {
        $path    = rtrim($path, '/') ?: '/';
        $pattern = preg_replace_callback('/:([A-Za-z_][A-Za-z0-9_]*)/', static function ($m) {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);

        return '/^' . str_replace('/', '\\/', $pattern) . '\/?$/u';
    }

    private function extractParamNames(string $path): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $path, $matches);
        return $matches[1];
    }
}
