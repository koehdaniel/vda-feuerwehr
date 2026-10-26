<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

require_admin();
$people = users_all();

render_header('Personen');
?>
<div class="toolbar">
    <span class="muted"><?= count($people) ?> Personen</span>
    <a class="btn btn-small" href="person.php">+ Neue Person</a>
</div>
<div class="card" style="margin-top:12px">
    <div class="search-bar" style="margin-top:-8px">
        <input type="search" id="search" placeholder="Name oder Arbeitgeber suchen …" autocomplete="off">
    </div>
    <ul class="list" id="people">
        <?php foreach ($people as $p): ?>
            <li>
                <a href="person.php?id=<?= (int)$p['id'] ?>">
                    <span class="main">
                        <?= e(user_display_name($p)) ?>
                        <?php if ($p['is_admin']): ?><span class="badge badge-red">Admin</span><?php endif; ?>
                        <?php if (!$p['active']): ?><span class="badge">inaktiv</span><?php endif; ?>
                        <span class="sub"><?= $p['ag_name'] !== '' ? e($p['ag_name']) : 'kein Arbeitgeber' ?></span>
                    </span>
                    <?php if ($p['ag_name'] !== ''): ?>
                        <?php if ($p['want_print']): ?><span class="badge">Druck</span><?php endif; ?>
                        <?php if ($p['want_mail']): ?><span class="badge">Mail</span><?php endif; ?>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (!$people): ?><p class="muted">Noch keine Personen angelegt.</p><?php endif; ?>
</div>
<script>
    document.getElementById('search').addEventListener('input', function () {
        var words = this.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        document.querySelectorAll('#people li').forEach(function (li) {
            var text = li.textContent.toLowerCase();
            li.hidden = !words.every(function (w) { return text.indexOf(w) !== -1; });
        });
    });
</script>
<?php render_footer();
