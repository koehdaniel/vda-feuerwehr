<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$person = $id ? user_by_id($id) : null;
if ($id && !$person) {
    http_response_code(404);
    exit('Person nicht gefunden.');
}
$isSelf = $person && (int)$person['id'] === (int)$admin['id'];

if (is_post()) {
    csrf_check();

    if (isset($_POST['send_link']) && $person) {
        try {
            if (send_magic_link($person)) {
                flash('ok', 'Login-Link an ' . $person['email'] . ' verschickt.');
            } else {
                flash('error', 'Kein Login-Link möglich: Person braucht eine E-Mail und muss aktiv sein.');
            }
        } catch (Throwable $ex) {
            flash('error', 'Mailversand fehlgeschlagen: ' . $ex->getMessage());
        }
        redirect('person.php?id=' . $person['id']);
    }

    $email = strtolower(post('email'));
    $data = [
        'nachname'   => post('nachname'),
        'vorname'    => post('vorname'),
        'email'      => $email === '' ? null : $email,
        'email_cc'   => post_or_null('email_cc') === null ? null : strtolower(post('email_cc')),
        'is_admin'   => $isSelf ? 1 : post_bool('is_admin'),
        'active'     => $isSelf ? 1 : post_bool('active'),
        'funktion'   => post('funktion'),
        'personalnummer' => post_or_null('personalnummer'),
        'want_print' => post_bool('want_print'),
        'want_mail'  => post_bool('want_mail'),
    ];

    $errors = [];
    if ($data['nachname'] === '' || $data['vorname'] === '') {
        $errors[] = 'Vor- und Nachname sind Pflicht.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Die E-Mail-Adresse ist ungültig.';
    }
    if ($email !== '') {
        $other = user_by_email($email);
        if ($other && (!$person || (int)$other['id'] !== (int)$person['id'])) {
            $errors[] = 'Diese E-Mail-Adresse ist schon bei ' . user_display_name($other) . ' hinterlegt.';
        }
    }
    if ($data['email_cc'] !== null && !filter_var($data['email_cc'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Die CC-Adresse ist ungültig.';
    }
    // Neues Passwort nur für andere Personen – das eigene ändert man unter „Meine Daten“
    $newPassword = $isSelf ? '' : (string)($_POST['password_new'] ?? '');
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    }
    // Arbeitgeber erst auswerten, wenn der Rest stimmt – sonst würde ein neuer AG trotz Fehler angelegt
    if (!$errors) {
        [$data['employer_id'], $errors] = employer_choice_from_post();
    }

    if ($errors) {
        foreach ($errors as $err) {
            flash('error', $err);
        }
        $person = array_merge($person ?? ['id' => 0, 'password_hash' => null], $data);
    } else {
        $columns = array_keys($data);
        if ($person) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $columns));
            db_exec("UPDATE users SET $set WHERE id = ?", [...array_values($data), $person['id']]);
            if (!$isSelf && post_bool('reset_password')) {
                db_exec('UPDATE users SET password_hash = NULL WHERE id = ?', [$person['id']]);
            }
            $savedId = (int)$person['id'];
        } else {
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            db_exec('INSERT INTO users (' . implode(', ', $columns) . ") VALUES ($placeholders)", array_values($data));
            $savedId = (int)db()->lastInsertId();
        }
        if ($newPassword !== '') {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $savedId]);
            flash('ok', 'Neues Passwort gesetzt.');
        }
        flash('ok', 'Gespeichert.');
        redirect('person.php?id=' . $savedId);
    }
}

$p = $person ?? [
    'id' => 0, 'nachname' => '', 'vorname' => '', 'email' => '', 'email_cc' => null, 'password_hash' => null,
    'is_admin' => 0, 'active' => 1, 'funktion' => '', 'employer_id' => null, 'personalnummer' => null, 'want_print' => 1, 'want_mail' => 0,
];

render_header($p['id'] ? user_display_name($p) : 'Neue Person');
?>
<p><a href="personen.php">← Alle Personen</a></p>

<form method="post" class="card">
    <?= csrf_field() ?>
    <h2>Person</h2>
    <div class="row">
        <div>
            <label for="vorname">Vorname</label>
            <input type="text" id="vorname" name="vorname" value="<?= e($p['vorname']) ?>" required>
        </div>
        <div>
            <label for="nachname">Nachname</label>
            <input type="text" id="nachname" name="nachname" value="<?= e($p['nachname']) ?>" required>
        </div>
    </div>
    <label for="email">E-Mail <span class="hint">(für Login und Mailversand)</span></label>
    <input type="email" id="email" name="email" value="<?= e($p['email'] ?? '') ?>">

    <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>> Aktiv</label>
    <label class="check"><input type="checkbox" name="is_admin" value="1" <?= $p['is_admin'] ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>> Admin</label>
    <label for="funktion">Funktion <span class="hint">(steht bei Admins unter der Unterschrift, z. B. „Wehrführer“)</span></label>
    <input type="text" id="funktion" name="funktion" value="<?= e($p['funktion']) ?>" maxlength="100">

    <h2 style="margin-top:20px">Arbeitgeber</h2>
    <?php render_employer_select(is_post() ? post('employer_id') : (string)($p['employer_id'] ?? '')); ?>
    <label for="personalnummer">Personalnummer <span class="hint">(optional)</span></label>
    <input type="text" id="personalnummer" name="personalnummer" value="<?= e($p['personalnummer']) ?>" maxlength="50">
    <label class="check"><input type="checkbox" name="want_print" value="1" <?= $p['want_print'] ? 'checked' : '' ?>> Ausdruck</label>
    <label class="check"><input type="checkbox" name="want_mail" value="1" <?= $p['want_mail'] ? 'checked' : '' ?>> Per Mail</label>
    <label for="email_cc">Kopie (CC) an <span class="hint">(optional, z. B. Arbeits-Mail – nicht für den Login)</span></label>
    <input type="email" id="email_cc" name="email_cc" value="<?= e($p['email_cc'] ?? '') ?>" autocomplete="off">

    <?php if (!$isSelf): ?>
        <h2 style="margin-top:20px">Passwort</h2>
        <p class="hint" style="margin-top:-6px">
            <?= $p['password_hash'] !== null ? 'Ein Passwort ist gesetzt.' : 'Noch kein Passwort gesetzt.' ?>
            Hier kannst du ein neues setzen – oder die Person tippt es selbst ein.
        </p>
        <label for="password_new">Neues Passwort <span class="hint">(mind. 8 Zeichen, leer lassen = unverändert)</span></label>
        <input type="password" id="password_new" name="password_new" autocomplete="new-password" minlength="8">
        <label class="check"><input type="checkbox" id="password_show"> Passwort anzeigen</label>
        <?php if ($p['id'] && $p['password_hash'] !== null): ?>
            <label class="check"><input type="checkbox" name="reset_password" value="1"> Passwort löschen (Anmeldung dann nur noch per Login-Link)</label>
        <?php endif; ?>
        <script>
            document.getElementById('password_show').addEventListener('change', function () {
                document.getElementById('password_new').type = this.checked ? 'text' : 'password';
            });
        </script>
    <?php endif; ?>

    <button class="btn" type="submit">Speichern</button>
</form>

<?php if ($p['id'] && $p['email'] && $p['active']): ?>
    <form method="post" class="card">
        <?= csrf_field() ?>
        <h2>Zugang</h2>
        <p class="muted" style="margin:0">Per Login-Link kann sich die Person ohne Passwort anmelden und ihre Daten selbst pflegen.</p>
        <button class="btn btn-secondary" type="submit" name="send_link" value="1">Login-Link an <?= e($p['email']) ?> senden</button>
    </form>
<?php endif; ?>
<?php render_footer();
