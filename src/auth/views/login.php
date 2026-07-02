<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: sans-serif; background: #f5f5f5; display: flex;
           justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
    .card { background: #fff; border-radius: 10px; box-shadow: 0 4px 20px #0001;
            padding: 40px 36px; width: 100%; max-width: 420px; }
    h1 { margin: 0 0 24px; font-size: 1.6rem; color: #1e1e2e; }
    label { display: block; margin-bottom: 4px; font-size: .875rem; color: #555; font-weight: 500; }
    input[type=email], input[type=password] {
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
    .alert { background: #fee2e2; color: #b91c1c; border-radius: 6px; padding: 10px 14px;
             margin-bottom: 20px; font-size: .9rem; }
    .links { margin-top: 20px; text-align: center; font-size: .875rem; color: #666; }
    .links a { color: #4f46e5; text-decoration: none; }
    .links a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="card">
  <h1>Entrar</h1>

  <?php if (!empty($error)): ?>
    <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="POST" action="/auth/login" novalidate>
    <div class="field">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" required autocomplete="email"
             value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div class="field">
      <label for="password">Senha</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>

    <button type="submit" class="btn">Entrar</button>
  </form>

  <div class="links">
    <a href="/auth/forgot-password">Esqueci minha senha</a>
    &nbsp;·&nbsp;
    <a href="/auth/register">Criar conta</a>
  </div>
</div>
</body>
</html>
