/**
 * Compare a chosen PDF with the original's SHA-256, in the browser only.
 */
(function () {
    'use strict';
    document.querySelectorAll('.es-verif__file').forEach(function (box) {
        var input = box.querySelector('input[type="file"]');
        var message = box.querySelector('.es-verif__filemsg');
        if (!input || !message || !window.crypto || !window.crypto.subtle) {
            box.hidden = true;
            return;
        }
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) {
                return;
            }
            message.textContent = 'Revisando…';
            message.className = 'es-verif__filemsg';
            file.arrayBuffer().then(function (buffer) {
                return window.crypto.subtle.digest('SHA-256', buffer);
            }).then(function (digest) {
                var hex = Array.prototype.map.call(new Uint8Array(digest), function (b) {
                    return b.toString(16).padStart(2, '0');
                }).join('');
                var same = hex === box.getAttribute('data-sha');
                message.textContent = same
                    ? 'El archivo es idéntico al original emitido.'
                    : 'El archivo no coincide con el original: puede haber sido modificado, o es otra versión.';
                message.className = 'es-verif__filemsg es-verif__filemsg--' + (same ? 'good' : 'bad');
            }).catch(function () {
                message.textContent = 'No se pudo leer el archivo.';
            });
        });
    });
})();
