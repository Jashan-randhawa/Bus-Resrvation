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

function require_role(string $role, bool $touch = true): void {
    $idle = time() - (int)($_SESSION['last_seen'] ?? 0);
    if (($_SESSION['role'] ?? '') !== $role || $idle > SESSION_IDLE_SECONDS) {
        $req_uri = $_SERVER['REQUEST_URI'] ?? '';
        logout_all();

        // Phase 1.6: Return JSON 401 for API endpoints instead of HTML redirect
        $is_api = str_contains($req_uri, '/api-') || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($is_api) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'session_expired',
                'message' => 'Your session has expired. Please sign in again.',
                'login_url' => BASE_URL . '/login.php'
            ]);
            exit;
        }

        // Phase 1.1: Preserve validated relative return path for re-authentication
        $target = BASE_URL . '/homepage.php';
        $safe_next = function_exists('safe_next_url') ? safe_next_url($req_uri) : null;
        if ($safe_next !== null && !str_contains($safe_next, 'homepage.php') && !str_contains($safe_next, 'logout')) {
            $target .= '?next=' . urlencode($safe_next);
        }
        header('Location: ' . $target);
        exit;
    }
    if ($touch) {
        $_SESSION['last_seen'] = time();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    if (function_exists('csrf_verify')) {
        csrf_verify();
    }
    logout_all();
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}
