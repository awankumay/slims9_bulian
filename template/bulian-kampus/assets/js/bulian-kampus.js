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

/*
 * bulian-kampus: kepala halaman lengket (sticky).
 * Seluruh kepala halaman memakai position: sticky dengan top negatif sebesar
 * tinggi strip + identitas, sehingga saat digulir hanya menu yang tertinggal.
 * Tingginya diukur ulang bila ukuran teks, lebar layar, atau logo berubah.
 */
(function () {
    var root = document.documentElement;
    var header = document.getElementById('bk-header');
    var nav = header && header.querySelector('.bk-nav');
    if (!header || !nav) return;

    var offset = 0;
    var ticking = false;

    function measure() {
        offset = Math.max(0, header.offsetHeight - nav.offsetHeight);
        root.style.setProperty('--bk-head-off', offset + 'px');
        root.style.setProperty('--bk-nav-h', nav.offsetHeight + 'px');
        update();
    }

    function update() {
        ticking = false;
        header.classList.toggle('is-stuck', window.pageYOffset > offset + 1);
    }

    window.addEventListener('scroll', function () {
        if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
    }, { passive: true });

    if (window.ResizeObserver) {
        new ResizeObserver(measure).observe(header);
    } else {
        window.addEventListener('resize', measure);
        window.addEventListener('load', measure);
    }
    root.classList.add('bk-js');
    measure();
})();

/*
 * bulian-kampus: area anggota, tandai tanggal harus kembali yang dekat (<= 3 hari)
 * atau lewat pada tabel pinjaman saat ini.
 */
(function () {
    var table = document.querySelector('.bk-member table.memberLoanList');
    if (!table || !/[?&]p=member(&|$)/.test(location.search) || /[?&]sec=(?!current_loan)/.test(location.search)) return;
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    Array.prototype.forEach.call(table.rows, function (row) {
        var cell = row.cells[row.cells.length - 1];
        var m = cell && /^(\d{4})-(\d{2})-(\d{2})$/.exec(cell.textContent.trim());
        if (!m) return;
        var days = (new Date(+m[1], m[2] - 1, +m[3]) - today) / 86400000;
        cell.classList.add('bk-due');
        if (days < 0) cell.classList.add('bk-due--late');
        else if (days <= 3) cell.classList.add('bk-due--soon');
    });
})();
