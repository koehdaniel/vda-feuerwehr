<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

// Einsatz-Historie: Übersicht, Details eines Einsatzes und Personen nachträglich erfassen
$signer = require_admin();

$id = (int)($_GET['id'] ?? 0);

// Übersicht
if (!$id) {
    $einsaetze = einsaetze_all();
    render_header('Einsätze');
    ?>
    <a class="btn" href="einsatz.php">+ Neuer Einsatz</a>
    <div class="card" style="margin-top:16px">
        <ul class="list">
            <?php foreach ($einsaetze as $e): ?>
                <li>
                    <a href="einsaetze.php?id=<?= (int)$e['id'] ?>">
                        <span class="main"><?= e(einsatz_label($e)) ?></span>
                        <span class="badge"><?= (int)$e['personen'] ?> Pers.</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$einsaetze): ?><p class="muted">Noch keine Einsätze erfasst.</p><?php endif; ?>
    </div>
    <?php
    render_footer();
    exit;
}

$einsatz = einsatz_by_id($id);
if (!$einsatz) {
    http_response_code(404);
    exit('Einsatz nicht gefunden.');
}

// Druck-PDF herunterladen
if (isset($_GET['druck'])) {
    $file = (string)$_GET['druck'];
    $known = array_filter(array_column(einsatz_certificates($id), 'druck_datei'));
    if (!in_array($file, $known, true)) {
        http_response_code(404);
        exit('Datei nicht gefunden.');
    }
    send_stored_pdf($id, $file);
}

$certificates = einsatz_certificates($id);
$done = array_map('intval', array_column($certificates, 'user_id'));
$available = array_values(array_filter(users_active(), fn($p) => !in_array((int)$p['id'], $done, true)));

if (is_post()) {
    csrf_check();

    // Einsatz endgültig löschen
    if (isset($_POST['delete_einsatz'])) {
        if (!post_bool('confirm')) {
            flash('error', 'Bitte bestätigen, dass der Einsatz endgültig gelöscht werden soll.');
            redirect('einsaetze.php?id=' . $id);
        }
        delete_einsatz($id);
        flash('ok', 'Einsatz „' . einsatz_label($einsatz) . '“ mit allen Bescheinigungen gelöscht.');
        redirect('einsaetze.php');
    }

    // Einzelne Person aus dem Einsatz entfernen
    if (isset($_POST['remove_cert'])) {
        $cert = db_one(
            'SELECT * FROM einsatz_personen WHERE id = ? AND einsatz_id = ? AND deleted_at IS NULL',
            [(int)$_POST['remove_cert'], $id]
        );
        if ($cert) {
            remove_certificate($cert, $signer);
            flash('ok', 'Bescheinigung entfernt und PDF gelöscht.');
        }
        redirect('einsaetze.php?id=' . $id);
    }

    // Personen nachträglich erfassen
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $selected = array_values(array_filter($available, fn($p) => in_array((int)$p['id'], $ids, true)));
    $signature = posted_signature();

    if (!$selected) {
        flash('error', 'Bitte mindestens eine Person auswählen.');
    } elseif (!$signature) {
        flash('error', 'Bitte unterschreiben.');
    } else {
        $count = issue_certificates($einsatz, $selected, $signer, $signature);
        flash('ok', "$count Bescheinigungen nachträglich erstellt.");
        redirect('einsaetze.php?id=' . $id);
    }
}

// Druck-PDFs mit Anzahl Seiten, neueste zuerst
$prints = array_count_values(array_filter(array_column($certificates, 'druck_datei')));
krsort($prints);
$rows = einsatz_certificates($id, true);

render_header('Einsatz');
?>
<p><a href="einsaetze.php">← Alle Einsätze</a></p>

<div class="card">
    <h2><?= e(einsatz_label($einsatz)) ?></h2>
    <p class="muted" style="margin:0">Angelegt von <?= e($einsatz['created_by_name'] ?? 'unbekannt') ?>
        am <?= e((new DateTimeImmutable($einsatz['created_at']))->format('d.m.Y H:i')) ?></p>
</div>

<?php foreach ($prints as $file => $pages): ?>
    <a class="btn" href="einsaetze.php?id=<?= $id ?>&amp;druck=<?= e(urlencode($file)) ?>" target="_blank">
        Druck-PDF öffnen (<?= $pages ?> <?= $pages === 1 ? 'Seite' : 'Seiten' ?>)
    </a>
    <?php if (count($prints) > 1): ?>
        <p class="hint">erstellt <?= e(DateTimeImmutable::createFromFormat('Ymd-His', substr($file, -19, 15))?->format('d.m.Y H:i') ?? '') ?></p>
    <?php endif; ?>
<?php endforeach; ?>

<div class="card" style="margin-top:16px">
    <h2>Bescheinigungen (<?= count($certificates) ?>)</h2>
    <ul class="list">
        <?php foreach ($rows as $c): ?>
            <li class="cert-row">
                <?php if ($c['deleted_at']): ?>
                    <div class="cert-removed">
                        <span class="main"><?= e(user_display_name($c)) ?>
                            <span class="sub">entfernt von <?= e($c['deleted_by_name'] ?? 'unbekannt') ?>, <?= e((new DateTimeImmutable($c['deleted_at']))->format('d.m.Y H:i')) ?> – PDF gelöscht</span>
                        </span>
                        <span class="badge">entfernt</span>
                    </div>
                <?php else: ?>
                    <a href="bescheinigung.php?id=<?= (int)$c['id'] ?>" target="_blank">
                        <span class="main"><?= e(user_display_name($c)) ?>
                            <span class="sub"><?= e($c['status_text']) ?></span>
                            <span class="sub">unterschrieben von <?= e($c['signed_by_name'] ?? 'unbekannt') ?>, <?= e((new DateTimeImmutable($c['created_at']))->format('d.m.Y H:i')) ?></span>
                        </span>
                        <span class="badge badge-<?= $c['status'] === 'ok' ? 'ok' : 'warn' ?>"><?= $c['status'] === 'ok' ? 'OK' : 'Hinweis' ?></span>
                    </a>
                    <form method="post" class="cert-remove">
                        <?= csrf_field() ?>
                        <button type="submit" name="remove_cert" value="<?= (int)$c['id'] ?>" class="btn btn-secondary btn-small"
                                onclick="return confirm(<?= e(json_encode('Bescheinigung von ' . user_display_name($c) . ' entfernen? Das PDF wird gelöscht.')) ?>)">Entfernen</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="hint">Bereits verschickte Mails lassen sich nicht zurückholen – entfernt wird nur die Kopie hier.</p>
</div>

<h2 style="margin-top:28px">Person nachträglich erfassen</h2>
<form method="post">
    <?= csrf_field() ?>
    <?php render_people_picker($available, 'Weitere Einsatzkräfte'); ?>
    <?php render_signature_field($signer); ?>
    <button class="btn" type="submit" id="submit">Nachträglich erstellen</button>
    <p class="hint">Die neuen Bescheinigungen bekommen dieselben Einsatzzeiten. Für den Druck entsteht eine eigene Druck-PDF.</p>
</form>

<form method="post" class="card danger-zone">
    <?= csrf_field() ?>
    <h2>Einsatz löschen</h2>
    <p class="hint" style="margin-top:0">Löscht den Einsatz mit allen <?= count($certificates) ?> Bescheinigungen und allen PDFs. Das lässt sich nicht rückgängig machen.</p>
    <label class="check"><input type="checkbox" name="confirm" value="1" required> Ja, endgültig löschen</label>
    <button class="btn btn-danger" type="submit" name="delete_einsatz" value="1">Einsatz löschen</button>
</form>
<?php render_footer();
