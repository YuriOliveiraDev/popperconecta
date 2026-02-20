<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

$u = current_user();
$id = (int)($u['id'] ?? 0);
if ($id <= 0) {
  http_response_code(401);
  echo 'Sessão inválida.';
  exit;
}

$success = '';
$error = '';

function upload_my_photo(int $userId): array {
  if (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
    return ['ok' => true, 'path' => null, 'error' => null];
  }

  $file = $_FILES['profile_photo'];
  $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

  if ($err === UPLOAD_ERR_NO_FILE) return ['ok' => true, 'path' => null, 'error' => null];
  if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'path' => null, 'error' => 'Erro no upload da foto.'];

  $tmp = (string)($file['tmp_name'] ?? '');
  $size = (int)($file['size'] ?? 0);

  if ($size <= 0) return ['ok' => false, 'path' => null, 'error' => 'Arquivo de foto inválido.'];
  if ($size > 2 * 1024 * 1024) return ['ok' => false, 'path' => null, 'error' => 'A foto deve ter no máximo 2MB.'];

  $imgInfo = @getimagesize($tmp);
  if ($imgInfo === false || empty($imgInfo['mime'])) {
    return ['ok' => false, 'path' => null, 'error' => 'Arquivo não é uma imagem válida.'];
  }
  $mime = (string)$imgInfo['mime'];

  $ext = null;
  if ($mime === 'image/jpeg') $ext = 'jpg';
  if ($mime === 'image/png')  $ext = 'png';
  if ($mime === 'image/webp') $ext = 'webp';

  if ($ext === null) return ['ok' => false, 'path' => null, 'error' => 'Formato de foto inválido. Use PNG, JPG ou WEBP.'];

  $dir = __DIR__ . '/uploads/profile_photos';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  $fileName = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
  $destAbs = $dir . '/' . $fileName;

  if (!move_uploaded_file($tmp, $destAbs)) {
    return ['ok' => false, 'path' => null, 'error' => 'Não foi possível salvar a foto.'];
  }

  return ['ok' => true, 'path' => '/uploads/profile_photos/' . $fileName, 'error' => null];
}

$stmt = db()->prepare('SELECT id, name, email, phone, birth_date, gender, profile_photo_path, role, setor, hierarquia FROM users WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$uRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$uRow) {
  http_response_code(404);
  echo 'Usuário não encontrado.';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $birth_date = trim($_POST['birth_date'] ?? '');
  $gender = trim($_POST['gender'] ?? '');
  $newPass = trim($_POST['new_pass'] ?? '');
  $removePhoto = (int)($_POST['remove_photo'] ?? 0) === 1;

  if ($name === '' || $email === '') {
    $error = 'Nome e e-mail são obrigatórios.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Formato de e-mail inválido.';
  } elseif ($phone !== '' && strlen($phone) > 20) {
    $error = 'Telefone muito longo.';
  } elseif ($birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
    $error = 'Data de nascimento inválida.';
  } elseif ($gender !== '' && !in_array($gender, ['M','F','O','N'], true)) {
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
          if ($removePhoto) $photoPath = null;

          $up = upload_my_photo($id);
          if (!$up['ok']) throw new Exception((string)$up['error']);
          if (!empty($up['path'])) $photoPath = $up['path'];

          if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET name=?, email=?, phone=?, birth_date=?, gender=?, profile_photo_path=?, password_hash=? WHERE id=?');
            $stmt->execute([
              $name,
              $email,
              ($phone !== '' ? $phone : null),
              ($birth_date !== '' ? $birth_date : null),
              ($gender !== '' ? $gender : null),
              $photoPath,
              $hash,
              $id
            ]);
          } else {
            $stmt = db()->prepare('UPDATE users SET name=?, email=?, phone=?, birth_date=?, gender=?, profile_photo_path=? WHERE id=?');
            $stmt->execute([
              $name,
              $email,
              ($phone !== '' ? $phone : null),
              ($birth_date !== '' ? $birth_date : null),
              ($gender !== '' ? $gender : null),
              $photoPath,
              $id
            ]);
          }

          db()->commit();
          $success = 'Dados atualizados com sucesso.';
        } catch (Throwable $e) {
          db()->rollBack();
          throw $e;
        }
      }
    } catch (Throwable $e) {
      $error = 'Erro ao atualizar: ' . $e->getMessage();
    }
  }

  $stmt = db()->prepare('SELECT id, name, email, phone, birth_date, gender, profile_photo_path, role, setor, hierarquia FROM users WHERE id=? LIMIT 1');
  $stmt->execute([$id]);
  $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

$activePage = 'me';

function selected(string $a, string $b): string { return $a === $b ? 'selected' : ''; }
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Meus dados — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />

  <style>
    .avatar-lg{width:72px;height:72px;border-radius:999px;object-fit:cover;border:1px solid rgba(15,23,42,.12);background:#fff;}
    .photo-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
    .file-control{padding-top:10px;height:auto;}
    .help-row{margin-top:8px;}
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container">
  <h2 class="page-title">Meus dados</h2>

  <section class="card card--narrow">
    <div class="card__header">
      <h3 class="card__title">Editar dados</h3>
      <p class="card__subtitle">Atualize seus dados e sua foto.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="form form--edit" action="/me.php" autocomplete="off" enctype="multipart/form-data">
      <div class="field field--full">
        <label class="field__label">Foto</label>
        <div class="photo-row">
          <?php if (!empty($uRow['profile_photo_path'])): ?>
            <img class="avatar-lg" src="<?= htmlspecialchars((string)$uRow['profile_photo_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto">
          <?php else: ?>
            <div class="avatar-lg" style="display:flex;align-items:center;justify-content:center;color:rgba(15,23,42,.45);font-weight:700;">—</div>
          <?php endif; ?>

          <div style="min-width:260px;flex:1;">
            <input class="field__control file-control" name="profile_photo" type="file" accept="image/png,image/jpeg,image/webp" />
            <div class="help help-row">PNG/JPG/WEBP. Máx: 2MB.</div>

            <?php if (!empty($uRow['profile_photo_path'])): ?>
              <label class="perm-item" style="margin-top:10px;">
                <input type="checkbox" name="remove_photo" value="1">
                <span>Remover foto</span>
              </label>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="field">
        <label class="field__label" for="name">Nome</label>
        <input class="field__control" id="name" name="name" type="text" required value="<?= htmlspecialchars((string)($uRow['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="email">E-mail</label>
        <input class="field__control" id="email" name="email" type="email" required value="<?= htmlspecialchars((string)($uRow['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="phone">Telefone</label>
        <input class="field__control" id="phone" name="phone" type="tel" maxlength="20" value="<?= htmlspecialchars((string)($uRow['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="birth_date">Data de Nascimento</label>
        <input class="field__control" id="birth_date" name="birth_date" type="date" value="<?= htmlspecialchars((string)($uRow['birth_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="gender">Gênero</label>
        <select class="field__control" id="gender" name="gender">
          <option value="" <?= selected('', (string)($uRow['gender'] ?? '')) ?>>Selecione...</option>
          <option value="M" <?= selected('M', (string)($uRow['gender'] ?? '')) ?>>Masculino</option>
          <option value="F" <?= selected('F', (string)($uRow['gender'] ?? '')) ?>>Feminino</option>
          <option value="O" <?= selected('O', (string)($uRow['gender'] ?? '')) ?>>Outro</option>
          <option value="N" <?= selected('N', (string)($uRow['gender'] ?? '')) ?>>Prefere não informar</option>
        </select>
      </div>

      <div class="field field--full">
        <label class="field__label" for="new_pass">Nova senha (opcional)</label>
        <input class="field__control" id="new_pass" name="new_pass" type="password" autocomplete="new-password" />
        <div class="help">Deixe em branco para não alterar.</div>
      </div>

      <div class="field field--full">
        <div class="help">
          Perfil: <strong><?= htmlspecialchars((string)($uRow['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
          <?php if (!empty($uRow['setor'])): ?> | Setor: <strong><?= htmlspecialchars((string)$uRow['setor'], ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
          <?php if (!empty($uRow['hierarquia'])): ?> | Hierarquia: <strong><?= htmlspecialchars((string)$uRow['hierarquia'], ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <a class="link link--pill" href="/index.php">Voltar</a>
        <button type="submit" class="btn btn--primary">Salvar</button>
      </div>
    </form>
  </section>
</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

</body>
</html>