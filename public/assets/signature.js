// Unterschriftsfeld: zeichnen per Finger, Stift oder Maus; wird beim Absenden als JPEG (weißer Hintergrund) mitgeschickt.
(function () {
    var canvas = document.getElementById('signature-pad');
    var form = canvas.closest('form');
    var output = document.getElementById('signature-data');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var hasInk = false;
    var last = null;

    function setup() {
        var ratio = Math.max(window.devicePixelRatio || 1, 2);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#1a3a9c';
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        hasInk = false;
    }

    function point(ev) {
        var r = canvas.getBoundingClientRect();
        return { x: ev.clientX - r.left, y: ev.clientY - r.top };
    }

    canvas.addEventListener('pointerdown', function (ev) {
        drawing = true;
        last = point(ev);
        canvas.setPointerCapture(ev.pointerId);
        ev.preventDefault();
    });
    canvas.addEventListener('pointermove', function (ev) {
        if (!drawing) return;
        var p = point(ev);
        ctx.beginPath();
        ctx.moveTo(last.x, last.y);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        last = p;
        hasInk = true;
        ev.preventDefault();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (type) {
        canvas.addEventListener(type, function () { drawing = false; });
    });

    document.getElementById('signature-clear').addEventListener('click', setup);

    form.addEventListener('submit', function (ev) {
        if (!hasInk) {
            ev.preventDefault();
            alert('Bitte zuerst im Feld unterschreiben.');
            return;
        }
        output.value = cropped().toDataURL('image/jpeg', 0.92);
    });

    // Schneidet den leeren Rand ab, damit die Unterschrift im PDF in voller Größe erscheint.
    function cropped() {
        var w = canvas.width, h = canvas.height;
        var data = ctx.getImageData(0, 0, w, h).data;
        var minX = w, minY = h, maxX = -1, maxY = -1;
        for (var y = 0; y < h; y++) {
            for (var x = 0; x < w; x++) {
                var i = (y * w + x) * 4;
                if (data[i] < 200 || data[i + 1] < 200 || data[i + 2] < 200) {
                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                }
            }
        }
        if (maxX < 0) return canvas;
        var pad = 10;
        minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
        maxX = Math.min(w - 1, maxX + pad); maxY = Math.min(h - 1, maxY + pad);
        var out = document.createElement('canvas');
        out.width = maxX - minX + 1;
        out.height = maxY - minY + 1;
        out.getContext('2d').drawImage(canvas, minX, minY, out.width, out.height, 0, 0, out.width, out.height);
        return out;
    }

    setup();
    window.addEventListener('resize', function () { if (!hasInk) setup(); });
})();
