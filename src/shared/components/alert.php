<?php
/**
 * Componente: alert
 *
 * Variáveis esperadas:
 *   string $message           Texto da mensagem (obrigatório)
 *   string $type   = 'info'   Tipo: 'success' | 'error' | 'warning' | 'info'
 *   bool   $dismiss = true    Mostra botão de fechar
 *
 * Uso:
 *   <?= component('alert', ['type' => 'success', 'message' => 'Salvo com sucesso!']) ?>
 */

$type    ??= 'info';
$dismiss ??= true;

$colors = [
    'success' => 'bg-green-50  border-green-400  text-green-800',
    'error'   => 'bg-red-50    border-red-400    text-red-800',
    'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
    'info'    => 'bg-blue-50   border-blue-400   text-blue-800',
];

$cls = $colors[$type] ?? $colors['info'];
?>
<div role="alert" class="crescent-alert <?= e($cls) ?> flex items-start gap-3 rounded border px-4 py-3 text-sm">
    <span class="flex-1"><?= e($message ?? '') ?></span>
    <?php if ($dismiss): ?>
        <button type="button" onclick="this.closest('[role=alert]').remove()"
                class="ml-auto shrink-0 opacity-60 hover:opacity-100"
                aria-label="Fechar">&times;</button>
    <?php endif ?>
</div>
