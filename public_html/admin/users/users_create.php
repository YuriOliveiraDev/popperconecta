<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=UTF-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_admin_perm('admin.users');

function json_out(array $payload, int $code = 200): void
{
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

// ✅ listas permitidas
$allowedAdminPerms = array_keys(ADMIN_PERMISSION_CATALOG);
$allowedDashPerms = array_keys(DASHBOARD_CATALOG);

// ✅ Dashboards padrão
$defaultDashPerms = [
  'dash.comercial.faturamento',
  'dash.comercial.executivo',
  'dash.comercial.insight',
  'dash.comercial.clientes',
  'dash.comex.importacoes',
];
$defaultDashPerms = array_values(array_unique(array_intersect($defaultDashPerms, $allowedDashPerms)));

function upload_profile_photo(int $userId, string $uploadBaseDirAbs, string $uploadBaseUrl): array
{
  if (!isset($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
    return ['ok' => true, 'path' => null, 'error' => null];
  }

  $file = $_FILES['profile_photo'];
  $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

  if ($err === UPLOAD_ERR_NO_FILE)
    return ['ok' => true, 'path' => null, 'error' => null];
  if ($err !== UPLOAD_ERR_OK)
    return ['ok' => false, 'path' => null, 'error' => 'Erro no upload da foto.'];

  $tmp = (string) ($file['tmp_name'] ?? '');
  $size = (int) ($file['size'] ?? 0);

  if ($size <= 0)
    return ['ok' => false, 'path' => null, 'error' => 'Arquivo de foto inválido.'];
  if ($size > 2 * 1024 * 1024)
    return ['ok' => false, 'path' => null, 'error' => 'A foto deve ter no máximo 2MB.'];

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

  if ($ext === null)
    return ['ok' => false, 'path' => null, 'error' => 'Formato de foto inválido. Use PNG, JPG ou WEBP.'];

  if (!is_dir($uploadBaseDirAbs))
    @mkdir($uploadBaseDirAbs, 0775, true);

  $fileName = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
  $destAbs = rtrim($uploadBaseDirAbs, '/') . '/' . $fileName;

  if (!move_uploaded_file($tmp, $destAbs)) {
    return ['ok' => false, 'path' => null, 'error' => 'Não foi possível salvar a foto.'];
  }

  return ['ok' => true, 'path' => rtrim($uploadBaseUrl, '/') . '/' . $fileName, 'error' => null];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok' => false, 'errors' => ['Método inválido.']], 405);
}

// dados
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['adm_email'] ?? ($_POST['email'] ?? ''));
$pass = (string) ($_POST['adm_pass'] ?? ($_POST['pass'] ?? ''));
$role = trim($_POST['role'] ?? 'user');
$setor = trim($_POST['setor'] ?? '');
$hierarquia = trim($_POST['hierarquia'] ?? 'Assistente');

$phone = trim($_POST['phone'] ?? '');
$birth_date = trim($_POST['birth_date'] ?? '');
$gender = trim($_POST['gender'] ?? '');

$permsAdmin = $_POST['perms_admin'] ?? [];
$permsDash = $_POST['perms_dash'] ?? [];

if (!is_array($permsAdmin))
  $permsAdmin = [];
if (!is_array($permsDash))
  $permsDash = [];

$permsAdmin = array_values(array_unique(array_intersect($permsAdmin, $allowedAdminPerms)));
$permsDash = array_values(array_unique(array_intersect($permsDash, $allowedDashPerms)));
$permsDash = array_values(array_unique(array_merge($defaultDashPerms, $permsDash)));

$perms = array_values(array_unique(array_merge($permsAdmin, $permsDash)));
$permsJson = json_encode($perms, JSON_UNESCAPED_UNICODE);

$errors = [];
if ($name === '')
  $errors[] = 'Nome é obrigatório.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
  $errors[] = 'Formato de e-mail inválido.';
if ($pass === '' || strlen($pass) < 6)
  $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
if ($setor === '')
  $errors[] = 'Setor é obrigatório.';
if (!in_array($role, ['user', 'admin'], true))
  $errors[] = 'Perfil inválido.';
if (!in_array($hierarquia, ['Diretor', 'Gerente', 'Gestor', 'Supervisor', 'Analista', 'Assistente'], true))
  $errors[] = 'Hierarquia inválida.';
if ($phone !== '' && strlen($phone) > 20)
  $errors[] = 'Telefone muito longo.';
if ($birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date))
  $errors[] = 'Data de nascimento inválida.';
if ($gender !== '' && !in_array($gender, ['M', 'F', 'O', 'N'], true))
  $errors[] = 'Gênero inválido.';

if ($errors)
  json_out(['ok' => false, 'errors' => $errors], 422);

try {
  $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    json_out(['ok' => false, 'errors' => ['Este e-mail já está cadastrado.']], 409);
  }

  db()->beginTransaction();
  try {
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $stmt = db()->prepare(
      'INSERT INTO users (name, email, phone, birth_date, gender, profile_photo_path, password_hash, role, setor, hierarquia, is_active, permissions)
       VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, 1, ?)'
    );
    $stmt->execute([
      $name,
      $email,
      ($phone !== '' ? $phone : null),
      ($birth_date !== '' ? $birth_date : null),
      ($gender !== '' ? $gender : null),
      $hash,
      $role,
      $setor,
      $hierarquia,
      $permsJson
    ]);

    $newUserId = (int) db()->lastInsertId();

    $uploadDirAbs = APP_ROOT . '/admin/uploads/profile_photos';
    $uploadBaseUrl = '/admin/uploads/profile_photos';
    $up = upload_profile_photo($newUserId, $uploadDirAbs, $uploadBaseUrl);

    if (!$up['ok'])
      throw new Exception((string) $up['error']);
    $photo = null;

    if (!empty($up['path'])) {
      $photo = (string) $up['path'];
      $stmt = db()->prepare("UPDATE users SET profile_photo_path=? WHERE id=?");
      $stmt->execute([$photo, $newUserId]);
    }

    db()->commit();

    // devolve dados para inserir na tabela
    json_out([
      'ok' => true,
      'message' => "Usuário cadastrado com sucesso!",
      'user' => [
        'id' => $newUserId,
        'name' => $name,
        'email' => $email,
        'setor' => $setor,
        'hierarquia' => $hierarquia,
        'role' => $role,
        'last_login_at' => '—',
        'profile_photo_path' => $photo,
      ]
    ]);
  } catch (Throwable $e) {
    db()->rollBack();
    throw $e;
  }
} catch (Throwable $e) {
  json_out(['ok' => false, 'errors' => ['Erro ao cadastrar usuário: ' . $e->getMessage()]], 500);
}