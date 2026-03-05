<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

require_admin_perm('admin.users');

$me = current_user();
$u = $me;
$activePage = 'admin';

try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$errors = [];

if (($_GET['deleted'] ?? '') === '1') {
  $success = 'Usuário excluído com sucesso.';
}

$allowedAdminPerms = array_keys(ADMIN_PERMISSION_CATALOG);
$allowedDashPerms  = array_keys(DASHBOARD_CATALOG);

$defaultDashPerms = [
  'dash.comercial.faturamento',
  'dash.comercial.executivo',
  'dash.comercial.insight',
  'dash.comercial.clientes',
  'dash.comex.importacoes',
];
$defaultDashPerms = array_values(array_unique(array_intersect($defaultDashPerms, $allowedDashPerms)));

function dash_groups(): array {
  $groups = [];
  foreach (DASHBOARD_CATALOG as $perm => $meta) {
    $g = (string)($meta['group'] ?? 'Outros');
    if (!isset($groups[$g])) $groups[$g] = [];
    $groups[$g][$perm] = $meta;
  }
  ksort($groups);
  return $groups;
}

try {
  $users = db()->query(
    'SELECT id, name, email, phone, birth_date, gender, role, setor, hierarquia, is_active, last_login_at, permissions, profile_photo_path
     FROM users
     ORDER BY name ASC'
  )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $users = [];
}

$setorOptions = [];
$hierOptions  = [];
foreach ($users as $r) {
  $s = (string)($r['setor'] ?? '');
  $h = (string)($r['hierarquia'] ?? '');
  if ($s !== '') $setorOptions[$s] = $s;
  if ($h !== '') $hierOptions[$h]  = $h;
}
ksort($setorOptions);
ksort($hierOptions);

?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin: Usuários — <?= htmlspecialchars((string) APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/../assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/admin-users.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin-users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/../assets/css/header.css') ?>" />

  <style>
/* =========================================================
   MODAL - FORCE CENTER + BLUR + SCROLL
========================================================= */
#createUserModal.modal{
  position: fixed !important;
  inset: 0 !important;
  display: none;
  z-index: 999999 !important;
  padding: 0 !important;
  background: transparent !important;
  overflow: hidden !important;
}
#createUserModal.modal.is-open{ display:block !important; }

#createUserModal .modal__backdrop{
  position: fixed !important;
  inset: 0 !important;
  opacity: 0;
  background: rgba(2,6,23,.55) !important;
  backdrop-filter: blur(8px) !important;
  -webkit-backdrop-filter: blur(8px) !important;
  transition: opacity .10s ease !important;
}

#createUserModal .modal__panel{
  position: fixed !important;
  left: 50% !important;
  top: 50% !important;

  width: min(980px, 96vw) !important;
  max-height: calc(100vh - 120px) !important;

  border-radius: 18px !important;
  background: rgba(255,255,255,.92) !important;
  border: 1px solid rgba(255,255,255,.35) !important;
  box-shadow: 0 30px 100px rgba(0,0,0,.35) !important;

  opacity: 0;
  transform: translate(-50%, -50%) translateY(10px) scale(.985) !important;
  transition: transform .14s cubic-bezier(.22,1,.36,1), opacity .10s ease !important;
  will-change: transform, opacity;
  overflow: hidden !important; /* scroll só no body */
}

#createUserModal.modal.is-open .modal__backdrop{ opacity: 1; }
#createUserModal.modal.is-open .modal__panel{
  opacity: 1;
  transform: translate(-50%, -50%) translateY(0) scale(1) !important;
}

#createUserModal .modal__body{
  max-height: calc(100vh - 220px) !important;
  overflow: auto !important;
  -webkit-overflow-scrolling: touch;
}
#createUserModal .modal__body::-webkit-scrollbar{ width: 6px; }
#createUserModal .modal__body::-webkit-scrollbar-track{ background: transparent; }
#createUserModal .modal__body::-webkit-scrollbar-thumb{
  background: rgba(92,44,140,.35);
  border-radius: 10px;
}
#createUserModal .modal__body::-webkit-scrollbar-thumb:hover{
  background: rgba(92,44,140,.55);
}

/* trava scroll do fundo */
html.modal-lock, body.modal-lock{ overflow:hidden !important; height:100% !important; }

/* botão submit centralizado dentro do grid */
#createUserModal .modal-actions{
  grid-column: 1 / -1;
  display:flex;
  justify-content:center;
  align-items:center;
  margin-top: 16px;
}

/* botão “Cadastrar novo usuário” com fonte igual */
.btn-icon{ font: inherit; font-size: 14px; font-weight: 800; }
/* =========================================================
   AJUSTES: TÍTULO + TABELA (mais linhas visíveis)
========================================================= */

/* Título menor */
.page-title .page-h2{
  margin: 0;
  font-size: 20px;
  line-height: 1.15;
  font-weight: 800;
}
@media (max-width: 720px){
  .page-title .page-h2{ font-size: 18px; }
}
/* =========================================================
   LISTA DE USUÁRIOS - LIMITAR PARA ~10 LINHAS
========================================================= */

.admin-users .table-wrap{
  max-height: 560px;   /* altura aproximada para ~10 usuários */
  overflow-y: auto;
  overflow-x: hidden;
}

/* scrollbar mais discreta */
.admin-users .table-wrap::-webkit-scrollbar{
  width: 8px;
}

.admin-users .table-wrap::-webkit-scrollbar-track{
  background: transparent;
}

.admin-users .table-wrap::-webkit-scrollbar-thumb{
  background: rgba(92,44,140,.35);
  border-radius: 10px;
}

.admin-users .table-wrap::-webkit-scrollbar-thumb:hover{
  background: rgba(92,44,140,.55);
}
  </style>
</head>

<body class="page">
  <?php require_once __DIR__ . '/../app/header.php'; ?>

  <main class="container admin-users">
    <div class="page-title">
      <h2 class="page-h2">Gerenciar Usuários</h2>

      <div class="ui-actions">
        <button type="button" class="btn-icon" id="btnOpenCreate" aria-haspopup="dialog" aria-controls="createUserModal">
          <span style="font-weight:900;">Cadastrar novo usuário</span>
          <span class="btn-icon__chev" aria-hidden="true">▾</span>
        </button>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert--ok" style="margin-bottom:12px;"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert--error" style="margin-bottom:12px;">
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- MODAL -->
    <div class="modal" id="createUserModal" role="dialog" aria-modal="true" aria-labelledby="createUserTitle">
      <div class="modal__backdrop" data-close="1"></div>

      <div class="modal__panel" role="document">
        <div class="modal__header">
          <div>
            <h3 class="modal__title" id="createUserTitle">Cadastrar Novo Usuário</h3>
            <p class="modal__subtitle">Preencha os campos para criar um novo usuário.</p>
          </div>
          <button type="button" class="modal__close" data-close="1" aria-label="Fechar">✕</button>
        </div>

        <div class="modal__body">
          <form class="form" id="createUserForm" autocomplete="off" enctype="multipart/form-data">
            <input class="offscreen-bait" type="email" name="fake_email" autocomplete="username" aria-hidden="true" tabindex="-1">
            <input class="offscreen-bait" type="password" name="fake_pass" autocomplete="current-password" aria-hidden="true" tabindex="-1">

            <label class="field" for="name">
              <span class="field__label">Nome Completo</span>
              <input class="field__control" id="name" name="name" type="text" required autocomplete="off" spellcheck="false" autocapitalize="off" />
            </label>

            <label class="field" for="adm_email">
              <span class="field__label">E-mail</span>
              <input class="field__control" id="adm_email" name="adm_email" type="email" required autocomplete="email" inputmode="email" spellcheck="false" autocapitalize="off" />
            </label>

            <label class="field" for="adm_pass">
              <span class="field__label">Senha</span>
              <input class="field__control" id="adm_pass" name="adm_pass" type="password" required autocomplete="new-password" minlength="6" />
            </label>

            <label class="field" for="phone">
              <span class="field__label">Telefone</span>
              <input class="field__control" id="phone" name="phone" type="tel" placeholder="(11) 99999-9999" maxlength="20" autocomplete="off" />
            </label>

            <label class="field" for="birth_date">
              <span class="field__label">Data de Nascimento</span>
              <input class="field__control" id="birth_date" name="birth_date" type="date" autocomplete="off" />
            </label>

            <label class="field" for="gender">
              <span class="field__label">Gênero</span>
              <select class="field__control" id="gender" name="gender" autocomplete="off">
                <option value="">Selecione...</option>
                <option value="M">Masculino</option>
                <option value="F">Feminino</option>
                <option value="O">Outro</option>
                <option value="N">Prefere não informar</option>
              </select>
            </label>

            <div class="field field--full">
              <label class="field__label">Foto de Perfil</label>
              <div class="photo-row">
                <img class="avatar-lg" id="userPhotoPreviewImg" alt="Foto" style="display:none;" />
                <div class="avatar-lg avatar-lg--emoji" id="userPhotoEmoji" aria-label="Sem foto">👤</div>

                <div style="min-width:260px;flex:1;">
                  <div class="file-field__row">
                    <input class="file-input" id="userProfilePhoto" name="profile_photo" type="file" accept="image/png,image/jpeg,image/webp" />
                    <label class="file-btn" for="userProfilePhoto">🖼️ Escolher foto</label>

                    <div class="file-meta">
                      <span class="file-name" id="userProfilePhotoName">Nenhum arquivo selecionado</span>
                      <span class="file-hint">PNG/JPG/WEBP • Máx: 2MB</span>
                    </div>
                  </div>
                  <div class="help help-row">Escolha uma foto e clique em salvar.</div>
                </div>
              </div>
            </div>

            <label class="field" for="setor">
              <span class="field__label">Setor</span>
              <select class="field__control" id="setor" name="setor" required autocomplete="off">
                <option value="">Selecione...</option>
                <option value="FACILITIES">FACILITIES</option>
                <option value="RH">RH</option>
                <option value="FINANCEIRO">FINANCEIRO</option>
                <option value="LOGISTICA">LOGISTICA</option>
                <option value="COMERCIAL">COMERCIAL</option>
                <option value="COMEX">COMEX</option>
                <option value="DIRETORIA">DIRETORIA</option>
                <option value="CONTROLADORIA">CONTROLADORIA</option>
                <option value="MARKETING">MARKETING</option>
              </select>
            </label>

            <label class="field" for="role">
              <span class="field__label">Perfil</span>
              <select class="field__control" id="role" name="role" required autocomplete="off">
                <option value="user">Usuário</option>
                <option value="admin">Administrador</option>
              </select>
            </label>

            <label class="field" for="hierarquia">
              <span class="field__label">Hierarquia</span>
              <select class="field__control" id="hierarquia" name="hierarquia" required autocomplete="off">
                <option value="Assistente">Assistente</option>
                <option value="Analista">Analista</option>
                <option value="Supervisor">Supervisor</option>
                <option value="Gestor">Gestor</option>
                <option value="Gerente">Gerente</option>
                <option value="Diretor">Diretor</option>
              </select>
            </label>

            <!-- suas permissões aqui (mantive como você já tem) -->
            <!-- ... cole o bloco PERMISSÕES (MELHORADAS) que você já montou ... -->

            <div class="modal-actions">
              <button class="btn btn--primary" type="submit">Cadastrar Usuário</button>
            </div>

          </form>
        </div>
      </div>
    </div>

    <!-- LISTA -->
    <section class="card card--mt">
      <div class="card__header">
        <h3 class="card__title">Usuários Cadastrados</h3>
        <p class="card__subtitle">Filtre por setor, hierarquia e perfil — e edite rapidamente.</p>
      </div>

      <div class="toolbar" role="region" aria-label="Filtros de usuários">
        <div class="field-inline" style="min-width: 260px; flex: 2;">
          <label for="userSearch">Pesquisar</label>
          <input type="text" id="userSearch" placeholder="Nome ou e-mail..." />
        </div>

        <div class="field-inline">
          <label for="filterSetor">Setor</label>
          <select id="filterSetor">
            <option value="">Todos</option>
            <?php foreach ($setorOptions as $v): ?>
              <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field-inline">
          <label for="filterHier">Hierarquia</label>
          <select id="filterHier">
            <option value="">Todos</option>
            <?php foreach ($hierOptions as $v): ?>
              <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field-inline">
          <label for="filterRole">Perfil</label>
          <select id="filterRole">
            <option value="">Todos</option>
            <option value="admin">Admin</option>
            <option value="user">User</option>
          </select>
        </div>

        <div class="toolbar-right">
          <span class="pill" id="usersCountPill">0 exibidos</span>
          <button type="button" class="btn-icon" id="btnClearFilters" title="Limpar filtros">Limpar</button>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table" id="usersTable">
          <thead>
            <tr>
              <th class="col-photo">Foto</th>
              <th class="col-name">Nome</th>
              <th class="col-email">E-mail</th>
              <th class="col-setor">Setor</th>
              <th class="col-hier">Hierarquia</th>
              <th class="col-role">Perfil</th>
              <th class="col-last">Último login</th>
              <th class="col-actions">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $row): ?>
              <?php
                $name = (string)($row['name'] ?? '');
                $email = (string)($row['email'] ?? '');
                $setor = (string)($row['setor'] ?? '');
                $hier  = (string)($row['hierarquia'] ?? '');
                $role  = (string)($row['role'] ?? 'user');

                // ✅ fix 404 de foto antiga sem /uploads/...
                $pp = (string)($row['profile_photo_path'] ?? '');
                if ($pp !== '' && $pp[0] !== '/') $pp = '/uploads/profile_photos/' . ltrim($pp, '/');
              ?>
              <tr
                data-name="<?= htmlspecialchars(mb_strtolower($name), ENT_QUOTES, 'UTF-8') ?>"
                data-email="<?= htmlspecialchars(mb_strtolower($email), ENT_QUOTES, 'UTF-8') ?>"
                data-setor="<?= htmlspecialchars($setor, ENT_QUOTES, 'UTF-8') ?>"
                data-hier="<?= htmlspecialchars($hier, ENT_QUOTES, 'UTF-8') ?>"
                data-role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"
              >
                <td class="col-photo">
                  <span class="cell-center">
                    <?php if ($pp !== ''): ?>
                      <img class="avatar" src="<?= htmlspecialchars($pp, ENT_QUOTES, 'UTF-8') ?>" alt="Foto">
                    <?php else: ?>
                      <span class="avatar avatar--placeholder" aria-label="Sem foto" title="Sem foto"><span aria-hidden="true">👤</span></span>
                    <?php endif; ?>
                  </span>
                </td>

                <td class="col-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="col-email"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="col-setor"><?= htmlspecialchars($setor, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="col-hier"><?= htmlspecialchars($hier, ENT_QUOTES, 'UTF-8') ?></td>

                <td class="col-role">
                  <?php if ($role === 'admin'): ?>
                    <span class="badge badge--admin">Admin</span>
                  <?php else: ?>
                    <span class="badge badge--user">User</span>
                  <?php endif; ?>
                </td>

                <td class="col-last"><?= htmlspecialchars((string)($row['last_login_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>

                <td class="col-actions">
                  <a class="link link--pill" href="/admin/user_edit.php?id=<?= (int)$row['id'] ?>">Editar</a>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if (!$users): ?>
              <tr><td colspan="8" class="muted">Nenhum usuário cadastrado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>

  <?php require_once __DIR__ . '/../app/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/users.js?v=<?= filemtime(__DIR__ . '/../assets/js/users.js') ?>"></script>

  <script>
/* =========================================================
   HELPERS ALERT + ESCAPE
========================================================= */
const alertHost = document.querySelector('.container.admin-users');

function showTopAlert(type, html) {
  const prev = document.getElementById('ajaxAlert');
  if (prev) prev.remove();

  const div = document.createElement('div');
  div.id = 'ajaxAlert';
  div.className = 'alert ' + (type === 'ok' ? 'alert--ok' : 'alert--error');
  div.style.marginBottom = '12px';
  div.innerHTML = html;

  if (alertHost) alertHost.insertBefore(div, alertHost.firstChild);
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

/* =========================================================
   MODAL (global, sem IIFE)
========================================================= */
const btnOpen = document.getElementById('btnOpenCreate');
const modal = document.getElementById('createUserModal');

function openModal(){
  if (!modal) return;
  modal.classList.add('is-open');
  document.documentElement.classList.add('modal-lock');
  document.body.classList.add('modal-lock');
  const first = modal.querySelector('input,select,button,textarea');
  if (first) setTimeout(() => first.focus(), 60);
}
function closeModal(){
  if (!modal) return;
  modal.classList.remove('is-open');
  document.documentElement.classList.remove('modal-lock');
  document.body.classList.remove('modal-lock');
}

if (btnOpen && modal) {
  btnOpen.addEventListener('click', () => (modal.classList.contains('is-open') ? closeModal() : openModal()));
  modal.addEventListener('click', (e) => {
    const t = e.target;
    if (t && t.getAttribute && t.getAttribute('data-close') === '1') closeModal();
  });
  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });
}

/* =========================================================
   FOTO PREVIEW
========================================================= */
const fileInput = document.getElementById('userProfilePhoto');
const img = document.getElementById('userPhotoPreviewImg');
const emoji = document.getElementById('userPhotoEmoji');
const fileNameEl = document.getElementById('userProfilePhotoName');

function showEmoji() {
  if (img) { img.style.display = 'none'; img.removeAttribute('src'); }
  if (emoji) emoji.style.display = '';
}
function showImg(src) {
  if (img) { img.src = src; img.style.display = ''; }
  if (emoji) emoji.style.display = 'none';
}
if (fileInput && img && emoji) {
  fileInput.addEventListener('change', () => {
    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if (!file) { showEmoji(); if (fileNameEl) fileNameEl.textContent = 'Nenhum arquivo selecionado'; return; }
    if (fileNameEl) fileNameEl.textContent = file.name;
    const url = URL.createObjectURL(file);
    showImg(url);
    img.onload = () => URL.revokeObjectURL(url);
  });
}

/* =========================================================
   INSERIR NOVO USUÁRIO NA TABELA
========================================================= */
function prependUserRow(u) {
  const tbody = document.querySelector('#usersTable tbody');
  if (!tbody) return;

  const empty = tbody.querySelector('tr .muted')?.closest('tr');
  if (empty) empty.remove();

  const tr = document.createElement('tr');
  tr.dataset.name  = (u.name || '').toLowerCase();
  tr.dataset.email = (u.email || '').toLowerCase();
  tr.dataset.setor = u.setor || '';
  tr.dataset.hier  = u.hierarquia || '';
  tr.dataset.role  = u.role || 'user';

  const photo = u.profile_photo_path ? escapeHtml(u.profile_photo_path) : '';
  const photoTd = photo
    ? `<img class="avatar" src="${photo}" alt="Foto">`
    : `<span class="avatar avatar--placeholder" aria-label="Sem foto" title="Sem foto"><span aria-hidden="true">👤</span></span>`;

  const badge = (u.role === 'admin')
    ? `<span class="badge badge--admin">Admin</span>`
    : `<span class="badge badge--user">User</span>`;

  tr.innerHTML = `
    <td class="col-photo"><span class="cell-center">${photoTd}</span></td>
    <td class="col-name">${escapeHtml(u.name || '')}</td>
    <td class="col-email">${escapeHtml(u.email || '')}</td>
    <td class="col-setor">${escapeHtml(u.setor || '')}</td>
    <td class="col-hier">${escapeHtml(u.hierarquia || '')}</td>
    <td class="col-role">${badge}</td>
    <td class="col-last">${escapeHtml(u.last_login_at || '—')}</td>
    <td class="col-actions"><a class="link link--pill" href="/admin/user_edit.php?id=${Number(u.id || 0)}">Editar</a></td>
  `;

  tbody.insertBefore(tr, tbody.firstChild);
  if (typeof applyUserFilters === 'function') applyUserFilters();
}

/* =========================================================
   FILTROS DE USUÁRIOS
========================================================= */
const input = document.getElementById('userSearch');
const selSetor = document.getElementById('filterSetor');
const selHier = document.getElementById('filterHier');
const selRole = document.getElementById('filterRole');
const tbody = document.querySelector('#usersTable tbody');
const pill = document.getElementById('usersCountPill');
const btnClear = document.getElementById('btnClearFilters');

function applyUserFilters() {
  if (!tbody) return;

  const term = (input && input.value ? input.value : '').trim().toLowerCase();
  const fSetor = (selSetor && selSetor.value ? selSetor.value : '');
  const fHier  = (selHier && selHier.value ? selHier.value : '');
  const fRole  = (selRole && selRole.value ? selRole.value : '');

  let shown = 0;
  const rows = tbody.querySelectorAll('tr[data-name]');
  rows.forEach(tr => {
    const name  = tr.dataset.name || '';
    const email = tr.dataset.email || '';
    const setor = tr.dataset.setor || '';
    const hier  = tr.dataset.hier || '';
    const role  = tr.dataset.role || '';

    const okTerm  = !term || name.includes(term) || email.includes(term);
    const okSetor = !fSetor || setor === fSetor;
    const okHier  = !fHier  || hier === fHier;
    const okRole  = !fRole  || role === fRole;

    const ok = okTerm && okSetor && okHier && okRole;
    tr.style.display = ok ? '' : 'none';
    if (ok) shown++;
  });

  if (pill) pill.textContent = shown + ' exibidos';
}

if (input) input.addEventListener('input', applyUserFilters);
if (selSetor) selSetor.addEventListener('change', applyUserFilters);
if (selHier) selHier.addEventListener('change', applyUserFilters);
if (selRole) selRole.addEventListener('change', applyUserFilters);

if (btnClear) {
  btnClear.addEventListener('click', () => {
    if (input) input.value = '';
    if (selSetor) selSetor.value = '';
    if (selHier) selHier.value = '';
    if (selRole) selRole.value = '';
    applyUserFilters();
  });
}
applyUserFilters();

/* =========================================================
   AJAX CADASTRO
========================================================= */
const createForm = document.getElementById('createUserForm');

if (createForm) {
  createForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const submitBtn = createForm.querySelector('button[type="submit"]');
    const oldText = submitBtn ? submitBtn.textContent : '';

    try {
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Salvando...'; }

      const fd = new FormData(createForm);

      const res = await fetch('/admin/users_create.php', {
        method: 'POST',
        body: fd,
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });

      const ct = (res.headers.get('content-type') || '').toLowerCase();
      const raw = await res.text();

      let data = null;
      if (ct.includes('application/json')) {
        try { data = JSON.parse(raw); } catch (_) { data = null; }
      }

      if (!res.ok || !data || data.ok !== true) {
        const msg =
          (data && Array.isArray(data.errors) && data.errors.length)
            ? `<ul style="margin:0;padding-left:18px;">${data.errors.map(x => `<li>${escapeHtml(x)}</li>`).join('')}</ul>`
            : `<div style="white-space:pre-wrap;">${escapeHtml(raw.slice(0, 800))}</div>`;
        showTopAlert('error', msg);
        openModal();
        return;
      }

      showTopAlert('ok', escapeHtml(data.message || 'Usuário cadastrado com sucesso!'));
      if (data.user) prependUserRow(data.user);

      closeModal();

      createForm.reset();
      showEmoji();
      if (fileNameEl) fileNameEl.textContent = 'Nenhum arquivo selecionado';

    } catch (err) {
      console.error(err);
      showTopAlert('error', 'Erro inesperado ao cadastrar usuário. Veja o Console/Network.');
      openModal();
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = oldText || 'Cadastrar Usuário'; }
    }
  });
}
  </script>
</body>
</html>