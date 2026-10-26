// Personenauswahl mit Suche: filtert die Liste, zählt die Auswahl und zeigt sie auf dem Absende-Knopf (#submit) an.
document.addEventListener('DOMContentLoaded', function () {
    var search = document.getElementById('search');
    var submit = document.getElementById('submit');
    var submitLabel = submit ? submit.textContent : '';
    var items = Array.prototype.slice.call(document.querySelectorAll('#people li'));
    var boxes = items.map(function (li) { return li.querySelector('input'); });

    // "Müller" wird auch mit "muller" oder "mueller" gefunden
    function norm(s) {
        s = s.toLowerCase();
        return s + ' ' + s.normalize('NFD').replace(/[̀-ͯ]/g, '')
            + ' ' + s.replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss');
    }
    items.forEach(function (li) { li.dataset.search = norm(li.dataset.search); });

    function visible() {
        return items.filter(function (li) { return !li.hidden; });
    }

    function update() {
        var count = boxes.filter(function (b) { return b.checked; }).length;
        document.getElementById('selected-count').textContent = count + ' ausgewählt';
        if (submit) submit.textContent = submitLabel + (count ? ' (' + count + ')' : '');
    }

    var onlyChecked = document.getElementById('only-checked');
    var onlyCheckedLabel = onlyChecked.textContent;

    function filter() {
        var words = search.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
        var checkedOnly = onlyChecked.getAttribute('aria-pressed') === 'true';
        items.forEach(function (li) {
            var match = words.every(function (w) { return li.dataset.search.indexOf(w) !== -1; });
            li.hidden = !match || (checkedOnly && !li.querySelector('input').checked);
        });
        document.getElementById('search-empty').hidden = visible().length > 0;
    }

    onlyChecked.addEventListener('click', function () {
        var on = onlyChecked.getAttribute('aria-pressed') !== 'true';
        onlyChecked.setAttribute('aria-pressed', on ? 'true' : 'false');
        onlyChecked.textContent = on ? 'Alle anzeigen' : onlyCheckedLabel;
        filter();
    });

    search.addEventListener('input', filter);
    // Enter im Suchfeld soll nicht das Formular abschicken, sondern einen eindeutigen Treffer abhaken
    search.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        ev.preventDefault();
        var hits = visible();
        if (hits.length === 1) {
            var box = hits[0].querySelector('input');
            box.checked = true;
            box.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    document.getElementById('people').addEventListener('change', function (ev) {
        update();
        // Nach dem Abhaken eines Suchtreffers Suche leeren, damit direkt der nächste Name getippt werden kann
        if (ev.target.checked && search.value !== '') {
            search.value = '';
            filter();
            search.focus();
        }
        // Im Modus "Nur angehakte" verschwindet eine Zeile sofort, wenn der Haken entfernt wird
        if (!ev.target.checked && onlyChecked.getAttribute('aria-pressed') === 'true') {
            filter();
        }
    });

    document.getElementById('toggle-all').addEventListener('click', function () {
        var shown = visible().map(function (li) { return li.querySelector('input'); });
        var allOn = shown.every(function (b) { return b.checked; });
        shown.forEach(function (b) { b.checked = !allOn; });
        update();
    });

    update();
});
