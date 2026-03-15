<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_admin(); // Troque para require_login() se quiser testar sem ser admin

$u = current_user();
$activePage = 'admin'; // destaca no header

// Dropdown "Dashboards" no header
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

function save_uploaded_image(array $file): ?string
{
  if (empty($file) || !isset($file['error']))
    return null;
  if ($file['error'] === UPLOAD_ERR_NO_FILE)
    return null;
  if ($file['error'] !== UPLOAD_ERR_OK)
    throw new Exception('Falha no upload (código ' . (int) $file['error'] . ').');

  $maxBytes = 5 * 1024 * 1024;
  if (!isset($file['size']) || (int) $file['size'] > $maxBytes) {
    throw new Exception('A imagem deve ter no máximo 5MB.');
  }

  $tmp = (string) ($file['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) {
    throw new Exception('Upload inválido.');
  }

  $origName = (string) ($file['name'] ?? '');
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

  $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
  if (!in_array($ext, $allowedExt, true)) {
    throw new Exception('Formato inválido. Use JPG, PNG ou WEBP.');
  }

  $mime = strtolower((string) ($file['type'] ?? ''));
  $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
  if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
    throw new Exception('Tipo de arquivo inválido.');
  }

  if ($ext === 'jpeg')
    $ext = 'jpg';

  $dir = APP_ROOT . '/uploads/comunicados';
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
      throw new Exception('Não foi possível criar a pasta de uploads.');
    }
  }

  $name = bin2hex(random_bytes(16)) . '.' . $ext;
  $dest = $dir . '/' . $name;

  if (!move_uploaded_file($tmp, $dest)) {
    throw new Exception('Não foi possível salvar a imagem.');
  }

  return '/uploads/comunicados/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['delete'])) {
      $idDel = (int) $_POST['delete'];

      $stmt = db()->prepare('SELECT imagem_path FROM comunicados WHERE id=? LIMIT 1');
      $stmt->execute([$idDel]);
      $old = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!empty($old['imagem_path'])) {
        $full = APP_ROOT . (string) $old['imagem_path'];
        if (is_file($full))
          @unlink($full);
      }

      $stmt = db()->prepare('DELETE FROM comunicados WHERE id=?');
      $stmt->execute([$idDel]);
      $success = 'Comunicado removido.';
    } elseif (isset($_POST['save'])) {
      $id = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;

      $titulo = trim((string) ($_POST['titulo'] ?? ''));
      $conteudo = trim((string) ($_POST['conteudo'] ?? ''));
      $ordem = (int) ($_POST['ordem'] ?? 0);

      // ✅ ativo SEM checkbox: default 1 para novos e mantém para edição
      // (se existir no POST, usamos; se não, será tratado abaixo)
      $ativo = isset($_POST['ativo']) ? (int) $_POST['ativo'] : null;

      // ✅ remove_imagem SEM checkbox: vem como 1/0 hidden
      $removeImagem = isset($_POST['remove_imagem']) ? (int) $_POST['remove_imagem'] : 0;

      $newPath = null;
      if (!empty($_FILES['imagem'])) {
        $newPath = save_uploaded_image($_FILES['imagem']);
      }

      if ($id) {
        $stmt = db()->prepare('SELECT imagem_path, ativo FROM comunicados WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentPath = (string) ($current['imagem_path'] ?? '');
        $currentAtivo = (int) ($current['ativo'] ?? 1);

        // se ativo não veio, mantém o atual
        if ($ativo === null)
          $ativo = $currentAtivo;

        $finalPath = $currentPath;

        if ($removeImagem === 1 && $currentPath !== '') {
          $full = APP_ROOT . $currentPath;
          if (is_file($full))
            @unlink($full);
          $finalPath = '';
        }

        if ($newPath !== null && $newPath !== '') {
          if ($currentPath !== '') {
            $full = APP_ROOT . $currentPath;
            if (is_file($full))
              @unlink($full);
          }
          $finalPath = $newPath;
        }

        $stmt = db()->prepare('UPDATE comunicados SET titulo=?, conteudo=?, imagem_path=?, ativo=?, ordem=? WHERE id=?');
        $stmt->execute([$titulo, $conteudo, $finalPath !== '' ? $finalPath : null, (int) $ativo, $ordem, $id]);
        $success = 'Comunicado atualizado.';
      } else {
        // novo -> ativo padrão 1 se não veio
        if ($ativo === null)
          $ativo = 1;
        $stmt = db()->prepare('INSERT INTO comunicados (titulo, conteudo, imagem_path, ativo, ordem) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$titulo, $conteudo, $newPath, (int) $ativo, $ordem]);
        $success = 'Comunicado criado.';
      }
    }
  } catch (Throwable $e) {
    $error = 'Erro: ' . $e->getMessage();
  }
}

$stmt = db()->query('SELECT id, titulo, conteudo, imagem_path, ativo, ordem FROM comunicados ORDER BY ordem ASC, id ASC');
$comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Comunicados — <?= htmlspecialchars((string) APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- CSS global -->
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(APP_ROOT . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(APP_ROOT . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(APP_ROOT . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet"
    href="/assets/css/comunicados.css?v=<?= filemtime(APP_ROOT . '/assets/css/comunicados.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(APP_ROOT . '/assets/css/header.css') ?>" />
</head>

<body class="page">

  <?php require_once APP_ROOT . '/app/layout/header.php'; ?>

  <main class="container comunicados">
    <h2 class="page-title">Gerenciar Comunicados</h2>

    <?php if ($success): ?>
      <div class="alert alert--ok">✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert--error">❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="card">
      <h3 id="formTitle">Adicionar Comunicado</h3>

      <form method="post" class="com-form" enctype="multipart/form-data">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="ativo" value="1">
        <input type="hidden" name="remove_imagem" value="0">

        <div class="form-grid">
          <div class="form-row">
            <label for="titulo">Título</label>
            <input id="titulo" type="text" name="titulo">
          </div>

          <div class="form-row">
            <label for="ordem">Ordem (menor aparece primeiro)</label>
            <input id="ordem" type="number" name="ordem" value="0">
          </div>
        </div>

        <div class="form-row">
          <label for="conteudo">Conteúdo</label>
          <textarea id="conteudo" name="conteudo"></textarea>
        </div>

        <div class="form-row">
          <label for="imagem">Imagem (opcional)</label>

          <div class="file-upload">
            <input id="imagem" class="file-upload__input" type="file" name="imagem"
              accept="image/png,image/jpeg,image/webp">
            <label class="file-upload__btn" for="imagem" id="imagemBtn">
              <span class="file-upload__icon" aria-hidden="true">📸</span>
              <span class="file-upload__text">Selecionar imagem</span>
            </label>
            <span class="file-upload__name" id="imagemName">Nenhum arquivo selecionado</span>
          </div>

          <div class="file-upload__hint">PNG/JPG/WEBP. Máx: 5MB.</div>
        </div>

        <div class="com-actions">
          <button type="submit" name="save" class="btn btn--primary" id="saveBtn">Salvar</button>
          <button type="button" class="btn btn--secondary" id="cancelEditBtn" style="display:none;">Cancelar
            edição</button>

          <!-- ✅ botão remover imagem só aparece no modo edição -->
          <button type="button" class="btn btn--secondary" id="removeImgBtn" style="display:none;">Remover imagem
            atual</button>
        </div>
      </form>

      <div class="comunicados-list">
        <h3>Comunicados Existentes</h3>

        <?php if (empty($comunicados)): ?>
          <p class="muted">Nenhum comunicado cadastrado.</p>
        <?php else: ?>
          <?php foreach ($comunicados as $c): ?>
            <div class="comunicado-card <?= ((int) $c['ativo'] === 1) ? '' : 'inativo' ?>">
              <?php if (!empty($c['imagem_path'])): ?>
                <img class="comunicado-img" src="<?= htmlspecialchars((string) $c['imagem_path'], ENT_QUOTES, 'UTF-8') ?>"
                  alt="Imagem do comunicado">
              <?php endif; ?>

              <h4 class="comunicado-title">
                <?= htmlspecialchars((string) ($c['titulo'] !== '' ? $c['titulo'] : 'Comunicado sem título'), ENT_QUOTES, 'UTF-8') ?>
              </h4>

              <div class="comunicado-meta">
                Ordem: <?= (int) $c['ordem'] ?> · <?= ((int) $c['ativo'] === 1) ? 'Ativo' : 'Inativo' ?>
              </div>

              <p class="comunicado-content">
                <?= htmlspecialchars((string) ($c['conteudo'] !== '' ? $c['conteudo'] : 'Sem conteúdo.'), ENT_QUOTES, 'UTF-8') ?>
              </p>

              <div class="comunicado-actions">
                <form method="post" style="display:inline;">
                  <input type="hidden" name="delete" value="<?= (int) $c['id'] ?>">
                  <button type="submit" class="btn btn--danger" onclick="return confirm('Remover?')">Remover</button>
                </form>

                <button class="btn btn--secondary" type="button" data-id="<?= (int) $c['id'] ?>"
                  data-titulo="<?= htmlspecialchars((string) $c['titulo'], ENT_QUOTES, 'UTF-8') ?>"
                  data-conteudo="<?= htmlspecialchars((string) $c['conteudo'], ENT_QUOTES, 'UTF-8') ?>"
                  data-imagem="<?= htmlspecialchars((string) ($c['imagem_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                  data-ordem="<?= (int) $c['ordem'] ?>" data-ativo="<?= ((int) $c['ativo'] === 1) ? 1 : 0 ?>"
                  onclick="editFromButton(this)">Editar</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(APP_ROOT . '/assets/js/header.js') ?>" defer></script>
  <script src="/assets/js/comunicados.js?v=<?= filemtime(APP_ROOT . '/assets/js/comunicados.js') ?>" defer></script>
</body>

</html>