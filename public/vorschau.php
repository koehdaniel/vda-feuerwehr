<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

// Vorschau der gespeicherten Vorlage mit Beispieldaten; die Unterschrift erscheint als Platzhalter.
$admin = require_admin();

$sample = [
    'vorname' => 'Max', 'nachname' => 'Mustermann', 'personalnummer' => '12345',
    'ag_name' => 'Musterfirma GmbH', 'ag_zusatz' => 'Personalabteilung', 'ag_strasse' => 'Musterstraße 1',
    'ag_plz' => '12345', 'ag_ort' => 'Musterstadt',
];
$von = new DateTimeImmutable('today 14:19');
$bis = new DateTimeImmutable('today 16:50');

$pdf = render_pdf([certificate_html($sample, $von, $bis, new DateTimeImmutable(), $admin, null)]);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Vorschau.pdf"');
header('Cache-Control: private, no-store');
echo $pdf;
