<?php
declare(strict_types=1);
// Legt den ersten Admin an (oder macht eine vorhandene Person zum Admin).
// Aufruf: php bin/create-admin.php <email> <vorname> <nachname> [passwort]
// Ohne Passwort meldet sich der Admin per Login-Link an.

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('Nur auf der Kommandozeile.');
}
if ($argc < 4) {
    fwrite(STDERR, "Aufruf: php bin/create-admin.php <email> <vorname> <nachname> [passwort]\n");
    exit(1);
}

[, $email, $vorname, $nachname] = $argv;
$email = strtolower($email);
$password = $argv[4] ?? null;
$hash = $password !== null ? password_hash($password, PASSWORD_DEFAULT) : null;

$existing = user_by_email($email);
if ($existing) {
    db_exec('UPDATE users SET is_admin = 1, active = 1 WHERE id = ?', [$existing['id']]);
    if ($hash !== null) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $existing['id']]);
    }
    echo "Vorhandene Person {$email} ist jetzt Admin.\n";
} else {
    db_exec(
        'INSERT INTO users (email, vorname, nachname, password_hash, is_admin) VALUES (?, ?, ?, ?, 1)',
        [$email, $vorname, $nachname, $hash]
    );
    echo "Admin {$email} angelegt.\n";
}
