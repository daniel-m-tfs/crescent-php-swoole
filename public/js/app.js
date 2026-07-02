/**
 * CrescentPHP — app.js
 *
 * JavaScript compartilhado. Coloque aqui interações globais do projeto.
 *
 * Referenciado nos templates via:
 *   <script src="<?= asset('js/app.js') ?>"></script>
 */

/* ── Flash messages ──────────────────────────────────────────────────────── */
// Remove alertas automaticamente após 5s (se tiverem data-autohide)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[role="alert"][data-autohide]').forEach(el => {
        const delay = parseInt(el.dataset.autohide, 10) || 5000;
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 400);
        }, delay);
    });
});

/* ── Confirmação de ações destrutivas ────────────────────────────────────── */
// Use data-confirm="Tem certeza?" em qualquer botão/form
document.addEventListener('click', e => {
    const el = e.target.closest('[data-confirm]');
    if (!el) return;
    if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
        e.stopImmediatePropagation();
    }
});

/* ── Envio de form via fetch (data-async) ────────────────────────────────── */
// Adicione data-async no <form> para submeter como JSON sem reload
document.addEventListener('submit', async e => {
    const form = e.target.closest('form[data-async]');
    if (!form) return;
    e.preventDefault();

    const method   = (form.method || 'POST').toUpperCase();
    const action   = form.action  || window.location.href;
    const feedback = form.querySelector('[data-feedback]');

    const body = JSON.stringify(Object.fromEntries(new FormData(form)));

    try {
        const res  = await fetch(action, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body,
        });
        const data = await res.json();

        if (feedback) {
            feedback.textContent = data.message ?? data.error ?? '';
            feedback.className   = res.ok ? 'text-green-600' : 'text-red-600';
        }

        if (res.ok && form.dataset.redirect) {
            window.location.href = form.dataset.redirect;
        }
    } catch (err) {
        if (feedback) {
            feedback.textContent = 'Erro de conexão.';
            feedback.className   = 'text-red-600';
        }
    }
});
