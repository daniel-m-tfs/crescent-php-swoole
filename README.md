# CrescentPHP

Framework MVC ultra leve para PHP — sem Composer, sem dependências, funciona até nas hospedagens de R$10/mês.
Inspirado no [Crescent Framework](https://crescent.tyne.com.br) (Lua).

> **Template de repositório GitHub** — use como base para novos projetos.

---

## Início rápido

```bash
# 1. Clone o template
git clone https://github.com/daniel-m-tfs/crescentPHP meu-projeto
cd meu-projeto

# 2. Configure o ambiente
cp .env.example .env
# edite .env com suas credenciais de banco

# 3. Rode as migrações
php crecli.php migrate

# 4. Servidor de desenvolvimento
php crecli.php serve
# acesse http://localhost:8000
```

---

## Core

### Rotas

```php
// app.php
$app = require __DIR__ . '/crescent/init.php';

// GET simples — retornar array vira JSON automaticamente
$app->get('/', fn($ctx) => ['status' => 'ok']);

// Parâmetro de rota  /:id
$app->get('/users/:id', function($ctx) {
    return ['id' => $ctx->params['id']];
});

// Query string  ?page=2  →  $ctx->query['page']
$app->get('/users', function($ctx) {
    $page = $ctx->query['page'] ?? 1;
    return ['page' => $page];
});

// Body (JSON ou form)  →  $ctx->body
$app->post('/users', function($ctx) {
    $name = $ctx->body['name'];
    // ...
    return $ctx->status(201)->json(['created' => $name]);
});

// Agrupamento com prefixo + middleware
$app->group('/api', function($app) {
    $app->get('/users', fn($ctx) => UserController::index($ctx));
}, [Auth::required()]);

$app->run();
```

**Métodos disponíveis:** `get`, `post`, `put`, `patch`, `delete`, `route` (múltiplos métodos), `group`.

**Retorno automático do handler:**
| Tipo de retorno | Comportamento |
|---|---|
| `array` / objeto | JSON |
| `string` | HTML |
| `null` / sem retorno | Handler enviou a resposta diretamente |

---

### Controllers

```php
// src/users/controllers/usersController.php
namespace App\Users\Controllers;

use App\Users\Models\UserModel;
use Crescent\Core\Context;

class UserController
{
    public static function index(Context $ctx): void
    {
        $users = UserModel::getAll();
        $ctx->json(['data' => $users]);
    }

    public static function show(Context $ctx): void
    {
        $user = UserModel::find((int) $ctx->params['id']);
        if (!$user) {
            $ctx->json(['error' => 'Não encontrado'], 404);
            return;
        }
        $ctx->json(['data' => $user]);
    }
}
```

---

### Models

```php
// src/users/models/usersModel.php
namespace App\Users\Models;

use Crescent\Core\Model;

class UserModel extends Model
{
    protected static string $table = 'users';

    // CRUD já incluído na classe pai:
    // UserModel::all()
    // UserModel::find($id)
    // UserModel::where(['active' => 1])
    // UserModel::insert($data)   → retorna ID
    // UserModel::update($id, $data)
    // UserModel::delete($id)
    // UserModel::count()
    // UserModel::query('SELECT ...', $params)
    // UserModel::transaction(fn($pdo) => ...)

    // Métodos específicos do domínio:
    public static function getAll(): array
    {
        return static::where(['active' => 1], 'name ASC');
    }
}
```

A conexão PDO é um **singleton** — instanciada uma única vez por processo.
Credenciais vêm automaticamente do `.env`.

---

### Views

```php
// Controller
$ctx->view('users/views/users_all.php', ['users' => $users]);

// src/users/views/users_all.php  — PHP puro, sem lógica de negócio
foreach ($users as $user):
    echo e($user['name']); // helper global de escaping
endforeach;
```

---

### Templates, Componentes e Assets

O bootstrap carrega automaticamente 3 helpers globais disponíveis em **qualquer view ou componente**:

| Helper | Descrição |
|---|---|
| `component($name, $data)` | Renderiza `src/shared/components/<name>.php` com `$data` como variáveis |
| `e($value)` | Escapa para HTML seguro — use em todo valor dinâmico |
| `asset($path)` | Retorna a URL de `/public/<path>` (suporta CDN via `APP_ASSET_URL`) |

#### Assets estáticos

Coloque CSS, JS e imagens em `/public/`. O Apache serve os arquivos diretamente (via `!-f` no `.htaccess`) e o servidor built-in faz o mesmo automaticamente.

```php
// Em qualquer view ou componente:
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="<?= asset('js/app.js') ?>"></script>
<img src="<?= asset('img/logo.png') ?>" alt="Logo">
```

Em produção, defina `APP_ASSET_URL=https://cdn.exemplo.com` no `.env` para servir via CDN.

#### Componentes

Componentes são arquivos PHP em `src/shared/components/<nome>.php` que recebem dados como variáveis e produzem HTML.

```php
// Componente simples
<?= component('alert', ['type' => 'success', 'message' => 'Salvo!']) ?>
<?= component('alert', ['type' => 'error',   'message' => 'Ops!']) ?>

// Componente de um módulo específico → src/users/components/row.php
<?= component('users/components/row', ['user' => $user]) ?>
```

**Anatomia de um componente** (`src/shared/components/alert.php`):

```php
<?php
// Variáveis recebidas via $data:
$type    = $type    ?? 'info';   // 'success' | 'error' | 'warning' | 'info'
$dismiss = $dismiss ?? true;
?>
<div role="alert" class="crescent-alert ...">
    <span><?= e($message ?? '') ?></span>
    <?php if ($dismiss): ?>
        <button onclick="this.closest('[role=alert]').remove()">&times;</button>
    <?php endif ?>
</div>
```

#### Layout como componente wrapper

Em vez de herança de templates, use um componente de layout com um `$slot`:

```php
// src/users/views/users_all.php
<?php ob_start() ?>

<h1>Usuários</h1>
<?= component('card', [
    'title' => 'Todos os usuários',
    'slot'  => implode('', array_map(
        fn($u) => '<p>' . e($u['name']) . '</p>',
        $users
    )),
]) ?>

<?php $slot = ob_get_clean() ?>
<?= component('layout', ['title' => 'Usuários', 'slot' => $slot]) ?>
```

**Componentes incluídos em `src/shared/components/`:**

| Componente | Variáveis principais |
|---|---|
| `layout` | `$title`, `$slot`, `$errors[]` |
| `card` | `$title`, `$slot`, `$footer` |
| `alert` | `$type`, `$message`, `$dismiss` |

---

### Contexto (`$ctx`)

| Propriedade / Método | Descrição |
|---|---|
| `$ctx->params['id']` | Parâmetros de rota (`/:id`) |
| `$ctx->query['page']` | Query string (`?page=1`) |
| `$ctx->body['name']` | Dados do body (JSON ou form) |
| `$ctx->state['user']` | Bag livre para middlewares |
| `$ctx->json($data, $status)` | Resposta JSON |
| `$ctx->view($template, $data)` | Renderiza view PHP |
| `$ctx->text($string)` | Texto puro |
| `$ctx->redirect($url)` | Redirecionamento |
| `$ctx->noContent()` | 204 No Content |
| `$ctx->status($code)` | Define status HTTP (fluent) |
| `$ctx->header($name, $value)` | Define header de resposta |
| `$ctx->bearerToken()` | Token Bearer da requisição |
| `$ctx->ip()` | IP do cliente |

---

## Middlewares

```php
use Crescent\Middleware\{Cors, Security, Logger, Auth};

// Globais
$app->use(Security::handle());
$app->use(Cors::handle(['origins' => ['https://meusite.com.br']]));
$app->use(Logger::handle());

// Por rota (array de middlewares + handler)
$app->get('/perfil', [Auth::required(), fn($ctx) => /* ... */]);

// Por grupo
$app->group('/admin', function($app) {
    $app->get('/dashboard', fn($ctx) => /* ... */);
}, [Auth::required()]);
```

### Middlewares incluídos

| Middleware | Descrição |
|---|---|
| `Security::handle($opts)` | Headers de segurança + rate limiting por arquivo |
| `Cors::handle($opts)` | CORS configurável, inclui preflight OPTIONS |
| `Logger::handle($file, $opts)` | Log de requisições em arquivo com rotation |
| `Auth::required()` | Exige JWT válido, popula `$ctx->state['user']` |
| `Auth::optional()` | Popula user se token presente, não bloqueia |

### Gerar e verificar tokens JWT

```php
// Login
$token = Auth::generateToken(['id' => 1, 'email' => 'ana@email.com']);

// Verificar manualmente
$payload = Auth::verifyToken($token); // array ou null
```

---

## Autenticação

O módulo `src/auth/` implementa um fluxo completo de autenticação com JWT armazenado em **cookie HttpOnly** (sem exposição ao JavaScript).

### Fluxo completo

```
Registro       POST /auth/register  →  cria usuário  →  emite JWT  →  redireciona
Login          POST /auth/login     →  valida senha  →  emite JWT  →  redireciona
Logout         POST /auth/logout    →  revoga JTI    →  limpa cookie  →  redireciona
Esqueci senha  POST /auth/forgot-password  →  gera token  →  envia e-mail
Redefinir      POST /auth/reset-password   →  valida token →  troca senha →  auto-login
```

### Como o JWT é armazenado

Ao autenticar, `Auth::issueToken()` define um cookie **HttpOnly + Secure + SameSite=Strict**. Em rotas protegidas o middleware lê o cookie (ou o header `Authorization: Bearer` como fallback para APIs/mobile).

```php
// Emitir — chamado internamente pelo AuthController:
Auth::issueToken($ctx, [
    'id'    => $user['id'],
    'email' => $user['email'],
    'role'  => $user['role'] ?? 'user',
]);

// Ler payload na rota protegida:
$user = $ctx->state['user']; // ['id', 'email', 'role', 'iat', 'exp', 'jti']

// Logout seguro (invalida o JTI no banco, limpa cookie):
Auth::revokeCurrentToken($ctx);
```

### Proteger rotas

```php
// Exige token válido (401 → redireciona para /auth/login no navegador)
$app->get('/dashboard', [Auth::required()], fn($ctx) => ...);

// Exige role específica (403 se não tiver permissão)
$app->delete('/admin/users/:id', [Auth::role('admin')], fn($ctx) => ...);

// Múltiplas roles aceitas
$app->get('/relatorios', [Auth::role('admin', 'manager')], fn($ctx) => ...);

// Token opcional (popula $ctx->state['user'] se present, não bloqueia)
$app->get('/home', [Auth::optional()], fn($ctx) => ...);

// Grupo protegido
$app->group('/admin', function($app) {
    $app->get('/dashboard', fn($ctx) => ...);
    $app->get('/users',     fn($ctx) => ...);
}, [Auth::required(), Auth::role('admin')]);
```

### Fluxo de recuperação de senha

1. Usuário acessa `GET /auth/forgot-password` e envia o e-mail
2. `AuthModel::createResetToken($email)` gera um token seguro (64 hex chars), armazena apenas o **hash SHA-256** no banco e retorna o token bruto
3. `Mailer` envia o link `https://app/auth/reset-password?token=<token>` com validade de 60 minutos
4. Usuário acessa o link → `GET /auth/reset-password` valida o token antes de exibir o form
5. `POST /auth/reset-password` troca a senha, invalida o token e faz auto-login

> O e-mail só é enviado se o usuário existir, mas a resposta ao cliente é sempre a mesma — isso evita vazamento de cadastros.

### Variáveis `.env` necessárias

```ini
JWT_SECRET=sua_chave_secreta_longa_e_aleatoria
JWT_TTL=28800          # Duração do token em segundos (padrão: 8h)

# Mailer (para recuperação de senha)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=seu@email.com
MAIL_PASS=senha_de_app
MAIL_FROM=noreply@meusite.com
MAIL_FROM_NAME="Meu App"
MAIL_ENCRYPTION=tls
```

### Tabelas necessárias

Rodadas por `php crecli.php migrate`:

| Tabela | Descrição |
|---|---|
| `users` | Usuários da aplicação |
| `password_resets` | Tokens de redefinição (hash SHA-256, TTL 60 min) |
| `revoked_tokens` | JTIs revogados no logout (limpeza automática após expirar) |

---

## Utilitários

```php
use Crescent\Utils\{Hash, Str, Path, Env, Headers, Mailer};

// Hash PBKDF2
$hash = Hash::make('senha123');
Hash::verify('senha123', $hash); // true
Hash::token();                   // token hex aleatório
Hash::uuid();                    // UUID v4

// String
Str::toSnake('UserModel');       // 'user_model'
Str::toPascal('user_model');     // 'UserModel'
Str::slug('Olá Mundo!');         // 'ola-mundo'
Str::isEmail('a@b.com');         // true
Str::escape('<script>');         // '&lt;script&gt;'

// Path
Path::join(APP_ROOT, 'src', 'users'); // /app/src/users
Path::root('config');                 // /app/config

// Env
Env::get('APP_ENV', 'development');
Env::isProduction();
Env::isDevelopment();

// Mailer (SMTP sem dependências)
Mailer::to('ana@email.com', 'Ana')
      ->subject('Bem-vinda!')
      ->html('<h1>Olá, Ana!</h1>')
      ->text('Olá, Ana!')
      ->send();

// Múltiplos destinatários + CC
Mailer::to(['a@x.com', 'b@x.com'])
      ->cc('gerente@x.com')
      ->subject('Relatório')
      ->html($html)
      ->send();
```

> **Variáveis `.env` do Mailer:** `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`, `MAIL_FROM_NAME`, `MAIL_ENCRYPTION` (`tls` / `ssl` / `none`).

---

## CLI

```bash
# Gerar módulo completo (controller + model + views + routes)
php crecli.php make:module products

# Gerar arquivos individuais
php crecli.php make:controller ProductController
php crecli.php make:model      ProductModel
php crecli.php make:migration  create_products_table
php crecli.php make:test       products

# Banco de dados
php crecli.php migrate
php crecli.php migrate:rollback      # reverte 1
php crecli.php migrate:rollback 3    # reverte 3
php crecli.php migrate:status

# Utilitários
php crecli.php routes        # lista todas as rotas
php crecli.php test          # roda todos os testes
php crecli.php serve 3000    # servidor built-in na porta 3000
```

---

## Testes

```php
// tests/test-users.php
use Crescent\Utils\Tests;

Tests::describe('UserModel', function () {

    Tests::it('deve retornar array', function () {
        $users = UserModel::all();
        Tests::expect($users)->toBeArray();
    });

    Tests::it('senha deve verificar corretamente', function () {
        $hash = Hash::make('abc123');
        Tests::expect(Hash::verify('abc123', $hash))->toBeTrue();
    });
});

// Rode com:
// php crecli.php test
```

---

## Estrutura de arquivos

```
meu-projeto/
├── app.php                        # Ponto de entrada
├── crecli.php                     # CLI
├── .env.example                   # Template de variáveis
├── .htaccess                      # Rewrite rules para Apache
├── config/
│   ├── development.php
│   └── production.php
├── crescent/                      # Core do framework (não edite)
│   ├── init.php                   # Bootstrap + autoloader
│   ├── server.php                 # Classe App principal
│   ├── core/
│   │   ├── context.php            # Contexto HTTP
│   │   ├── request.php            # Request
│   │   ├── response.php           # Response
│   │   ├── router.php             # Roteador
│   │   └── model.php              # Base Model (PDO singleton)
│   ├── middleware/
│   │   ├── auth.php               # JWT HS256
│   │   ├── cors.php               # CORS
│   │   ├── logger.php             # Log em arquivo
│   │   └── security.php           # Headers + rate limit
│   └── utils/
│       ├── env.php                # Leitura de .env
│       ├── hash.php               # PBKDF2 + UUID + token
│       ├── mailer.php             # SMTP sem dependências
│       ├── view.php               # Helpers: component(), e(), asset()
│       ├── tests.php              # Biblioteca de testes
│       ├── headers.php            # Helpers de headers HTTP
│       ├── path.php               # Manipulação de caminhos
│       └── str.php                # Manipulação de strings
├── public/                        # Assets estáticos (servidos diretamente)
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   └── app.js
│   └── img/
├── src/                           # Seu código (módulos)
│   ├── shared/
│   │   └── components/            # Componentes reutilizáveis
│   │       ├── layout.php         # Wrapper de página (title + slot)
│   │       ├── card.php           # Card com título e slot
│   │       └── alert.php          # Mensagem de feedback
│   ├── auth/
│   │   ├── init.php
│   │   ├── controllers/
│   │   │   └── authController.php
│   │   ├── models/
│   │   │   └── authModel.php
│   │   ├── views/
│   │   │   ├── login.php
│   │   │   ├── register.php
│   │   │   ├── forgot_password.php
│   │   │   └── reset_password.php
│   │   └── routes/
│   │       └── authRoutes.php
│   └── users/
│       ├── init.php
│       ├── controllers/
│       │   └── usersController.php
│       ├── models/
│       │   └── usersModel.php
│       ├── views/
│       │   ├── users_all.php
│       │   └── users_crud.php
│       └── routes/
│           └── usersRoutes.php
├── migrations/
│   ├── 20260108230701_create_users_table.php
│   └── 20260413000000_create_auth_tables.php
├── tests/
│   └── test-framework.php
└── logs/                          # Criado automaticamente
```

---

## Requisitos

- PHP 8.1+
- Extensão PDO + PDO_MySQL (ou PDO_SQLite para SQLite)
- Apache com `mod_rewrite` **ou** servidor PHP built-in (`serve`)
- Sem Composer, sem nada além do PHP padrão de hospedagem
