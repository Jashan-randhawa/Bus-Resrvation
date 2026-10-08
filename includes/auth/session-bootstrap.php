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

function is_super_admin(): bool {
    return ($_SESSION['role'] ?? '') === 'super_admin';
}

function can_write(): bool {
    return in_array($_SESSION['role'] ?? '', ['super_admin', 'operator'], true);
}

function login_user(string $portalRole, array $row): void {
    session_regenerate_id(true); // defeats session fixation
    $assignedRole = !empty($row['role']) ? (string)$row['role'] : ($portalRole === 'admin' ? 'super_admin' : 'user');
    $_SESSION['role'] = $assignedRole;
    $_SESSION['uid'] = (int)$row['id'];
    if (in_array($assignedRole, ['super_admin', 'operator', 'viewer', 'admin'], true)) {
        $_SESSION['admin_id'] = (int)$row['id'];
    }
    $_SESSION['name'] = (string)($row['name'] ?? 'User');
    $_SESSION['phone'] = (string)($row['phone'] ?? '');
    $_SESSION['last_seen'] = time();
    $_SESSION['last'] = time();
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

function require_role(string ...$allowed): void {
    $idle = time() - (int)($_SESSION['last_seen'] ?? $_SESSION['last'] ?? 0);
    $currentRole = $_SESSION['role'] ?? '';

    // If session missing or timed out, redirect to login
    if ($currentRole === '' || $idle > SESSION_IDLE_SECONDS) {
        $req_uri = $_SERVER['REQUEST_URI'] ?? '';
        logout_all();

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

        $target = BASE_URL . '/homepage.php';
        $safe_next = function_exists('safe_next_url') ? safe_next_url($req_uri) : null;
        if ($safe_next !== null && !str_contains($safe_next, 'homepage.php') && !str_contains($safe_next, 'logout')) {
            $target .= '?next=' . urlencode($safe_next);
        }
        header('Location: ' . $target);
        exit;
    }

    // Expand 'admin' to encompass any administrative role
    $effective_allowed = [];
    foreach ($allowed as $a) {
        if ($a === 'admin') {
            $effective_allowed[] = 'admin';
            $effective_allowed[] = 'super_admin';
            $effective_allowed[] = 'operator';
            $effective_allowed[] = 'viewer';
        } else {
            $effective_allowed[] = $a;
        }
    }

    if (!in_array($currentRole, $effective_allowed, true)) {
        http_response_code(403);
        exit('Forbidden');
    }

    $_SESSION['last_seen'] = time();
    $_SESSION['last'] = time();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['logout'])) {
    if (function_exists('csrf_verify')) {
        csrf_verify();
    }
    logout_all();
    header('Location: ' . BASE_URL . '/homepage.php');
    exit;
}
