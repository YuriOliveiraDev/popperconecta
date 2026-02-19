<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

// ✅ Essencial para o header.php funcionar
$u = current_user();

// ✅ Dropdown "Dashboards" no header
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['delete'])) {
      $stmt = db()->prepare('DELETE FROM comunicados WHERE id=?');
      $stmt->execute([$_POST['delete']]);
      $success = 'Comunicado removido.';
    } elseif (isset($_POST['save'])) {
      $id = $_POST['id'] ?? null;
      $titulo = trim($_POST['titulo'] ?? '');
      $conteudo = trim($_POST['conteudo'] ?? '');
      $ativo = isset($_POST['ativo']) ? 1 : 0;
      $ordem = (int)($_POST['ordem'] ?? 0);

      if (!$titulo || !$conteudo) throw new Exception('Título e conteúdo são obrigatórios.');

      if ($id) {
        $stmt = db()->prepare('UPDATE comunicados SET titulo=?, conteudo=?, ativo=?, ordem=? WHERE id=?');
        $stmt->execute([$titulo, $conteudo, $ativo, $ordem, $id]);
        $success = 'Comunicado atualizado.';
      } else {
        $stmt = db()->prepare('INSERT INTO comunicados (titulo, conteudo, ativo, ordem) VALUES (?, ?, ?, ?)');
        $stmt->execute([$titulo, $conteudo, $ativo, $ordem]);
        $success = 'Comunicado criado.';
      }
    }
  } catch (Throwable $e) {
    $error = 'Erro: ' . $e->getMessage();
  }
}

$stmt = db()->query('SELECT id, titulo, conteudo, ativo, ordem FROM comunicados ORDER BY ordem ASC, id ASC');
$comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Comunicados — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- ✅ CSS atualizados com cache-busting -->
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />

  <style>
    .comunicado-item { margin-bottom: 20px; padding: 12px; border: 1px solid rgba(15,23,42,.1); border-radius: 8px; }
    .comunicado-item.inativo { opacity: 0.6; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; margin-bottom: 4px; font-weight: 600; }
    .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid rgba(15,23,42,.2); border-radius: 4px; }
    .form-group textarea { height: 80px; }
  </style>
</head>
<body class="page">

  <!-- ✅ Header antigo substituído pelo template -->
  <?php require_once __DIR__ . '/../app/header.php'; ?>

  <main class="container">
    <h2 class="page-title">Gerenciar Comunicados</h2>

    <div class="card">
      <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <h3>Adicionar Novo Comunicado</h3>
      <form method="post" class="form">
        <input type="hidden" name="id" value="">
        <div class="form-group">
          <label>Título</label>
          <input type="text" name="titulo" required>
        </div>
        <div class="form-group">
          <label>Conteúdo</label>
          <textarea name="conteudo" required></textarea>
        </div>
        <div class="form-group">
          <label>Ordem (número menor aparece primeiro)</label>
          <input type="number" name="ordem" value="0">
        </div>
        <div class="form-group">
          <label><input type="checkbox" name="ativo" checked> Ativo</label>
        </div>
        <button type="submit" name="save" class="btn btn--primary">Salvar</button>
      </form>

      <h3 style="margin-top: 40px;">Comunicados Existentes</h3>
      <?php if (empty($comunicados)): ?>
        <p class="muted">Nenhum comunicado cadastrado.</p>
      <?php else: ?>
        <?php foreach ($comunicados as $c): ?>
          <div class="comunicado-item <?= $c['ativo'] ? '' : 'inativo' ?>">
            <h4><?= htmlspecialchars($c['titulo']) ?> (Ordem: <?= $c['ordem'] ?>) <?= $c['ativo'] ? '' : '(Inativo)' ?></h4>
            <p><?= htmlspecialchars($c['conteudo']) ?></p>
            <form method="post" style="display: inline;">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" name="delete" class="btn btn--danger" onclick="return confirm('Remover?')">Remover</button>
            </form>
            <button class="btn btn--secondary" onclick="editComunicado(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['titulo']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($c['conteudo']), ENT_QUOTES, 'UTF-8') ?>', <?= $c['ordem'] ?>, <?= $c['ativo'] ? 1 : 0 ?>)">Editar</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <!-- ✅ Adicionado: script para os dropdowns do header funcionarem -->
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>

  <script>
    function editComunicado(id, titulo, conteudo, ordem, ativo) {
      document.querySelector('input[name="id"]').value = id;
      document.querySelector('input[name="titulo"]').value = titulo;
      document.querySelector('textarea[name="conteudo"]').value = conteudo;
      document.querySelector('input[name="ordem"]').value = ordem;
      document.querySelector('input[name="ativo"]').checked = ativo;
      document.querySelector('h3').textContent = 'Editar Comunicado';
      document.querySelector('button[name="save"]').textContent = 'Atualizar';
    }
  </script>
</body>
</html>