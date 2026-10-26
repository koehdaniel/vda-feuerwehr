<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$user = require_login();

if (is_post()) {
    csrf_check();

    if (isset($_POST['save_profile'])) {
        $cc = post_or_null('email_cc');
        $cc = $cc === null ? null : strtolower($cc);
        $errors = $cc !== null && !filter_var($cc, FILTER_VALIDATE_EMAIL) ? ['Die CC-Adresse ist ungültig.'] : [];
        // Arbeitgeber erst auswerten, wenn der Rest stimmt – sonst würde ein neuer AG trotz Fehler angelegt
        if (!$errors) {
            [$employerId, $errors] = employer_choice_from_post();
        }
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            // Formular mit den Eingaben erneut anzeigen statt umzuleiten
            $formError = true;
        } else {
            db_exec(
                'UPDATE users SET employer_id = ?, personalnummer = ?, want_print = ?, want_mail = ?, email_cc = ? WHERE id = ?',
                [$employerId, post_or_null('personalnummer'), post_bool('want_print'), post_bool('want_mail'), $cc, $user['id']]
            );
            flash('ok', 'Deine Daten wurden gespeichert.');
        }
    }

    if (isset($_POST['change_email'])) {
        $email = strtolower(post('email'));
        try {
            $error = request_email_change($user, $email);
        } catch (Throwable $ex) {
            $error = 'Die Bestätigungsmail konnte nicht verschickt werden. Bitte später erneut versuchen.';
            error_log('Bestätigungsmail an ' . $email . ' fehlgeschlagen: ' . $ex->getMessage());
        }
        if ($error) {
            flash('error', $error);
        } else {
            flash('ok', "Wir haben dir einen Bestätigungslink an $email geschickt. Erst nach dem Klick darauf gilt die neue Adresse.");
        }
    }

    if (isset($_POST['cancel_email'])) {
        db_exec('UPDATE users SET email_neu = NULL, email_token_hash = NULL, email_token_expires = NULL WHERE id = ?', [$user['id']]);
        flash('ok', 'Änderung der E-Mail-Adresse abgebrochen.');
    }

    if (isset($_POST['save_password'])) {
        $new = (string)($_POST['password_new'] ?? '');
        if (needs_current_password($user) && !password_verify((string)($_POST['password_current'] ?? ''), $user['password_hash'])) {
            flash('error', 'Das aktuelle Passwort stimmt nicht.');
        } elseif (strlen($new) < 8) {
            flash('error', 'Das neue Passwort muss mindestens 8 Zeichen lang sein.');
        } elseif ($new !== (string)($_POST['password_repeat'] ?? '')) {
            flash('error', 'Die beiden Passwörter stimmen nicht überein.');
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('ok', 'Passwort gespeichert.');
        }
    }

    if (empty($formError)) {
        redirect('profil.php');
    }
}

$certificates = user_certificates((int)$user['id']);

render_header('Meine Daten');
?>
<?php if ($certificates): ?>
    <div class="card">
        <h2>Meine letzten Bescheinigungen</h2>
        <ul class="list">
            <?php foreach ($certificates as $c): ?>
                <li>
                    <a href="bescheinigung.php?id=<?= (int)$c['id'] ?>" target="_blank">
                        <span class="main"><?= e(einsatz_label($c)) ?><span class="sub"><?= e($c['status_text']) ?></span></span>
                        <span class="badge">PDF</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" class="card">
    <?= csrf_field() ?>
    <h2><?= e($user['vorname'] . ' ' . $user['nachname']) ?></h2>
    <p class="hint" style="margin-top:-6px">Name falsch? Bitte an die Wehrführung wenden.</p>

    <label for="email">E-Mail <span class="hint">(für Login und Bescheinigungen per Mail)</span></label>
    <input type="email" id="email" name="email" value="<?= e($user['email'] ?? '') ?>" autocomplete="email" required>
    <?php if ($user['email_neu'] && $user['email_token_expires'] > date('Y-m-d H:i:s')): ?>
        <p class="flash flash-info" style="margin:10px 0 0">
            Neue Adresse <strong><?= e($user['email_neu']) ?></strong> wartet auf Bestätigung – bitte den Link in der Mail anklicken.
        </p>
        <button class="btn btn-secondary btn-small" type="submit" name="cancel_email" value="1" formnovalidate>Änderung abbrechen</button>
    <?php endif; ?>
    <button class="btn" type="submit" name="change_email" value="1">E-Mail ändern</button>
    <p class="hint">Die neue Adresse gilt erst, wenn du den Link in der Bestätigungsmail anklickst. Bis dahin bleibt die bisherige aktiv.</p>
</form>

<form method="post" class="card">
    <?= csrf_field() ?>
    <h2>Arbeitgeber</h2>
    <?php render_employer_select(is_post() ? post('employer_id') : (string)($user['employer_id'] ?? '')); ?>

    <label for="personalnummer">Personalnummer <span class="hint">(optional, steht dann auf der Bescheinigung)</span></label>
    <input type="text" id="personalnummer" name="personalnummer" value="<?= e(is_post() ? post('personalnummer') : $user['personalnummer']) ?>" maxlength="50">

    <h2 style="margin-top:20px">Wie möchtest du die Bescheinigung?</h2>
    <label class="check"><input type="checkbox" name="want_print" value="1" <?= $user['want_print'] ? 'checked' : '' ?>> Ausgedruckt</label>
    <label class="check"><input type="checkbox" name="want_mail" value="1" <?= $user['want_mail'] ? 'checked' : '' ?> <?= $user['email'] ? '' : 'disabled' ?>> Als PDF per Mail</label>
    <?php if (!$user['email']): ?>
        <p class="hint">Für den Mailversand muss die Wehrführung eine E-Mail-Adresse hinterlegen.</p>
    <?php endif; ?>
    <label for="email_cc">Kopie (CC) an <span class="hint">(optional, z. B. deine Arbeits-Mail – gilt nicht für den Login)</span></label>
    <input type="email" id="email_cc" name="email_cc" value="<?= e(is_post() ? post('email_cc') : $user['email_cc']) ?>" autocomplete="off">

    <button class="btn" type="submit" name="save_profile" value="1">Speichern</button>
</form>

<form method="post" class="card">
    <?= csrf_field() ?>
    <h2><?= $user['password_hash'] === null ? 'Passwort setzen' : 'Passwort ändern' ?></h2>
    <?php if (needs_current_password($user)): ?>
        <label for="password_current">Aktuelles Passwort</label>
        <input type="password" id="password_current" name="password_current" autocomplete="current-password" required>
    <?php endif; ?>
    <label for="password_new">Neues Passwort <span class="hint">(mind. 8 Zeichen)</span></label>
    <input type="password" id="password_new" name="password_new" autocomplete="new-password" minlength="8" required>
    <label for="password_repeat">Wiederholen</label>
    <input type="password" id="password_repeat" name="password_repeat" autocomplete="new-password" minlength="8" required>
    <button class="btn" type="submit" name="save_password" value="1">Passwort speichern</button>
</form>
<?php render_footer();
