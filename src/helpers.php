<?php
declare(strict_types=1);

function config(): array
{
    static $config = null;
    return $config ??= require APP_ROOT . '/config/config.php';
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

/** Wie post(), aber ein leeres Feld wird zu NULL (für optionale Spalten). */
function post_or_null(string $key): ?string
{
    $value = post($key);
    return $value === '' ? null : $value;
}

function post_bool(string $key): int
{
    return empty($_POST[$key]) ? 0 : 1;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Ungültige Anfrage (CSRF). Bitte Seite neu laden.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Liest ein deutsches Datum ("26.10.2026", "26.10.26", "26102026") und gibt "Y-m-d" zurück, oder null.
 * Muss zu public/assets/datetime-de.js passen.
 */
function parse_date_de(string $s): ?string
{
    $s = trim($s);
    if (!preg_match('/^(\d{1,2})[.\-\/ ](\d{1,2})[.\-\/ ](\d{2}|\d{4})$/', $s, $m)
        && !preg_match('/^(\d{2})(\d{2})(\d{2}|\d{4})$/', $s, $m)) {
        return null;
    }
    [, $d, $mo, $y] = array_map('intval', $m);
    $y = $y < 100 ? 2000 + $y : $y;
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
}

/** Liest eine Uhrzeit ("14:30", "14.30", "1430", "9") und gibt "H:i" zurück, oder null. */
function parse_time(string $s): ?string
{
    $s = trim($s);
    if (!preg_match('/^(\d{1,2})(?:[:.,h ]?(\d{2}))?$/', $s, $m)) {
        return null;
    }
    $h = (int)$m[1];
    $min = (int)($m[2] ?? 0);
    return $h < 24 && $min < 60 ? sprintf('%02d:%02d', $h, $min) : null;
}

/** Ersetzt {platzhalter} im Text. */
function fill(string $text, array $vars): string
{
    $map = [];
    foreach ($vars as $k => $v) {
        $map['{' . $k . '}'] = (string)$v;
    }
    return strtr($text, $map);
}

/** Dateinamen-taugliche ASCII-Variante, z. B. "Müller" -> "Mueller". */
function slug(string $s): string
{
    $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss']);
    return trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-');
}

function render_header(string $title): void
{
    $user = current_user();
    $flashes = take_flashes();
    require APP_ROOT . '/templates/header.php';
}

/** Dropdown für den Arbeitgeber inkl. Formular für einen neuen. */
function render_employer_select(string $selected): void
{
    $employers = employers_all();
    require APP_ROOT . '/templates/employer_select.php';
}

/** Personenliste mit Suche und Checkboxen (Feld ids[]). */
function render_people_picker(array $people, string $title): void
{
    require APP_ROOT . '/templates/people_picker.php';
}

/** Unterschriftsfeld (Feld signature) für den angemeldeten Admin. */
function render_signature_field(array $signer): void
{
    require APP_ROOT . '/templates/signature_field.php';
}

function render_footer(): void
{
    require APP_ROOT . '/templates/footer.php';
}
