<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

require_admin();

if (is_post()) {
    csrf_check();

    if (isset($_POST['save_texts'])) {
        $values = [];
        foreach (settings_fields() as $fields) {
            foreach (array_keys($fields) as $key) {
                if (isset($_POST[$key])) {
                    $values[$key] = (string)$_POST[$key];
                }
            }
        }
        settings_save($values);
        flash('ok', 'Texte gespeichert.');
    }

    if (isset($_POST['upload_logo'])) {
        $upload = $_FILES['logo'] ?? null;
        $info = $upload && $upload['error'] === UPLOAD_ERR_OK && $upload['size'] <= 2_000_000
            ? getimagesize($upload['tmp_name'])
            : false;
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$info['mime'] ?? ''] ?? null;
        if (!$ext) {
            flash('error', 'Bitte ein Bild im Format JPG oder PNG (max. 2 MB) auswählen.');
        } else {
            $old = logo_file();
            $name = 'logo-' . date('YmdHis') . '.' . $ext;
            move_uploaded_file($upload['tmp_name'], STORAGE_DIR . '/' . $name);
            settings_save(['logo' => $name]);
            if ($old) {
                unlink($old);
            }
            flash('ok', 'Logo gespeichert.');
        }
    }

    if (isset($_POST['delete_logo'])) {
        $old = logo_file();
        settings_save(['logo' => '']);
        if ($old) {
            unlink($old);
        }
        flash('ok', 'Logo entfernt.');
    }

    redirect('einstellungen.php');
}

$values = settings_all();
render_header('Einstellungen');
?>
<form method="post" class="card" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <h2>Logo</h2>
    <?php if (logo_file()): ?>
        <img class="logo-current" src="<?= e(image_data_uri(logo_file())) ?>" alt="Aktuelles Logo">
    <?php else: ?>
        <p class="hint" style="margin-top:0">Noch kein Logo hinterlegt – die Bescheinigung erscheint dann ohne Logo.</p>
    <?php endif; ?>
    <label for="logo">Neues Logo <span class="hint">(JPG oder PNG, max. 2 MB)</span></label>
    <input type="file" id="logo" name="logo" accept="image/jpeg,image/png">
    <button class="btn" type="submit" name="upload_logo" value="1">Logo hochladen</button>
    <?php if (logo_file()): ?>
        <button class="btn btn-secondary" type="submit" name="delete_logo" value="1"
                onclick="return confirm('Logo wirklich entfernen?')">Logo entfernen</button>
    <?php endif; ?>
</form>

<form method="post">
    <?= csrf_field() ?>
    <?php foreach (settings_fields() as $section => $fields): ?>
        <div class="card">
            <h2><?= e($section) ?></h2>
            <?php foreach ($fields as $key => [$label, $type]): ?>
                <label for="<?= e($key) ?>"><?= e($label) ?></label>
                <?php if ($type === 'html'): ?>
                    <p class="hint" style="margin-top:0">Platzhalter antippen, um ihn an der Cursorposition einzufügen:</p>
                    <div class="chips">
                        <?php foreach (certificate_placeholders() as $ph => $desc): ?>
                            <button type="button" class="chip" data-insert="<?= e($ph) ?>" title="<?= e($desc) ?>"><?= e($ph) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <details class="hint">
                        <summary>Was bedeuten die Platzhalter?</summary>
                        <dl class="placeholders">
                            <?php foreach (certificate_placeholders() as $ph => $desc): ?>
                                <dt><?= e($ph) ?></dt><dd><?= e($desc) ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    </details>
                    <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" class="html-editor" rows="20"><?= e($values[$key]) ?></textarea>
                    <a class="btn btn-secondary btn-small" href="vorschau.php" target="_blank">Vorschau-PDF (gespeicherter Stand)</a>
                <?php elseif ($type === 'textarea'): ?>
                    <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" rows="<?= max(3, substr_count($values[$key], "\n") + 2) ?>"><?= e($values[$key]) ?></textarea>
                <?php else: ?>
                    <input type="text" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <button class="btn" type="submit" name="save_texts" value="1">Texte speichern</button>
</form>

<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.0/tinymce.min.js"></script>
<script>
    tinymce.init({
        selector: 'textarea.html-editor',
        license_key: 'gpl',
        language: 'de',
        language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@24.12.9/langs7/de.js',
        menubar: false,
        branding: false,
        promotion: false,
        plugins: 'table lists code',
        toolbar: 'undo redo | bold italic underline | fontsize forecolor | alignleft aligncenter alignright | table | removeformat code',
        font_size_formats: '8pt 10pt 12pt 14pt 15pt 18pt 24pt',
        content_style: <?= json_encode(certificate_css() . ' body { margin: 12px; } td { border: 1px dashed #ddd; }') ?>,
        entity_encoding: 'raw',
        convert_urls: false,
        height: 620,
        table_default_styles: { width: '100%' }
    });

    document.querySelectorAll('[data-insert]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var editor = tinymce.get('vorlage');
            if (editor) {
                editor.insertContent(btn.dataset.insert);
                editor.focus();
            }
        });
    });
</script>
<?php render_footer();
