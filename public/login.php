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
                send_magic_link($user, post_bool('remember') === 1);
            } catch (Throwable $ex) {
                error_log('Login-Link an ' . $email . ' fehlgeschlagen: ' . $ex->getMessage());
            }
        }
        // Immer dieselbe Meldung, damit nicht erkennbar ist, welche Adressen existieren
        flash('info', 'Falls die Adresse bei uns hinterlegt ist, wurde ein Login-Link verschickt. Bitte Postfach prüfen.');
        redirect('login.php');
    }

    $user = attempt_password_login($email, (string)($_POST['password'] ?? ''));
    if ($user) {
        login_user((int)$user['id']);
        if (post_bool('remember')) {
            remember_user((int)$user['id']);
        }
        redirect('index.php');
    }
    flash('error', 'Anmeldung fehlgeschlagen. Kein Passwort oder vergessen? Dann „Login-Link per Mail“ nutzen.');
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

    <?php if (remember_enabled()): ?>
        <label class="check"><input type="checkbox" name="remember" value="1" checked> Angemeldet bleiben</label>
    <?php endif; ?>

    <button class="btn" type="submit">Anmelden</button>
    <button class="btn btn-secondary" type="submit" name="magic" value="1" formnovalidate>Login-Link per Mail</button>
    <p class="hint">Statt mit Passwort kannst du dich jederzeit über einen Login-Link per Mail anmelden – nur E-Mail eintragen und den Knopf tippen.</p>
</form>
<?php render_footer();
