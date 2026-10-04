<?php
// includes/auth/session-bootstrap.php (include this instead of calling session_start())
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); // Render terminates TLS
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('busres_sid');
    session_start();
}

const SESSION_IDLE_SECONDS = 1800; // 30 minutes

function login_user(string $role, array $row): void {
    session_regenerate_id(true); // defeats session fixation
    $_SESSION['role'] = $role; // 'user' | 'admin'
    $_SESSION['uid'] = (int)$row['id'];
    $_SESSION['name'] = (string)$row['name'];
    $_SESSION['phone'] = (string)$row['phone'];
    $_SESSION['last_seen'] = time();
    // never store the password or its hash in the session
}

function logout_all(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_role(string $role): void {
    $idle = time() - (int)($_SESSION['last_seen'] ?? 0);
    if (($_SESSION['role'] ?? '') !== $role || $idle > SESSION_IDLE_SECONDS) {
        logout_all();
        header('Location: ' . BASE_URL . '/homepage.php');
        exit;
    }
    $_SESSION['last_seen'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    if (function_exists('csrf_verify')) {
        csrf_verify();
    }
    logout_all();
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}
