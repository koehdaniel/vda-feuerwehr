<?php /** @var array $people @var string $title */ ?>
<div class="card">
    <div class="toolbar">
        <h2 style="margin:0"><?= e($title) ?></h2>
        <span class="muted" id="selected-count">0 ausgewählt</span>
    </div>
    <?php if (!$people): ?>
        <p class="muted">Niemand verfügbar.</p>
    <?php endif; ?>
    <div class="search-bar">
        <input type="search" id="search" placeholder="Name suchen …" autocomplete="off" enterkeyhint="search">
        <div class="search-actions">
            <button type="button" class="btn btn-secondary btn-small" id="only-checked" aria-pressed="false">Nur angehakte anzeigen</button>
            <button type="button" class="btn btn-secondary btn-small" id="toggle-all">Alle an/aus</button>
        </div>
    </div>
    <p class="hint" id="search-empty" hidden>Niemand gefunden.</p>
    <ul class="list" id="people">
        <?php $postedIds = array_map('intval', (array)($_POST['ids'] ?? [])); ?>
        <?php foreach ($people as $p): ?>
            <li data-search="<?= e($p['vorname'] . ' ' . $p['nachname']) ?>"><label class="check">
                <input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $postedIds, true) ? 'checked' : '' ?>>
                <span class="main"><?= e(user_display_name($p)) ?>
                    <?php if ($p['ag_name'] !== ''): ?>
                        <span class="sub"><?= e($p['ag_name']) ?></span>
                    <?php else: ?>
                        <span class="sub sub-warn">kein Arbeitgeber hinterlegt</span>
                    <?php endif; ?>
                </span>
                <?php if ($p['want_print']): ?><span class="badge">Druck</span><?php endif; ?>
                <?php if ($p['want_mail']): ?><span class="badge <?= $p['email'] ? '' : 'badge-warn' ?>">Mail</span><?php endif; ?>
            </label></li>
        <?php endforeach; ?>
    </ul>
    <p class="hint">Personen ohne Arbeitgeber sind rot markiert – auf ihrer Bescheinigung bleibt die Anschrift zum Ausfüllen von Hand frei.</p>
</div>
<script src="assets/people-picker.js"></script>
