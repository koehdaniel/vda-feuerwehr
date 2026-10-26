<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

// Startseite: Admins landen beim neuen Einsatz, alle anderen bei ihren Daten
$user = require_login();
redirect($user['is_admin'] ? 'einsatz.php' : 'profil.php');
