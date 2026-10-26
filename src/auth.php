<?php
declare(strict_types=1);

const REMEMBER_COOKIE = 'vda_remember';
const REMEMBER_DAYS = 90;

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (empty($_SESSION['user_id'])) {
            login_from_remember_cookie();
        }
        if (!empty($_SESSION['user_id'])) {
            $u = user_by_id((int)$_SESSION['user_id']);
            if ($u && $u['active']) {
                $user = $u;
            }
        }
    }
    return $user;
}

/** Ist „Angemeldet bleiben“ möglich? Dafür braucht es einen geheimen Schlüssel in der Konfiguration. */
function remember_enabled(): bool
{
    return strlen((string)(config()['secret'] ?? '')) >= 32;
}

/**
 * Signatur für das „Angemeldet bleiben“-Cookie. Der Passwort-Hash fließt mit ein:
 * Wird das Passwort geändert oder gelöscht, werden dadurch alle bisherigen Cookies der Person ungültig.
 */
function remember_signature(array $user, int $expires): string
{
    return hash_hmac('sha256', $user['id'] . '.' . $expires . '.' . ($user['password_hash'] ?? ''), config()['secret']);
}

/** Setzt das signierte Cookie „Benutzer-ID.Ablauf.Signatur“ (90 Tage, verlängert sich bei Nutzung). */
function remember_user(int $userId): void
{
    $user = user_by_id($userId);
    if (!$user || !remember_enabled()) {
        return;
    }
    $expires = time() + REMEMBER_DAYS * 86400;
    set_remember_cookie($user['id'] . '.' . $expires . '.' . remember_signature($user, $expires), $expires);
}

function set_remember_cookie(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function has_remember_cookie(): bool
{
    return !empty($_COOKIE[REMEMBER_COOKIE]);
}

/** Meldet anhand des Cookies an, wenn Signatur und Ablauf stimmen und die Person aktiv ist. */
function login_from_remember_cookie(): void
{
    $cookie = (string)($_COOKIE[REMEMBER_COOKIE] ?? '');
    if (!remember_enabled() || !preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{64})$/', $cookie, $m)) {
        return;
    }
    [, $userId, $expires, $signature] = $m;
    $user = (int)$expires > time() ? user_by_id((int)$userId) : null;
    if (!$user || !$user['active'] || !hash_equals(remember_signature($user, (int)$expires), $signature)) {
        set_remember_cookie('', time() - 3600);
        return;
    }
    login_user((int)$user['id']);
    remember_user((int)$user['id']);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (!$user['is_admin']) {
        http_response_code(403);
        exit('Kein Zugriff.');
    }
    return $user;
}

/** $viaLink: per Login-Link angemeldet – dann darf ein neues Passwort ohne das alte gesetzt werden. */
function login_user(int $userId, bool $viaLink = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['via_link'] = $viaLink;
}

/** Muss beim Passwortändern das aktuelle Passwort eingegeben werden? */
function needs_current_password(array $user): bool
{
    return $user['password_hash'] !== null && empty($_SESSION['via_link']);
}

function logout_user(): void
{
    if (has_remember_cookie()) {
        set_remember_cookie('', time() - 3600);
    }
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Liefert den Benutzer bei korrektem Passwort, sonst null. */
function attempt_password_login(string $email, string $password): ?array
{
    $user = user_by_email($email);
    if (!$user || !$user['active'] || $user['password_hash'] === null) {
        // Gleiche Laufzeit wie ein echter Vergleich, damit nicht erkennbar ist, ob es die Adresse gibt
        password_verify($password, password_hash('dummy', PASSWORD_DEFAULT));
        return null;
    }
    return password_verify($password, $user['password_hash']) ? $user : null;
}

/**
 * Verschickt einen Login-Link an aktive Benutzer mit E-Mail – auch wenn ein Passwort gesetzt ist.
 * Gibt true zurück, wenn eine Mail rausging.
 */
function send_magic_link(array $user, bool $remember = false): bool
{
    if (!$user['active'] || empty($user['email'])) {
        return false;
    }
    $minutes = (int)(config()['magic_link_minutes'] ?? 30);
    $token = bin2hex(random_bytes(32));
    db_exec(
        'INSERT INTO login_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        [$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + $minutes * 60)]
    );
    // r=1: nach dem Klick angemeldet bleiben
    $link = rtrim(config()['base_url'], '/') . '/magic.php?t=' . $token . ($remember ? '&r=1' : '');
    $vars = ['vorname' => $user['vorname'], 'nachname' => $user['nachname'], 'link' => $link, 'minuten' => $minutes];
    send_mail($user['email'], fill(setting('loginmail_betreff'), $vars), fill(setting('loginmail_text'), $vars));
    return true;
}

/**
 * Prüft eine gewünschte neue E-Mail-Adresse und verschickt den Bestätigungslink an sie.
 * Gibt eine Fehlermeldung zurück oder null bei Erfolg.
 */
function request_email_change(array $user, string $email): ?string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Die E-Mail-Adresse ist ungültig.';
    }
    if ($email === $user['email']) {
        return 'Das ist bereits deine aktuelle E-Mail-Adresse.';
    }
    $other = user_by_email($email);
    if ($other && (int)$other['id'] !== (int)$user['id']) {
        return 'Diese E-Mail-Adresse wird schon von einer anderen Person verwendet.';
    }

    $token = bin2hex(random_bytes(32));
    db_exec(
        'UPDATE users SET email_neu = ?, email_token_hash = ?, email_token_expires = ? WHERE id = ?',
        [$email, hash('sha256', $token), date('Y-m-d H:i:s', time() + 86400), $user['id']]
    );
    $link = rtrim(config()['base_url'], '/') . '/email-bestaetigen.php?t=' . $token;
    $vars = ['vorname' => $user['vorname'], 'nachname' => $user['nachname'], 'link' => $link, 'email' => $email];
    send_mail($email, fill(setting('emailmail_betreff'), $vars), fill(setting('emailmail_text'), $vars));
    return null;
}

/** Löst einen Bestätigungslink ein und übernimmt die neue Adresse. Gibt eine Fehlermeldung zurück oder null. */
function confirm_email_change(string $token): ?string
{
    $user = preg_match('/^[a-f0-9]{64}$/', $token)
        ? db_one('SELECT * FROM users WHERE email_token_hash = ? AND email_token_expires > ?', [hash('sha256', $token), date('Y-m-d H:i:s')])
        : null;
    if (!$user || !$user['active']) {
        return 'Der Bestätigungslink ist ungültig oder abgelaufen.';
    }
    $other = user_by_email($user['email_neu']);
    if ($other && (int)$other['id'] !== (int)$user['id']) {
        return 'Diese E-Mail-Adresse wird inzwischen von einer anderen Person verwendet.';
    }
    db_exec(
        'UPDATE users SET email = email_neu, email_neu = NULL, email_token_hash = NULL, email_token_expires = NULL WHERE id = ?',
        [$user['id']]
    );
    return null;
}

/** Löst einen Login-Link ein und liefert den Benutzer, oder null wenn ungültig/abgelaufen. */
function consume_magic_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $row = db_one(
        'SELECT * FROM login_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?',
        [hash('sha256', $token), date('Y-m-d H:i:s')]
    );
    if (!$row) {
        return null;
    }
    db_exec('UPDATE login_tokens SET used_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $row['id']]);
    db_exec('DELETE FROM login_tokens WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $user = user_by_id((int)$row['user_id']);
    return $user && $user['active'] ? $user : null;
}
