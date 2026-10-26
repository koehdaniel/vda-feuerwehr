<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$user = consume_magic_token((string)($_GET['t'] ?? ''));
if (!$user) {
    flash('error', 'Der Login-Link ist ungültig oder abgelaufen. Bitte einen neuen anfordern.');
    redirect('login.php');
}

login_user((int)$user['id'], true);
flash('ok', $user['password_hash'] === null ? 'Angemeldet. Wenn du magst, kannst du unten ein Passwort setzen.' : 'Angemeldet.');
redirect('profil.php');
