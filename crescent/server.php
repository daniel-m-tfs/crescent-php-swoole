<?php

namespace Crescent;

use Crescent\Core\Router;
use Crescent\Core\Request;
use Crescent\Core\Response;
use Crescent\Core\Context;
use Crescent\Core\SwooleRequest;
use Crescent\Core\SwooleResponse;
use Crescent\Core\WsRouter;
use Crescent\Core\WsContext;

/**
 * Classe principal do CrescentPHP.
 *
 * Uso em app.php (Apache/Nginx):
 *
 *   $app = require __DIR__ . '/crescent/init.php';
 *   $app->use(Cors::handle());
 *   $app->get('/', fn($ctx) => $ctx->json(['status' => 'ok']));
 *   $app->run();
 *
 * Uso em swoole.php (Docker + Swoole):
 *
 *   $app->ws('/ws/chat',
 *       onOpen:    fn(WsContext $ctx) => $ctx->push(['event' => 'connected']),
 *       onMessage: fn(WsContext $ctx, string $data) => $ctx->broadcast($data),
 *   );
 *   $app->runWithSwoole();
 */
class App
{
    private Router   $router;
    private WsRouter $wsRouter;
    private array    $globalMiddlewares = [];

    /** @var array<int, array{ctx: WsContext, route: array}> */
    private array $wsConnections = [];

    public function __construct()
    {
        $this->router   = new Router();
        $this->wsRouter = new WsRouter();
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

    // ─── WebSocket ────────────────────────────────────────────────────────────

    /**
     * Registra uma rota WebSocket.
     *
     * Os callbacks recebem um WsContext como primeiro argumento.
     * onMessage recebe também a string de dados brutos como segundo argumento.
     *
     * Exemplo:
     *   $app->ws('/ws/chat',
     *       onOpen:    fn(WsContext $ctx) => $ctx->push(['event' => 'connected']),
     *       onMessage: fn(WsContext $ctx, string $data) => $ctx->broadcast($data),
     *       onClose:   fn(WsContext $ctx) => null,
     *   );
     */
    public function ws(
        string    $path,
        ?callable $onOpen    = null,
        ?callable $onMessage = null,
        ?callable $onClose   = null,
    ): void {
        $this->wsRouter->add($path, $onOpen, $onMessage, $onClose);
    }

    // ─── Execução ─────────────────────────────────────────────────────────────

    /**
     * Processa a requisição atual via Apache/Nginx/servidor built-in.
     * Deve ser chamado uma única vez, no final de app.php.
     */
    public function run(): void
    {
        $this->dispatch(new Request(), new Response());
    }

    /**
     * Inicia o servidor Swoole (HTTP + WebSocket no mesmo porta).
     * Requer a extensão `swoole` instalada.
     *
     * Deve ser chamado no final de swoole.php.
     */
    public function runWithSwoole(string $host = '0.0.0.0', int $port = 9501): void
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException(
                'A extensão Swoole não está instalada. ' .
                'Instale com: pecl install swoole'
            );
        }

        $isDev   = \Crescent\Utils\Env::isDevelopment();
        $env     = $isDev ? 'development' : 'production';
        $workers = swoole_cpu_num();

        $server = new \Swoole\WebSocket\Server($host, $port);

        $server->set([
            'worker_num'               => $workers,
            'daemonize'                => false,
            'log_file'                 => APP_ROOT . '/logs/swoole.log',
            'log_level'                => \SWOOLE_LOG_WARNING,
            'heartbeat_check_interval' => 30,
            'heartbeat_idle_time'      => 60,
            'open_http_protocol'       => true,
        ]);

        // ── HTTP ──────────────────────────────────────────────────────────────
        $server->on('request', function (
            \Swoole\Http\Request  $swooleReq,
            \Swoole\Http\Response $swooleRes,
        ): void {
            $request  = new SwooleRequest($swooleReq);
            $response = new SwooleResponse($swooleRes);
            $this->dispatch($request, $response);
        });

        // ── WebSocket: nova conexão ───────────────────────────────────────────
        $server->on('open', function (
            \Swoole\WebSocket\Server $server,
            \Swoole\Http\Request     $swooleReq,
        ): void {
            $uri    = $swooleReq->server['request_uri'] ?? '/';
            $path   = rtrim(parse_url($uri, PHP_URL_PATH) ?? '/', '/') ?: '/';
            $query  = $swooleReq->get ?? [];

            $route = $this->wsRouter->match($path);

            if ($route === null) {
                // Caminho sem rota WS registrada → recusa a conexão
                $server->close($swooleReq->fd);
                return;
            }

            $ctx = new WsContext($server, $swooleReq->fd, $path, $route['params'], $query);

            $this->wsConnections[$swooleReq->fd] = ['ctx' => $ctx, 'route' => $route];

            if ($route['open'] !== null) {
                ($route['open'])($ctx);
            }
        });

        // ── WebSocket: mensagem recebida ──────────────────────────────────────
        $server->on('message', function (
            \Swoole\WebSocket\Server $server,
            \Swoole\WebSocket\Frame  $frame,
        ): void {
            $conn = $this->wsConnections[$frame->fd] ?? null;
            if ($conn === null) {
                return;
            }

            if ($conn['route']['message'] !== null) {
                ($conn['route']['message'])($conn['ctx'], $frame->data);
            }
        });

        // ── WebSocket: conexão encerrada ──────────────────────────────────────
        $server->on('close', function (
            \Swoole\WebSocket\Server $server,
            int $fd,
        ): void {
            $conn = $this->wsConnections[$fd] ?? null;

            if ($conn !== null) {
                if ($conn['route']['close'] !== null) {
                    ($conn['route']['close'])($conn['ctx']);
                }
                unset($this->wsConnections[$fd]);
            }
        });

        // ── Boot ──────────────────────────────────────────────────────────────
        echo "\033[32m[CrescentPHP Swoole] Servidor iniciado\033[0m\n";
        echo "\033[36m  ► http://{$host}:{$port}  (HTTP + WebSocket)\033[0m\n";
        echo "\033[36m  ► Ambiente: {$env}  |  Workers: {$workers}\033[0m\n";
        echo "\033[33m  Pressione Ctrl+C para encerrar.\033[0m\n\n";

        $server->start();
    }

    /**
     * Processa uma requisição com os objetos Request/Response fornecidos.
     * Usado por run() (PHP built-in) e runWithSwoole() (adaptadores Swoole).
     */
    public function dispatch(Request $request, Response $response): void
    {
        $match = $this->router->match($request->method, $request->path);

        if ($match === null) {
            $accept = $request->header('accept') ?? '';
            $isApi  = str_starts_with($request->path, '/api/')
                   || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'));
            if ($isApi) {
                $response->json(['error' => 'Rota não encontrada'], 404);
            } else {
                $response->status(404)->view('shared/views/404.php');
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
