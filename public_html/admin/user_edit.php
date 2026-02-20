<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';
require_admin();

$u = current_user(); // header
$me = current_user();

$success = '';
$error = '';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido.';
  exit;
}

function upload_profile_photo_for_user(int $userId): array {
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

  $dir = __DIR__ . '/../uploads/profile_photos';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);

  $fileName = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
  $destAbs = $dir . '/' . $fileName;

  if (!move_uploaded_file($tmp, $destAbs)) {
    return ['ok' => false, 'path' => null, 'error' => 'Não foi possível salvar a foto.'];
  }

  return ['ok' => true, 'path' => '/uploads/profile_photos/' . $fileName, 'error' => null];
}

// Carrega dados do usuário
$stmt = db()->prepare('SELECT id, name, email, phone, birth_date, gender, profile_photo_path, role, setor, hierarquia, is_active, last_login_at, permissions FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  http_response_code(404);
  echo 'Usuário não encontrado.';
  exit;
}

$allowedPerms = array_keys(PERMISSION_CATALOG);
$curPerms = user_perms($user);
$curPerms = array_values(array_unique(array_intersect($curPerms, $allowedPerms)));

// EXCLUI USUÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if ((int)$me['id'] === (int)$id) {
    $error = 'Você não pode excluir o seu próprio usuário.';
  } else {
    try {
      $stmt = db()->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
      $stmt->execute([$id]);

      if ($stmt->rowCount() < 1) {
        $error = 'Não foi possível excluir (usuário não encontrado).';
      } else {
        header('Location: /admin/users.php?deleted=1');
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Erro ao excluir: ' . $e->getMessage();
    }
  }
}

// Salva alterações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $birth_date = trim($_POST['birth_date'] ?? '');
  $gender = trim($_POST['gender'] ?? '');
  $role = trim($_POST['role'] ?? 'user');
  $setor = trim($_POST['setor'] ?? '');
  $hierarquia = trim($_POST['hierarquia'] ?? 'Assistente');
  $is_active = (int)($_POST['is_active'] ?? 1);
  $newPass = trim($_POST['new_pass'] ?? '');
  $removePhoto = (int)($_POST['remove_photo'] ?? 0) === 1;

  // Permissões (checkbox)
  $perms = $_POST['perms'] ?? [];
  if (!is_array($perms)) $perms = [];
  $perms = array_values(array_unique(array_intersect($perms, $allowedPerms)));
  $permsJson = json_encode($perms, JSON_UNESCAPED_UNICODE);

  if ($name === '' || $email === '' || $setor === '') {
    $error = 'Nome, e-mail e setor são obrigatórios.';
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
      // E-mail duplicado?
      $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
      $stmt->execute([$email, $id]);
      if ($stmt->fetch()) {
        $error = 'Este e-mail já está em uso por outro usuário.';
      } else {
        db()->beginTransaction();
        try {
          $photoPath = $user['profile_photo_path'] ?? null;

          if ($removePhoto) $photoPath = null;

          $up = upload_profile_photo_for_user($id);
          if (!$up['ok']) throw new Exception((string)$up['error']);
          if (!empty($up['path'])) $photoPath = $up['path'];

          if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET name=?, email=?, phone=?, birth_date=?, gender=?, profile_photo_path=?, role=?, setor=?, hierarquia=?, is_active=?, permissions=?, password_hash=? WHERE id=?');
            $stmt->execute([
              $name,
              $email,
              ($phone !== '' ? $phone : null),
              ($birth_date !== '' ? $birth_date : null),
              ($gender !== '' ? $gender : null),
              $photoPath,
              $role,
              $setor,
              $hierarquia,
              $is_active,
              $permsJson,
              $hash,
              $id
            ]);
          } else {
            $stmt = db()->prepare('UPDATE users SET name=?, email=?, phone=?, birth_date=?, gender=?, profile_photo_path=?, role=?, setor=?, hierarquia=?, is_active=?, permissions=? WHERE id=?');
            $stmt->execute([
              $name,
              $email,
              ($phone !== '' ? $phone : null),
              ($birth_date !== '' ? $birth_date : null),
              ($gender !== '' ? $gender : null),
              $photoPath,
              $role,
              $setor,
              $hierarquia,
              $is_active,
              $permsJson,
              $id
            ]);
          }

          db()->commit();
          $success = 'Usuário atualizado com sucesso.';

          // Recarrega
          $stmt = db()->prepare('SELECT id, name, email, phone, birth_date, gender, profile_photo_path, role, setor, hierarquia, is_active, last_login_at, permissions FROM users WHERE id = ? LIMIT 1');
          $stmt->execute([$id]);
          $user = $stmt->fetch(PDO::FETCH_ASSOC);

          $curPerms = user_perms($user);
          $curPerms = array_values(array_unique(array_intersect($curPerms, $allowedPerms)));
        } catch (Throwable $e) {
          db()->rollBack();
          throw $e;
        }
      }
    } catch (Throwable $e) {
      $error = 'Erro ao atualizar: ' . $e->getMessage();
    }
  }
}

$setores = ['FACILITIES','RH','FINANCEIRO','LOGISTICA','COMERCIAL','COMEX','DIRETORIA','CONTROLADORIA','MARKETING'];
$hierarquias = ['Assistente', 'Analista', 'Supervisor', 'Gestor', 'Gerente', 'Diretor'];

function selected(string $a, string $b): string { return $a === $b ? 'selected' : ''; }
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Editar Usuário — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/edit.css?v=<?= filemtime(__DIR__ . '/../assets/css/edit.css') ?>" />

  <style>
    .topbar{overflow:visible!important;}
    .page{overflow:visible!important;}

    .perm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 14px;padding-top:6px;}
    .perm-item{display:inline-flex;align-items:center;gap:8px;margin:0;user-select:none;}
    .perm-help{margin-top:8px;font-size:12px;opacity:.8;}
    @media (max-width: 720px){.perm-grid{grid-template-columns:1fr;}}

    .avatar-lg{width:72px;height:72px;border-radius:999px;object-fit:cover;border:1px solid rgba(15,23,42,.12);background:#fff;}
    .photo-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
    .file-control{padding-top:10px;height:auto;}
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

<a class="link" href="/admin/users.php" style="display:block;margin:12px 20px;">← Voltar</a>

<main class="container">
  <h2 class="page-title">Editar Usuário</h2>

  <div class="card card--narrow">
    <div class="card__header">
      <h3 class="card__title"><?= htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="card__subtitle"><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="form form--edit" action="/admin/user_edit.php?id=<?= (int)$user['id'] ?>" enctype="multipart/form-data">
      <div class="field field--full">
        <label class="field__label">Foto</label>
        <div class="photo-row">
          <?php if (!empty($user['profile_photo_path'])): ?>
            <img class="avatar-lg" src="<?= htmlspecialchars((string)$user['profile_photo_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto">
          <?php else: ?>
            <div class="avatar-lg" style="display:flex;align-items:center;justify-content:center;color:rgba(15,23,42,.45);font-weight:700;">—</div>
          <?php endif; ?>

          <div style="min-width:260px;flex:1;">
            <input class="field__control file-control" name="profile_photo" type="file" accept="image/png,image/jpeg,image/webp" />
            <div class="perm-help">PNG/JPG/WEBP. Máx: 2MB.</div>

            <?php if (!empty($user['profile_photo_path'])): ?>
              <label class="perm-item" style="margin-top:10px;">
                <input type="checkbox" name="remove_photo" value="1">
                <span>Remover foto</span>
              </label>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="field">
        <label class="field__label" for="name">Nome completo</label>
        <input class="field__control" id="name" name="name" type="text" required value="<?= htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="email">E-mail</label>
        <input class="field__control" id="email" name="email" type="email" required value="<?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="phone">Telefone</label>
        <input class="field__control" id="phone" name="phone" type="tel" maxlength="20" value="<?= htmlspecialchars((string)($user['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="birth_date">Data de nascimento</label>
        <input class="field__control" id="birth_date" name="birth_date" type="date" value="<?= htmlspecialchars((string)($user['birth_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="gender">Gênero</label>
        <select class="field__control" id="gender" name="gender">
          <option value="" <?= selected('', (string)($user['gender'] ?? '')) ?>>Selecione...</option>
          <option value="M" <?= selected('M', (string)($user['gender'] ?? '')) ?>>Masculino</option>
          <option value="F" <?= selected('F', (string)($user['gender'] ?? '')) ?>>Feminino</option>
          <option value="O" <?= selected('O', (string)($user['gender'] ?? '')) ?>>Outro</option>
          <option value="N" <?= selected('N', (string)($user['gender'] ?? '')) ?>>Prefere não informar</option>
        </select>
      </div>

      <div class="field">
        <label class="field__label" for="setor">Setor</label>
        <select class="field__control" id="setor" name="setor" required>
          <?php
            $curSetor = (string)($user['setor'] ?? '');
            echo '<option value="">Selecione...</option>';
            foreach ($setores as $s) {
              echo '<option value="' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '" ' . ($s === $curSetor ? 'selected' : '') . '>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</option>';
            }
          ?>
        </select>
      </div>

      <div class="field">
        <label class="field__label" for="hierarquia">Hierarquia</label>
        <select class="field__control" id="hierarquia" name="hierarquia" required>
          <?php
            $cur = (string)($user['hierarquia'] ?? 'Assistente');
            foreach ($hierarquias as $h) {
              echo '<option value="' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '" ' . ($cur === $h ? 'selected' : '') . '>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</option>';
            }
          ?>
        </select>
      </div>

      <div class="field">
        <label class="field__label" for="role">Perfil</label>
        <select class="field__control" id="role" name="role" required>
          <option value="user" <?= (($user['role'] ?? '') === 'user') ? 'selected' : '' ?>>Usuário</option>
          <option value="admin" <?= (($user['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
        </select>
      </div>

      <div class="field field--full">
        <label class="field__label">Permissões (Administração)</label>
        <div class="perm-grid">
          <?php foreach (PERMISSION_CATALOG as $perm => $meta): ?>
            <label class="perm-item">
              <input type="checkbox" name="perms[]" value="<?= htmlspecialchars($perm, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($perm, $curPerms, true) ? 'checked' : '' ?>>
              <span><?= htmlspecialchars((string)($meta['label'] ?? $perm), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="perm-help">Marque as áreas do Admin que este usuário poderá acessar.</div>
      </div>

      <div class="field">
        <label class="field__label" for="is_active">Status</label>
        <select class="field__control" id="is_active" name="is_active" required>
          <option value="1" <?= ((int)($user['is_active'] ?? 0) === 1) ? 'selected' : '' ?>>Ativo</option>
          <option value="0" <?= ((int)($user['is_active'] ?? 0) === 0) ? 'selected' : '' ?>>Inativo</option>
        </select>
      </div>

      <div class="field field--full">
        <label class="field__label" for="new_pass">Nova senha (deixe em branco para não alterar)</label>
        <input class="field__control" id="new_pass" name="new_pass" type="password" autocomplete="new-password" />
        <div class="help">Se ficar em branco, a senha atual será mantida.</div>
      </div>

      <div class="form-actions">
        <a class="link link--pill" href="/admin/users.php">Cancelar</a>
        <button type="submit" class="btn btn--primary">Salvar alterações</button>
      </div>
    </form>

    <form method="post" action="/admin/user_edit.php?id=<?= (int)$user['id'] ?>" onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Essa ação não pode ser desfeita.');" style="margin-top: 14px;">
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="btn btn--danger" <?= ((int)$me['id'] === (int)$user['id']) ? 'disabled' : '' ?>
        title="<?= ((int)$me['id'] === (int)$user['id']) ? 'Você não pode excluir o seu próprio usuário.' : 'Excluir usuário' ?>">
        Excluir usuário
      </button>
    </form>

  </div>
</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>

</body>
</html>