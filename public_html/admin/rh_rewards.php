<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$u = current_user();

// header dropdown dashboards
try {
  $dashboards = db()->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}
$current_dash = 'executivo';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['delete_id'])) {
      $id = (int)$_POST['delete_id'];
      $stmt = db()->prepare("DELETE FROM popper_coin_rewards WHERE id=?");
      $stmt->execute([$id]);
      $success = 'Recompensa removida.';
    } else {
      $id = (int)($_POST['id'] ?? 0);
      $title = trim((string)($_POST['title'] ?? ''));
      $description = trim((string)($_POST['description'] ?? ''));
      $cost = (int)($_POST['cost'] ?? 0);
      $inventory = (int)($_POST['inventory'] ?? 0); // ✅ Novo campo
      $isActive = isset($_POST['is_active']) ? 1 : 0;
      $sortOrder = (int)($_POST['sort_order'] ?? 0);

      if ($title === '') throw new Exception('Título é obrigatório.');
      if ($cost <= 0) throw new Exception('Custo deve ser maior que zero.');
      if ($inventory < 0) throw new Exception('Inventário deve ser maior ou igual a zero.'); // ✅ Validação

      if ($id > 0) {
        $stmt = db()->prepare("UPDATE popper_coin_rewards SET title=?, description=?, cost=?, inventory=?, is_active=?, sort_order=? WHERE id=?");
        $stmt->execute([$title, ($description !== '' ? $description : null), $cost, $inventory, $isActive, $sortOrder, $id]);
        $success = 'Recompensa atualizada.';
      } else {
        $stmt = db()->prepare("INSERT INTO popper_coin_rewards (title, description, cost, inventory, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, ($description !== '' ? $description : null), $cost, $inventory, $isActive, $sortOrder]);
        $success = 'Recompensa criada.';
      }
    }
  } catch (Throwable $e) {
    $error = 'Erro: ' . $e->getMessage();
  }
}

$rewards = db()->query("SELECT id, title, description, cost, inventory, is_active, sort_order FROM popper_coin_rewards ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RH · Recompensas — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

<main class="container">
  <h2 class="page-title">RH · Recompensas (Popper Coins)</h2>

  <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <div class="card">
    <h3>Criar / editar recompensa</h3>

    <form method="post" class="form" id="rewardForm">
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

      <label class="field">
        <span class="field__label">
          <input type="checkbox" name="is_active" checked />
          Ativa
        </span>
      </label>

      <button class="btn btn--primary" type="submit" id="saveBtn" style="display: none;">Salvar</button>
      <button class="btn btn--secondary" type="button" id="newBtn" onclick="resetRewardForm()">Novo</button>
    </form>
  </div>

  <div class="card card--mt">
    <h3>Recompensas cadastradas</h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Título</th>
            <th class="right">Custo</th>
            <th class="right">Inventário</th>
            <th>Status</th>
            <th class="right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rewards as $rw): ?>
            <tr>
              <td><?= htmlspecialchars((string)$rw['title'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="right"><?= (int)$rw['cost'] ?></td>
              <td class="right"><?= (int)$rw['inventory'] ?></td>
              <td><?= (int)$rw['is_active'] === 1 ? 'Ativa' : 'Inativa' ?></td>
              <td class="right">
                <button class="btn btn--secondary" type="button"
                  onclick="editReward(<?= (int)$rw['id'] ?>,'<?= htmlspecialchars(addslashes((string)$rw['title']), ENT_QUOTES, 'UTF-8') ?>','<?= htmlspecialchars(addslashes((string)($rw['description'] ?? '')), ENT_QUOTES, 'UTF-8') ?>',<?= (int)$rw['cost'] ?>,<?= (int)$rw['inventory'] ?>,<?= (int)$rw['sort_order'] ?>,<?= (int)$rw['is_active'] ?>)">Editar</button>

                <form method="post" style="display:inline" onsubmit="return confirm('Remover recompensa?');">
                  <input type="hidden" name="delete_id" value="<?= (int)$rw['id'] ?>" />
                  <button class="btn btn--danger" type="submit">Remover</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rewards): ?>
            <tr><td colspan="5" class="muted">Nenhuma recompensa cadastrada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
<script>
  function toggleSaveButton() {
    const idField = document.querySelector('#rewardForm input[name="id"]');
    const saveBtn = document.getElementById('saveBtn');
    const newBtn = document.getElementById('newBtn');

    if (!idField || !saveBtn || !newBtn) return;

    const id = String(idField.value || '').trim();
    const isEditing = (id !== '' && Number(id) > 0);

    if (isEditing) {
      saveBtn.style.setProperty('display', 'inline-flex', 'important');
      newBtn.style.setProperty('display', 'none', 'important');
    } else {
      saveBtn.style.setProperty('display', 'none', 'important');
      newBtn.style.setProperty('display', 'inline-flex', 'important');
    }
  }

  function resetRewardForm(){
    const f = document.getElementById('rewardForm');
    f.querySelector('input[name="id"]').value = '';
    f.querySelector('input[name="title"]').value = '';
    f.querySelector('input[name="description"]').value = '';
    f.querySelector('input[name="cost"]').value = '';
    f.querySelector('input[name="inventory"]').value = '0'; // ✅ Reset para 0
    f.querySelector('input[name="sort_order"]').value = '0';
    f.querySelector('input[name="is_active"]').checked = true;
    toggleSaveButton(); // ✅ Oculta "Salvar" ao resetar
  }

  function editReward(id,title,description,cost,inventory,sort_order,is_active){ // ✅ Inclui inventory
    const f = document.getElementById('rewardForm');
    f.querySelector('input[name="id"]').value = id;
    f.querySelector('input[name="title"]').value = title;
    f.querySelector('input[name="description"]').value = description;
    f.querySelector('input[name="cost"]').value = cost;
    f.querySelector('input[name="inventory"]').value = inventory; // ✅ Preenche inventory
    f.querySelector('input[name="sort_order"]').value = sort_order;
    f.querySelector('input[name="is_active"]').checked = (is_active === 1);
    toggleSaveButton(); // ✅ Mostra "Salvar" ao editar
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ✅ Inicializa ao carregar a página
  document.addEventListener('DOMContentLoaded', toggleSaveButton);
</script>
</body>
</html>