<?php
// Kopieren nach config/config.php und anpassen. config.php wird NICHT eingecheckt.
return [
    // Öffentliche Adresse der Anwendung, ohne abschließenden Slash (für Login-Links in Mails)
    'base_url' => 'https://vda.example.de',

    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=vda;charset=utf8mb4',
        'user' => 'vda',
        'pass' => '',
    ],

    'smtp' => [
        'host'      => 'smtp.example.de',
        'port'      => 587,
        'secure'    => 'tls', // 'tls' (STARTTLS), 'ssl' (SMTPS, meist Port 465) oder '' (unverschlüsselt)
        'user'      => 'vda@example.de',
        'pass'      => '',
        'from'      => 'vda@example.de',
        'from_name' => 'Freiwillige Feuerwehr Musterstadt',
    ],

    // Nur lokal: Mails als .eml-Dateien in dieses Verzeichnis schreiben statt per SMTP senden. In Produktion leer lassen.
    'mail_dir' => '',

    // Geheimer Schlüssel für „Angemeldet bleiben“ (mind. 32 Zeichen, zufällig). Erzeugen z. B. mit:
    //   php -r "echo bin2hex(random_bytes(32));"
    // Wird er geändert, sind alle „Angemeldet bleiben“-Anmeldungen ungültig. Leer = Funktion aus.
    'secret' => '',

    // Gültigkeit der Login-Links in Minuten
    'magic_link_minutes' => 30,
];
