<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_login();

$u = current_user();
$me = $u;
$activePage = 'me';

$id = (int) ($u['id'] ?? 0);
if ($id <= 0) {
  http_response_code(401);
  echo 'Sessão inválida.';
  exit;
}

$success = '';
$error = '';

function h(?string $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function selected(string $a, string $b): string
{
  return $a === $b ? 'selected' : '';
}

function load_me_by_id(int $id): ?array
{
  $stmt = db()->prepare('
    SELECT id, name, email, phone, birth_date, gender, profile_photo_path, role, setor, hierarquia, is_active
    FROM users
    WHERE id = ? LIMIT 1
  ');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  return $row ?: null;
}

function upload_my_photo(int $userId): array
{
  if (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
    return ['ok' => true, 'path' => null, 'error' => null];
  }

  $file = $_FILES['profile_photo'];
  $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

  if ($err === UPLOAD_ERR_NO_FILE) {
    return ['ok' => true, 'path' => null, 'error' => null];
  }

  if ($err !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'path' => null, 'error' => 'Erro no upload da foto.'];
  }

  $tmp = (string) ($file['tmp_name'] ?? '');
  $size = (int) ($file['size'] ?? 0);

  if ($size <= 0) {
    return ['ok' => false, 'path' => null, 'error' => 'Arquivo de foto inválido.'];
  }

  if ($size > 2 * 1024 * 1024) {
    return ['ok' => false, 'path' => null, 'error' => 'A foto deve ter no máximo 2MB.'];
  }

  $imgInfo = @getimagesize($tmp);
  if ($imgInfo === false || empty($imgInfo['mime'])) {
    return ['ok' => false, 'path' => null, 'error' => 'Arquivo não é uma imagem válida.'];
  }

  $mime = (string) $imgInfo['mime'];
  $ext = null;

  if ($mime === 'image/jpeg') $ext = 'jpg';
  if ($mime === 'image/png')  $ext = 'png';
  if ($mime === 'image/webp') $ext = 'webp';

  if ($ext === null) {
    return ['ok' => false, 'path' => null, 'error' => 'Formato de foto inválido. Use PNG, JPG ou WEBP.'];
  }

  $dir = __DIR__ . '/uploads/profile_photos';
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }

  $fileName = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
  $destAbs = $dir . '/' . $fileName;

  if (!move_uploaded_file($tmp, $destAbs)) {
    return ['ok' => false, 'path' => null, 'error' => 'Não foi possível salvar a foto.'];
  }

  return ['ok' => true, 'path' => '/uploads/profile_photos/' . $fileName, 'error' => null];
}

$uRow = load_me_by_id($id);

if (!$uRow) {
  http_response_code(404);
  echo 'Usuário não encontrado.';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim((string) ($_POST['name'] ?? ''));
  $email = trim((string) ($_POST['email'] ?? ''));
  $phone = trim((string) ($_POST['phone'] ?? ''));
  $birth_date = trim((string) ($_POST['birth_date'] ?? ''));
  $gender = trim((string) ($_POST['gender'] ?? ''));
  $newPass = trim((string) ($_POST['new_pass'] ?? ''));
  $removePhoto = (int) ($_POST['remove_photo'] ?? 0) === 1;

  if ($name === '' || $email === '') {
    $error = 'Nome e e-mail são obrigatórios.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Formato de e-mail inválido.';
  } elseif ($phone !== '' && strlen($phone) > 20) {
    $error = 'Telefone muito longo.';
  } elseif ($birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
    $error = 'Data de nascimento inválida.';
  } elseif ($gender !== '' && !in_array($gender, ['M', 'F', 'O', 'N'], true)) {
    $error = 'Gênero inválido.';
  } elseif ($newPass !== '' && strlen($newPass) < 6) {
    $error = 'A nova senha deve ter pelo menos 6 caracteres.';
  } else {
    try {
      $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
      $stmt->execute([$email, $id]);

      if ($stmt->fetch()) {
        $error = 'Este e-mail já está em uso por outro usuário.';
      } else {
        db()->beginTransaction();

        try {
          $photoPath = $uRow['profile_photo_path'] ?? null;

          if ($removePhoto) {
            $photoPath = null;
          }

          $up = upload_my_photo($id);
          if (!$up['ok']) {
            throw new Exception((string) $up['error']);
          }

          if (!empty($up['path'])) {
            $photoPath = (string) $up['path'];
          }

          if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);

            $stmt = db()->prepare('
              UPDATE users
              SET
                name = ?,
                email = ?,
                phone = ?,
                birth_date = ?,
                gender = ?,
                profile_photo_path = ?,
                password_hash = ?
              WHERE id = ?
            ');

            $stmt->execute([
              $name,
              $email,
              ($phone !== '' ? $phone : null),
              ($birth_date !== '' ? $birth_date : null),
              ($gender !== '' ? $gender : null),
              $photoPath,
              $hash,
              $id,
            ]);
          } else {
            $stmt = db()->prepare('
              UPDATE users
              SET
                name = ?,
                email = ?,
                phone = ?,
                birth_date = ?,
                gender = ?,
                profile_photo_path = ?
              WHERE id = ?
            ');

            $stmt->execute([
              $name,
              $email,
              ($phone !== '' ? $phone : null),
              ($birth_date !== '' ? $birth_date : null),
              ($gender !== '' ? $gender : null),
              $photoPath,
              $id,
            ]);
          }

          db()->commit();
          $success = 'Dados atualizados com sucesso.';

          $uRow = load_me_by_id($id);
          if (!$uRow) {
            throw new Exception('Não foi possível recarregar seus dados.');
          }
        } catch (Throwable $e) {
          if (db()->inTransaction()) {
            db()->rollBack();
          }
          throw $e;
        }
      }
    } catch (Throwable $e) {
      $error = 'Erro ao atualizar: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Meus dados — <?= h((string) APP_NAME) ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />

  <?php if (file_exists(__DIR__ . '/assets/css/user_edit.css')): ?>
    <link rel="stylesheet" href="/assets/css/user_edit.css?v=<?= filemtime(__DIR__ . '/assets/css/user_edit.css') ?>" />
  <?php endif; ?>

  <style>
    .me-page {
      padding-bottom: 32px;
    }

    .me-hero {
      margin-bottom: 18px;
    }

    .me-hero__box {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      flex-wrap: wrap;
      padding: 22px 24px;
      border-radius: 24px;
      background: linear-gradient(135deg, rgba(92, 44, 140, .12), rgba(124, 58, 237, .08), rgba(255, 255, 255, .92));
      border: 1px solid rgba(92, 44, 140, .10);
      box-shadow: 0 18px 50px rgba(15, 23, 42, .06);
    }

    .me-hero__title {
      margin: 0;
      font-size: clamp(1.5rem, 2vw, 2rem);
      font-weight: 800;
      color: #0f172a;
    }

    .me-hero__subtitle {
      margin: 6px 0 0;
      color: #64748b;
      font-size: .98rem;
    }

    .me-shell {
      max-width: 980px;
      margin: 0 auto;
    }

    .me-card {
      background: rgba(255, 255, 255, .96);
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 28px;
      box-shadow: 0 24px 60px rgba(15, 23, 42, .08);
      overflow: hidden;
      backdrop-filter: blur(10px);
    }

    .me-card__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      flex-wrap: wrap;
      padding: 24px 28px;
      border-bottom: 1px solid rgba(15, 23, 42, .08);
      background:
        radial-gradient(circle at top right, rgba(92, 44, 140, .12), transparent 38%),
        linear-gradient(180deg, rgba(248, 250, 252, .96), rgba(255, 255, 255, .98));
    }

    .me-card__identity {
      display: flex;
      align-items: center;
      gap: 16px;
      min-width: 0;
    }

    .me-card__avatar-mini,
    .avatar-lg,
    .avatar-lg--emoji {
      flex: 0 0 auto;
    }

    .me-card__avatar-mini {
      width: 68px;
      height: 68px;
      border-radius: 20px;
      overflow: hidden;
      border: 1px solid rgba(15, 23, 42, .08);
      background: linear-gradient(180deg, #fff, #f8fafc);
      box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      font-size: 1.5rem;
    }

    .me-card__avatar-mini img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .me-card__meta {
      min-width: 0;
    }

    .me-card__title {
      margin: 0;
      font-size: 1.3rem;
      font-weight: 800;
      color: #0f172a;
    }

    .me-card__subtitle {
      margin: 4px 0 0;
      color: #64748b;
      word-break: break-word;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 999px;
      font-weight: 700;
      font-size: .9rem;
      border: 1px solid transparent;
      white-space: nowrap;
    }

    .status-pill--ok {
      background: rgba(22, 163, 74, .10);
      color: #15803d;
      border-color: rgba(22, 163, 74, .16);
    }

    .status-pill--off {
      background: rgba(239, 68, 68, .08);
      color: #b91c1c;
      border-color: rgba(239, 68, 68, .14);
    }

    .me-form {
      padding: 26px 28px 30px;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 18px;
    }

    .field {
      min-width: 0;
    }

    .field--full {
      grid-column: 1 / -1;
    }

    .form-section-title {
      font-size: .94rem;
      font-weight: 800;
      color: #334155;
      text-transform: uppercase;
      letter-spacing: .04em;
      margin-bottom: 4px;
    }

    .field__label {
      display: block;
      margin-bottom: 8px;
      font-size: .92rem;
      font-weight: 700;
      color: #334155;
    }

    .field__control {
      width: 100%;
      min-height: 46px;
      border-radius: 14px;
      border: 1px solid rgba(15, 23, 42, .12);
      background: #fff;
      padding: 12px 14px;
      color: #0f172a;
      outline: none;
      transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .field__control:focus {
      border-color: rgba(92, 44, 140, .45);
      box-shadow: 0 0 0 4px rgba(92, 44, 140, .10);
    }

    .photo-row {
      display: flex;
      align-items: center;
      gap: 18px;
      flex-wrap: wrap;
      padding: 16px;
      border: 1px dashed rgba(15, 23, 42, .12);
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(248, 250, 252, .9), rgba(255, 255, 255, 1));
    }

    .avatar-lg,
    .avatar-lg--emoji {
      width: 96px;
      height: 96px;
      border-radius: 24px;
      object-fit: cover;
      border: 1px solid rgba(15, 23, 42, .10);
      box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
      background: #fff;
    }

    .avatar-lg--emoji {
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 34px;
      color: rgba(15, 23, 42, .45);
      background: linear-gradient(180deg, rgba(255,255,255,.95), rgba(248,250,252,1));
    }

    .file-field__row {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 10px;
    }

    .file-input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
      width: 1px;
      height: 1px;
    }

    .file-btn,
    .btn,
    .link--pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 44px;
      padding: 0 16px;
      border-radius: 14px;
      font-weight: 700;
      text-decoration: none;
      transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
      cursor: pointer;
    }

    .file-btn:hover,
    .btn:hover,
    .link--pill:hover {
      transform: translateY(-1px);
    }

    .file-btn {
      background: #0f172a;
      color: #fff;
      border: 1px solid #0f172a;
      box-shadow: 0 10px 24px rgba(15, 23, 42, .16);
    }

    .file-meta {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .file-name {
      font-size: .94rem;
      font-weight: 700;
      color: #0f172a;
      word-break: break-word;
    }

    .file-hint,
    .help {
      color: #64748b;
      font-size: .9rem;
    }

    .perm-item {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 12px;
      background: rgba(15, 23, 42, .04);
      border: 1px solid rgba(15, 23, 42, .06);
      color: #334155;
      font-size: .92rem;
      font-weight: 600;
    }

    .perm-item input[type="checkbox"] {
      accent-color: #5c2c8c;
    }

    .alert {
      margin: 18px 28px 0;
      padding: 14px 16px;
      border-radius: 16px;
      font-weight: 700;
      border: 1px solid transparent;
    }

    .alert--ok {
      background: rgba(22, 163, 74, .08);
      color: #166534;
      border-color: rgba(22, 163, 74, .14);
    }

    .alert--error {
      background: rgba(239, 68, 68, .08);
      color: #991b1b;
      border-color: rgba(239, 68, 68, .14);
    }

    .info-strip {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      padding: 14px 16px;
      border-radius: 18px;
      background: rgba(15, 23, 42, .04);
      border: 1px solid rgba(15, 23, 42, .08);
    }

    .info-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      border-radius: 999px;
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .08);
      color: #334155;
      font-size: .9rem;
      font-weight: 700;
    }

    .form-actions {
      grid-column: 1 / -1;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      flex-wrap: wrap;
      padding-top: 8px;
    }

    .link--pill {
      background: rgba(15, 23, 42, .06);
      color: #0f172a;
      border: 1px solid rgba(15, 23, 42, .10);
    }

    .btn--primary {
      border: 1px solid #5c2c8c;
      background: linear-gradient(135deg, #5c2c8c, #7c3aed);
      color: #fff;
      box-shadow: 0 14px 28px rgba(92, 44, 140, .22);
    }

    @media (max-width: 860px) {
      .me-form {
        grid-template-columns: 1fr;
      }

      .me-card__header,
      .me-hero__box {
        padding: 18px;
      }

      .me-form {
        padding: 20px 18px 22px;
      }

      .alert {
        margin-left: 18px;
        margin-right: 18px;
      }

      .form-actions {
        justify-content: stretch;
      }

      .form-actions > * {
        flex: 1 1 100%;
      }
    }
  </style>
</head>

<body class="page">
  <?php require_once APP_ROOT . '/app/layout/header.php'; ?>

  <main class="container me-page">
    <section class="me-hero">
      <div class="me-hero__box">
        <div>
          <h1 class="me-hero__title">Meus dados</h1>
          <p class="me-hero__subtitle">Atualize suas informações pessoais, foto de perfil e senha.</p>
        </div>

        <a class="link--pill" href="/index.php">← Voltar</a>
      </div>
    </section>

    <section class="me-shell">
      <div class="me-card">
        <div class="me-card__header">
          <div class="me-card__identity">
            <div class="me-card__avatar-mini">
              <?php if (!empty($uRow['profile_photo_path'])): ?>
                <img src="<?= h((string) $uRow['profile_photo_path']) ?>" alt="Sua foto">
              <?php else: ?>
                <span>👤</span>
              <?php endif; ?>
            </div>

            <div class="me-card__meta">
              <h2 class="me-card__title"><?= h((string) $uRow['name']) ?></h2>
              <p class="me-card__subtitle"><?= h((string) $uRow['email']) ?></p>
            </div>
          </div>

          <div>
            <span class="status-pill <?= ((int) ($uRow['is_active'] ?? 0) === 1) ? 'status-pill--ok' : 'status-pill--off' ?>">
              <?= ((int) ($uRow['is_active'] ?? 0) === 1) ? 'Conta ativa' : 'Conta inativa' ?>
            </span>
          </div>
        </div>

        <?php if ($success): ?>
          <div class="alert alert--ok"><?= h($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert--error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="me-form" action="/me.php" autocomplete="off" enctype="multipart/form-data">
          <div class="field field--full">
            <div class="form-section-title">Foto de perfil</div>

            <div class="photo-row">
              <?php if (!empty($uRow['profile_photo_path'])): ?>
                <img class="avatar-lg" id="mePhotoPreviewImg" src="<?= h((string) $uRow['profile_photo_path']) ?>" alt="Foto">
                <div class="avatar-lg avatar-lg--emoji" id="mePhotoEmoji" style="display:none;" aria-label="Sem foto">👤</div>
              <?php else: ?>
                <img class="avatar-lg" id="mePhotoPreviewImg" alt="Foto" style="display:none;" />
                <div class="avatar-lg avatar-lg--emoji" id="mePhotoEmoji" aria-label="Sem foto">👤</div>
              <?php endif; ?>

              <div style="min-width:280px; flex:1;">
                <div class="file-field__row">
                  <input class="file-input" id="meProfilePhoto" name="profile_photo" type="file" accept="image/png,image/jpeg,image/webp" />
                  <label class="file-btn" for="meProfilePhoto">Escolher foto</label>

                  <div class="file-meta">
                    <span class="file-name" id="meProfilePhotoName">Nenhum arquivo selecionado</span>
                    <span class="file-hint">PNG/JPG/WEBP • Máx: 2MB</span>
                  </div>

                  <?php if (!empty($uRow['profile_photo_path'])): ?>
                    <label class="perm-item">
                      <input type="checkbox" name="remove_photo" value="1">
                      <span>Remover foto atual</span>
                    </label>
                  <?php endif; ?>
                </div>

                <div class="help" style="margin-top:8px;">Escolha uma nova foto e clique em salvar para atualizar.</div>
              </div>
            </div>
          </div>

          <div class="field field--full">
            <div class="form-section-title">Dados principais</div>
          </div>

          <div class="field">
            <label class="field__label" for="name">Nome completo</label>
            <input class="field__control" id="name" name="name" type="text" required value="<?= h((string) $uRow['name']) ?>" />
          </div>

          <div class="field">
            <label class="field__label" for="email">E-mail</label>
            <input class="field__control" id="email" name="email" type="email" required value="<?= h((string) $uRow['email']) ?>" />
          </div>

          <div class="field">
            <label class="field__label" for="phone">Telefone</label>
            <input class="field__control" id="phone" name="phone" type="tel" maxlength="20" value="<?= h((string) ($uRow['phone'] ?? '')) ?>" />
          </div>

          <div class="field">
            <label class="field__label" for="birth_date">Data de nascimento</label>
            <input class="field__control" id="birth_date" name="birth_date" type="date" value="<?= h((string) ($uRow['birth_date'] ?? '')) ?>" />
          </div>

          <div class="field field--full">
            <label class="field__label" for="gender">Gênero</label>
            <select class="field__control" id="gender" name="gender">
              <option value="" <?= selected('', (string) ($uRow['gender'] ?? '')) ?>>Selecione...</option>
              <option value="M" <?= selected('M', (string) ($uRow['gender'] ?? '')) ?>>Masculino</option>
              <option value="F" <?= selected('F', (string) ($uRow['gender'] ?? '')) ?>>Feminino</option>
              <option value="O" <?= selected('O', (string) ($uRow['gender'] ?? '')) ?>>Outro</option>
              <option value="N" <?= selected('N', (string) ($uRow['gender'] ?? '')) ?>>Prefere não informar</option>
            </select>
          </div>

          <div class="field field--full">
            <div class="form-section-title">Segurança</div>
          </div>

          <div class="field field--full">
            <label class="field__label" for="new_pass">Nova senha</label>
            <input class="field__control" id="new_pass" name="new_pass" type="password" autocomplete="new-password" />
            <div class="help" style="margin-top:8px;">Deixe em branco para manter a senha atual.</div>
          </div>

          <div class="field field--full">
            <div class="form-section-title">Informações da conta</div>
            <div class="info-strip">
              <span class="info-chip">Perfil: <strong><?= h((string) ($uRow['role'] ?? '')) ?></strong></span>
              <?php if (!empty($uRow['setor'])): ?>
                <span class="info-chip">Setor: <strong><?= h((string) $uRow['setor']) ?></strong></span>
              <?php endif; ?>
              <?php if (!empty($uRow['hierarquia'])): ?>
                <span class="info-chip">Hierarquia: <strong><?= h((string) $uRow['hierarquia']) ?></strong></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-actions">
            <a class="link--pill" href="/index.php">Cancelar</a>
            <button type="submit" class="btn btn--primary">Salvar alterações</button>
          </div>
        </form>
      </div>
    </section>
  </main>

  <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

  <script>
    (function () {
      const input = document.getElementById('meProfilePhoto');
      const img = document.getElementById('mePhotoPreviewImg');
      const emoji = document.getElementById('mePhotoEmoji');
      const remove = document.querySelector('input[name="remove_photo"]');
      const fileNameEl = document.getElementById('meProfilePhotoName');

      if (!input || !img || !emoji) return;

      function showEmoji() {
        img.style.display = 'none';
        img.removeAttribute('src');
        emoji.style.display = '';
      }

      function showImg(src) {
        img.src = src;
        img.style.display = '';
        emoji.style.display = 'none';
      }

      input.addEventListener('change', function () {
        const file = input.files && input.files[0] ? input.files[0] : null;

        if (fileNameEl) {
          fileNameEl.textContent = file ? file.name : 'Nenhum arquivo selecionado';
        }

        if (!file) return;

        if (remove && remove.checked) {
          remove.checked = false;
        }

        const url = URL.createObjectURL(file);
        showImg(url);

        img.onload = function () {
          URL.revokeObjectURL(url);
        };
      });

      if (remove) {
        remove.addEventListener('change', function () {
          if (!remove.checked) return;

          input.value = '';
          if (fileNameEl) {
            fileNameEl.textContent = 'Nenhum arquivo selecionado';
          }
          showEmoji();
        });
      }
    })();
  </script>
</body>

</html>