<?php
declare(strict_types=1);
// Nur lokale Entwicklung: setzt die Datenbank NEU auf (löscht alle Tabellen!) und legt Testdaten an.
// Aufruf: php bin/dev-setup.php   (Admin: admin@test.de / test1234)

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('Nur auf der Kommandozeile.');
}
if (!str_contains(config()['base_url'], 'localhost')) {
    exit("Abbruch: base_url ist nicht localhost – das sieht nicht nach lokaler Entwicklung aus.\n");
}

db()->exec('DROP TABLE IF EXISTS einsatz_personen, einsaetze, login_tokens, users, employers, settings');
foreach (preg_split('/;\s*\n/', (string)file_get_contents(APP_ROOT . '/sql/schema.sql')) as $statement) {
    if (trim(preg_replace('/^--.*$/m', '', $statement)) !== '') {
        db()->exec($statement);
    }
}

$employers = [
    // Alle Firmen und Adressen sind frei erfunden
    ['Beispiel Software AG', '', 'Musterweg 12', '12345', 'Musterstadt'],
    ['Musterfirma GmbH', 'Personalabteilung', 'Industriestraße 3', '12345', 'Musterstadt'],
    ['Muster Metallbau GmbH & Co. KG', '', 'Gewerbering 7', '12345', 'Musterstadt'],
    ['Stadtverwaltung Musterstadt', '', 'Rathausplatz 1', '12345', 'Musterstadt'],
    ['Beispielklinikum Musterhausen', '', 'Am Klinikum 5', '54321', 'Musterhausen'],
    ['Musterbank eG', '', 'Marktplatz 9', '54321', 'Musterhausen'],
];
$employerIds = [];
foreach ($employers as $emp) {
    db_exec('INSERT INTO employers (name, zusatz, strasse, plz, ort) VALUES (?, ?, ?, ?, ?)', $emp);
    $employerIds[] = (int)db()->lastInsertId();
}

$insert = db()->prepare(
    'INSERT INTO users (email, vorname, nachname, is_admin, funktion, password_hash, employer_id, personalnummer, want_print, want_mail)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insert->execute(['admin@test.de', 'Erika', 'Musterfrau', 1, 'Wehrführer', password_hash('test1234', PASSWORD_DEFAULT), null, null, 1, 0]);
$insert->execute(['max@test.de', 'Max', 'Mustermann', 0, '', null, $employerIds[0], '123456', 0, 1]);

$vornamen = ['Anna', 'Jörg', 'Tim', 'Lena', 'Lukas', 'Sophie', 'Jonas', 'Marie', 'Felix', 'Laura', 'Max', 'Julia', 'Paul', 'Hannah', 'Jan', 'Lea', 'Tobias', 'Sarah', 'Niklas', 'Katharina'];
$nachnamen = ['Müller', 'Schäfer', 'Becker', 'Schmidt', 'Weber', 'Wagner', 'Hofmann', 'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger', 'Hartmann', 'Lange'];
mt_srand(42);
for ($i = 0; $i < 83; $i++) {
    $vorname = $vornamen[$i % count($vornamen)];
    $nachname = $nachnamen[($i * 7 + intdiv($i, 20)) % count($nachnamen)];
    // etwa jede sechste Person ohne Arbeitgeber
    $employerId = mt_rand(0, 5) ? $employerIds[mt_rand(1, count($employerIds) - 1)] : null;
    $email = mt_rand(0, 4) ? strtolower(slug($vorname . '.' . $nachname)) . $i . '@test.de' : null;
    $personalnummer = $employerId && mt_rand(0, 1) ? (string)mt_rand(10000, 99999) : null;
    $insert->execute([$email, $vorname, $nachname, 0, '', null, $employerId, $personalnummer, mt_rand(0, 1), $email ? mt_rand(0, 1) : 0]);
}

echo "Datenbank neu aufgesetzt. Admin: admin@test.de / test1234\n";
