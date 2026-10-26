<?php /** @var array $employers @var string $selected  ('' = keiner, 'new' = neu, sonst ID) */ ?>
<label for="employer_id">Arbeitgeber</label>
<select id="employer_id" name="employer_id">
    <option value="">– kein Arbeitgeber –</option>
    <?php foreach ($employers as $emp): ?>
        <option value="<?= (int)$emp['id'] ?>" <?= $selected === (string)$emp['id'] ? 'selected' : '' ?>><?= e(employer_label($emp)) ?></option>
    <?php endforeach; ?>
    <option value="new" <?= $selected === 'new' ? 'selected' : '' ?>>+ Neuen Arbeitgeber anlegen …</option>
</select>
<p class="hint">Nicht in der Liste? Ganz unten „Neuen Arbeitgeber anlegen“ wählen.</p>

<fieldset id="new-employer" class="subform" <?= $selected === 'new' ? '' : 'hidden' ?>>
    <legend>Neuer Arbeitgeber</legend>
    <label for="new_name">Firma</label>
    <input type="text" id="new_name" name="new_name" value="<?= e(post('new_name')) ?>" autocomplete="organization">
    <label for="new_zusatz">Zusatz <span class="hint">(optional, z. B. Personalabteilung)</span></label>
    <input type="text" id="new_zusatz" name="new_zusatz" value="<?= e(post('new_zusatz')) ?>">
    <label for="new_strasse">Straße und Hausnummer</label>
    <input type="text" id="new_strasse" name="new_strasse" value="<?= e(post('new_strasse')) ?>">
    <div class="row">
        <div class="narrow">
            <label for="new_plz">PLZ</label>
            <input type="text" id="new_plz" name="new_plz" value="<?= e(post('new_plz')) ?>" inputmode="numeric" maxlength="10">
        </div>
        <div>
            <label for="new_ort">Ort</label>
            <input type="text" id="new_ort" name="new_ort" value="<?= e(post('new_ort')) ?>">
        </div>
    </div>
</fieldset>
<script>
    (function () {
        var select = document.getElementById('employer_id');
        var box = document.getElementById('new-employer');
        select.addEventListener('change', function () {
            box.hidden = select.value !== 'new';
            if (!box.hidden) document.getElementById('new_name').focus();
        });
    })();
</script>
