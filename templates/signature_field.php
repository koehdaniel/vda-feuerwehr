<?php /** @var array $signer */ ?>
<div class="card">
    <h2>Unterschrift</h2>
    <label for="signature-pad">Unterschrift von <?= e($signer['vorname'] . ' ' . $signer['nachname']) ?>
        <span class="hint">(mit Finger oder Maus)</span></label>
    <canvas id="signature-pad" class="signature-pad"></canvas>
    <input type="hidden" name="signature" id="signature-data">
    <button class="btn btn-secondary btn-small" type="button" id="signature-clear">Feld leeren</button>
    <p class="hint">
        Unter der Unterschrift steht: <?= e($signer['vorname'] . ' ' . $signer['nachname']) ?><?= $signer['funktion'] !== '' ? ', ' . e($signer['funktion']) : '' ?>.
        <?php if ($signer['funktion'] === ''): ?>Deine Funktion (z. B. „Wehrführer“) kannst du unter „Personen“ hinterlegen.<?php endif; ?>
    </p>
</div>
<script src="assets/signature.js"></script>
