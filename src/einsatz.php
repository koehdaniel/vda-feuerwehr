<?php
declare(strict_types=1);

function einsatz_by_id(int $id): ?array
{
    return db_one(
        "SELECT e.*, CONCAT(u.vorname, ' ', u.nachname) AS created_by_name
         FROM einsaetze e LEFT JOIN users u ON u.id = e.created_by WHERE e.id = ?",
        [$id]
    );
}

function einsaetze_all(): array
{
    return db_all(
        'SELECT e.*, (SELECT COUNT(*) FROM einsatz_personen ep WHERE ep.einsatz_id = e.id) AS personen
         FROM einsaetze e ORDER BY e.von DESC'
    );
}

/** Ausgestellte Bescheinigungen eines Einsatzes mit Name der Person und des Unterzeichners. */
function einsatz_certificates(int $einsatzId): array
{
    return db_all(
        "SELECT ep.*, u.vorname, u.nachname, CONCAT(s.vorname, ' ', s.nachname) AS signed_by_name
         FROM einsatz_personen ep
         JOIN users u ON u.id = ep.user_id
         LEFT JOIN users s ON s.id = ep.signed_by
         WHERE ep.einsatz_id = ? ORDER BY u.nachname, u.vorname",
        [$einsatzId]
    );
}

/** Die letzten Bescheinigungen einer Person. */
function user_certificates(int $userId, int $limit = 10): array
{
    return db_all(
        'SELECT ep.*, e.von, e.bis, e.stichwort
         FROM einsatz_personen ep JOIN einsaetze e ON e.id = ep.einsatz_id
         WHERE ep.user_id = ? ORDER BY e.von DESC LIMIT ' . $limit,
        [$userId]
    );
}

function einsatz_label(array $einsatz): string
{
    $von = new DateTimeImmutable($einsatz['von']);
    $bis = new DateTimeImmutable($einsatz['bis']);
    $label = $von->format('d.m.Y H:i') . ' – ' . ($von->format('Ymd') === $bis->format('Ymd') ? $bis->format('H:i') : $bis->format('d.m.Y H:i'));
    return $einsatz['stichwort'] !== '' ? $label . ' · ' . $einsatz['stichwort'] : $label;
}

function certificate_dir(int $einsatzId): string
{
    $dir = CERT_DIR . '/' . $einsatzId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/** Prüft die mitgeschickte Unterschrift und gibt sie als data:-URI zurück, oder null wenn ungültig. */
function posted_signature(): ?string
{
    $data = post('signature');
    $prefix = 'data:image/jpeg;base64,';
    $jpeg = str_starts_with($data, $prefix) ? base64_decode(substr($data, strlen($prefix)), true) : false;
    if (!$jpeg || strlen($jpeg) > 2_000_000 || (getimagesizefromstring($jpeg)['mime'] ?? '') !== 'image/jpeg') {
        return null;
    }
    return $prefix . base64_encode($jpeg);
}

/**
 * Erstellt für jede Person eine Bescheinigung, legt das PDF ab, verschickt es per Mail bzw. nimmt es
 * in eine gemeinsame Druck-PDF auf und speichert alles in einsatz_personen.
 * Personen, die für diesen Einsatz schon eine Bescheinigung haben, werden übersprungen.
 * Gibt die Anzahl der neu ausgestellten Bescheinigungen zurück.
 */
function issue_certificates(array $einsatz, array $people, array $signer, string $signature): int
{
    set_time_limit(300);
    $einsatzId = (int)$einsatz['id'];
    $von = new DateTimeImmutable($einsatz['von']);
    $bis = new DateTimeImmutable($einsatz['bis']);
    $erstellt = new DateTimeImmutable();
    $dir = certificate_dir($einsatzId);
    $existing = array_column(einsatz_certificates($einsatzId), 'user_id');

    $rows = [];
    $printHtml = [];
    foreach ($people as $p) {
        if (in_array($p['id'], $existing)) {
            continue;
        }
        $html = certificate_html($p, $von, $bis, $erstellt, $signer, $signature);
        // Personen-ID vorne, damit gleichnamige Personen sich nicht überschreiben
        $file = $p['id'] . '_' . certificate_filename($p, $von);
        file_put_contents($dir . '/' . $file, render_pdf([$html]));

        $print = (bool)$p['want_print'];
        $note = '';
        if ($p['want_mail'] && $p['email']) {
            try {
                $vars = ['datum' => $von->format('d.m.Y'), 'vorname' => $p['vorname'], 'nachname' => $p['nachname']];
                send_mail(
                    $p['email'],
                    fill(setting('mail_betreff'), $vars),
                    fill(setting('mail_text'), $vars),
                    [certificate_filename($p, $von) => (string)file_get_contents($dir . '/' . $file)],
                    $p['email_cc']
                );
            } catch (Throwable $ex) {
                $print = true;
                // Fehlermeldung nur ins Log – sie enthält oft die Mailadresse
                error_log('Bescheinigung per Mail an Person ' . $p['id'] . ' fehlgeschlagen: ' . $ex->getMessage());
                $note = 'Mail fehlgeschlagen – stattdessen im Druck';
            }
        } elseif ($p['want_mail']) {
            $print = true;
            $note = 'keine E-Mail hinterlegt – stattdessen im Druck';
        } elseif (!$print) {
            $print = true;
            $note = 'keine Zustellart gewählt – im Druck';
        }

        $sent = $p['want_mail'] && $p['email'] && $note === '';
        $text = $note !== '' ? $note : implode(' + ', array_filter([$sent ? 'per Mail' : '', $print ? 'im Druck' : '']));
        if ($print) {
            $printHtml[] = $html;
        }
        $rows[] = [$p['id'], $file, $print, $note === '' ? 'ok' : 'warn', mb_substr($text, 0, 500)];
    }

    $druckFile = null;
    if ($printHtml) {
        $druckFile = 'Druck_' . $von->format('Y-m-d') . '_' . date('Ymd-His') . '.pdf';
        file_put_contents($dir . '/' . $druckFile, render_pdf($printHtml));
    }

    $stmt = db()->prepare(
        'INSERT INTO einsatz_personen (einsatz_id, user_id, signed_by, pdf_datei, druck_datei, status, status_text)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as [$userId, $file, $print, $status, $text]) {
        $stmt->execute([$einsatzId, $userId, $signer['id'], $file, $print ? $druckFile : null, $status, $text]);
    }
    return count($rows);
}

/** Liefert ein abgelegtes PDF aus und beendet das Skript. */
function send_stored_pdf(int $einsatzId, string $file): never
{
    $path = CERT_DIR . '/' . $einsatzId . '/' . basename($file);
    if (!is_file($path)) {
        http_response_code(404);
        exit('Datei nicht gefunden.');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}
