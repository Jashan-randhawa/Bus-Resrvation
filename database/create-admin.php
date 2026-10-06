<?php
// database/create-admin.php -- php database/create-admin.php "Full Name" "you@example.com" "9876543210"
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/db_con.php';

[$_, $name, $email, $phone, $role] = $argv + [null, null, null, null, 'super_admin'];
if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone) {
    fwrite(STDERR, "Usage: php create-admin.php \"Name\" \"email\" \"phone\" [role: super_admin|operator|viewer]\n");
    exit(1);
}
if (!in_array($role, ['super_admin', 'operator', 'viewer'], true)) {
    $role = 'super_admin';
}

fwrite(STDOUT, 'Password (min 12 chars): ');
// Disable echo if on POSIX system, or standard fgets
if (stripos(PHP_OS, 'WIN') === 0) {
    $pwd = trim((string)fgets(STDIN));
} else {
    system('stty -echo');
    $pwd = trim((string)fgets(STDIN));
    system('stty echo');
    echo "\n";
}

if (strlen($pwd) < 12) {
    fwrite(STDERR, "Too short. Minimum 12 characters required.\n");
    exit(1);
}

$hash = password_hash($pwd, PASSWORD_DEFAULT);
$has_role = table_has_column($link, 'admin', 'role');
if ($has_role) {
    $stmt = mysqli_prepare($link, 'INSERT INTO admin (name, Email_id, Password, phone, role) VALUES (?,?,?,?,?)');
    mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $hash, $phone, $role);
} else {
    $stmt = mysqli_prepare($link, 'INSERT INTO admin (name, Email_id, Password, phone) VALUES (?,?,?,?)');
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $hash, $phone);
}
if (mysqli_stmt_execute($stmt)) {
    echo "Admin created successfully with role '{$role}'.\n";
} else {
    echo "Error creating admin: " . mysqli_error($link) . "\n";
}
