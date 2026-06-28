<?php
// config/auth.php - Admin session auth helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Builds a correct URL path to a file inside /admin/, regardless of
 * whether the app is installed at the domain root (e.g. /admin/login.php)
 * or inside a subfolder (e.g. /signature/admin/login.php).
 *
 * Works by reading the current request's directory from SCRIPT_NAME,
 * which always reflects the real install path on the server.
 */
function adminUrl(string $file): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin'));
    $scriptDir = rtrim($scriptDir, '/');

    // If the current script isn't inside /admin (rare), append it.
    if (basename($scriptDir) !== 'admin') {
        $scriptDir .= '/admin';
    }

    return $scriptDir . '/' . ltrim($file, '/');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . adminUrl('login.php'));
        exit;
    }
}

function adminLogin(string $username, string $password): bool
{
    require_once __DIR__ . '/db.php';
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, password, full_name, role FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id']        = $user['id'];
        $_SESSION['admin_name']      = $user['full_name'];
        $_SESSION['admin_role']      = $user['role'];
        $_SESSION['admin_username']  = $username;
        return true;
    }
    return false;
}

function adminLogout(): void
{
    session_unset();
    session_destroy();
    header('Location: ' . adminUrl('login.php'));
    exit;
}

function currentAdmin(): array
{
    return [
        'id'       => $_SESSION['admin_id']       ?? 0,
        'name'     => $_SESSION['admin_name']      ?? 'Admin',
        'role'     => $_SESSION['admin_role']      ?? 'admin',
        'username' => $_SESSION['admin_username']  ?? '',
    ];
}
