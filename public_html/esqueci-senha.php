<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config.php';
require_once APP_ROOT . '/app/integrations/mail_graph.php';

start_session();
date_default_timezone_set('America/Sao_Paulo');

$success = '';
$error = '';
$emailValue = '';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $emailValue = $email;

    if ($email === '') {
        $error = 'Informe seu e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } else {
        try {
            $pdo = db();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("
                SELECT id, email, name, is_active
                FROM users
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ((int) ($user['is_active'] ?? 0) !== 1) {
                    $error = 'Existe um cadastro com este e-mail, mas a conta está inativa. Procure o administrador.';
                } else {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
                    $expiresAt = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

                    $pdo->beginTransaction();

                    $invalidateOld = $pdo->prepare("
            UPDATE password_resets
            SET used_at = NOW()
            WHERE user_id = :user_id
              AND used_at IS NULL
        ");
                    $invalidateOld->execute([
                        ':user_id' => (int) $user['id'],
                    ]);

                    $insert = $pdo->prepare("
            INSERT INTO password_resets (
                user_id,
                email,
                token_hash,
                expires_at,
                request_ip,
                request_user_agent
            ) VALUES (
                :user_id,
                :email,
                :token_hash,
                :expires_at,
                :request_ip,
                :request_user_agent
            )
        ");
                    $insert->execute([
                        ':user_id' => (int) $user['id'],
                        ':email' => (string) $user['email'],
                        ':token_hash' => $tokenHash,
                        ':expires_at' => $expiresAt,
                        ':request_ip' => $ip,
                        ':request_user_agent' => $ua,
                    ]);

                    $pdo->commit();

                    $resetLink = APP_URL . '/reset-password.php?token=' . urlencode($token);

                    $subject = 'Redefinição de senha - Popper Conecta';

                    $html = '
        <div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#1f2937">
          <h2 style="margin:0 0 16px">Redefinição de senha</h2>
          <p>Olá, ' . h((string) ($user['name'] ?? 'usuário')) . '.</p>
          <p>Recebemos uma solicitação para redefinir sua senha no <strong>Popper Conecta</strong>.</p>
          <p>Clique no botão abaixo para criar uma nova senha:</p>
          <p style="margin:24px 0">
            <a href="' . h($resetLink) . '" style="background:#22c55e;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;display:inline-block;font-weight:700">
              Redefinir minha senha
            </a>
          </p>
          <p>Se preferir, copie e cole este link no navegador:</p>
          <p><a href="' . h($resetLink) . '">' . h($resetLink) . '</a></p>
          <p>Este link expira em 30 minutos.</p>
          <p>Se você não solicitou essa alteração, ignore este e-mail.</p>
        </div>';

                    send_mail_graph(
                        (string) $user['email'],
                        $subject,
                        $html
                    );

                    $success = 'Encontramos um cadastro com este e-mail. O link de redefinição foi enviado com sucesso.';
                    $emailValue = '';
                }
            } else {
                $error = 'Nenhum cadastro foi encontrado com este e-mail.';
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Erro ao processar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title>Esqueci minha senha — <?= htmlspecialchars(APP_NAME) ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <link rel="icon" type="image/png" href="/assets/img/favicon.ico" />

    <link rel="stylesheet" type="text/css" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="/assets/fonts/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="/assets/vendor/animate/animate.css">
    <link rel="stylesheet" type="text/css" href="/assets/vendor/css-hamburgers/hamburgers.min.css">
    <link rel="stylesheet" type="text/css" href="/assets/vendor/select2/select2.min.css">

    <link rel="stylesheet" type="text/css" href="/assets/css/util.css">
    <link rel="stylesheet" type="text/css" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/base.css?v=<?= @filemtime(__DIR__ . '/assets/css/base.css') ?: time() ?>">
    <link rel="stylesheet"
        href="/assets/css/auth-split.css?v=<?= @filemtime(__DIR__ . '/assets/css/auth-split.css') ?: time() ?>">

    <style>
        .auth-note {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.55;
            background: #f6f8fb;
            border: 1px solid #e6ebf2;
            color: #445065;
        }

        .auth-note strong {
            color: #1f2a37;
        }

        .auth-success {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(28, 184, 65, .10);
            border: 1px solid rgba(28, 184, 65, .20);
            color: #177a32;
            font-size: 14px;
            line-height: 1.6;
            word-break: break-word;
        }

        .auth-links-stack {
            margin-top: 18px;
            display: flex;
            justify-content: center;
        }

        .auth-card__desc--small {
            max-width: 360px;
            margin-left: auto;
            margin-right: auto;
        }

        @media (max-width: 991px) {

            .auth-note,
            .auth-success {
                font-size: 13px;
            }
        }
    </style>
</head>

<body class="page auth-split">
    <main class="auth-wrap">
        <section class="auth-panel">

            <aside class="auth-left" aria-hidden="true">
                <div class="auth-brand auth-brand--welcome">
                    <h1 class="auth-brand__title">Recuperar acesso</h1>
                    <p class="auth-brand__subtitle">
                        Informe seu e-mail corporativo para receber<br>
                        o link de redefinição de senha.
                    </p>
                </div>

                <div class="popper-shape popper-shape--1"></div>
                <div class="popper-shape popper-shape--2"></div>
            </aside>

            <section class="auth-right">
                <div class="auth-card">

                    <div class="auth-logo-top">
                        <img src="/assets/img/logo.png" alt="Popper">
                    </div>

                    <header class="auth-card__header">
                        <h2 class="auth-card__title">Esqueci minha senha</h2>
                        <p class="auth-card__desc auth-card__desc--small">
                            Digite seu e-mail para receber as instruções de redefinição.
                        </p>
                    </header>

                    <?php if (!empty($error)): ?>
                        <div class="auth-alert" role="alert" aria-live="polite">
                            <i class="fa fa-exclamation-triangle auth-alert__icon" aria-hidden="true"></i>
                            <div class="auth-alert__text"><?= h($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="auth-success" role="status" aria-live="polite">
                            <?= h($success) ?>
                        </div>
                    <?php endif; ?>

                    <form class="auth-form" method="post" action="/esqueci-senha.php" novalidate>
                        <label class="auth-field">
                            <span class="auth-field__label">E-mail corporativo</span>
                            <div class="auth-field__control">
                                <i class="fa fa-envelope auth-field__icon" aria-hidden="true"></i>
                                <input class="auth-input" type="email" name="email" placeholder="seuemail@popper.com.br"
                                    value="<?= h($emailValue) ?>" required autocomplete="email">
                            </div>
                        </label>

                        <button class="auth-btn" type="submit">
                            <span class="auth-btn__text">Enviar link</span>
                        </button>

                        <div class="auth-links-stack">
                            <a class="auth-link" href="/login.php">Voltar ao login</a>
                        </div>

                        <div class="auth-note">
                            <strong>Aviso:</strong> se o e-mail estiver vinculado a uma conta ativa, você receberá uma
                            mensagem com o link para redefinir sua senha.
                        </div>

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

    <script src="/assets/vendor/jquery/jquery-3.2.1.min.js"></script>
    <script src="/assets/vendor/bootstrap/js/popper.js"></script>
    <script src="/assets/vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="/assets/vendor/select2/select2.min.js"></script>
</body>

</html>