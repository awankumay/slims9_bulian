/*
 * bulian-kampus: halaman pilih ruang buku tamu (parts/kiosk/rooms.php).
 * - Pilihan ruang + layout disimpan per perangkat di localStorage bila
 *   "Ingat pilihan" dicentang; kunjungan berikutnya ke ?p=visitor tanpa ruang
 *   langsung diarahkan ke kiosk ruang itu. ?pick=1 selalu menampilkan halaman ini.
 * - Pilih layout tanpa memuat ulang halaman; tautan ruang ikut diperbarui.
 */
(function () {
    var conf = window.BK_ROOMS || {};
    var root = document.getElementById('bk-rooms');
    if (!root) return;

    function href(room, layout) {
        return 'index.php?p=visitor&' + (room ? 'room=' + encodeURIComponent(room) : 'noroom=1') + '&layout=' + layout;
    }
    function load() {
        try { return JSON.parse(localStorage.getItem(conf.storeKey) || 'null'); } catch (e) { return null; }
    }
    function save(value) {
        try {
            if (value) localStorage.setItem(conf.storeKey, JSON.stringify(value));
            else localStorage.removeItem(conf.storeKey);
        } catch (e) {}
    }

    // perangkat sudah diatur: langsung ke kiosknya (kecuali admin minta pilih ulang)
    var saved = load();
    if (root.getAttribute('data-pick') !== '1' && saved && typeof saved.room === 'string' &&
        (saved.room === '' || conf.rooms.indexOf(saved.room) !== -1) && conf.layouts.indexOf(saved.layout) !== -1) {
        location.replace(href(saved.room, saved.layout));
        return;
    }

    var active = root.querySelector('.bk-rooms__layout[aria-current]');
    var current = active ? active.getAttribute('data-layout') : conf.layouts[0];
    var remember = document.getElementById('bk-rooms-remember');
    var targets = root.querySelectorAll('[data-room]');

    function setLayout(layout) {
        current = layout;
        root.querySelectorAll('.bk-rooms__layout').forEach(function (a) {
            if (a.getAttribute('data-layout') === layout) a.setAttribute('aria-current', 'true');
            else a.removeAttribute('aria-current');
        });
        targets.forEach(function (a) { a.setAttribute('href', href(a.getAttribute('data-room'), layout)); });
    }

    root.querySelectorAll('.bk-rooms__layout').forEach(function (a) {
        a.addEventListener('click', function (ev) {
            ev.preventDefault();
            setLayout(a.getAttribute('data-layout'));
        });
    });

    targets.forEach(function (a) {
        a.addEventListener('click', function () {
            save(remember && remember.checked ? { room: a.getAttribute('data-room'), layout: current } : null);
        });
    });

    // pencarian ruang (hanya tampil bila ruangnya banyak)
    var q = document.getElementById('bk-rooms-q');
    var empty = document.getElementById('bk-rooms-empty');
    if (q) {
        q.addEventListener('input', function () {
            var term = q.value.trim().toLowerCase();
            var shown = 0;
            root.querySelectorAll('.bk-rooms__grid > li').forEach(function (li) {
                var match = !term || li.textContent.toLowerCase().indexOf(term) !== -1;
                li.hidden = !match;
                if (match) shown++;
            });
            empty.hidden = shown > 0;
        });
    }
})();
