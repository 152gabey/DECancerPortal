<?php
// Sets a new password for one user. Run it from the terminal, for example:
//   php frontend/tools/set_password.php researcher1 'TestPassword123'
if (PHP_SAPI !== 'cli') {
    exit('Run this from the terminal.');
}
require __DIR__ . '/../includes/db.php';

[$script, $username, $password] = $argv + [null, null, null];
if (!$username || !$password) {
    exit("Usage: php set_password.php USERNAME NEWPASSWORD\n");
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = getDb()->prepare('UPDATE Users SET Password_Hash = ? WHERE Username = ?');
$stmt->execute([$hash, $username]);

echo $stmt->rowCount() ? "Password updated for $username.\n" : "No user called $username.\n";
