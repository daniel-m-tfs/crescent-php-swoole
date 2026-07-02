<?php
/**
 * Componente: card
 *
 * Variáveis esperadas:
 *   string $title              Título do card (obrigatório)
 *   string $slot  = ''         Conteúdo HTML interno (use ob_start/ob_get_clean)
 *   string $footer = ''        Rodapé opcional
 *
 * Uso com conteúdo inline:
 *   <?= component('card', ['title' => 'Usuários', 'slot' => '<p>Conteúdo</p>']) ?>
 *
 * Uso com ob_start (recomendado para blocos grandes):
 *   <?php ob_start() ?>
 *   <p>Conteúdo longo aqui...</p>
 *   <?php $slot = ob_get_clean() ?>
 *   <?= component('card', ['title' => 'Meu Card', 'slot' => $slot]) ?>
 */

$slot   ??= '';
$footer ??= '';
?>
<div class="crescent-card rounded-lg border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 px-5 py-3">
        <h3 class="text-base font-semibold text-gray-800"><?= e($title ?? '') ?></h3>
    </div>
    <div class="px-5 py-4">
        <?= $slot ?>
    </div>
    <?php if ($footer): ?>
        <div class="border-t border-gray-100 px-5 py-3 text-sm text-gray-500">
            <?= $footer ?>
        </div>
    <?php endif ?>
</div>
