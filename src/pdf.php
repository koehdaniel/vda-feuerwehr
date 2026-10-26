<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/** Seitenlayout der Bescheinigung. Wird auch im Editor verwendet, damit er dem PDF möglichst gleicht. */
function certificate_css(): string
{
    return <<<'CSS'
body { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 1.3; color: #000; }
p { margin: 0 0 2.5mm; }
table { border-collapse: collapse; }
td { padding: 0 0 2mm; vertical-align: top; }
img.unterschrift { height: 15mm; }
CSS;
}

function image_data_uri(string $file): string
{
    $mime = getimagesize($file)['mime'] ?? 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($file));
}

/**
 * HTML einer Bescheinigungsseite: Briefkopf mit Logo und die Vorlage mit ersetzten Platzhaltern.
 * $signer ist der angemeldete Benutzer, $signature eine data:-URI des Unterschriftsbildes (oder null für die Vorschau).
 */
function certificate_html(
    array $person,
    DateTimeInterface $von,
    DateTimeInterface $bis,
    DateTimeInterface $erstellt,
    array $signer,
    ?string $signature
): string {
    $address = array_filter(
        [$person['ag_name'], $person['ag_zusatz'], $person['ag_strasse'], trim($person['ag_plz'] . ' ' . $person['ag_ort'])],
        fn($line) => trim($line) !== ''
    );
    $pnr = trim($person['personalnummer'] ?? '');

    $values = [
        'wehr'                   => e(setting('wehr')),
        'ort'                    => e(setting('ort')),
        // Ohne Arbeitgeber bleibt Platz für vier Zeilen, um die Anschrift von Hand einzutragen
        'arbeitgeber_adresse'    => $address ? implode('<br>', array_map('e', $address)) : '&nbsp;<br>&nbsp;<br>&nbsp;<br>&nbsp;',
        'datum'                  => e($erstellt->format('d.m.Y')),
        'vorname'                => e($person['vorname']),
        'nachname'               => e($person['nachname']),
        'personalnummer'         => e($pnr),
        'personalnummer_zusatz'  => $pnr === '' ? '' : e(' (Personal-Nr. ' . $pnr . ')'),
        'von_datum'              => e($von->format('d.m.Y')),
        'von_uhrzeit'            => e($von->format('H:i')),
        'bis_datum'              => e($bis->format('d.m.Y')),
        'bis_uhrzeit'            => e($bis->format('H:i')),
        'unterschrift'           => $signature !== null
            ? '<img class="unterschrift" src="' . e($signature) . '" alt="">'
            : '<span style="color:#999">[Unterschrift]</span>',
        'unterzeichner'          => e($signer['vorname'] . ' ' . $signer['nachname']),
        'unterzeichner_funktion' => e($signer['funktion'] ?? ''),
    ];

    $kopf = nl2br(e(setting('briefkopf')), false);
    $logoFile = logo_file();
    $logo = $logoFile
        ? '<td style="width: 38mm; text-align: right; vertical-align: middle;"><img src="' . image_data_uri($logoFile) . '" style="width: 36mm;" alt=""></td>'
        : '';

    return <<<HTML
<table style="width: 100%; margin-bottom: 12mm;">
<tr>
    <td style="font-family: Helvetica, Arial, sans-serif; font-size: 24pt; line-height: 1.2; vertical-align: middle;">{$kopf}</td>
    {$logo}
</tr>
</table>
HTML . fill(setting('vorlage'), $values);
}

/** Setzt mehrere Bescheinigungsseiten zu einem PDF zusammen. */
function render_pdf(array $pagesHtml): string
{
    $body = implode('<div style="page-break-before: always;"></div>', $pagesHtml);
    $css = certificate_css();
    $html = <<<HTML
<!doctype html>
<html lang="de"><head><meta charset="utf-8">
<style>@page { margin: 15mm 20mm 15mm 20mm; } {$css}</style>
</head><body>{$body}</body></html>
HTML;

    $options = new Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsPhpEnabled(false);
    $options->setDefaultFont('Times');
    $options->setChroot([STORAGE_DIR]);
    $options->setTempDir(TMP_DIR);
    $options->setFontCache(TMP_DIR);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return (string)$dompdf->output();
}

function certificate_filename(array $person, DateTimeInterface $von): string
{
    return 'AG-Bescheinigung_' . $von->format('Y-m-d') . '_' . slug($person['nachname']) . '_' . slug($person['vorname']) . '.pdf';
}
