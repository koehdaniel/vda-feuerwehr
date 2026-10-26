<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$employer = $id ? employer_by_id($id) : null;
if ($id && !$employer) {
    http_response_code(404);
    exit('Arbeitgeber nicht gefunden.');
}

if (is_post()) {
    csrf_check();

    if (isset($_POST['merge']) && $employer) {
        $target = employer_by_id((int)post('merge_into'));
        if (!$target || (int)$target['id'] === (int)$employer['id']) {
            flash('error', 'Bitte einen anderen Arbeitgeber zum Zusammenführen wählen.');
            redirect('arbeitgeber.php?id=' . $employer['id']);
        }
        $moved = db_exec('UPDATE users SET employer_id = ? WHERE employer_id = ?', [$target['id'], $employer['id']]);
        db_exec('DELETE FROM employers WHERE id = ?', [$employer['id']]);
        flash('ok', "„{$employer['name']}“ wurde mit „{$target['name']}“ zusammengeführt ($moved Personen übertragen).");
        redirect('arbeitgeber.php?id=' . $target['id']);
    }

    if (isset($_POST['delete']) && $employer) {
        $count = (int)db_one('SELECT COUNT(*) AS n FROM users WHERE employer_id = ?', [$employer['id']])['n'];
        if ($count > 0) {
            flash('error', "Löschen nicht möglich: $count Personen sind diesem Arbeitgeber zugeordnet. Stattdessen zusammenführen.");
            redirect('arbeitgeber.php?id=' . $employer['id']);
        }
        db_exec('DELETE FROM employers WHERE id = ?', [$employer['id']]);
        flash('ok', 'Arbeitgeber gelöscht.');
        redirect('arbeitgeber.php');
    }

    [$data, $errors] = employer_from_post();
    if ($errors) {
        foreach ($errors as $err) {
            flash('error', $err);
        }
        redirect('arbeitgeber.php?id=' . ($employer['id'] ?? 0));
    }
    if ($employer) {
        db_exec('UPDATE employers SET name = ?, zusatz = ?, strasse = ?, plz = ?, ort = ? WHERE id = ?', [...array_values($data), $employer['id']]);
        $savedId = (int)$employer['id'];
    } else {
        db_exec('INSERT INTO employers (name, zusatz, strasse, plz, ort) VALUES (?, ?, ?, ?, ?)', array_values($data));
        $savedId = (int)db()->lastInsertId();
    }
    flash('ok', 'Gespeichert.');
    redirect('arbeitgeber.php?id=' . $savedId);
}

// Übersicht
if ($id === null) {
    $employers = employers_all();
    render_header('Arbeitgeber');
    ?>
    <div class="toolbar">
        <span class="muted"><?= count($employers) ?> Arbeitgeber</span>
        <a class="btn btn-small" href="arbeitgeber.php?id=0">+ Neuer Arbeitgeber</a>
    </div>
    <div class="card" style="margin-top:12px">
        <ul class="list">
            <?php foreach ($employers as $emp): ?>
                <li>
                    <a href="arbeitgeber.php?id=<?= (int)$emp['id'] ?>">
                        <span class="main"><?= e($emp['name']) ?>
                            <span class="sub"><?= e(trim($emp['strasse'] . ', ' . $emp['plz'] . ' ' . $emp['ort'], ', ')) ?></span>
                        </span>
                        <span class="badge"><?= (int)$emp['personen'] ?> Pers.</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$employers): ?><p class="muted">Noch keine Arbeitgeber angelegt.</p><?php endif; ?>
    </div>
    <?php
    render_footer();
    exit;
}

// Bearbeiten / neu
$e = $employer ?? ['id' => 0, 'name' => '', 'zusatz' => '', 'strasse' => '', 'plz' => '', 'ort' => ''];
$members = $employer
    ? db_all('SELECT id, vorname, nachname FROM users WHERE employer_id = ? ORDER BY nachname, vorname', [$employer['id']])
    : [];

render_header($employer ? $employer['name'] : 'Neuer Arbeitgeber');
?>
<p><a href="arbeitgeber.php">← Alle Arbeitgeber</a></p>

<form method="post" class="card">
    <?= csrf_field() ?>
    <label for="name">Firma</label>
    <input type="text" id="name" name="name" value="<?= e($e['name']) ?>" required>
    <label for="zusatz">Zusatz <span class="hint">(optional, z. B. Personalabteilung)</span></label>
    <input type="text" id="zusatz" name="zusatz" value="<?= e($e['zusatz']) ?>">
    <label for="strasse">Straße und Hausnummer</label>
    <input type="text" id="strasse" name="strasse" value="<?= e($e['strasse']) ?>" required>
    <div class="row">
        <div class="narrow">
            <label for="plz">PLZ</label>
            <input type="text" id="plz" name="plz" value="<?= e($e['plz']) ?>" inputmode="numeric" maxlength="10" required>
        </div>
        <div>
            <label for="ort">Ort</label>
            <input type="text" id="ort" name="ort" value="<?= e($e['ort']) ?>" required>
        </div>
    </div>
    <?php if ($members): ?>
        <p class="hint">Änderungen gelten für alle <?= count($members) ?> zugeordneten Personen.</p>
    <?php endif; ?>
    <button class="btn" type="submit">Speichern</button>
</form>

<?php if ($employer): ?>
    <div class="card">
        <h2>Zugeordnete Personen (<?= count($members) ?>)</h2>
        <ul class="list">
            <?php foreach ($members as $m): ?>
                <li><a href="person.php?id=<?= (int)$m['id'] ?>"><span class="main"><?= e(user_display_name($m)) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$members): ?><p class="muted">Niemand.</p><?php endif; ?>
    </div>

    <form method="post" class="card">
        <?= csrf_field() ?>
        <h2>Doppelt angelegt?</h2>
        <p class="hint" style="margin-top:0">Alle Personen werden auf den gewählten Arbeitgeber übertragen, danach wird dieser Eintrag gelöscht.</p>
        <label for="merge_into">Zusammenführen mit</label>
        <select id="merge_into" name="merge_into" required>
            <option value="">– bitte wählen –</option>
            <?php foreach (employers_all() as $emp): ?>
                <?php if ((int)$emp['id'] !== (int)$employer['id']): ?>
                    <option value="<?= (int)$emp['id'] ?>"><?= e(employer_label($emp)) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary" type="submit" name="merge" value="1"
                onclick="return confirm('Wirklich zusammenführen? Dieser Eintrag wird danach gelöscht.')">Zusammenführen</button>
        <?php if (!$members): ?>
            <button class="btn btn-secondary" type="submit" name="delete" value="1" formnovalidate
                    onclick="return confirm('Arbeitgeber wirklich löschen?')">Arbeitgeber löschen</button>
        <?php endif; ?>
    </form>
<?php endif; ?>
<?php render_footer();
