<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';
require_perm('admin.rh');

$u = current_user();

// ✅ para o header marcar Popper Coins como ativo
$activePage = 'coins';

// header dropdown dashboards
try {
  $dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$current_dash = 'executivo';

// ✅ Flash messages (substitui $success/$error)
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'deactivate' && $id > 0) {
      $stmt = db()->prepare("UPDATE popper_coin_rewards SET is_active=0 WHERE id=?");
      $stmt->execute([$id]);
      $_SESSION['flash_success'] = 'Recompensa desativada (não aparece mais para resgate).';
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    } elseif ($action === 'activate' && $id > 0) {
      $stmt = db()->prepare("UPDATE popper_coin_rewards SET is_active=1 WHERE id=?");
      $stmt->execute([$id]);
      $_SESSION['flash_success'] = 'Recompensa reativada (voltou a aparecer para resgate).';
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    } elseif ($action === 'delete' && $id > 0) {
      // Busca imagem atual para remover do disco
      $stmt = db()->prepare("SELECT image_url FROM popper_coin_rewards WHERE id=? LIMIT 1");
      $stmt->execute([$id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      // Exclui registro
      $stmt = db()->prepare("DELETE FROM popper_coin_rewards WHERE id=?");
      $stmt->execute([$id]);

      // Remove arquivo físico (se for do padrão /uploads/rewards/)
      if (!empty($row['image_url']) && is_string($row['image_url'])) {
        $prefix = '/uploads/rewards/';
        if (str_starts_with($row['image_url'], $prefix)) {
          $filePath = __DIR__ . '/..' . $row['image_url']; // vira caminho físico
          if (is_file($filePath)) {
            @unlink($filePath);
          }
        }
      }

      $_SESSION['flash_success'] = 'Recompensa excluída.';
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    } elseif ($action === 'save') {
      $title = trim((string) ($_POST['title'] ?? ''));
      $description = trim((string) ($_POST['description'] ?? ''));
      $cost = (int) ($_POST['cost'] ?? 0);
      $inventory = (int) ($_POST['inventory'] ?? 0);
      $isActive = isset($_POST['is_active']) ? 1 : 0;
      $sortOrder = (int) ($_POST['sort_order'] ?? 0);

      if ($title === '')
        throw new Exception('Título é obrigatório.');
      if ($cost <= 0)
        throw new Exception('Custo deve ser maior que zero.');
      if ($inventory < 0)
        throw new Exception('Inventário deve ser maior ou igual a zero.');

      // ✅ Processar upload de imagem
      $imageUrl = null;
      if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = $_FILES['image']['type'];

        // Validações básicas
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($fileType, $allowedTypes))
          throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.');
        if ($fileSize > 5 * 1024 * 1024)
          throw new Exception('Arquivo muito grande. Máximo 5MB.');

        // Cria diretório se não existir
        $uploadDir = __DIR__ . '/../uploads/rewards/';
        if (!is_dir($uploadDir))
          mkdir($uploadDir, 0755, true);

        // Gera nome único
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = uniqid('reward_', true) . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($fileTmpPath, $destPath))
          throw new Exception('Erro ao salvar imagem.');

        $imageUrl = '/uploads/rewards/' . $newFileName;
      }

      if ($id > 0) {
        if ($imageUrl !== null) {
          $stmt = db()->prepare("UPDATE popper_coin_rewards SET title=?, description=?, cost=?, inventory=?, is_active=?, sort_order=?, image_url=? WHERE id=?");
          $stmt->execute([$title, ($description !== '' ? $description : null), $cost, $inventory, $isActive, $sortOrder, $imageUrl, $id]);
        } else {
          $stmt = db()->prepare("UPDATE popper_coin_rewards SET title=?, description=?, cost=?, inventory=?, is_active=?, sort_order=? WHERE id=?");
          $stmt->execute([$title, ($description !== '' ? $description : null), $cost, $inventory, $isActive, $sortOrder, $id]);
        }
        $_SESSION['flash_success'] = 'Recompensa atualizada.';
      } else {
        $stmt = db()->prepare("INSERT INTO popper_coin_rewards (title, description, cost, inventory, is_active, sort_order, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, ($description !== '' ? $description : null), $cost, $inventory, $isActive, $sortOrder, $imageUrl]);
        $_SESSION['flash_success'] = 'Recompensa criada.';
      }
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    } else {
      throw new Exception('Ação inválida.');
    }
  } catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Erro: ' . $e->getMessage();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
  }
}

// ✅ Query ajustada: ativas primeiro, depois inativas
$rewards = db()->query("SELECT id, title, description, cost, inventory, is_active, sort_order, image_url FROM popper_coin_rewards ORDER BY is_active DESC, sort_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RH · Recompensas — <?= htmlspecialchars((string) APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- CSS global -->
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/../assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/rh_rewards.css?v=<?= filemtime(__DIR__ . '/../assets/css/rh_rewards.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/../assets/css/header.css') ?>" />

  <style>
    .reward-row.is-inactive {
      opacity: 0.55;
      filter: grayscale(0.2);
    }

    .badge--muted {
      background: #ccc;
      color: #666;
    }
  </style>
</head>

<body class="page">

  <?php require_once __DIR__ . '/../app/header.php'; ?>

  <main class="container rh-rewards">
    <h2 class="page-title">RH · Recompensas (Popper Coins)</h2>

    <?php if ($success): ?>
      <div class="alert alert--ok alert--purple">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="card">
      <h3>Criar / editar recompensa</h3>

      <form method="post" enctype="multipart/form-data" class="form" id="rewardForm">
        <input type="hidden" name="action" value="save" />
        <input type="hidden" name="id" value="" />

        <label class="field">
          <span class="field__label">Título</span>
          <input class="field__control" name="title" required maxlength="120" />
        </label>

        <label class="field">
          <span class="field__label">Descrição</span>
          <input class="field__control" name="description" maxlength="500" />
        </label>

        <label class="field">
          <span class="field__label">Custo (coins)</span>
          <input class="field__control" name="cost" type="number" step="1" min="1" required />
        </label>

        <label class="field">
          <span class="field__label">Inventário (quantidade disponível)</span>
          <input class="field__control" name="inventory" type="number" step="1" min="0" value="0" required />
        </label>

        <label class="field">
          <span class="field__label">Ordem</span>
          <input class="field__control" name="sort_order" type="number" step="1" value="0" />
        </label>

        <label class="field file-field">
          <span class="field__label">Imagem (opcional)</span>

          <div class="file-field__row file-field__row--with-preview">
            <div class="file-field__controls">
              <input class="file-input" id="rewardImage" name="image" type="file"
                accept="image/png,image/jpeg,image/gif,image/webp" data-file-name="#rewardImageName"
                data-file-preview="#rewardImagePreview" />

              <label class="file-btn" for="rewardImage">🖼️ Escolher imagem</label>

              <div class="file-meta">
                <span class="file-name" id="rewardImageName">Nenhum arquivo selecionado</span>
                <span class="file-hint">PNG/JPG/GIF/WEBP • Máx: 5MB</span>
              </div>
            </div>

            <div class="file-preview file-preview--side">
              <img id="rewardImagePreview" alt="Prévia da imagem selecionada" />
            </div>
          </div>
        </label>

        <label class="field">
          <span class="field__label">
            <input type="checkbox" name="is_active" checked />
            Ativa
          </span>
        </label>

        <div class="rh-rewards-actions">
          <button class="btn btn--primary" type="submit" id="saveBtn">Criar</button>
        </div>
      </form>
    </div>

    <div class="card card--mt">
      <h3>Recompensas cadastradas</h3>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Imagem</th>
              <th>Título</th>
              <th class="right">Custo</th>
              <th class="right">Inventário</th>
              <th>Status</th>
              <th class="right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rewards as $rw): ?>
              <?php $isActive = ((int) $rw['is_active'] === 1); ?>
              <tr class="reward-row <?= $isActive ? '' : 'is-inactive' ?>">
                <td>
                  <?php if (!empty($rw['image_url'])): ?>
                    <img src="<?= htmlspecialchars($rw['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagem"
                      style="width:50px;height:50px;object-fit:cover;border-radius:4px;" />
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) $rw['title'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="right"><?= (int) $rw['cost'] ?></td>
                <td class="right"><?= (int) $rw['inventory'] ?></td>
                <td>
                  <?php if ($isActive): ?>
                    <span class="badge badge--ok">Ativa</span>
                  <?php else: ?>
                    <span class="badge badge--muted">Inativa</span>
                  <?php endif; ?>
                </td>
                <td class="right">
                  <button class="btn btn--secondary" type="button"
                    onclick="editReward(<?= (int) $rw['id'] ?>,'<?= htmlspecialchars(addslashes((string) $rw['title']), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars(addslashes((string) ($rw['description'] ?? '')), ENT_QUOTES, 'UTF-8') ?>',<?= (int) $rw['cost'] ?>,<?= (int) $rw['inventory'] ?>,<?= (int) $rw['sort_order'] ?>,<?= (int) $rw['is_active'] ?>)">Editar</button>

                  <form method="post" style="display:inline"
                    onsubmit="return confirm('Excluir recompensa? Essa ação não pode ser desfeita.');">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="id" value="<?= (int) $rw['id'] ?>" />
                    <button class="btn btn--danger" type="submit">Excluir</button>
                  </form>

                  <?php if ($isActive): ?>
                    <form method="post" style="display:inline"
                      onsubmit="return confirm('Desativar recompensa? Ela não aparecerá mais para resgate.');">
                      <input type="hidden" name="action" value="deactivate" />
                      <input type="hidden" name="id" value="<?= (int) $rw['id'] ?>" />
                      <button class="btn btn--danger" type="submit">Desativar</button>
                    </form>
                  <?php else: ?>
                    <form method="post" style="display:inline"
                      onsubmit="return confirm('Reativar recompensa? Ela voltará a aparecer para resgate.');">
                      <input type="hidden" name="action" value="activate" />
                      <input type="hidden" name="id" value="<?= (int) $rw['id'] ?>" />
                      <button class="btn btn--primary" type="submit">Reativar</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$rewards): ?>
              <tr>
                <td colspan="6" class="muted">Nenhuma recompensa cadastrada.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <?php require_once __DIR__ . '/../app/footer.php'; ?>
  <div class="modal-backdrop" id="confirmBackdrop" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="modal__header">
      <h4 class="modal__title" id="confirmTitle">Confirmar ação</h4>
      <button type="button" class="modal__close" id="confirmClose" aria-label="Fechar">×</button>
    </div>

    <div class="modal__body">
      <p class="modal__text" id="confirmText"></p>
    </div>

    <div class="modal__actions">
      <button type="button" class="btn btn--secondary" id="confirmCancel">Cancelar</button>
      <button type="button" class="btn btn--danger" id="confirmOk">Confirmar</button>
    </div>
  </div>
</div>

  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/rh_rewards.js?v=<?= filemtime(__DIR__ . '/../assets/js/rh_rewards.js') ?>"></script>
  <?php
  $fiPath = __DIR__ . '/../assets/js/file-inputs.js';
  $fiVer = is_file($fiPath) ? (string) filemtime($fiPath) : (string) time();
  ?>
  <script src="/assets/js/file-inputs.js?v=<?= htmlspecialchars($fiVer, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>