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

    // Gültigkeit der Login-Links in Minuten
    'magic_link_minutes' => 30,
];
