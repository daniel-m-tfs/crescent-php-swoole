<?php
/**
 * Componente: layout (wrapper de página completa)
 *
 * Variáveis esperadas:
 *   string $title   = 'CrescentPHP'   Título da aba/página
 *   string $slot    = ''              Conteúdo principal da página
 *   array  $errors  = []             Mensagens de erro para exibição global
 *
 * Uso numa view:
 *   <?php ob_start() ?>
 *   <h1>Olá, <?= e($user['name']) ?>!</h1>
 *   <p>Bem-vindo ao dashboard.</p>
 *   <?php $slot = ob_get_clean() ?>
 *   <?= component('layout', ['title' => 'Dashboard', 'slot' => $slot]) ?>
 */

$title  = $title  ?? 'CrescentPHP';
$slot   = $slot   ?? '';
$errors = $errors ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">

    <nav class="border-b border-gray-200 bg-white px-6 py-3 flex items-center gap-4 shadow-sm">
        <a href="/" class="font-bold text-indigo-600">CrescentPHP</a>
        <div class="flex-1"></div>
        <a href="/auth/logout" class="text-sm text-gray-500 hover:text-gray-800">Sair</a>
    </nav>

    <main class="mx-auto max-w-5xl px-4 py-8">
        <?php foreach ($errors as $err): ?>
            <?= component('alert', ['type' => 'error', 'message' => $err]) ?>
        <?php endforeach ?>

        <?= $slot ?>
    </main>

    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
