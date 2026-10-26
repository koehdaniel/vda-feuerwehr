<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$email = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email'));

    if (isset($_POST['magic'])) {
        $user = $email !== '' ? user_by_email($email) : null;
        if ($user) {
            try {
                send_magic_link($user);
            } catch (Throwable $ex) {
                error_log('Login-Link an ' . $email . ' fehlgeschlagen: ' . $ex->getMessage());
            }
        }
        // Immer dieselbe Meldung, damit nicht erkennbar ist, welche Adressen existieren
        flash('info', 'Falls für diese Adresse noch kein Passwort gesetzt ist, wurde ein Login-Link verschickt. Bitte Postfach prüfen.');
        redirect('login.php');
    }

    $user = attempt_password_login($email, (string)($_POST['password'] ?? ''));
    if ($user) {
        login_user((int)$user['id']);
        redirect('index.php');
    }
    flash('error', 'Anmeldung fehlgeschlagen. Noch kein Passwort? Dann „Login-Link per Mail“ nutzen.');
    redirect('login.php');
}

render_header('Anmelden');
?>
<form method="post" class="card">
    <?= csrf_field() ?>
    <label for="email">E-Mail</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>

    <label for="password">Passwort</label>
    <input type="password" id="password" name="password" autocomplete="current-password">

    <button class="btn" type="submit">Anmelden</button>
    <button class="btn btn-secondary" type="submit" name="magic" value="1" formnovalidate>Login-Link per Mail</button>
    <p class="hint">Den Login-Link gibt es nur, solange noch kein Passwort gesetzt ist. Passwort vergessen? Wende dich an die Wehrführung.</p>
</form>
<?php render_footer();
