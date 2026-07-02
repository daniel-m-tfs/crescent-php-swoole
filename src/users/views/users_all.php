<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; color: #333; padding: 2rem; }
        h1 { margin-bottom: 1.5rem; }
        .btn { display: inline-block; padding: .5rem 1rem; background: #4f46e5; color: #fff; border-radius: 6px; text-decoration: none; font-size: .875rem; }
        .btn:hover { background: #4338ca; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        th, td { text-align: left; padding: .75rem 1rem; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; font-size: .875rem; color: #6b7280; }
        tr:last-child td { border-bottom: none; }
        .actions { display: flex; gap: .5rem; }
        .empty { text-align: center; padding: 3rem; color: #9ca3af; }
    </style>
</head>
<body>
    <h1>Usuários</h1>

    <p style="margin-bottom:1rem">
        <a class="btn" href="/users/form">+ Novo usuário</a>
    </p>

    <?php if (empty($users)): ?>
        <div class="empty">Nenhum usuário cadastrado ainda.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Criado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $user['id']) ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['created_at'] ?? '—') ?></td>
                        <td class="actions">
                            <a class="btn" href="/users/<?= $user['id'] ?>/form">Editar</a>
                            <form method="POST" action="/users/<?= $user['id'] ?>/delete" onsubmit="return confirm('Tem certeza?')">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-danger" type="submit">Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
