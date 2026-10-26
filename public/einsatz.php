<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

// Neuen Einsatz anlegen und Bescheinigungen erstellen
$signer = require_admin();
$people = users_active();

if (is_post()) {
    csrf_check();

    $parse = function (string $prefix): DateTimeImmutable|false {
        $date = parse_date_de(post($prefix . '_datum'));
        $time = parse_time(post($prefix . '_zeit'));
        return $date && $time ? DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$date $time") : false;
    };
    $von = $parse('von');
    $bis = $parse('bis');
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $selected = array_values(array_filter($people, fn($p) => in_array((int)$p['id'], $ids, true)));
    $signature = posted_signature();

    $errors = [];
    if (!$von || !$bis) {
        $errors[] = 'Bitte Beginn und Ende vollständig angeben (Datum als TT.MM.JJJJ, Uhrzeit als HH:MM).';
    } elseif ($bis <= $von) {
        $errors[] = 'Das Ende muss nach dem Beginn liegen.';
    }
    if (!$selected) {
        $errors[] = 'Bitte mindestens eine Person auswählen.';
    }
    if (!$signature) {
        $errors[] = 'Bitte unterschreiben.';
    }

    if (!$errors) {
        db_exec(
            'INSERT INTO einsaetze (von, bis, stichwort, created_by) VALUES (?, ?, ?, ?)',
            [$von->format('Y-m-d H:i:s'), $bis->format('Y-m-d H:i:s'), post('stichwort'), $signer['id']]
        );
        $einsatz = einsatz_by_id((int)db()->lastInsertId());
        $count = issue_certificates($einsatz, $selected, $signer, $signature);
        flash('ok', "$count Bescheinigungen erstellt.");
        redirect('einsaetze.php?id=' . $einsatz['id']);
    }

    foreach ($errors as $err) {
        flash('error', $err);
    }
}

// Vorbelegung: heute; Ende = jetzt, aufgerundet auf die nächste Viertelstunde, mindestens 10 Minuten voraus
$now = new DateTimeImmutable();
$ende = $now->setTime((int)$now->format('H'), intdiv((int)$now->format('i'), 15) * 15)->modify('+15 minutes');
if ($ende->getTimestamp() - $now->getTimestamp() < 600) {
    $ende = $ende->modify('+15 minutes');
}
$form = [
    'von_datum' => $now->format('d.m.Y'),
    'von_zeit'  => '',
    'bis_datum' => $ende->format('d.m.Y'),
    'bis_zeit'  => $ende->format('H:i'),
];
if (is_post()) {
    foreach (array_keys($form) as $key) {
        $form[$key] = post($key);
    }
}

render_header('Neuer Einsatz');
?>
<form method="post">
    <?= csrf_field() ?>
    <div class="card">
        <h2>Einsatz</h2>
        <div class="row">
            <div>
                <label for="von_datum">Beginn – Datum</label>
                <input type="text" id="von_datum" name="von_datum" value="<?= e($form['von_datum']) ?>" data-format="date"
                       placeholder="TT.MM.JJJJ" inputmode="numeric" autocomplete="off" maxlength="10" required>
            </div>
            <div class="narrow">
                <label for="von_zeit">Uhrzeit</label>
                <input type="text" id="von_zeit" name="von_zeit" value="<?= e($form['von_zeit']) ?>" data-format="time"
                       placeholder="HH:MM" inputmode="numeric" autocomplete="off" maxlength="5" required>
            </div>
        </div>
        <div class="row">
            <div>
                <label for="bis_datum">Ende – Datum</label>
                <input type="text" id="bis_datum" name="bis_datum" value="<?= e($form['bis_datum']) ?>" data-format="date"
                       placeholder="TT.MM.JJJJ" inputmode="numeric" autocomplete="off" maxlength="10" required>
            </div>
            <div class="narrow">
                <label for="bis_zeit">Uhrzeit</label>
                <input type="text" id="bis_zeit" name="bis_zeit" value="<?= e($form['bis_zeit']) ?>" data-format="time"
                       placeholder="HH:MM" inputmode="numeric" autocomplete="off" maxlength="5" required>
            </div>
        </div>
        <p class="hint">Auch Kurzformen gehen, z. B. „261026“ oder „1430“.</p>
        <label for="stichwort">Stichwort <span class="hint">(optional, nur für die Übersicht – z. B. „F2 Wohnhaus“)</span></label>
        <input type="text" id="stichwort" name="stichwort" value="<?= e(post('stichwort')) ?>" maxlength="200">
    </div>

    <?php render_people_picker($people, 'Einsatzkräfte'); ?>
    <?php render_signature_field($signer); ?>

    <button class="btn" type="submit" id="submit">Bescheinigungen erstellen</button>
    <p class="hint">Das Erstellen kann bei vielen Personen etwas dauern – bitte nicht doppelt tippen.</p>
</form>
<script src="assets/datetime-de.js"></script>
<?php render_footer();
