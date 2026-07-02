<?php

namespace Crescent\Utils {

/**
 * View — helpers de template disponíveis em todas as views e componentes.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * COMPONENTES
 * ──────────────────────────────────────────────────────────────────────────────
 * Componentes são arquivos PHP em src/shared/components/<nome>.php que recebem
 * dados como variáveis e retornam HTML via output buffering.
 *
 * Uso dentro de qualquer view:
 *
 *   <?= component('alert', ['type' => 'success', 'message' => 'Salvo!']) ?>
 *   <?= component('card',  ['title' => 'Usuários', 'items' => $users]) ?>
 *
 * Componentes de um módulo específico (em src/<modulo>/components/):
 *
 *   <?= component('users/row', ['user' => $user]) ?>
 *   // → src/users/components/row.php
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * LAYOUTS VIA COMPONENTE WRAPPER
 * ──────────────────────────────────────────────────────────────────────────────
 * Em vez de um mecanismo de herança, use o componente de layout como wrapper:
 *
 *   // src/users/views/users_all.php
 *   <?php ob_start() ?>
 *   <h1>Lista de usuários</h1>
 *   <?php $slot = ob_get_clean() ?>
 *   <?= component('layout', ['title' => 'Usuários', 'slot' => $slot]) ?>
 *
 *   // src/shared/components/layout.php recebe $title e $slot
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * HELPERS GLOBAIS (carregados automaticamente pelo bootstrap)
 * ──────────────────────────────────────────────────────────────────────────────
 *   component(string $name, array $data = []): string
 *   e(string $value): string          — escapa para HTML seguro
 *   asset(string $path): string       — URL de arquivo em /public/
 */
class View
{
    /**
     * Renderiza um componente e retorna o HTML resultante.
     *
     * Resolução do caminho (ordem de prioridade):
     *   1. src/shared/components/<name>.php
     *   2. src/<name>.php  (para componentes declarados com path de módulo)
     *
     * @param string $name   Nome do componente, ex: 'alert', 'card', 'users/row'
     * @param array  $data   Variáveis expostas dentro do componente
     * @throws \RuntimeException se o arquivo não for encontrado
     */
    public static function component(string $name, array $data = []): string
    {
        $path = static::resolveComponent($name);

        if (!file_exists($path)) {
            $root    = defined('APP_ROOT') ? APP_ROOT : dirname(dirname(__DIR__));
            $default = $root . '/src/shared/components/' . $name . '.php';
            throw new \RuntimeException(
                "Componente não encontrado: {$name}" . PHP_EOL .
                "Esperado em: {$default}"
            );
        }

        // Expõe $data como variáveis locais, sem sobrescrever variáveis existentes
        extract($data, EXTR_SKIP);

        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    /**
     * Escapa string para saída HTML segura.
     * Equivalente compacto de htmlspecialchars().
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ─── Resolução de caminhos ────────────────────────────────────────────────

    private static function resolveComponent(string $name): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(dirname(__DIR__));
        $name = ltrim($name, '/');

        // 1. src/shared/components/<name>.php
        $shared = $root . '/src/shared/components/' . $name . '.php';
        if (file_exists($shared)) {
            return $shared;
        }

        // 2. src/<name>.php  (ex: 'users/components/row')
        $direct = $root . '/src/' . $name . '.php';
        if (file_exists($direct)) {
            return $direct;
        }

        // Retorna caminho padrão para mensagem de erro descritiva
        return $shared;
    }
}

} // end namespace Crescent\Utils

namespace {

// ─── Helpers globais ──────────────────────────────────────────────────────────
// Registrados apenas uma vez; disponíveis em qualquer view/componente.

if (!function_exists('component')) {
    /**
     * Renderiza um componente e retorna o HTML.
     *
     * @param string $name  Nome do componente (ex: 'alert', 'card', 'users/row')
     * @param array  $data  Variáveis expostas dentro do componente
     */
    function component(string $name, array $data = []): string
    {
        return \Crescent\Utils\View::component($name, $data);
    }
}

if (!function_exists('e')) {
    /**
     * Escapa string para saída HTML — use em todo valor dinâmico.
     *
     *   <h1><?= e($title) ?></h1>
     *   <input value="<?= e($user['name']) ?>">
     */
    function e(string $value): string
    {
        return \Crescent\Utils\View::escape($value);
    }
}

if (!function_exists('asset')) {
    /**
     * Retorna a URL de um arquivo estático em /public/.
     *
     *   <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
     *   <script src="<?= asset('js/app.js') ?>"></script>
     *   <img src="<?= asset('img/logo.png') ?>" alt="Logo">
     *
     * Em produção, defina APP_ASSET_URL no .env para apontar para um CDN:
     *   APP_ASSET_URL=https://cdn.exemplo.com
     */
    function asset(string $path): string
    {
        $base = rtrim(\Crescent\Utils\Env::get('APP_ASSET_URL', '/public'), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

} // end namespace global
