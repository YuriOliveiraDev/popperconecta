<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/services/popper_news.php';

require_admin();

$u = current_user();
$activePage = 'admin';
$success = '';
$error = '';

try {
    $dashboards = db()
        ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dashboards = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!isset($_FILES['pdf'])) {
            throw new RuntimeException('Selecione um PDF para enviar.');
        }

        popper_news_store_uploaded_pdf($_FILES['pdf']);
        $success = 'PDF do Popper News atualizado com sucesso.';
    } catch (Throwable $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}

$currentPdfPath = popper_news_public_path();
$currentPdfUrl = popper_news_public_url();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Popper News — Admin</title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(APP_ROOT . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(APP_ROOT . '/assets/css/header.css') ?>" />

  <style>
    .pn-admin {
      max-width: 1100px;
      margin: 0 auto;
      padding: 28px 18px 42px;
    }

    .pn-card {
      background: rgba(255,255,255,.96);
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 28px;
      box-shadow: 0 24px 60px rgba(15,23,42,.08);
      overflow: hidden;
    }

    .pn-head {
      padding: 24px 26px;
      background:
        radial-gradient(circle at top right, rgba(92,44,140,.12), transparent 34%),
        linear-gradient(180deg, rgba(248,250,252,.96), rgba(255,255,255,.98));
      border-bottom: 1px solid rgba(15,23,42,.08);
    }

    .pn-head h1 {
      margin: 0 0 8px;
      font-size: 2rem;
      color: #5c2c8c;
    }

    .pn-head p,
    .pn-muted {
      margin: 0;
      color: #64748b;
      line-height: 1.7;
    }

    .pn-body {
      padding: 24px 26px 30px;
      display: grid;
      gap: 20px;
    }

    .pn-alert {
      padding: 14px 16px;
      border-radius: 16px;
      font-weight: 700;
      border: 1px solid transparent;
    }

    .pn-alert--ok {
      background: rgba(22,163,74,.08);
      color: #166534;
      border-color: rgba(22,163,74,.16);
    }

    .pn-alert--error {
      background: rgba(239,68,68,.08);
      color: #991b1b;
      border-color: rgba(239,68,68,.16);
    }

    .pn-grid {
      display: grid;
      grid-template-columns: 1fr .95fr;
      gap: 20px;
      align-items: start;
    }

    .pn-panel {
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 22px;
      padding: 20px;
      background: #fff;
    }

    .pn-panel h2 {
      margin: 0 0 12px;
      color: #0f172a;
      font-size: 1.25rem;
    }

    .pn-upload {
      display: grid;
      gap: 14px;
    }

    .pn-file {
      display: grid;
      gap: 10px;
      padding: 16px;
      border: 1px dashed rgba(92,44,140,.24);
      border-radius: 18px;
      background: linear-gradient(180deg, rgba(248,244,251,.95), rgba(255,255,255,1));
    }

    .pn-file input[type="file"] {
      width: 100%;
    }

    .pn-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .pn-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
      padding: 0 18px;
      border-radius: 14px;
      border: 1px solid transparent;
      text-decoration: none;
      font-weight: 800;
      cursor: pointer;
      transition: transform .18s ease, box-shadow .18s ease;
    }

    .pn-btn:hover {
      transform: translateY(-1px);
    }

    .pn-btn--primary {
      background: linear-gradient(135deg, #5c2c8c, #7c3aed);
      color: #fff;
      box-shadow: 0 16px 30px rgba(92,44,140,.22);
    }

    .pn-btn--ghost {
      background: rgba(15,23,42,.04);
      color: #0f172a;
      border-color: rgba(15,23,42,.10);
    }

    .pn-viewer {
      width: 100%;
      min-height: 700px;
      border: 0;
      border-radius: 18px;
      background: linear-gradient(180deg, #f8f5fb 0%, #efe7f5 100%);
      box-shadow: inset 0 0 0 1px rgba(111,44,145,.08);
    }

    @media (max-width: 920px) {
      .pn-grid {
        grid-template-columns: 1fr;
      }

      .pn-viewer {
        min-height: 560px;
      }
    }
  </style>
</head>
<body class="page">
  <?php require_once APP_ROOT . '/app/layout/header.php'; ?>

  <main class="pn-admin">
    <div class="pn-card">
      <div class="pn-head">
        <h1>Popper News</h1>
        <p>Suba um novo PDF para atualizar a visualização do `index2`. Esta área é restrita a administradores.</p>
      </div>

      <div class="pn-body">
        <?php if ($success !== ''): ?>
          <div class="pn-alert pn-alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <div class="pn-alert pn-alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="pn-grid">
          <section class="pn-panel">
            <h2>Enviar novo PDF</h2>
            <p class="pn-muted">A edição enviada aqui passa a ser a versão exibida no `index2`. Aceita apenas PDF, até 100MB.</p>

            <form class="pn-upload" method="post" enctype="multipart/form-data">
              <div class="pn-file">
                <label for="pdf"><strong>Arquivo PDF</strong></label>
                <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required>
                <span class="pn-muted">Dica: use um nome simples. O sistema salva a edição atual em um caminho fixo para o portal.</span>
              </div>

              <div class="pn-actions">
                <button class="pn-btn pn-btn--primary" type="submit">Publicar novo PDF</button>
                <a class="pn-btn pn-btn--ghost" href="/index2.php">Voltar para a home</a>
                <?php if ($currentPdfUrl !== null): ?>
                  <a class="pn-btn pn-btn--ghost" href="<?= htmlspecialchars($currentPdfUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir PDF atual</a>
                <?php endif; ?>
              </div>
            </form>
          </section>

          <section class="pn-panel">
            <h2>PDF atual</h2>
            <?php if ($currentPdfUrl !== null): ?>
              <p class="pn-muted">Arquivo publicado: <code><?= htmlspecialchars((string)$currentPdfPath, ENT_QUOTES, 'UTF-8') ?></code></p>
              <iframe
                class="pn-viewer"
                src="<?= htmlspecialchars($currentPdfUrl . '#view=FitH', ENT_QUOTES, 'UTF-8') ?>"
                title="Pré-visualização do Popper News atual">
              </iframe>
            <?php else: ?>
              <p class="pn-muted">Nenhum PDF publicado ainda. Assim que você enviar um arquivo, ele será exibido aqui e também no `index2`.</p>
            <?php endif; ?>
          </section>
        </div>
      </div>
    </div>
  </main>

  <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>
</body>
</html>
