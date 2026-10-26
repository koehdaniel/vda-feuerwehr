<?php /** @var string $title @var array|null $user @var array $flashes */ ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#b3141b">
    <title><?= e($title) ?> – AG-Bescheinigungen</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php">
        <span class="brand-mark" aria-hidden="true"></span>
        AG-Bescheinigungen
    </a>
    <?php if ($user): ?>
        <a class="logout" href="logout.php">Abmelden</a>
    <?php endif; ?>
</header>
<?php if ($user): ?>
    <nav class="tabs">
        <a href="profil.php">Meine Daten</a>
        <?php if ($user['is_admin']): ?>
            <a href="einsaetze.php">Einsätze</a>
            <a href="personen.php">Personen</a>
            <a href="arbeitgeber.php">Arbeitgeber</a>
            <a href="einstellungen.php">Einstellungen</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
<main>
    <h1><?= e($title) ?></h1>
    <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
