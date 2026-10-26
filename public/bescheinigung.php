<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

// Einzelne Bescheinigung herunterladen – für die eigene Person oder als Admin
$user = require_login();

$cert = db_one('SELECT * FROM einsatz_personen WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$cert || ((int)$cert['user_id'] !== (int)$user['id'] && !$user['is_admin'])) {
    http_response_code(404);
    exit('Bescheinigung nicht gefunden.');
}

send_stored_pdf((int)$cert['einsatz_id'], $cert['pdf_datei']);
