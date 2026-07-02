<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($user) ? 'Editar usuário' : 'Novo usuário' ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; color: #333; padding: 2rem; }
        h1 { margin-bottom: 1.5rem; }
        .card { background: #fff; border-radius: 8px; padding: 1.5rem; max-width: 480px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .25rem; color: #374151; }
        input { width: 100%; padding: .5rem .75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; }
        input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 2px rgba(79,70,229,.2); }
        .field { margin-bottom: 1rem; }
        .hint { font-size: .75rem; color: #9ca3af; margin-top: .25rem; }
        .btn { display: inline-block; padding: .6rem 1.25rem; background: #4f46e5; color: #fff; border-radius: 6px; font-size: .9rem; border: none; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #4338ca; }
        .btn-secondary { background: #6b7280; }
        .btn-secondary:hover { background: #4b5563; }
        .actions { display: flex; gap: .75rem; margin-top: 1.25rem; }
    </style>
</head>
<body>
    <h1><?= isset($user) ? 'Editar usuário' : 'Novo usuário' ?></h1>

    <div class="card">
        <?php
            $isEdit = isset($user) && !empty($user['id']);
            $action = $isEdit ? '/users/' . $user['id'] . '/update' : '/users';
            $method = 'POST';
        ?>

        <form method="<?= $method ?>" action="<?= $action ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="_method" value="PUT">
            <?php endif; ?>

            <div class="field">
                <label for="name">Nome completo</label>
                <input type="text" id="name" name="name" required
                       value="<?= htmlspecialchars($user['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="password">
                    <?= $isEdit ? 'Nova senha (deixe em branco para manter)' : 'Senha' ?>
                </label>
                <input type="password" id="password" name="password"
                       <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
                <p class="hint">Mínimo 8 caracteres.</p>
            </div>

            <div class="actions">
                <button class="btn" type="submit">
                    <?= $isEdit ? 'Salvar alterações' : 'Criar usuário' ?>
                </button>
                <a class="btn btn-secondary" href="/users">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
