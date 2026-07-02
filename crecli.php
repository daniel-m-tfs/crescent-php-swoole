#!/usr/bin/env php
<?php

/**
 * crecli.php — CLI do CrescentPHP
 *
 * Uso:
 *   php crecli.php <comando> [argumentos]
 *
 * Comandos disponíveis:
 *   make:module     <nome>    — Gera estrutura completa de módulo em src/
 *   make:controller <nome>    — Gera um Controller
 *   make:model      <nome>    — Gera um Model
 *   make:migration  <nome>    — Gera arquivo de migration com timestamp
 *   make:test       <nome>    — Gera arquivo de teste
 *   migrate                   — Roda migrações pendentes
 *   migrate:rollback [n]      — Reverte n migrações (padrão: 1)
 *   migrate:status            — Lista status das migrações
 *   routes                    — Lista todas as rotas registradas
 *   test [arquivo]            — Roda testes (todos ou específico)
 *   serve [porta]             — Inicia servidor PHP built-in (dev)
 */

declare(strict_types=1);

// ─── Bootstrap rápido (sem iniciar servidor HTTP) ─────────────────────────────

define('APP_ROOT',      __DIR__);
define('CRESCENT_ROOT', __DIR__ . '/crescent');
define('APP_START',     microtime(true));

require CRESCENT_ROOT . '/utils/env.php';
\Crescent\Utils\Env::load(APP_ROOT . '/.env');

// ─── CLI core ─────────────────────────────────────────────────────────────────

$cli = new CrescentCLI($argv);
$cli->run();

// ─────────────────────────────────────────────────────────────────────────────

class CrescentCLI
{
    private array  $args;
    private string $command;

    // Cores ANSI
    private const GREEN  = "\033[32m";
    private const YELLOW = "\033[33m";
    private const RED    = "\033[31m";
    private const CYAN   = "\033[36m";
    private const BOLD   = "\033[1m";
    private const RESET  = "\033[0m";

    public function __construct(array $argv)
    {
        array_shift($argv); // remove o nome do script
        $this->command = array_shift($argv) ?? 'help';
        $this->args    = $argv;
    }

    public function run(): void
    {
        $commands = [
            'make:module'     => 'makeModule',
            'make:controller' => 'makeController',
            'make:model'      => 'makeModel',
            'make:migration'  => 'makeMigration',
            'make:test'       => 'makeTest',
            'migrate'         => 'migrate',
            'migrate:rollback'=> 'migrateRollback',
            'migrate:status'  => 'migrateStatus',
            'routes'          => 'listRoutes',
            'test'            => 'runTests',
            'serve'           => 'serve',
            'help'            => 'help',
        ];

        $method = $commands[$this->command] ?? null;

        if (!$method) {
            $this->error("Comando desconhecido: {$this->command}");
            $this->help();
            exit(1);
        }

        $this->{$method}();
    }

    // ─── Generators ───────────────────────────────────────────────────────────

    private function makeModule(): void
    {
        $name = $this->args[0] ?? null;
        if (!$name) {
            $this->error('Informe o nome do módulo. Ex: php crecli.php make:module posts');
            exit(1);
        }

        $snake    = $this->toSnake($name);
        $pascal   = $this->toPascal($name);
        $basePath = APP_ROOT . "/src/{$snake}";

        $dirs = [
            $basePath,
            "{$basePath}/controllers",
            "{$basePath}/models",
            "{$basePath}/views",
            "{$basePath}/routes",
        ];

        foreach ($dirs as $dir) {
            $this->mkdir($dir);
        }

        $this->write("{$basePath}/init.php",                         $this->stubModuleInit($snake));
        $this->write("{$basePath}/controllers/{$snake}Controller.php", $this->stubController($snake, $pascal));
        $this->write("{$basePath}/models/{$snake}Model.php",          $this->stubModel($snake, $pascal));
        $this->write("{$basePath}/routes/{$snake}Routes.php",         $this->stubRoutes($snake, $pascal));
        $this->write("{$basePath}/views/{$snake}_all.php",            $this->stubViewAll($snake, $pascal));
        $this->write("{$basePath}/views/{$snake}_crud.php",           $this->stubViewCrud($pascal));

        $this->success("Módulo '{$pascal}' criado em src/{$snake}/");
        $this->info("Adicione em app.php:  require __DIR__ . '/src/{$snake}/routes/{$snake}Routes.php';");
    }

    private function makeController(): void
    {
        $name = $this->args[0] ?? null;
        if (!$name) { $this->error('Informe o nome.'); exit(1); }

        $snake  = $this->toSnake(str_replace('Controller', '', $name));
        $pascal = $this->toPascal($snake);
        $file   = APP_ROOT . "/src/{$snake}/controllers/{$snake}Controller.php";

        $this->mkdir(dirname($file));
        $this->write($file, $this->stubController($snake, $pascal));
        $this->success("Controller criado: src/{$snake}/controllers/{$snake}Controller.php");
    }

    private function makeModel(): void
    {
        $name = $this->args[0] ?? null;
        if (!$name) { $this->error('Informe o nome.'); exit(1); }

        $snake  = $this->toSnake(str_replace('Model', '', $name));
        $pascal = $this->toPascal($snake);
        $file   = APP_ROOT . "/src/{$snake}/models/{$snake}Model.php";

        $this->mkdir(dirname($file));
        $this->write($file, $this->stubModel($snake, $pascal));
        $this->success("Model criado: src/{$snake}/models/{$snake}Model.php");
    }

    private function makeMigration(): void
    {
        $name = $this->args[0] ?? null;
        if (!$name) { $this->error('Informe o nome. Ex: create_posts_table'); exit(1); }

        $timestamp = date('YmdHis');
        $snake     = $this->toSnake($name);
        $file      = APP_ROOT . "/migrations/{$timestamp}_{$snake}.php";

        $this->mkdir(APP_ROOT . '/migrations');
        $this->write($file, $this->stubMigration($snake));
        $this->success("Migration criada: migrations/{$timestamp}_{$snake}.php");
    }

    private function makeTest(): void
    {
        $name = $this->args[0] ?? null;
        if (!$name) { $this->error('Informe o nome.'); exit(1); }

        $snake = $this->toSnake($name);
        $file  = APP_ROOT . "/tests/test-{$snake}.php";

        $this->mkdir(APP_ROOT . '/tests');
        $this->write($file, $this->stubTest($name));
        $this->success("Arquivo de teste criado: tests/test-{$snake}.php");
    }

    // ─── Migrations ───────────────────────────────────────────────────────────

    private function migrate(): void
    {
        $pdo = $this->getPDO();
        $this->ensureMigrationsTable($pdo);

        $ran   = $this->getRanMigrations($pdo);
        $files = $this->getMigrationFiles();
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = require $file;
            $migration->up($pdo);

            $stmt = $pdo->prepare('INSERT INTO _migrations (name, ran_at) VALUES (?, NOW())');
            $stmt->execute([$name]);

            $this->success("  ✓ {$name}");
            $count++;
        }

        if ($count === 0) {
            $this->info('Nenhuma migration pendente.');
        } else {
            $this->success("{$count} migration(s) executada(s).");
        }
    }

    private function migrateRollback(): void
    {
        $steps = (int) ($this->args[0] ?? 1);
        $pdo   = $this->getPDO();
        $this->ensureMigrationsTable($pdo);

        $ran   = $this->getRanMigrations($pdo, $steps);
        $files = array_reverse($this->getMigrationFiles());
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $ran, true)) {
                continue;
            }

            $migration = require $file;
            $migration->down($pdo);

            $stmt = $pdo->prepare('DELETE FROM _migrations WHERE name = ?');
            $stmt->execute([$name]);

            $this->warn("  ↩ {$name}");
            $count++;
        }

        if ($count === 0) {
            $this->info('Nada para reverter.');
        }
    }

    private function migrateStatus(): void
    {
        $pdo = $this->getPDO();
        $this->ensureMigrationsTable($pdo);

        $ran   = $this->getRanMigrations($pdo);
        $files = $this->getMigrationFiles();

        $this->line(str_pad('Status', 8) . ' Migration');
        $this->line(str_repeat('─', 60));

        foreach ($files as $file) {
            $name   = basename($file, '.php');
            $done   = in_array($name, $ran, true);
            $status = $done ? self::GREEN . '  ✓ ran ' . self::RESET : self::YELLOW . 'pending' . self::RESET;
            $this->line("{$status}  {$name}");
        }
    }

    // ─── Routes ───────────────────────────────────────────────────────────────

    private function listRoutes(): void
    {
        // Carrega o app sem processar requisição
        $app = $this->loadApp();

        $routes = $app->routes();

        if (empty($routes)) {
            $this->info('Nenhuma rota registrada.');
            return;
        }

        $this->line("\n" . self::BOLD . str_pad('METHOD', 10) . 'PATH' . self::RESET);
        $this->line(str_repeat('─', 60));

        foreach ($routes as $route) {
            $method = str_pad($route['method'], 10);
            $color  = match ($route['method']) {
                'GET'    => self::GREEN,
                'POST'   => self::CYAN,
                'PUT','PATCH' => self::YELLOW,
                'DELETE' => self::RED,
                default  => '',
            };
            $this->line("{$color}{$method}" . self::RESET . $route['path']);
        }
        echo "\n";
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    private function runTests(): void
    {
        $target = $this->args[0] ?? null;

        if ($target) {
            $file = APP_ROOT . '/tests/' . $target;
            if (!file_exists($file)) {
                $file = APP_ROOT . '/tests/test-' . $target . '.php';
            }
            if (!file_exists($file)) {
                $this->error("Arquivo de teste não encontrado: {$target}");
                exit(1);
            }
            require $file;
        } else {
            $files = glob(APP_ROOT . '/tests/test-*.php') ?: [];
            if (empty($files)) {
                $this->info('Nenhum arquivo de teste encontrado em tests/test-*.php');
                return;
            }
            foreach ($files as $file) {
                require $file;
            }
        }

        \Crescent\Utils\Tests::run();
    }

    // ─── Serve ────────────────────────────────────────────────────────────────

    private function serve(): void
    {
        $port = $this->args[0] ?? \Crescent\Utils\Env::get('APP_PORT', '8000');
        $host = '0.0.0.0';

        $this->info("🌙 CrescentPHP dev server em http://localhost:{$port}");
        $this->info("   Ctrl+C para parar\n");

        passthru("php -S {$host}:{$port} " . escapeshellarg(APP_ROOT . '/app.php'));
    }

    // ─── Help ─────────────────────────────────────────────────────────────────

    private function help(): void
    {
        echo <<<HELP

        \033[1m🌙 CrescentPHP CLI\033[0m

        \033[33mGeração de código:\033[0m
          make:module     <nome>    Gera módulo completo em src/
          make:controller <nome>    Gera Controller
          make:model      <nome>    Gera Model
          make:migration  <nome>    Gera arquivo de migration
          make:test       <nome>    Gera arquivo de teste

        \033[33mBanco de dados:\033[0m
          migrate                   Executa migrações pendentes
          migrate:rollback [n]      Reverte n migrações (padrão 1)
          migrate:status            Lista status das migrações

        \033[33mUtilitários:\033[0m
          routes                    Lista rotas registradas
          test [arquivo]            Roda testes
          serve [porta]             Inicia servidor de desenvolvimento

        \033[2mExemplos:\033[0m
          php crecli.php make:module products
          php crecli.php migrate
          php crecli.php serve 3000

        HELP;
    }

    // ─── Utilities ────────────────────────────────────────────────────────────

    private function getPDO(): \PDO
    {
        $driver  = \Crescent\Utils\Env::get('DB_DRIVER',  'mysql');
        $host    = \Crescent\Utils\Env::get('DB_HOST',    '127.0.0.1');
        $port    = \Crescent\Utils\Env::get('DB_PORT',    '3306');
        $dbname  = \Crescent\Utils\Env::get('DB_NAME',    '');
        $user    = \Crescent\Utils\Env::get('DB_USER',    'root');
        $pass    = \Crescent\Utils\Env::get('DB_PASS',    '');
        $charset = \Crescent\Utils\Env::get('DB_CHARSET', 'utf8mb4');

        if ($driver === 'sqlite') {
            return new \PDO("sqlite:{$dbname}", options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        }

        $dsn = "{$driver}:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    private function ensureMigrationsTable(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS `_migrations` (
                `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`   VARCHAR(255) NOT NULL,
                `ran_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `_migrations_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL);
    }

    private function getRanMigrations(\PDO $pdo, int $limit = 0): array
    {
        $sql  = 'SELECT name FROM _migrations ORDER BY id DESC';
        $sql .= $limit > 0 ? " LIMIT {$limit}" : '';
        return $pdo->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): array
    {
        $files = glob(APP_ROOT . '/migrations/*.php') ?: [];
        sort($files);
        return $files;
    }

    private function loadApp(): \Crescent\App
    {
        // Simula ambiente CLI para carregar as rotas sem fazer output
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';

        ob_start();
        /** @var \Crescent\App $app */
        $app = require APP_ROOT . '/crescent/init.php';

        // Inclui todos os arquivos de rotas
        $routeFiles = glob(APP_ROOT . '/src/*/routes/*Routes.php') ?: [];
        foreach ($routeFiles as $f) {
            require_once $f;
        }
        ob_end_clean();

        return $app;
    }

    private function mkdir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            $this->info("  criado: " . $this->relative($path));
        }
    }

    private function write(string $path, string $content): void
    {
        if (file_exists($path)) {
            $this->warn("  existe: " . $this->relative($path) . " (pulado)");
            return;
        }
        file_put_contents($path, $content);
        $this->info("  gerado: " . $this->relative($path));
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(APP_ROOT, '', $path), '/\\');
    }

    // ─── Strings ──────────────────────────────────────────────────────────────

    private function toSnake(string $str): string
    {
        $str = preg_replace('/([a-z])([A-Z])/', '$1_$2', $str);
        return strtolower(trim($str, '_'));
    }

    private function toPascal(string $str): string
    {
        return str_replace([' ', '_', '-'], '', ucwords($str, ' _-'));
    }

    // ─── Output ───────────────────────────────────────────────────────────────

    private function success(string $msg): void { echo self::GREEN  . $msg . self::RESET . PHP_EOL; }
    private function error(string $msg):   void { echo self::RED    . "✗ {$msg}" . self::RESET . PHP_EOL; }
    private function warn(string $msg):    void { echo self::YELLOW . $msg . self::RESET . PHP_EOL; }
    private function info(string $msg):    void { echo self::CYAN   . $msg . self::RESET . PHP_EOL; }
    private function line(string $msg):    void { echo $msg . PHP_EOL; }

    // ─── Stubs ────────────────────────────────────────────────────────────────

    private function stubModuleInit(string $snake): string
    {
        return <<<PHP
        <?php
        require __DIR__ . '/routes/{$snake}Routes.php';
        PHP;
    }

    private function stubController(string $snake, string $pascal): string
    {
        return <<<PHP
        <?php

        namespace App\\{$pascal}\\Controllers;

        use App\\{$pascal}\\Models\\{$pascal}Model;
        use Crescent\\Core\\Context;

        class {$pascal}Controller
        {
            public static function index(Context \$ctx): void
            {
                \$items = {$pascal}Model::all();
                \$ctx->json(['data' => \$items]);
            }

            public static function show(Context \$ctx): void
            {
                \$item = {$pascal}Model::find((int) \$ctx->params['id']);
                if (!\$item) {
                    \$ctx->json(['error' => 'Não encontrado'], 404);
                    return;
                }
                \$ctx->json(['data' => \$item]);
            }

            public static function store(Context \$ctx): void
            {
                \$id   = {$pascal}Model::insert((array) \$ctx->body);
                \$item = {$pascal}Model::find((int) \$id);
                \$ctx->status(201)->json(['data' => \$item]);
            }

            public static function update(Context \$ctx): void
            {
                {$pascal}Model::update((int) \$ctx->params['id'], (array) \$ctx->body);
                \$item = {$pascal}Model::find((int) \$ctx->params['id']);
                \$ctx->json(['data' => \$item]);
            }

            public static function destroy(Context \$ctx): void
            {
                {$pascal}Model::delete((int) \$ctx->params['id']);
                \$ctx->noContent();
            }
        }
        PHP;
    }

    private function stubModel(string $snake, string $pascal): string
    {
        return <<<PHP
        <?php

        namespace App\\{$pascal}\\Models;

        use Crescent\\Core\\Model;

        class {$pascal}Model extends Model
        {
            protected static string \$table      = '{$snake}s';
            protected static string \$primaryKey = 'id';
        }
        PHP;
    }

    private function stubRoutes(string $snake, string $pascal): string
    {
        return <<<PHP
        <?php

        use App\\{$pascal}\\Controllers\\{$pascal}Controller;

        \$app->group('/{$snake}s', function (\$app) {
            \$app->get('/',      fn (\$ctx) => {$pascal}Controller::index(\$ctx));
            \$app->get('/:id',   fn (\$ctx) => {$pascal}Controller::show(\$ctx));
            \$app->post('/',     fn (\$ctx) => {$pascal}Controller::store(\$ctx));
            \$app->put('/:id',   fn (\$ctx) => {$pascal}Controller::update(\$ctx));
            \$app->delete('/:id',fn (\$ctx) => {$pascal}Controller::destroy(\$ctx));
        });
        PHP;
    }

    private function stubViewAll(string $snake, string $pascal): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><title>{$pascal}s</title></head>
        <body>
            <h1>{$pascal}s</h1>
            <pre><?php print_r(\${$snake}s ?? []); ?></pre>
        </body>
        </html>
        HTML;
    }

    private function stubViewCrud(string $pascal): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><title>Formulário {$pascal}</title></head>
        <body>
            <h1><?= isset(\$item) ? 'Editar' : 'Novo' ?> {$pascal}</h1>
            <form method="POST">
                <!-- campos aqui -->
                <button type="submit">Salvar</button>
            </form>
        </body>
        </html>
        HTML;
    }

    private function stubMigration(string $snake): string
    {
        $date = date('Y-m-d H:i:s');
        return <<<PHP
        <?php

        /**
         * Migration: {$snake}
         * Gerado em: {$date}
         */

        return new class {

            public function up(\\PDO \$pdo): void
            {
                \$pdo->exec(<<<SQL
                    CREATE TABLE IF NOT EXISTS `{$snake}` (
                        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                SQL);
            }

            public function down(\\PDO \$pdo): void
            {
                \$pdo->exec('DROP TABLE IF EXISTS `{$snake}`');
            }
        };
        PHP;
    }

    private function stubTest(string $name): string
    {
        $pascal = $this->toPascal($name);
        return <<<PHP
        <?php

        use Crescent\\Utils\\Tests;

        // Bootstrap mínimo
        if (!defined('APP_ROOT')) {
            define('APP_ROOT',      dirname(__DIR__));
            define('CRESCENT_ROOT', APP_ROOT . '/crescent');
            require CRESCENT_ROOT . '/utils/env.php';
            \\Crescent\\Utils\\Env::load(APP_ROOT . '/.env');
        }

        Tests::describe('{$pascal}', function () {

            Tests::it('exemplo: verdade é verdade', function () {
                Tests::expect(true)->toBeTrue();
            });

        });
        PHP;
    }
}
