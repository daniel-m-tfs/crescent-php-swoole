<?php

namespace Crescent;

use Crescent\Core\Router;
use Crescent\Core\Request;
use Crescent\Core\Response;
use Crescent\Core\Context;

/**
 * Classe principal do CrescentPHP.
 *
 * Uso em app.php:
 *
 *   $app = require __DIR__ . '/crescent/init.php';
 *
 *   $app->use(Cors::handle());
 *   $app->use(Security::handle());
 *
 *   $app->get('/', fn($ctx) => $ctx->json(['status' => 'ok']));
 *
 *   require __DIR__ . '/src/users/routes/usersRoutes.php';
 *
 *   $app->run();
 */
class App
{
    private Router $router;
    private array  $globalMiddlewares = [];

    public function __construct()
    {
        $this->router = new Router();
    }

    // ─── Middlewares globais ──────────────────────────────────────────────────

    /**
     * Adiciona um middleware global (executado em todas as rotas).
     *
     * @param callable $middleware  function(Context $ctx, callable $next): void
     */
    public function use(callable $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    // ─── Registro de rotas ────────────────────────────────────────────────────

    /**
     * @param callable|callable[] $handler   Handler ou array [middleware, ..., handler]
     */
    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Registra a mesma rota para múltiplos métodos HTTP.
     *
     * @param string[] $methods
     */
    public function route(array $methods, string $path, callable|array $handler, array $middlewares = []): void
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $middlewares);
        }
    }

    /**
     * Agrupa rotas com prefixo e/ou middlewares compartilhados.
     *
     * Uso:
     *   $app->group('/api', function (App $app) {
     *       $app->get('/users', ...);    // → /api/users
     *   }, [Auth::required()]);
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $this->router->group($prefix, function (Router $router) use ($callback, $middlewares): void {
            // Cria "sub-app" que proxia para o mesmo router com o contexto de grupo
            $proxy = new GroupProxy($router, $middlewares);
            $callback($proxy);
        }, $middlewares);
    }

    // ─── Execução ─────────────────────────────────────────────────────────────

    /**
     * Processa a requisição atual e envia a resposta.
     * Deve ser chamado uma única vez, no final de app.php.
     */
    public function run(): void
    {
        $request  = new Request();
        $response = new Response();

        $match = $this->router->match($request->method, $request->path);

        if ($match === null) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $isApi  = str_starts_with($request->path, '/api/')
                   || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'));
            if ($isApi) {
                $response->json(['error' => 'Rota não encontrada'], 404);
            } else {
                http_response_code(404);
                include APP_ROOT . '/src/shared/views/404.php';
            }
            return;
        }

        $ctx = new Context($request, $response, $match['params']);

        $middlewares = array_merge($this->globalMiddlewares, $match['middlewares']);
        $handler     = $match['handler'];

        // Constrói a cadeia de middlewares de dentro para fora
        $chain = function () use ($ctx, $handler): void {
            $result = $handler($ctx);
            $this->handleReturn($result, $ctx);
        };

        foreach (array_reverse($middlewares) as $mw) {
            $innerChain = $chain;
            $chain = static function () use ($mw, $ctx, $innerChain): void {
                $mw($ctx, $innerChain);
            };
        }

        try {
            $chain();
        } catch (\Throwable $e) {
            $this->handleError($e, $ctx);
        }
    }

    // ─── Debug ────────────────────────────────────────────────────────────────

    /** Lista todas as rotas registradas. */
    public function routes(): array
    {
        return $this->router->getRoutes();
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares): void
    {
        // Suporte a array [Middleware::handle(), ..., fn($ctx)=>...]
        if (is_array($handler)) {
            $routeMiddlewares = array_slice($handler, 0, -1);
            $finalHandler     = end($handler);
            $middlewares      = array_merge($middlewares, $routeMiddlewares);
            $handler          = $finalHandler;
        }

        $this->router->add($method, $path, $handler, $middlewares);
    }

    /**
     * Trata o valor de retorno do handler automaticamente.
     *
     * - array / object  → JSON
     * - string          → HTML
     * - null / void     → ignora (handler enviou a resposta diretamente)
     */
    private function handleReturn(mixed $result, Context $ctx): void
    {
        if ($ctx->response->isSent() || $result === null) {
            return;
        }

        if (is_array($result) || is_object($result)) {
            $ctx->json($result);
        } elseif (is_string($result)) {
            $ctx->html($result);
        }
    }

    private function handleError(\Throwable $e, Context $ctx): void
    {
        $isDev = \Crescent\Utils\Env::isDevelopment();

        if ($ctx->response->isSent()) {
            return;
        }

        $payload = ['error' => 'Erro interno do servidor'];

        if ($isDev) {
            $payload['message'] = $e->getMessage();
            $payload['file']    = $e->getFile();
            $payload['line']    = $e->getLine();
            $payload['trace']   = explode("\n", $e->getTraceAsString());
        }

        $ctx->json($payload, 500);
    }
}

// ─── GroupProxy ───────────────────────────────────────────────────────────────

/**
 * Proxy usado internamente pelo group() para registrar rotas
 * no Router com o prefixo e middlewares corretos já aplicados.
 *
 * @internal
 */
class GroupProxy
{
    public function __construct(
        private readonly Router $router,
        private readonly array  $groupMiddlewares = []
    ) {}

    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    private function add(string $method, string $path, callable|array $handler, array $routeMiddlewares): void
    {
        if (is_array($handler)) {
            $extra   = array_slice($handler, 0, -1);
            $handler = end($handler);
            $routeMiddlewares = array_merge($routeMiddlewares, $extra);
        }

        $this->router->add($method, $path, $handler, array_merge($this->groupMiddlewares, $routeMiddlewares));
    }
}
