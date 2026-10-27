/*
 * bulian-kampus: pengaturan tampilan (ukuran teks dan kontras tinggi).
 * Pilihan disimpan di localStorage per peramban dan diterapkan sebelum
 * halaman digambar oleh skrip kecil di parts/header.php.
 */
(function () {
    var root = document.documentElement;

    function store(key, value) {
        try {
            if (value) localStorage.setItem(key, value);
            else localStorage.removeItem(key);
        } catch (e) {}
    }

    function syncButtons() {
        var size = root.getAttribute('data-bk-size') || '';
        document.querySelectorAll('.bk-a11y [data-bk-size]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', btn.getAttribute('data-bk-size') === size ? 'true' : 'false');
        });
        var contrast = root.getAttribute('data-bk-contrast') === '1';
        document.querySelectorAll('.bk-a11y [data-bk-contrast]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', contrast ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function (ev) {
        var sizeBtn = ev.target.closest && ev.target.closest('.bk-a11y [data-bk-size]');
        if (sizeBtn) {
            var size = sizeBtn.getAttribute('data-bk-size');
            if (size) root.setAttribute('data-bk-size', size);
            else root.removeAttribute('data-bk-size');
            store('bk-text-size', size);
            syncButtons();
            return;
        }
        var contrastBtn = ev.target.closest && ev.target.closest('.bk-a11y [data-bk-contrast]');
        if (contrastBtn) {
            var on = root.getAttribute('data-bk-contrast') !== '1';
            if (on) root.setAttribute('data-bk-contrast', '1');
            else root.removeAttribute('data-bk-contrast');
            store('bk-contrast', on ? '1' : '');
            syncButtons();
        }
    });

    syncButtons();
})();
