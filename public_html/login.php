<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

start_session();

$info = [
  'method' => $_SERVER['REQUEST_METHOD'] ?? '',
  'https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'on' : 'off',
  'sid' => session_id(),
  'has_user_session' => isset($_SESSION['user']) ? 'yes' : 'no',
];

$error = '';
$emailValue = '';
$rememberValue = false;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $email = trim((string) ($_POST['email'] ?? ''));
  $pass = (string) ($_POST['pass'] ?? '');
  $remember = !empty($_POST['remember']);

  $emailValue = $email;
  $rememberValue = $remember;

  $info['post_email'] = $email !== '' ? $email : '(vazio)';
  $info['post_pass_len'] = (string) strlen($pass);
  $info['post_remember'] = $remember ? '1' : '0';

  try {
    $ok = login($email, $pass, $remember);
    $info['login_return'] = $ok ? 'true' : 'false';
    $info['sid_after_login'] = session_id();
    $info['has_user_session_after'] = isset($_SESSION['user']) ? 'yes' : 'no';
    $info['remember_cookie_after'] = isset($_COOKIE['remember_me']) ? 'yes' : 'no';

    if ($ok) {
      header('Location: /index.php');
      exit;
    }

    $error = 'E-mail ou senha inválidos.';
  } catch (Throwable $e) {
    $error = 'Erro no login(): ' . $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <title>Login — <?= htmlspecialchars(APP_NAME) ?></title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet">

  <link rel="icon" type="image/png" href="/assets/img/favicon.ico" />

  <!-- libs -->
  <link rel="stylesheet" type="text/css" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="/assets/fonts/font-awesome-4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" type="text/css" href="/assets/vendor/animate/animate.css">
  <link rel="stylesheet" type="text/css" href="/assets/vendor/css-hamburgers/hamburgers.min.css">
  <link rel="stylesheet" type="text/css" href="/assets/vendor/select2/select2.min.css">

  <!-- seus css -->
  <link rel="stylesheet" type="text/css" href="/assets/css/util.css">
  <link rel="stylesheet" type="text/css" href="/assets/css/auth.css">
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />

  <!-- loader -->
  <link rel="stylesheet"
    href="/assets/css/loader.css?v=<?= @filemtime(__DIR__ . '/assets/css/loader.css') ?: time() ?>" />

  <!-- layout split -->
  <link rel="stylesheet"
    href="/assets/css/auth-split.css?v=<?= @filemtime(__DIR__ . '/assets/css/auth-split.css') ?: time() ?>" />
</head>

<body class="page auth-split">
  <main class="auth-wrap">
    <section class="auth-panel">

      <!-- LADO ESQUERDO -->
      <aside class="auth-left" aria-hidden="true">
        <div class="auth-brand auth-brand--welcome">
          <h1 class="auth-brand__title">Bem-vindo de volta!</h1>
          <p class="auth-brand__subtitle">
            Plataforma interna Popper Conecta.<br>
            Conectando pessoas, processos e resultados.
          </p>
        </div>

        <!-- shapes (ok) -->
        <div class="popper-shape popper-shape--1"></div>
        <div class="popper-shape popper-shape--2"></div>

        <!-- (REMOVIDO) grid pontilhado
        <div class="popper-grid"></div>
        -->
      </aside>

      <!-- LADO DIREITO -->
      <section class="auth-right">
        <div class="auth-card">

          <div class="auth-logo-top">
            <img src="/assets/img/logo.png" alt="Popper">
          </div>

          <header class="auth-card__header">
            <h2 class="auth-card__title">Login</h2>
            <p class="auth-card__desc">Entre com seu e-mail e senha corporativo.</p>
          </header>

          <?php if (!empty($error)): ?>
            <div class="auth-alert" role="alert" aria-live="polite">
              <i class="fa fa-exclamation-triangle auth-alert__icon" aria-hidden="true"></i>
              <div class="auth-alert__text"><?= htmlspecialchars($error) ?></div>
            </div>
          <?php endif; ?>

          <form class="auth-form" method="post" action="/login.php" novalidate>
            <label class="auth-field">
              <span class="auth-field__label">E-mail</span>
              <div class="auth-field__control">
                <i class="fa fa-envelope auth-field__icon" aria-hidden="true"></i>
                <input class="auth-input" type="email" name="email" value="<?= htmlspecialchars($emailValue) ?>"
                  placeholder="seuemail@popper.com.br" required autocomplete="username">
                  
              </div>
            </label>

            <label class="auth-field">
              <span class="auth-field__label">Senha</span>
              <div class="auth-field__control">
                <i class="fa fa-lock auth-field__icon" aria-hidden="true"></i>
                <input class="auth-input" type="password" name="pass" placeholder="••••••••" required
                  autocomplete="current-password">
              </div>
            </label>

            <div class="auth-row">
              <label class="auth-check">
                <input type="checkbox" name="remember" value="1" <?= $rememberValue ? 'checked' : '' ?>>
                <span>Lembrar de mim</span>
              </label>

              <a class="auth-link" href="/esqueci-senha.php">Esqueci minha senha</a>
            </div>

            <button class="auth-btn" type="submit">
              <span class="auth-btn__text">Entrar</span>
            </button>

            <footer class="auth-footer">
              <span class="auth-footer__text">
                Transformando celebrações em resultados extraordinários.
              </span>
            </footer>
          </form>

        </div>
      </section>

    </section>
  </main>

  <?php require_once __DIR__ . '/app/footer.php'; ?>

  <script src="/assets/vendor/jquery/jquery-3.2.1.min.js"></script>
  <script src="/assets/vendor/bootstrap/js/popper.js"></script>
  <script src="/assets/vendor/bootstrap/js/bootstrap.min.js"></script>
  <script src="/assets/vendor/select2/select2.min.js"></script>
  <script src="/assets/vendor/tilt/tilt.jquery.min.js"></script>

  <script>
    $('.js-tilt').tilt({ scale: 1.05 });
  </script>

  <script src="/assets/js/main.js"></script>

  <!-- ✅ loader.js precisa vir ANTES do script que chama PopperLoading -->
  <script src="/assets/js/loader.js?v=<?= @filemtime(__DIR__ . '/assets/js/loader.js') ?: time() ?>"></script>

  <!-- ✅ Mostra loader e só depois envia o POST -->
  <script>
    (function () {
      const form = document.querySelector('.auth-form');
      if (!form) return;

      const btn = form.querySelector('.auth-btn');
      let submitted = false;

      form.addEventListener('submit', function (e) {
        if (submitted) return;
        e.preventDefault();
        submitted = true;

        document.body.classList.add('is-leaving');

        if (btn) {
          btn.classList.add('is-loading');
          btn.disabled = true;
        }

        // Mostra loader (agora existe, porque loader.js já carregou)
        if (window.PopperLoading && typeof window.PopperLoading.show === 'function') {
          window.PopperLoading.show('Entrando…', 'Validando acesso');
        }

        // espera 1s e envia de verdade
        setTimeout(() => form.submit(), 1000);
      });
    })();
  </script>
</body>

</html>