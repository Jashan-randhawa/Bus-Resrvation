<?php
// database/migrations/002_hash_passwords.php -- run once: php database/migrations/002_hash_passwords.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../includes/db_con.php';

mysqli_query($link, 'ALTER TABLE admin MODIFY Password VARCHAR(255) NOT NULL');
mysqli_query($link, 'ALTER TABLE costumer MODIFY pwd VARCHAR(255) NOT NULL');

function rehash(mysqli $link, string $table, string $idCol, string $pwCol): int {
    $n = 0;
    $res = mysqli_query($link, "SELECT $idCol AS id, $pwCol AS pw FROM $table");
    $upd = mysqli_prepare($link, "UPDATE $table SET $pwCol = ? WHERE $idCol = ?");
    while ($row = mysqli_fetch_assoc($res)) {
        if (password_get_info($row['pw'])['algo'] !== null) {
            continue; // already a hash
        }
        $hash = password_hash($row['pw'], PASSWORD_DEFAULT);
        $id = (int)$row['id'];
        mysqli_stmt_bind_param($upd, 'si', $hash, $id);
        mysqli_stmt_execute($upd);
        $n++;
    }
    return $n;
}

echo 'admin: ' . rehash($link, 'admin', 'id', 'Password') . " rehashed\n";
echo 'costumer: ' . rehash($link, 'costumer', 'id', 'pwd') . " rehashed\n";
