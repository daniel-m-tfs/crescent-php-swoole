<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redefinir senha</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: sans-serif; background: #f5f5f5; display: flex;
           justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 24px; }
    .card { background: #fff; border-radius: 10px; box-shadow: 0 4px 20px #0001;
            padding: 40px 36px; width: 100%; max-width: 420px; }
    h1 { margin: 0 0 24px; font-size: 1.6rem; color: #1e1e2e; }
    label { display: block; margin-bottom: 4px; font-size: .875rem; color: #555; font-weight: 500; }
    input[type=password] {
      width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px;
      font-size: 1rem; outline: none; transition: border .2s;
    }
    input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px #4f46e510; }
    .field { margin-bottom: 20px; }
    .btn {
      width: 100%; padding: 11px; background: #4f46e5; color: #fff; border: none;
      border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background .2s;
    }
    .btn:hover { background: #4338ca; }
    .alert { border-radius: 6px; padding: 12px 14px; margin-bottom: 20px; font-size: .9rem; }
    .alert-error { background: #fee2e2; color: #b91c1c; }
    .alert-list { background: #fee2e2; color: #b91c1c; border-radius: 6px; padding: 10px 14px 10px 28px;
                  margin-bottom: 20px; font-size: .9rem; }
    .alert-list li { margin-bottom: 4px; }
    .links { margin-top: 20px; text-align: center; font-size: .875rem; }
    .links a { color: #4f46e5; text-decoration: none; }
    .links a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="card">
  <h1>Redefinir senha</h1>

  <?php if (!empty($expired)): ?>
    <div class="alert alert-error">
      <?= htmlspecialchars($error ?? 'Link inválido ou expirado.', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="links">
      <a href="/auth/forgot-password">Solicitar novo link</a>
    </div>

  <?php else: ?>

    <?php if (!empty($errors)): ?>
      <ul class="alert-list">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="POST" action="/auth/reset-password" novalidate>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="field">
        <label for="password">Nova senha <small style="color:#999">(mín. 8 caracteres)</small></label>
        <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
      </div>

      <div class="field">
        <label for="password_confirm">Confirmar nova senha</label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
      </div>

      <button type="submit" class="btn">Salvar nova senha</button>
    </form>

    <div class="links">
      <a href="/auth/login">← Voltar ao login</a>
    </div>

  <?php endif; ?>
</div>
</body>
</html>
