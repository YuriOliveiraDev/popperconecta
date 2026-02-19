<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';

start_session();

$info = [
  'method' => $_SERVER['REQUEST_METHOD'] ?? '',
  'https'  => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'on' : 'off',
  'sid'    => session_id(),
  'has_user_session' => isset($_SESSION['user']) ? 'yes' : 'no',
];

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['pass'] ?? '');

  $info['post_email'] = $email !== '' ? $email : '(vazio)';
  $info['post_pass_len'] = (string)strlen($pass);

  try {
    $ok = login($email, $pass);
    $info['login_return'] = $ok ? 'true' : 'false';
    $info['sid_after_login'] = session_id();
    $info['has_user_session_after'] = isset($_SESSION['user']) ? 'yes' : 'no';

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
    <!---->
    <link rel="icon" type="image/png" href="/assets/img/favicon.ico"/>
    <!---->
    <link rel="stylesheet" type="text/css" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <!---->
    <link rel="stylesheet" type="text/css" href="/assets/fonts/font-awesome-4.7.0/css/font-awesome.min.css">
    <!---->
    <link rel="stylesheet" type="text/css" href="/assets/vendor/animate/animate.css">
    <!---->
    <link rel="stylesheet" type="text/css" href="/assets/vendor/css-hamburgers/hamburgers.min.css">
    <!---->
    <link rel="stylesheet" type="text/css" href="/assets/vendor/select2/select2.min.css">
    <!---->
    <link rel="stylesheet" type="text/css" href="/assets/css/util.css">
    <link rel="stylesheet" type="text/css" href="/assets/css/auth.css">
    <!---->
</head>
<body>
    
    <div class="limiter">
        <div class="container-login100">
            <div class="wrap-login100">
                <form class="login100-form validate-form" method="post" action="/login.php">
                    <!-- Logo centralizado dentro do form -->
                    <div class="logoOutside">
                        <img src="/assets/img/logo.jpg" alt="Logo">
                    </div>

                    <div class="wrap-input100 validate-input" data-validate="E-mail válido é obrigatório: ex@abc.xyz">
                        <input class="input100" type="email" name="email" placeholder="E-mail" required>
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-envelope" aria-hidden="true"></i>
                        </span>
                    </div>

                    <div class="wrap-input100 validate-input" data-validate="Senha é obrigatória">
                        <input class="input100" type="password" name="pass" placeholder="Senha" required>
                        <span class="focus-input100"></span>
                        <span class="symbol-input100">
                            <i class="fa fa-lock" aria-hidden="true"></i>
                        </span>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="error-card">
                            <i class="fa fa-exclamation-triangle error-icon"></i>
                            <span class="error-text"><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="container-login100-form-btn">
                        <button class="login100-form-btn" type="submit">
                            Entrar
                        </button>
                    </div>

                    <div class="text-center p-t-80 footer-text">
                        <span class="txt2">
                            Transformando celebrações em resultados extraordinários.
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    
<!---->
    <script src="/assets/vendor/jquery/jquery-3.2.1.min.js"></script>
<!---->
    <script src="/assets/vendor/bootstrap/js/popper.js"></script>
    <script src="/assets/vendor/bootstrap/js/bootstrap.min.js"></script>
<!---->
    <script src="/assets/vendor/select2/select2.min.js"></script>
<!---->
    <script src="/assets/vendor/tilt/tilt.jquery.min.js"></script>
    <script>
        $('.js-tilt').tilt({
            scale: 1.1
        })
    </script>
<!---->
    <script src="/assets/js/main.js"></script>

</body>
</html>