# CrescentPHP — Referência para IA

> NÃO publicar em produção. Arquivo de contexto para assistentes de IA.
> PHP 8.1+ | Sem Composer | MVC | PDO singleton | JWT via cookie HttpOnly | Swoole (HTTP assíncrono + WebSocket)

---

## REGRAS CRÍTICAS DO AUTOLOADER

O autoloader resolve `Namespace\Class` → arquivo com `lcfirst(ClassName)`.

| Namespace prefix       | Diretório base        |
|------------------------|-----------------------|
| `Crescent\Middleware\` | `crescent/middleware/`|
| `Crescent\Utils\`      | `crescent/utils/`     |
| `Crescent\Core\`       | `crescent/core/`      |
| `App\`                 | `src/`                |

**Exemplos de resolução:**
- `Crescent\Utils\Str`           → `crescent/utils/str.php`       ← arquivo é `str.php`, NÃO `string.php`
- `Crescent\Utils\Hash`          → `crescent/utils/hash.php`
- `Crescent\Utils\Mailer`        → `crescent/utils/mailer.php`
- `Crescent\Utils\View`          → `crescent/utils/view.php`
- `Crescent\Core\SwooleRequest`  → `crescent/core/swooleRequest.php`
- `Crescent\Core\SwooleResponse` → `crescent/core/swooleResponse.php`
- `Crescent\Core\WsRouter`       → `crescent/core/wsRouter.php`
- `Crescent\Core\WsContext`      → `crescent/core/wsContext.php`
- `App\Users\Controllers\UserController` → `src/users/controllers/userController.php`
- `App\Auth\Models\AuthModel`    → `src/auth/models/authModel.php`

**Regra:** partes do namespace viram diretórios em lowercase; o nome da classe vira o nome do arquivo com `lcfirst` (primeira letra minúscula).

---

## ESTRUTURA DE ARQUIVOS

```
app.php                   Ponto de entrada Apache/Nginx; registra middlewares, rotas, módulos, $app->run()
swoole.php                Ponto de entrada Swoole; mesmas rotas + $app->ws() + $app->runWithSwoole()
crecli.php                CLI
crescent/init.php         Bootstrap: define APP_ROOT/CRESCENT_ROOT, registra autoloader,
                          carrega .env, config/ENV.php, view.php, retorna new App()
crescent/server.php       class App (get/post/put/patch/delete/route/group/use/ws/run/runWithSwoole/dispatch)
crescent/core/
  context.php             $ctx — params, query, body, state, json(), view(), redirect()...
  request.php             Leitura de método, path, headers, body, IP (PHP tradicional)
  response.php            json(), view(), html(), text(), redirect(), noContent()
  swooleRequest.php       Adapta \Swoole\Http\Request → Request (sem ler $_SERVER)
  swooleResponse.php      Estende Response, sobrescreve send() para usar $swoole->end()
  wsRouter.php            Roteamento de conexões WebSocket com parâmetros /:param
  wsContext.php           Contexto WS: push(), broadcast(), close(), connectionCount()
  router.php              Matching de rotas HTTP com parâmetros /:id
  model.php               Classe base Model (PDO singleton)
crescent/middleware/
  auth.php                JWT HS256 — required(), optional(), role(), issueToken(), revokeCurrentToken()
  cors.php                CORS configurável
  logger.php              Log em arquivo com rotation
  security.php            Headers de segurança + rate limiting
                          ATENÇÃO: header_remove() só roda fora de CLI (não aplica em Swoole)
crescent/utils/
  env.php                 Env::get/set/load/isProduction/isDevelopment
  hash.php                Hash::make/verify/needsRehash/token/uuid
  str.php                 Str::toSnake/toCamel/toPascal/toKebab/slug/truncate/...
  path.php                Path::join/root/ext/basename/normalize
  mailer.php              Mailer::to()->subject()->html()->text()->send()
  view.php                Helpers globais: component(), e(), asset()
  tests.php               Tests::describe/it/expect/run
  headers.php             Helpers de headers HTTP
public/                   Assets estáticos (CSS, JS, imagens) — servidos diretamente
src/
  shared/components/      Componentes reutilizáveis: layout.php, card.php, alert.php
  auth/                   Módulo de autenticação completo
  users/                  Módulo de usuários
  chat/                   Módulo de exemplo WebSocket
    init.php              require chatRoutes.php + chatWsRoutes.php
    controllers/
      chatController.php     GET /chat, GET /api/chat/status
      chatWsController.php   eventos WS: onOpen/onMessage/onClose dos dois canais
                             mantém mapa fd→número amigável (counter sequencial)
    routes/
      chatRoutes.php         rotas HTTP do chat
      chatWsRoutes.php       $app->ws('/ws/chat') + $app->ws('/ws/notify/:channel')
    views/
      chat.php               interface web completa (HTML/CSS/JS nativo)
migrations/               Arquivos de migration com timestamp
tests/                    test-*.php — carregados por crecli test
logs/                     Criado automaticamente
config/
  development.php
  production.php
Dockerfile                PHP 8.3-cli + Swoole + PDO MySQL + Opcache
docker-compose.yml        Serviços: app (Swoole), db (MySQL 8), redis (Redis 7)
docker/php.ini            Configuração PHP otimizada para Swoole
```

---

## APP & ROTAS

```php
// app.php (Apache/Nginx)
$app = require __DIR__ . '/crescent/init.php';

$app->use(Security::handle());
$app->use(Cors::handle(['origins' => ['https://example.com']]));
$app->use(Logger::handle());

$app->get('/',           fn($ctx) => ['status' => 'ok']);          // array → JSON
$app->get('/users/:id',  fn($ctx) => $ctx->params['id']);          // parâmetro de rota
$app->post('/users',     fn($ctx) => $ctx->status(201)->json([]));
$app->put('/users/:id',  fn($ctx) => ...);
$app->patch('/users/:id',fn($ctx) => ...);
$app->delete('/users/:id', fn($ctx) => ...);
$app->route(['GET','POST'], '/form', fn($ctx) => ...);             // múltiplos métodos

// Grupo com prefixo + middlewares
$app->group('/api', function($app) {
    $app->get('/users', fn($ctx) => ...);   // → /api/users
}, [Auth::required()]);

// Middlewares inline na rota
$app->get('/perfil',     [Auth::required(), fn($ctx) => ...]);

require __DIR__ . '/src/users/routes/usersRoutes.php';
require __DIR__ . '/src/auth/init.php';

$app->run(); // modo tradicional
```

```php
// swoole.php (Docker / VPS com Swoole)
// Mesmas rotas HTTP acima, mais:
use Crescent\Core\WsContext;

$app->ws('/ws/chat',
    onOpen:    fn(WsContext $ctx) => $ctx->push(['event' => 'connected']),
    onMessage: fn(WsContext $ctx, string $data) => $ctx->broadcast($data),
    onClose:   fn(WsContext $ctx) => null,
);

$app->runWithSwoole(); // em vez de $app->run()
// Não chame $app->run() e $app->runWithSwoole() no mesmo entry point.
```

**Retorno automático do handler:**
- `array` / objeto → JSON
- `string` → HTML
- `null` / void → handler já enviou a resposta com $ctx

---

## CONTEXTO ($ctx)

```php
$ctx->params['id']              // /:id
$ctx->query['page']             // ?page=1
$ctx->body['name']              // JSON ou form POST
$ctx->state['user']             // bag para middlewares

$ctx->json($data, $status=200)
$ctx->view('modulo/views/arquivo.php', $data=[])
$ctx->html($string)
$ctx->text($string)
$ctx->redirect('/url', $status=302)
$ctx->noContent()               // 204
$ctx->status(201)->json([])     // fluent
$ctx->header('X-Foo', 'bar')    // fluent

$ctx->method()                  // 'GET'
$ctx->path()                    // '/users/1'
$ctx->ip()
$ctx->bearerToken()             // Authorization: Bearer ...
$ctx->requestHeader('accept')
```

---

## WEBSOCKET (modo Swoole)

Registrado em `swoole.php` — **nunca em `app.php`**.

```php
use Crescent\Core\WsContext;

// Rota estática
$app->ws('/ws/chat',
    onOpen:    function (WsContext $ctx): void {
        $ctx->push(['event' => 'connected', 'fd' => $ctx->fd]);
    },
    onMessage: function (WsContext $ctx, string $rawData): void {
        $payload = json_decode($rawData, true);
        $ctx->broadcast(['event' => 'message', 'from' => $ctx->fd, 'text' => $payload['text'] ?? '']);
    },
    onClose:   function (WsContext $ctx): void {
        $ctx->broadcast(['event' => 'user_left', 'fd' => $ctx->fd]);
    },
);

// Rota dinâmica com parâmetro
$app->ws('/ws/notify/:channel',
    onOpen: fn(WsContext $ctx) => $ctx->push(['event' => 'subscribed', 'channel' => $ctx->params['channel']]),
);

$app->runWithSwoole(); // NÃO $app->run()
```

### WsContext API

```php
$ctx->fd                             // int — file descriptor (ID único da conexão)
$ctx->path                           // string — caminho da URL de upgrade
$ctx->params['room']                 // parâmetros de rota (/:room)
$ctx->query['token']                 // query string da URL de upgrade
$ctx->state['user'] = $payload;      // bag de estado entre eventos (persiste open→message→close)

$ctx->push($data)                    // envia para ESTA conexão (string|array→JSON)
$ctx->broadcast($data, $exclude=[]) // envia para TODAS as conexões
$ctx->close()                        // fecha esta conexão
$ctx->connectionCount()              // número de WS ativos
```

### Regras
- Rotas WS não registradas: conexão recusada automaticamente (fd fechado)
- `onMessage` recebe `(WsContext $ctx, string $rawData)` — $rawData é sempre a string crua
- HTTP e WebSocket usam a mesma porta no Swoole

---

## DOCKER

```bash
# Via crecli (recomendado)
php crecli.php docker:up              # docker compose up -d (sem rebuild)
php crecli.php docker:rebuild         # docker compose up --build -d
php crecli.php docker:restart         # reinicia todos os containers
php crecli.php docker:restart app     # reinicia só o app
php crecli.php docker <cmd> [args]    # executa qualquer crecli dentro do container

# Equivalentes diretos
docker compose up --build -d
docker compose restart app
docker compose exec app php crecli.php migrate
```

**Regra de quando usar cada um:**
- Mudou `src/` → `docker:restart app` (volume montado, sem rebuild)
- Mudou `crescent/`, `Dockerfile`, `docker/php.ini` → `docker:rebuild`

**Problema MySQL 8 com DB_USER=root:** `MYSQL_USER=root` é inválido no MySQL 8 — ele só aceita usuários não-root nesse parâmetro. O `docker-compose.yml` usa apenas `MYSQL_ROOT_PASSWORD: ${DB_PASS}` quando `DB_USER=root`. Use sempre um usuário dedicado (`DB_USER=crescent`) ou garanta que o `.env` não defina `DB_USER=root`.

**`DOCKER_SERVICE`:** nome do serviço alvo para `php crecli.php docker <cmd>`. Padrão `app`. Override via `.env`.

Arquivos Docker:
- `Dockerfile` — PHP 8.3-cli + Swoole (PECL) + pdo_mysql + opcache
- `docker-compose.yml` — serviços: `app`, `db`, `redis`; rede interna `crescent`
- `docker/php.ini` — opcache otimizado, log de erros em `/var/www/logs/`

---

## MODEL BASE

Todas as classes de model estendem `Crescent\Core\Model`.

```php
namespace App\Products\Models;
use Crescent\Core\Model;

class ProductModel extends Model
{
    protected static string $table      = 'products';
    protected static string $primaryKey = 'id'; // padrão — override se diferente
}
```

**API disponível automaticamente:**

```php
ProductModel::all($orderBy='')                    // SELECT *
ProductModel::find($id)                           // por PK → ?array
ProductModel::findWhere(['active' => 1])          // primeiro match → ?array
ProductModel::where(['active' => 1], 'name ASC')  // todos os matches → array
ProductModel::insert(['name' => 'X'])             // → int|string (lastInsertId)
ProductModel::update($id, ['name' => 'Y'])        // → rowCount
ProductModel::updateWhere(['active' => 0], ['deleted' => 1])
ProductModel::delete($id)                         // → rowCount
ProductModel::count(['active' => 1])
ProductModel::query('SELECT * FROM products WHERE price > ?', [10])
ProductModel::execute('UPDATE ...', $params)      // → PDOStatement
ProductModel::transaction(fn($pdo) => ...)
```

**PDO singleton:** uma conexão por processo. Credenciais via `.env` (`DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`).

---

## AUTENTICAÇÃO

### Middlewares

```php
use Crescent\Middleware\Auth;

Auth::required()              // 401/redirect /auth/login se inválido
Auth::optional()              // popula $ctx->state['user'] se presente, não bloqueia
Auth::role('admin')           // 403 se role ausente/diferente
Auth::role('admin', 'manager') // aceita múltiplas roles
```

Após middleware: `$ctx->state['user']` contém o payload do JWT (`id`, `email`, `role`, `iat`, `exp`, `jti`).

### Emissão e revogação

```php
// Emitir (define cookie HttpOnly + retorna token string)
Auth::issueToken($ctx, ['id' => 1, 'email' => 'a@b.com', 'role' => 'user']);
Auth::issueToken($ctx, $payload, $ttl=28800); // TTL em segundos

// Revogar (logout — armazena JTI em revoked_tokens, limpa cookie)
Auth::revokeCurrentToken($ctx);

// Uso manual
$token   = Auth::generateToken($payload, $ttl=28800);
$payload = Auth::verifyToken($token);  // ?array — null se inválido/expirado
```

### Fluxo do módulo auth

```
POST /auth/register        → valida → UserModel::create() → issueToken → redirect /dashboard
POST /auth/login           → Hash::verify → issueToken → redirect $query['redirect'] ?? /dashboard
POST /auth/logout          → revokeCurrentToken → redirect /auth/login
POST /auth/forgot-password → AuthModel::createResetToken → Mailer::send (link com token bruto)
POST /auth/reset-password  → AuthModel::findValidResetToken → UserModel::update(senha) → issueToken
```

**ATENÇÃO:** `UserModel::create()` já aplica `Hash::make()` internamente. NÃO faça hash antes de chamar `create()`.

### Tabelas necessárias (migrations incluídas)

| Tabela            | Uso                                         |
|-------------------|---------------------------------------------|
| `users`           | Usuários                                    |
| `password_resets` | Hash SHA-256 do token de reset (TTL 60 min) |
| `revoked_tokens`  | JTIs revogados no logout                    |

### Variáveis .env da autenticação

```ini
JWT_SECRET=sua_chave_longa_e_aleatoria
JWT_TTL=28800
```

---

## VIEWS, COMPONENTES E ASSETS

### Renderizar view no controller

```php
$ctx->view('users/views/users_all.php', ['users' => $users]);
// Resolve em: src/users/views/users_all.php
```

### Helpers globais (disponíveis em toda view/componente)

```php
e($value)                     // htmlspecialchars — use em TODO valor dinâmico
asset('css/app.css')          // → /public/css/app.css (ou CDN via APP_ASSET_URL)
component('alert', $data)     // renderiza src/shared/components/alert.php
component('users/components/row', ['user' => $u])  // módulo-específico
```

### Criar um componente

Arquivo: `src/shared/components/nome.php`

```php
<?php
// Variáveis injetadas via $data (extract):
$type    ??= 'info';
$message ??= '';
?>
<div class="alert alert-<?= e($type) ?>"><?= e($message) ?></div>
```

### Pattern de layout com slot

```php
// src/users/views/users_all.php
<?php ob_start() ?>
<h1>Usuários</h1>
<?= component('card', ['title' => 'Lista', 'slot' => $conteudo]) ?>
<?php $slot = ob_get_clean() ?>
<?= component('layout', ['title' => 'Usuários', 'slot' => $slot]) ?>
```

### Componentes incluídos em src/shared/components/

| Arquivo      | Variáveis                              |
|--------------|----------------------------------------|
| `layout.php` | `$title`, `$slot`, `$errors[]`         |
| `card.php`   | `$title`, `$slot`, `$footer`           |
| `alert.php`  | `$type` (success/error/warning/info), `$message`, `$dismiss` |

### Assets estáticos

- Colocar em `public/css/`, `public/js/`, `public/img/`
- Apache serve via `!-f` no `.htaccess`
- Servidor built-in (`crecli serve`) serve via `return false` em `app.php`
- CDN: `APP_ASSET_URL=https://cdn.exemplo.com` no `.env`

---

## UTILITÁRIOS

### Hash

```php
use Crescent\Utils\Hash;

Hash::make('senha')            // PBKDF2-SHA256 com salt aleatório
Hash::verify('senha', $hash)   // bool — timing-safe
Hash::needsRehash($hash)       // bool — parâmetros desatualizados?
Hash::token(32)                // string hex de 64 chars (entropia: 32 bytes)
Hash::uuid()                   // UUID v4
```

### Str

```php
use Crescent\Utils\Str;

Str::toSnake('UserModel')      // 'user_model'
Str::toCamel('user_model')     // 'userModel'
Str::toPascal('user_model')    // 'UserModel'
Str::toKebab('UserModel')      // 'user-model'
Str::slug('Olá Mundo!')        // 'ola-mundo'
Str::truncate($str, 100)       // trunca com '…'
Str::escape('<script>')        // htmlspecialchars
Str::isEmail('a@b.com')        // bool
Str::isUrl('https://...')      // bool
Str::isUuid($str)              // bool
Str::startsWith/endsWith/contains($haystack, $needle)
Str::random(16)                // string aleatória alfanumérica
Str::plural('item', $count)    // pluralização simples (inglês)
```

### Path

```php
use Crescent\Utils\Path;

Path::join(APP_ROOT, 'src', 'users')  // concatena com DIRECTORY_SEPARATOR
Path::root('config')                  // APP_ROOT . '/config'
Path::ext('foto.jpg')                 // 'jpg'
Path::basename('dir/file.php', true)  // 'file' (sem extensão)
Path::normalize('/a/b/../c')          // '/a/c'
```

### Env

```php
use Crescent\Utils\Env;

Env::load('/path/to/.env')
Env::get('APP_ENV', 'development')
Env::set('FOO', 'bar')
Env::isProduction()
Env::isDevelopment()
```

### Mailer

```php
use Crescent\Utils\Mailer;

Mailer::to('a@b.com', 'Nome')
      ->cc('c@d.com')
      ->bcc('e@f.com')
      ->subject('Assunto')
      ->html('<h1>Olá</h1>')
      ->text('Olá')
      ->attach('/path/arquivo.pdf')
      ->send();
```

**.env necessário:**

```ini
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=seu@email.com
MAIL_PASS=senha_de_app
MAIL_FROM=noreply@app.com
MAIL_FROM_NAME="App"
MAIL_ENCRYPTION=tls   # tls | ssl | none
```

---

## CLI (crecli.php)

```bash
php crecli.php make:module <nome>          # cria src/<snake>/{controller,model,views,routes,init}
php crecli.php make:controller <Nome>      # cria controller isolado
php crecli.php make:model <Nome>           # cria model isolado
php crecli.php make:migration <nome>       # cria migrations/<timestamp>_<nome>.php
php crecli.php make:test <nome>            # cria tests/test-<nome>.php

php crecli.php migrate                     # executa pendentes
php crecli.php migrate:rollback [n=1]      # reverte n migrações
php crecli.php migrate:status              # lista status

php crecli.php routes                      # lista rotas registradas
php crecli.php test [arquivo]              # roda tests/test-*.php (ou arquivo específico)
php crecli.php serve [porta=8000]          # servidor built-in PHP (modo tradicional)
php crecli.php swoole:start [host] [p]     # inicia Swoole HTTP+WebSocket (requer ext swoole)

php crecli.php docker:up                   # docker compose up -d
php crecli.php docker:rebuild              # docker compose up --build -d (rebuild imagem)
php crecli.php docker:restart [serviço]    # reinicia tudo ou um serviço (app/db/redis)
php crecli.php docker <cmd> [args]         # proxy: executa crecli dentro do container
```

### Estrutura de módulo gerada por make:module posts

```
src/posts/
  init.php
  controllers/postsController.php   namespace App\Posts\Controllers; class PostsController
  models/postsModel.php             namespace App\Posts\Models;      class PostsModel extends Model
  views/posts_all.php
  views/posts_crud.php
  routes/postsRoutes.php
```

Após gerar, adicionar em `app.php`:

```php
require __DIR__ . '/src/posts/routes/postsRoutes.php';
```

### Estrutura de migration

```php
// migrations/20260413000000_create_posts_table.php
return new class {
    public function up(\PDO $pdo): void {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS `posts` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `title`      VARCHAR(255) NOT NULL,
                `created_at` DATETIME DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    public function down(\PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS `posts`');
    }
};
```

---

## TESTES

```php
// tests/test-posts.php
use Crescent\Utils\Tests;

Tests::describe('PostsModel', function () {
    Tests::it('deve retornar array', function () {
        $posts = PostsModel::all();
        Tests::expect($posts)->toBeArray();
    });

    Tests::it('count retorna inteiro', function () {
        Tests::expect(PostsModel::count())->toBeInt();
    });
});
```

**Matchers disponíveis:** `toBe`, `toEqual`, `toBeTrue`, `toBeFalse`, `toBeNull`, `toBeArray`, `toBeInt`, `toBeString`, `toContain`, `toBeGreaterThan`, `toBeLessThan`, `toThrow`.

**ATENÇÃO:** usar `mb_strlen()` para strings UTF-8, nunca `strlen()` com matchers numéricos.

---

## MIDDLEWARES

```php
// Assinatura de um middleware
function (Context $ctx, callable $next): void {
    // antes
    $next();
    // depois (opcional)
}

// Security — opções
Security::handle([
    'rate_limit'     => 60,     // req/minuto por IP
    'rate_window'    => 60,
    'storage'        => '/tmp', // diretório de contagem
]);

// Cors — opções
Cors::handle([
    'origins'  => ['https://app.com'],
    'methods'  => ['GET','POST','PUT','DELETE'],
    'headers'  => ['Content-Type','Authorization'],
    'max_age'  => 86400,
]);

// Logger — opções
Logger::handle(
    file: APP_ROOT . '/logs/access.log',
    opts: ['rotate' => true, 'max_size' => 5 * 1024 * 1024]
);
```

---

## VARIÁVEIS .ENV COMPLETAS

```ini
APP_ENV=development          # development | production
APP_PORT=8000
APP_TIMEZONE=America/Sao_Paulo
APP_ASSET_URL=               # URL base para asset() — vazio = /public

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=crescent
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

JWT_SECRET=
JWT_TTL=28800

MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=
MAIL_PASS=
MAIL_FROM=
MAIL_FROM_NAME=
MAIL_ENCRYPTION=tls

# Swoole / Docker
SWOOLE_HOST=0.0.0.0
SWOOLE_PORT=9501
APP_PORT=9501                # porta exposta no host (docker compose)
DB_ROOT_PASS=rootsecret      # senha root MySQL (docker)
REDIS_EXTERNAL_PORT=6379     # porta Redis no host (docker)
```

---

## ARMADILHAS CONHECIDAS

| Problema | Causa | Solução |
|---|---|---|
| `Class "Crescent\Utils\Str" not found` | Autoloader busca `str.php`, arquivo renomeado | O arquivo correto é `crescent/utils/str.php` |
| Login sempre "credenciais inválidas" | `Hash::make()` chamado duas vezes (controller + `UserModel::create()`) | Passar senha pura para `create()` |
| Componente "Call to unknown function e()" | `view.php` não carregado ainda | Adicionar `function_exists('e') \|\| require_once ...view.php` no topo |
| `strlen()` falhando em testes UTF-8 | `strlen` conta bytes, não caracteres | Usar `mb_strlen()` |
| Arquivo de view não encontrado | Path passado para `$ctx->view()` deve ser relativo a `/src/` | Ex: `'users/views/users_all.php'` |
| Acesso direto a `crescent/` em produção | `.htaccess` com rule faltando | Regra `RewriteRule ^(crescent\|src\|...)` → 403 já incluída |
| Conexão WS recusada imediatamente | Caminho não tem rota `$app->ws()` registrada | Registrar a rota em `swoole.php` |
| `runWithSwoole()` em `app.php` | Dois entry points misturados | Usar `run()` em `app.php`, `runWithSwoole()` em `swoole.php` |
| `Extension swoole not loaded` local | Swoole não instalado | `pecl install swoole` ou usar `docker compose up` |
| `MYSQL_USER=root` causa erro no container | MySQL 8 rejeita root como MYSQL_USER | Usar `DB_USER=crescent` (usuário dedicado); root só via `DB_ROOT_PASS` |
| `header_remove()` warning em Swoole | Função nativa de headers não funciona em CLI | Já corrigido com `PHP_SAPI !== 'cli'` em security.php |
| Números de usuário WS pulam (1, 18, 26...) | `fd` é descritor do SO, consumido também por HTTP | Usar contador sequencial interno em vez de `$ctx->fd` como identificador visível |