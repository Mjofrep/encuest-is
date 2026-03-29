<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/security.php';

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', isHttpsRequest() ? '1' : '0');

    session_start();
}

function currentUser(): ?array
{
    startSecureSession();

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    static $cached = null;
    if (is_array($cached) && (int)($cached['id'] ?? 0) === $userId) {
        return $cached;
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, nombre, email, estado FROM fb_usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || (int)($user['estado'] ?? 0) !== 1) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT r.clave FROM fb_roles r JOIN fb_usuarios_roles ur ON ur.rol_id = r.id WHERE ur.usuario_id = ?");
    $stmt->execute([$userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $user['roles'] = $roles;
    $cached = $user;

    return $user;
}

function userHasRole(string $role): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }

    $roles = $user['roles'] ?? [];
    return in_array($role, $roles, true);
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function requireRole(array $roles): array
{
    $user = requireLogin();
    $userRoles = $user['roles'] ?? [];

    foreach ($roles as $role) {
        if (in_array($role, $userRoles, true)) {
            return $user;
        }
    }

    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

function loginUser(string $email, string $password): bool
{
    $email = trim(strtolower($email));
    if ($email === '' || $password === '') {
        return false;
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, password_hash, estado FROM fb_usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || (int)($row['estado'] ?? 0) !== 1) {
        return false;
    }

    if (!password_verify($password, (string)$row['password_hash'])) {
        return false;
    }

    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['id'];

    auditLog((int)$row['id'], 'login', 'Inicio de sesion');

    return true;
}

function logoutUser(): void
{
    $user = currentUser();
    if ($user) {
        auditLog((int)$user['id'], 'logout', 'Cierre de sesion');
    }

    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function auditLog(int $userId, string $accion, string $detalle = ''): void
{
    if ($userId <= 0) {
        return;
    }

    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO fb_auditoria (usuario_id, accion, detalle, ip, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $userId,
        $accion,
        $detalle !== '' ? $detalle : null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
