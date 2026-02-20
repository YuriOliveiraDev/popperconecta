<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$u = current_user();

try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

function save_uploaded_image(array $file): ?string {
  if (empty($file) || !isset($file['error'])) return null;
  if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Falha no upload (código ' . (int)$file['error'] . ').');

  // Limite: 5 MB
  $maxBytes = 5 * 1024 * 1024;
  if (!isset($file['size']) || (int)$file['size'] > $maxBytes) {
    throw new Exception('A imagem deve ter no máximo 5MB.');
  }

  $tmp = (string)($file['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) {
    throw new Exception('Upload inválido.');
  }

  // Valida extensão
  $origName = (string)($file['name'] ?? '');
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

  $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
  if (!in_array($ext, $allowedExt, true)) {
    throw new Exception('Formato inválido. Use JPG, PNG ou WEBP.');
  }

  // Valida MIME enviado pelo navegador (não é 100% confiável, mas ajuda)
  $mime = strtolower((string)($file['type'] ?? ''));
  $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
  if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
    throw new Exception('Tipo de arquivo inválido.');
  }

  // Normaliza extensão jpeg -> jpg
  if ($ext === 'jpeg') $ext = 'jpg';

  // Pasta destino (dentro do public_html)
  $dir = __DIR__ . '/../uploads/comunicados';
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
      throw new Exception('Não foi possível criar a pasta de uploads.');
    }
  }

  // Nome único (evita colisão e evita usar nome do usuário)
  $name = bin2hex(random_bytes(16)) . '.' . $ext;
  $dest = $dir . '/' . $name;

  if (!move_uploaded_file($tmp, $dest)) {
    throw new Exception('Não foi possível salvar a imagem.');
  }

  // Caminho público que será salvo no banco
  return '/uploads/comunicados/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['delete'])) {
      $idDel = (int)$_POST['delete'];

      // (Opcional) apagar arquivo antigo
      $stmt = db()->prepare('SELECT imagem_path FROM comunicados WHERE id=? LIMIT 1');
      $stmt->execute([$idDel]);
      $old = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!empty($old['imagem_path'])) {
        $full = __DIR__ . '/..' . (string)$old['imagem_path'];
        if (is_file($full)) @unlink($full);
      }

      $stmt = db()->prepare('DELETE FROM comunicados WHERE id=?');
      $stmt->execute([$idDel]);
      $success = 'Comunicado removido.';
    } elseif (isset($_POST['save'])) {
      $id = $_POST['id'] !== '' ? (int)$_POST['id'] : null;

      $titulo = trim($_POST['titulo'] ?? '');
      $conteudo = trim($_POST['conteudo'] ?? '');
      $ativo = isset($_POST['ativo']) ? 1 : 0;
      $ordem = (int)($_POST['ordem'] ?? 0);

      // Remover imagem?
      $removeImagem = isset($_POST['remove_imagem']) ? 1 : 0;

      // Upload novo (opcional) - MOVER PARA ANTES DA VALIDAÇÃO
      $newPath = null;
      if (!empty($_FILES['imagem'])) {
        $newPath = save_uploaded_image($_FILES['imagem']);
      }

      // Agora NÃO é obrigatório ter título e conteúdo.
      // Permite imagem apenas, texto apenas, ou ambos.
      // Se nada for fornecido, permite (comunicado vazio, mas ativo pode ser usado para pausar)

      if ($id) {
        // Carrega imagem atual
        $stmt = db()->prepare('SELECT imagem_path FROM comunicados WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentPath = (string)($current['imagem_path'] ?? '');

        $finalPath = $currentPath;

        // Se marcou remover, remove e apaga arquivo
        if ($removeImagem && $currentPath !== '') {
          $full = __DIR__ . '/..' . $currentPath;
          if (is_file($full)) @unlink($full);
          $finalPath = '';
        }

        // Se enviou nova, substitui e apaga antiga
        if ($newPath !== null && $newPath !== '') {
          if ($currentPath !== '') {
            $full = __DIR__ . '/..' . $currentPath;
            if (is_file($full)) @unlink($full);
          }
          $finalPath = $newPath;
        }

        $stmt = db()->prepare('UPDATE comunicados SET titulo=?, conteudo=?, imagem_path=?, ativo=?, ordem=? WHERE id=?');
        $stmt->execute([$titulo, $conteudo, $finalPath !== '' ? $finalPath : null, $ativo, $ordem, $id]);
        $success = 'Comunicado atualizado.';
      } else {
        $stmt = db()->prepare('INSERT INTO comunicados (titulo, conteudo, imagem_path, ativo, ordem) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$titulo, $conteudo, $newPath, $ativo, $ordem]);
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
  <title>Comunicados — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />

  <style>
    .comunicado-item { margin-bottom: 20px; padding: 12px; border: 1px solid rgba(15,23,42,.1); border-radius: 8px; }
    .comunicado-item.inativo { opacity: 0.6; }
    .comunicado-img { width: 100%; max-height: 260px; object-fit: cover; border-radius: 8px; margin: 8px 0 10px; border: 1px solid rgba(15,23,42,.08); }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; margin-bottom: 4px; font-weight: 600; }
    .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid rgba(15,23,42,.2); border-radius: 4px; }
    .form-group textarea { height: 80px; }
  </style>
</head>
<body class="page">

  <?php require_once __DIR__ . '/../app/header.php'; ?>

  <main class="container">
    <h2 class="page-title">Gerenciar Comunicados</h2>

    <div class="card">
      <?php if ($success): ?><div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

      <h3>Adicionar / Editar Comunicado</h3>
      <form method="post" class="form" enctype="multipart/form-data">
        <input type="hidden" name="id" value="">

        <div class="form-group">
          <label>Título</label>
          <input type="text" name="titulo"> <!-- ✅ REMOVIDO required -->
        </div>

        <div class="form-group">
          <label>Conteúdo</label>
          <textarea name="conteudo"></textarea> <!-- ✅ REMOVIDO required -->
        </div>

        <div class="form-group">
          <label>Imagem (opcional)</label>
          <input type="file" name="imagem" accept="image/png,image/jpeg,image/webp">
          <label style="margin-top:6px; font-weight:600;">
            <input type="checkbox" name="remove_imagem"> Remover imagem atual
          </label>
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
          <div class="comunicado-item <?= ((int)$c['ativo'] === 1) ? '' : 'inativo' ?>">
            <?php if (!empty($c['imagem_path'])): ?>
              <img class="comunicado-img" src="<?= htmlspecialchars((string)$c['imagem_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagem do comunicado">
            <?php endif; ?>

            <h4>
              <?= htmlspecialchars((string)$c['titulo'] !== '' ? $c['titulo'] : 'Comunicado sem título', ENT_QUOTES, 'UTF-8') ?> <!-- ✅ Fallback se título vazio -->
              (Ordem: <?= (int)$c['ordem'] ?>)
              <?= ((int)$c['ativo'] === 1) ? '' : '(Inativo)' ?>
            </h4>

            <p><?= htmlspecialchars((string)$c['conteudo'] !== '' ? $c['conteudo'] : 'Sem conteúdo.', ENT_QUOTES, 'UTF-8') ?></p> <!-- ✅ Fallback se conteúdo vazio -->

            <form method="post" style="display:inline;">
              <input type="hidden" name="delete" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="btn btn--danger" onclick="return confirm('Remover?')">Remover</button>
            </form>

            <button
              class="btn btn--secondary"
              type="button"
              onclick="editComunicado(
                <?= (int)$c['id'] ?>,
                <?= json_encode((string)$c['titulo'], JSON_UNESCAPED_UNICODE) ?>,
                <?= json_encode((string)$c['conteudo'], JSON_UNESCAPED_UNICODE) ?>,
                <?= json_encode((string)$c['imagem_path'] ?? '', JSON_UNESCAPED_UNICODE) ?>, <!-- ✅ Inclui imagem_path no JS -->
                <?= (int)$c['ordem'] ?>,
                <?= ((int)$c['ativo'] === 1) ? 1 : 0 ?>
              )"
            >Editar</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>

  <script>
    function editComunicado(id, titulo, conteudo, imagem_path, ordem, ativo) { // ✅ Inclui imagem_path
      document.querySelector('input[name="id"]').value = id;
      document.querySelector('input[name="titulo"]').value = titulo;
      document.querySelector('textarea[name="conteudo"]').value = conteudo;
      document.querySelector('input[name="ordem"]').value = ordem;
      document.querySelector('input[name="ativo"]').checked = !!ativo;
      document.querySelector('h3').textContent = 'Editar Comunicado';
      document.querySelector('button[name="save"]').textContent = 'Atualizar';
      // checkbox remover imagem volta sempre desmarcado
      var rm = document.querySelector('input[name="remove_imagem"]');
      if (rm) rm.checked = false;
    }
  </script>
</body>
</html>