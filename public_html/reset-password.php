<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

try {
    if (!function_exists('db')) {
        throw new RuntimeException('Função db() não encontrada em /app/db.php');
    }

    $pdo = db();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('db() não retornou uma instância válida de PDO.');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    die('<pre style="color:red">Erro ao iniciar: ' . h($e->getMessage()) . '</pre>');
}

if ($token === '') {
    $error = 'Token inválido ou ausente.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $token !== '') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($password === '' || $confirm === '') {
        $error = 'Preencha os campos de senha.';
    } elseif ($password !== $confirm) {
        $error = 'As senhas não coincidem.';
    } elseif (strlen($password) < 8) {
        $error = 'A senha deve ter pelo menos 8 caracteres.';
    } else {
        try {
            $stmt = $pdo->query("
                SELECT id, user_id, email, token_hash, expires_at, used_at
                FROM password_resets
                WHERE used_at IS NULL
                  AND expires_at >= NOW()
                ORDER BY id DESC
            ");

            $resets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $found = null;
            foreach ($resets as $row) {
                if (password_verify($token, $row['token_hash'])) {
                    $found = $row;
                    break;
                }
            }

            if (!$found) {
                $error = 'Este link é inválido ou expirou.';
            } else {
                $newHash = password_hash($password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $stmtUpdateUser = $pdo->prepare("
                    UPDATE users
                    SET password_hash = ?
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmtUpdateUser->execute([
                    $newHash,
                    (int) $found['user_id']
                ]);

                $stmtMarkUsed = $pdo->prepare("
                    UPDATE password_resets
                    SET used_at = NOW()
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmtMarkUsed->execute([
                    (int) $found['id']
                ]);

                $stmtInvalidateOthers = $pdo->prepare("
                    UPDATE password_resets
                    SET used_at = NOW()
                    WHERE user_id = ?
                      AND used_at IS NULL
                      AND id <> ?
                ");
                $stmtInvalidateOthers->execute([
                    (int) $found['user_id'],
                    (int) $found['id']
                ]);

                $pdo->commit();

                $success = 'Senha redefinida com sucesso. Agora você já pode entrar no sistema.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Erro ao redefinir senha: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>

    <head>
        <title>Redefinir senha — <?= htmlspecialchars(APP_NAME) ?></title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
            rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
            rel="stylesheet">

        <link rel="icon" type="image/png" href="/assets/img/favicon.ico" />

        <link rel="stylesheet" href="/assets/css/util.css">
        <link rel="stylesheet" href="/assets/css/auth.css">
        <link rel="stylesheet" href="/assets/css/base.css">
        <link rel="stylesheet" href="/assets/css/auth-split.css">
    </head>
</head>

<body class="page auth-split">

    <main class="auth-wrap">
        <section class="auth-panel">

            <!-- LADO ESQUERDO -->
            <aside class="auth-left" aria-hidden="true">

                <div class="auth-brand auth-brand--welcome">
                    <h1 class="auth-brand__title">Redefinição de senha</h1>

                    <p class="auth-brand__subtitle">
                        Crie uma nova senha para acessar o<br>
                        Popper Conecta.
                    </p>
                </div>

                <div class="popper-shape popper-shape--1"></div>
                <div class="popper-shape popper-shape--2"></div>

            </aside>


            <!-- LADO DIREITO -->
            <section class="auth-right">

                <div class="auth-card">

                    <div class="auth-logo-top">
                        <img src="/assets/img/logo.png" alt="Popper">
                    </div>

                    <header class="auth-card__header">
                        <h2 class="auth-card__title">Nova senha</h2>
                        <p class="auth-card__desc">Digite sua nova senha abaixo.</p>
                    </header>


                    <?php if ($error): ?>
                        <div class="auth-alert">
                            <i class="fa fa-exclamation-triangle auth-alert__icon"></i>
                            <div class="auth-alert__text"><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>


                    <?php if ($success): ?>

                        <div class="auth-success">
                            <?= htmlspecialchars($success) ?>
                        </div>

                        <br>

                        <a class="auth-btn" href="/login.php">
                            <span class="auth-btn__text">Ir para login</span>
                        </a>

                    <?php else: ?>

                        <form method="post" class="auth-form">

                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                            <label class="auth-field">
                                <span class="auth-field__label">Nova senha</span>

                                <div class="auth-field__control">
                                    <i class="fa fa-lock auth-field__icon"></i>

                                    <input class="auth-input" type="password" name="password" required
                                        placeholder="Digite a nova senha">
                                </div>
                            </label>


                            <label class="auth-field">
                                <span class="auth-field__label">Confirmar senha</span>

                                <div class="auth-field__control">
                                    <i class="fa fa-lock auth-field__icon"></i>

                                    <input class="auth-input" type="password" name="confirm" required
                                        placeholder="Confirme a senha">
                                </div>
                            </label>


                            <button class="auth-btn" type="submit">
                                <span class="auth-btn__text">Salvar nova senha</span>
                            </button>

                        </form>

                    <?php endif; ?>

                </div>
            </section>

        </section>
    </main>

</body>

</html>