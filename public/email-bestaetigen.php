<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

// Bestätigungslink für eine neue E-Mail-Adresse – funktioniert auch ohne Anmeldung (z. B. auf einem anderen Gerät)
$error = confirm_email_change((string)($_GET['t'] ?? ''));
if ($error) {
    flash('error', $error);
} else {
    flash('ok', 'Deine neue E-Mail-Adresse ist bestätigt.' . (current_user() ? '' : ' Melde dich ab jetzt damit an.'));
}
redirect(current_user() ? 'profil.php' : 'login.php');
