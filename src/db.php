<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'];
        $pdo = new PDO($c['dsn'], $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

// Benutzer inklusive Arbeitgeberdaten als ag_*-Felder (leer, wenn kein Arbeitgeber)
const USER_SELECT = "SELECT u.*,
        COALESCE(e.name, '') AS ag_name, COALESCE(e.zusatz, '') AS ag_zusatz, COALESCE(e.strasse, '') AS ag_strasse,
        COALESCE(e.plz, '') AS ag_plz, COALESCE(e.ort, '') AS ag_ort
    FROM users u LEFT JOIN employers e ON e.id = u.employer_id";

function user_by_id(int $id): ?array
{
    return db_one(USER_SELECT . ' WHERE u.id = ?', [$id]);
}

function user_by_email(string $email): ?array
{
    return db_one(USER_SELECT . ' WHERE u.email = ?', [$email]);
}

function users_all(): array
{
    return db_all(USER_SELECT . ' ORDER BY u.active DESC, u.nachname, u.vorname');
}

function user_display_name(array $u): string
{
    return $u['nachname'] . ', ' . $u['vorname'];
}

/** Für Bescheinigungen auswählbare Einsatzkräfte: alle aktiven, auch ohne Arbeitgeber. */
function users_active(): array
{
    return db_all(USER_SELECT . ' WHERE u.active = 1 ORDER BY u.nachname, u.vorname');
}

function employers_all(): array
{
    return db_all('SELECT e.*, (SELECT COUNT(*) FROM users u WHERE u.employer_id = e.id) AS personen
                   FROM employers e ORDER BY e.name, e.ort');
}

function employer_by_id(int $id): ?array
{
    return db_one('SELECT * FROM employers WHERE id = ?', [$id]);
}

function employer_label(array $e): string
{
    return $e['name'] . ($e['ort'] !== '' ? ' – ' . $e['ort'] : '');
}

/** Liest und prüft die Arbeitgeberfelder eines Formulars (Präfix "new_"). Gibt [Daten, Fehler] zurück. */
function employer_from_post(string $prefix = ''): array
{
    $data = [
        'name'    => post($prefix . 'name'),
        'zusatz'  => post($prefix . 'zusatz'),
        'strasse' => post($prefix . 'strasse'),
        'plz'     => post($prefix . 'plz'),
        'ort'     => post($prefix . 'ort'),
    ];
    $errors = [];
    if ($data['name'] === '' || $data['strasse'] === '' || $data['plz'] === '' || $data['ort'] === '') {
        $errors[] = 'Für den Arbeitgeber bitte Firma, Straße, PLZ und Ort angeben.';
    }
    return [$data, $errors];
}

/**
 * Auswertung des Arbeitgeber-Dropdowns (Feld employer_id): leer = keiner, Zahl = vorhandener,
 * "new" = neu anlegen aus den Feldern new_*. Gleicher Name + PLZ wird wiederverwendet statt doppelt angelegt.
 * Gibt [employer_id|null, Fehler] zurück.
 */
function employer_choice_from_post(): array
{
    $choice = post('employer_id');
    if ($choice === '') {
        return [null, []];
    }
    if ($choice !== 'new') {
        $employer = employer_by_id((int)$choice);
        return $employer ? [(int)$employer['id'], []] : [null, ['Der gewählte Arbeitgeber existiert nicht mehr.']];
    }
    [$data, $errors] = employer_from_post('new_');
    if ($errors) {
        return [null, $errors];
    }
    $existing = db_one('SELECT id FROM employers WHERE LOWER(name) = LOWER(?) AND plz = ?', [$data['name'], $data['plz']]);
    if ($existing) {
        return [(int)$existing['id'], []];
    }
    db_exec('INSERT INTO employers (name, zusatz, strasse, plz, ort) VALUES (?, ?, ?, ?, ?)', array_values($data));
    return [(int)db()->lastInsertId(), []];
}
