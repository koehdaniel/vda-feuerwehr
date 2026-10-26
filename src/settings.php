<?php
declare(strict_types=1);

const DEFAULT_VORLAGE = <<<'HTML'
<p>An:</p>
<p>{arbeitgeber_adresse}</p>
<p>&nbsp;</p>
<p style="text-align: right;">{ort} {datum}</p>
<p>&nbsp;</p>
<p style="text-align: center;"><span style="text-decoration: underline; font-size: 15pt;">Bescheinigung für den Arbeitgeber</span></p>
<p>&nbsp;</p>
<p>Ich bescheinige, dass <strong><span style="font-size: 14pt;">{vorname} {nachname}</span></strong>{personalnummer_zusatz}</p>
<p>sich mit der {wehr} im Einsatz befand.</p>
<table style="width: 100%;">
<tbody>
<tr><td style="width: 40%;">Von <strong>{von_datum}</strong></td><td><strong>{von_uhrzeit} Uhr.</strong></td></tr>
<tr><td style="width: 40%;">Bis <strong>{bis_datum}</strong></td><td><strong>{bis_uhrzeit} Uhr.</strong></td></tr>
</tbody>
</table>
<p>&nbsp;</p>
<p>Mit freundlichen Grüßen</p>
<p>{unterschrift}</p>
<p>{unterzeichner}<br>{unterzeichner_funktion}<br>{wehr}</p>
<p><em><span style="font-size: 8pt; color: #555555;">Dieses Schreiben wurde maschinell erstellt und digital unterschrieben. Es ist auch ohne handschriftliche Unterschrift gültig.</span></em></p>
<p>&nbsp;</p>
<p>Verdienstausfallabrechnungen richten Sie bitte an:<br>Stadtverwaltung {ort}<br>Herr Max Mustermann<br>Musterstraße 1<br>12345 {ort}<br>Telefon: 01234/56789-0</p>
HTML;

/**
 * In der Datenbank pflegbare Texte mit Beschriftung, Feldtyp (text, textarea, html) und Standardwert.
 */
function settings_fields(): array
{
    return [
        'Allgemein' => [
            'wehr' => ['Name der Feuerwehr – Platzhalter {wehr}', 'text', 'Freiwillige Feuerwehr Musterstadt'],
            'ort'  => ['Ort – Platzhalter {ort}', 'text', 'Musterstadt'],
        ],
        'Bescheinigung' => [
            'briefkopf' => ['Briefkopf (links vom Logo)', 'textarea', "Freiwillige Feuerwehr\nMusterstadt"],
            'vorlage'   => ['Text der Bescheinigung', 'html', DEFAULT_VORLAGE],
        ],
        'Mail mit Bescheinigung' => [
            'mail_betreff' => ['Betreff – {datum} = Einsatzdatum', 'text', 'Arbeitgeberbescheinigung Einsatz vom {datum}'],
            'mail_text'    => ['Text – {vorname}, {nachname}, {datum}', 'textarea', "Hallo {vorname},\n\nanbei erhältst du die Bescheinigung für deinen Arbeitgeber zum Einsatz vom {datum}.\nBitte leite sie an deinen Arbeitgeber weiter.\n\nViele Grüße\nDeine Wehrführung"],
        ],
        'Mail mit Login-Link' => [
            'loginmail_betreff' => ['Betreff', 'text', 'Dein Login für die Arbeitgeberbescheinigungen'],
            'loginmail_text'    => ['Text – {vorname}, {link}, {minuten}', 'textarea', "Hallo {vorname},\n\nüber diesen Link kannst du dich anmelden (gültig für {minuten} Minuten):\n{link}\n\nIm Profil kannst du deine Arbeitgeberdaten pflegen und ein Passwort setzen.\n\nViele Grüße\nDeine Wehrführung"],
        ],
        'Mail zur Bestätigung einer neuen E-Mail-Adresse' => [
            'emailmail_betreff' => ['Betreff', 'text', 'Bitte bestätige deine neue E-Mail-Adresse'],
            'emailmail_text'    => ['Text – {vorname}, {email}, {link}', 'textarea', "Hallo {vorname},\n\ndu möchtest für die Arbeitgeberbescheinigungen künftig die Adresse {email} verwenden.\nBitte bestätige das über diesen Link (gültig für 24 Stunden):\n{link}\n\nWenn du das nicht warst, kannst du diese Mail ignorieren.\n\nViele Grüße\nDeine Wehrführung"],
        ],
    ];
}

/** Platzhalter für den Bescheinigungstext mit Erklärung (für die Hilfe in den Einstellungen). */
function certificate_placeholders(): array
{
    return [
        '{wehr}'                   => 'Name der Feuerwehr (oben unter „Allgemein“)',
        '{ort}'                    => 'Ort (oben unter „Allgemein“)',
        '{arbeitgeber_adresse}'    => 'Anschrift des Arbeitgebers (mehrzeilig)',
        '{datum}'                  => 'Erstelldatum',
        '{vorname}'                => 'Vorname der Einsatzkraft',
        '{nachname}'               => 'Nachname der Einsatzkraft',
        '{personalnummer}'         => 'Personalnummer (leer, wenn keine)',
        '{personalnummer_zusatz}'  => '„ (Personal-Nr. 123)“ – oder nichts, wenn keine hinterlegt',
        '{von_datum}'              => 'Einsatzbeginn Datum',
        '{von_uhrzeit}'            => 'Einsatzbeginn Uhrzeit',
        '{bis_datum}'              => 'Einsatzende Datum',
        '{bis_uhrzeit}'            => 'Einsatzende Uhrzeit',
        '{unterschrift}'           => 'Die beim Einsatz geleistete Unterschrift (Bild)',
        '{unterzeichner}'          => 'Vorname Nachname der angemeldeten Person',
        '{unterzeichner_funktion}' => 'Funktion der angemeldeten Person (unter „Personen“ pflegbar)',
    ];
}

function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = ['logo' => ''];
        foreach (settings_fields() as $fields) {
            foreach ($fields as $key => [, , $default]) {
                $cache[$key] = $default;
            }
        }
        foreach (db_all('SELECT k, v FROM settings') as $row) {
            if (array_key_exists($row['k'], $cache)) {
                $cache[$row['k']] = $row['v'];
            }
        }
    }
    return $cache;
}

function setting(string $key): string
{
    return settings_all()[$key] ?? '';
}

function settings_save(array $values): void
{
    $stmt = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    foreach ($values as $k => $v) {
        $stmt->execute([$k, str_replace("\r\n", "\n", $v)]);
    }
}

/** Pfad zum hochgeladenen Logo in storage/, oder null wenn keins hinterlegt ist. */
function logo_file(): ?string
{
    $name = basename(setting('logo'));
    return $name !== '' && is_file(STORAGE_DIR . '/' . $name) ? STORAGE_DIR . '/' . $name : null;
}
