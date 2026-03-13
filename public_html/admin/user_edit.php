<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

require_admin_perm('admin.users');

$u = current_user();
$me = $u;
$activePage = 'admin';

try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido.';
  exit;
}

function h(?string $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function selected(string $a, string $b): string
{
  return $a === $b ? 'selected' : '';
}

function load_user_by_id(int $id): ?array
{
  $stmt = db()->prepare('
    SELECT id, name, email, phone, birth_date, gender, profile_photo_path, role, setor, hierarquia, is_active, last_login_at, permissions
    FROM users
    WHERE id = ? LIMIT 1
  ');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  return $row ?: null;
}

function upload_profile_photo_for_user(int $userId): array
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

  if ($mime === 'image/jpeg')
    $ext = 'jpg';
  if ($mime === 'image/png')
    $ext = 'png';
  if ($mime === 'image/webp')
    $ext = 'webp';

  if ($ext === null) {
    return ['ok' => false, 'path' => null, 'error' => 'Formato de foto inválido. Use PNG, JPG ou WEBP.'];
  }

  $dir = __DIR__ . '/../uploads/profile_photos';
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

$user = load_user_by_id($id);

if (!$user) {
  http_response_code(404);
  echo 'Usuário não encontrado.';
  exit;
}

$allowedAdminPerms = array_keys(ADMIN_PERMISSION_CATALOG);
$allowedDashPerms = array_keys(DASHBOARD_CATALOG);

$curAllPerms = user_perms($user);
$curAdminPerms = array_values(array_unique(array_intersect($curAllPerms, $allowedAdminPerms)));
$curDashPerms = array_values(array_unique(array_intersect($curAllPerms, $allowedDashPerms)));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if ((int) $me['id'] === (int) $id) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete') {
  $name = trim((string) ($_POST['name'] ?? ''));
  $email = trim((string) ($_POST['email'] ?? ''));
  $phone = trim((string) ($_POST['phone'] ?? ''));
  $birth_date = trim((string) ($_POST['birth_date'] ?? ''));
  $gender = trim((string) ($_POST['gender'] ?? ''));
  $role = trim((string) ($_POST['role'] ?? 'user'));
  $setor = trim((string) ($_POST['setor'] ?? ''));
  $hierarquia = trim((string) ($_POST['hierarquia'] ?? 'Assistente'));
  $is_active = (int) ($_POST['is_active'] ?? 1);
  $newPass = trim((string) ($_POST['new_pass'] ?? ''));
  $removePhoto = (int) ($_POST['remove_photo'] ?? 0) === 1;

  $permsAdmin = $_POST['perms_admin'] ?? [];
  $permsDash = $_POST['perms_dash'] ?? [];

  if (!is_array($permsAdmin))
    $permsAdmin = [];
  if (!is_array($permsDash))
    $permsDash = [];

  $permsAdmin = array_values(array_unique(array_intersect($permsAdmin, $allowedAdminPerms)));
  $permsDash = array_values(array_unique(array_intersect($permsDash, $allowedDashPerms)));

  $perms = array_values(array_unique(array_merge($permsAdmin, $permsDash)));
  $permsJson = json_encode($perms, JSON_UNESCAPED_UNICODE);

  if ($name === '' || $email === '' || $setor === '') {
    $error = 'Nome, e-mail e setor são obrigatórios.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Formato de e-mail inválido.';
  } elseif ($phone !== '' && strlen($phone) > 20) {
    $error = 'Telefone muito longo.';
  } elseif ($birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
    $error = 'Data de nascimento inválida.';
  } elseif ($gender !== '' && !in_array($gender, ['M', 'F', 'O', 'N'], true)) {
    $error = 'Gênero inválido.';
  } elseif (!in_array($role, ['user', 'admin'], true)) {
    $error = 'Perfil inválido.';
  } elseif (!in_array($hierarquia, ['Assistente', 'Analista', 'Supervisor', 'Gestor', 'Gerente', 'Diretor'], true)) {
    $error = 'Hierarquia inválida.';
  } elseif (!in_array($is_active, [0, 1], true)) {
    $error = 'Status inválido.';
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
          $photoPath = $user['profile_photo_path'] ?? null;

          if ($removePhoto) {
            $photoPath = null;
          }

          $up = upload_profile_photo_for_user($id);
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
                role = ?,
                setor = ?,
                hierarquia = ?,
                is_active = ?,
                permissions = ?,
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
              $role,
              $setor,
              $hierarquia,
              $is_active,
              $permsJson,
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
                profile_photo_path = ?,
                role = ?,
                setor = ?,
                hierarquia = ?,
                is_active = ?,
                permissions = ?
              WHERE id = ?
            ');

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
              $id,
            ]);
          }

          db()->commit();

          $success = 'Usuário atualizado com sucesso.';

          $user = load_user_by_id($id);
          if (!$user) {
            throw new Exception('Não foi possível recarregar os dados do usuário.');
          }

          $curAllPerms = user_perms($user);
          $curAdminPerms = array_values(array_unique(array_intersect($curAllPerms, $allowedAdminPerms)));
          $curDashPerms = array_values(array_unique(array_intersect($curAllPerms, $allowedDashPerms)));
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

$setores = [
  'FACILITIES',
  'RH',
  'FINANCEIRO',
  'LOGISTICA',
  'COMERCIAL',
  'COMEX',
  'DIRETORIA',
  'CONTROLADORIA',
  'MARKETING',
];

$hierarquias = [
  'Assistente',
  'Analista',
  'Supervisor',
  'Gestor',
  'Gerente',
  'Diretor',
];

$groups = [];
foreach (DASHBOARD_CATALOG as $perm => $meta) {
  $group = (string) ($meta['group'] ?? 'Outros');
  if (!isset($groups[$group])) {
    $groups[$group] = [];
  }
  $groups[$group][$perm] = $meta;
}
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Editar Usuário — <?= h((string) APP_NAME) ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/../assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/user_edit.css?v=<?= filemtime(__DIR__ . '/../assets/css/user_edit.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/../assets/css/header.css') ?>" />
</head>

<body class="page">
  <?php require_once __DIR__ . '/../app/header.php'; ?>

  <main class="container user-edit">
    <section class="user-edit-hero">
      <div class="user-edit-hero__content">
        <div class="user-edit-hero__actions">
          <a class="btn-ghost" href="/admin/users.php"></a>
        </div>
      </div>
    </section>

    <section class="user-edit-shell">
      <div class="user-edit-card">
        <div class="user-edit-card__header">
          <div class="user-edit-card__identity">
            <div class="user-edit-card__avatar-mini">
              <?php if (!empty($user['profile_photo_path'])): ?>
                <img src="<?= h((string) $user['profile_photo_path']) ?>" alt="Foto do usuário">
              <?php else: ?>
                <span>👤</span>
              <?php endif; ?>
            </div>

            <div class="user-edit-card__meta">
              <h2 class="user-edit-card__title"><?= h((string) $user['name']) ?></h2>
              <p class="user-edit-card__subtitle"><?= h((string) $user['email']) ?></p>
            </div>
          </div>

          <div class="user-edit-card__status">
            <span
              class="status-pill <?= ((int) ($user['is_active'] ?? 0) === 1) ? 'status-pill--ok' : 'status-pill--off' ?>">
              <?= ((int) ($user['is_active'] ?? 0) === 1) ? 'Ativo' : 'Inativo' ?>
            </span>
          </div>
        </div>

        <?php if ($success): ?>
          <div class="alert alert--ok"><?= h($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert--error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form form--edit" action="/admin/user_edit.php?id=<?= (int) $user['id'] ?>"
          enctype="multipart/form-data">
          <div class="field field--full">
            <div class="form-section-title">Foto de perfil</div>

            <div class="photo-row">
              <?php if (!empty($user['profile_photo_path'])): ?>
                <img class="avatar-lg" id="userPhotoPreviewImg" src="<?= h((string) $user['profile_photo_path']) ?>"
                  alt="Foto">
                <div class="avatar-lg avatar-lg--emoji" id="userPhotoEmoji" style="display:none;" aria-label="Sem foto">👤
                </div>
              <?php else: ?>
                <img class="avatar-lg" id="userPhotoPreviewImg" alt="Foto" style="display:none;" />
                <div class="avatar-lg avatar-lg--emoji" id="userPhotoEmoji" aria-label="Sem foto">👤</div>
              <?php endif; ?>

              <div>
                <div class="file-field__row">
                  <input class="file-input" id="userProfilePhoto" name="profile_photo" type="file"
                    accept="image/png,image/jpeg,image/webp" />
                  <label class="file-btn" for="userProfilePhoto">Escolher foto</label>

                  <div class="file-meta">
                    <span class="file-name" id="userProfilePhotoName">Nenhum arquivo selecionado</span>
                    <span class="file-hint">PNG/JPG/WEBP • Máx: 2MB</span>
                  </div>

                  <?php if (!empty($user['profile_photo_path'])): ?>
                    <label class="perm-item">
                      <input type="checkbox" name="remove_photo" value="1">
                      <span>Remover foto atual</span>
                    </label>
                  <?php endif; ?>
                </div>

                <div class="help">Escolha uma foto e clique em salvar para atualizar.</div>
              </div>
            </div>
          </div>

          <div class="field field--full">
            <div class="form-section-title">Dados principais</div>
          </div>

          <div class="field">
            <label class="field__label" for="name">Nome completo</label>
            <input class="field__control" id="name" name="name" type="text" required
              value="<?= h((string) $user['name']) ?>" />
          </div>

          <div class="field">
            <label class="field__label" for="email">E-mail</label>
            <input class="field__control" id="email" name="email" type="email" required
              value="<?= h((string) $user['email']) ?>" />
          </div>

          <div class="field">
            <label class="field__label" for="phone">Telefone</label>
            <input class="field__control" id="phone" name="phone" type="tel" maxlength="20"
              value="<?= h((string) ($user['phone'] ?? '')) ?>" />
          </div>

          <div class="field">
            <label class="field__label" for="birth_date">Data de nascimento</label>
            <input class="field__control" id="birth_date" name="birth_date" type="date"
              value="<?= h((string) ($user['birth_date'] ?? '')) ?>" />
          </div>

          <div class="field">
            <label class="field__label" for="gender">Gênero</label>
            <select class="field__control" id="gender" name="gender">
              <option value="" <?= selected('', (string) ($user['gender'] ?? '')) ?>>Selecione...</option>
              <option value="M" <?= selected('M', (string) ($user['gender'] ?? '')) ?>>Masculino</option>
              <option value="F" <?= selected('F', (string) ($user['gender'] ?? '')) ?>>Feminino</option>
              <option value="O" <?= selected('O', (string) ($user['gender'] ?? '')) ?>>Outro</option>
              <option value="N" <?= selected('N', (string) ($user['gender'] ?? '')) ?>>Prefere não informar</option>
            </select>
          </div>

          <div class="field">
            <label class="field__label" for="setor">Setor</label>
            <select class="field__control" id="setor" name="setor" required>
              <option value="">Selecione...</option>
              <?php foreach ($setores as $s): ?>
                <option value="<?= h($s) ?>" <?= $s === (string) ($user['setor'] ?? '') ? 'selected' : '' ?>>
                  <?= h($s) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label class="field__label" for="hierarquia">Hierarquia</label>
            <select class="field__control" id="hierarquia" name="hierarquia" required>
              <?php foreach ($hierarquias as $h): ?>
                <option value="<?= h($h) ?>" <?= $h === (string) ($user['hierarquia'] ?? 'Assistente') ? 'selected' : '' ?>>
                  <?= h($h) ?>
                </option>
              <?php endforeach; ?>
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
            <div class="form-section-title">Permissões administrativas</div>
          </div>

          <div class="field field--full">
            <div class="perm-grid">
              <?php foreach (ADMIN_PERMISSION_CATALOG as $perm => $meta): ?>
                <label class="perm-item">
                  <input type="checkbox" name="perms_admin[]" value="<?= h($perm) ?>" <?= in_array($perm, $curAdminPerms, true) ? 'checked' : '' ?>>
                  <span><?= h((string) ($meta['label'] ?? $perm)) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="perm-help">Marque as áreas do Admin que este usuário poderá acessar.</div>
          </div>

          <div class="field field--full">
            <div class="form-section-title">Permissões de dashboards</div>
          </div>

          <div class="field field--full">
            <?php foreach ($groups as $groupName => $items): ?>
              <div class="perm-group">
                <div class="perm-group__name"><?= h($groupName) ?></div>

                <div class="perm-grid">
                  <?php foreach ($items as $perm => $meta): ?>
                    <label class="perm-item">
                      <input type="checkbox" name="perms_dash[]" value="<?= h($perm) ?>" <?= in_array($perm, $curDashPerms, true) ? 'checked' : '' ?>>
                      <span><?= h((string) ($meta['label'] ?? $perm)) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>

            <div class="perm-help">Marque quais dashboards este usuário pode visualizar.</div>
          </div>

          <div class="field field--full">
            <div class="form-section-title">Segurança e status</div>
          </div>

          <div class="field">
            <label class="field__label" for="is_active">Status</label>
            <select class="field__control" id="is_active" name="is_active" required>
              <option value="1" <?= ((int) ($user['is_active'] ?? 0) === 1) ? 'selected' : '' ?>>Ativo</option>
              <option value="0" <?= ((int) ($user['is_active'] ?? 0) === 0) ? 'selected' : '' ?>>Inativo</option>
            </select>
          </div>

          <div class="field field--full">
            <label class="field__label" for="new_pass">Nova senha</label>
            <input class="field__control" id="new_pass" name="new_pass" type="password" autocomplete="new-password" />
            <div class="help">Deixe em branco para manter a senha atual.</div>
          </div>

          <div class="form-actions">
            <a class="link link--pill" href="/admin/users.php">Cancelar</a>
            <button type="submit" class="btn btn--primary">Salvar alterações</button>
          </div>
        </form>

        <form method="post" action="/admin/user_edit.php?id=<?= (int) $user['id'] ?>"
          onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Essa ação não pode ser desfeita.');"
          class="delete-form">
          <input type="hidden" name="action" value="delete">

          <button type="submit" class="btn btn--danger" <?= ((int) $me['id'] === (int) $user['id']) ? 'disabled' : '' ?>
            title="<?= ((int) $me['id'] === (int) $user['id']) ? 'Você não pode excluir o seu próprio usuário.' : 'Excluir usuário' ?>">
            Excluir usuário
          </button>
        </form>
      </div>
    </section>
  </main>

  <?php require_once __DIR__ . '/../app/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/user-edit.js?v=<?= filemtime(__DIR__ . '/../assets/js/user-edit.js') ?>"></script>

  <script>
    (function () {
      const input = document.getElementById('userProfilePhoto');
      const img = document.getElementById('userPhotoPreviewImg');
      const emoji = document.getElementById('userPhotoEmoji');
      const remove = document.querySelector('input[name="remove_photo"]');
      const fileNameEl = document.getElementById('userProfilePhotoName');

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
        if (!file) return;

        if (fileNameEl) {
          fileNameEl.textContent = file.name;
        }

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

    <script>
document.addEventListener("DOMContentLoaded", () => {

        // marca item individual + visita página
        document.querySelectorAll(".notif__item").forEach(item => {
          item.addEventListener("click", function (e) {
            let id = this.dataset.id;
            let href = this.getAttribute("href");

            if (!href || href === "#") return; // itens não clicáveis

            e.preventDefault(); // evita abrir antes de marcar lida

            fetch("/notifications_read.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: "id=" + encodeURIComponent(id)
            })
              .then(r => r.json())
              .then(resp => {
                if (resp.ok) {
                  this.classList.remove("is-unread");

                  // atualiza badge no ícone
                  let badge = document.querySelector(".notif__badge");
                  if (badge) {
                    let n = parseInt(badge.innerText.replace("+", "") || "0") - 1;
                    badge.innerText = n > 0 ? n : "";
                    if (n <= 0) badge.style.display = "none";
                  }
                }

                // abre página após marcar como lida
                window.location.href = href;
              })
              .catch(() => window.location.href = href);
          });
        });

      // marcar todas
      let btnAll = document.getElementById("notifMarkAll");
      if (btnAll) {
        btnAll.addEventListener("click", () => {
          fetch("/notifications_read.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "all=1"
          })
            .then(r => r.json())
            .then(resp => {
              if (resp.ok) {
                document.querySelectorAll(".notif__item.is-unread").forEach(i => i.classList.remove("is-unread"));
                let badge = document.querySelector(".notif__badge");
                if (badge) badge.style.display = "none";
              }
            });
        });
  }

});
  </script>
  </script>
</body>

</html>