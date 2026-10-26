// Formatiert Felder mit data-format="date" (TT.MM.JJJJ) bzw. "time" (HH:MM) beim Verlassen.
// Die eigentliche Prüfung macht der Server (parse_date_de / parse_time in src/helpers.php).
(function () {
    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function formatDate(s) {
        var m = s.match(/^(\d{1,2})[.\-\/ ](\d{1,2})[.\-\/ ](\d{2}|\d{4})$/) || s.match(/^(\d{2})(\d{2})(\d{2}|\d{4})$/);
        if (!m) return s;
        var y = parseInt(m[3], 10);
        return pad(parseInt(m[1], 10)) + '.' + pad(parseInt(m[2], 10)) + '.' + (y < 100 ? 2000 + y : y);
    }

    function formatTime(s) {
        var m = s.match(/^(\d{1,2})(?:[:.,h ]?(\d{2}))?$/);
        if (!m) return s;
        return pad(parseInt(m[1], 10)) + ':' + (m[2] || '00');
    }

    document.querySelectorAll('input[data-format]').forEach(function (input) {
        input.addEventListener('blur', function () {
            var v = input.value.trim();
            if (v === '') return;
            input.value = input.dataset.format === 'date' ? formatDate(v) : formatTime(v);
        });
    });
})();
