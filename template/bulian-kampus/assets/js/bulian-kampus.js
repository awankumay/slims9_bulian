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

/*
 * bulian-kampus: menu anggota di navbar. Di layar sempit tombol keranjang langsung
 * membuka halaman keranjang; di layar lebar panel pratinjau dibuka, dan bila jumlah
 * berubah lewat "Tambah ke keranjang" (AJAX) daftar lama diganti catatan singkat.
 */
(function () {
    var cart = document.querySelector('[data-bk-cart]');
    if (!cart) return;
    cart.addEventListener('click', function (e) {
        if (window.matchMedia('(max-width: 991px)').matches) {
            e.stopImmediatePropagation();
            location.href = cart.getAttribute('href');
        }
    }, true);
    if (window.jQuery) {
        window.jQuery(cart.parentNode).on('show.bs.dropdown', function () {
            var badge = document.getElementById('count-basket');
            var stale = badge && badge.textContent.trim() !== badge.getAttribute('data-rendered');
            var pop = cart.parentNode;
            var list = pop.querySelector('[data-bk-cart-list]');
            if (list) list.hidden = stale;
            pop.querySelector('[data-bk-cart-empty]').hidden = stale || !!list;
            pop.querySelector('[data-bk-cart-stale]').hidden = !stale;
            if (stale) pop.querySelector('[data-bk-cart-count]').textContent = badge.textContent.trim();
        });
    }
})();

/*
 * bulian-kampus: area anggota. Pesan dari member.inc.php (ubah kata sandi, hasil
 * pesanan lewat surel) ada di #info yang disembunyikan; tampilkan di atas tab.
 */
(function () {
    var flash = document.getElementById('bk-member-flash');
    var info = document.querySelector('.bk-member #info');
    if (!flash || !info) return;
    Array.prototype.forEach.call(info.querySelectorAll('.alert, span[style]'), function (el) {
        var text = el.textContent.trim();
        if (!text) return;
        var kind = 'info';
        if (/alert-danger|red/i.test(el.className + ' ' + (el.getAttribute('style') || ''))) kind = 'danger';
        else if (/alert-success|28a745/i.test(el.className + ' ' + (el.getAttribute('style') || ''))) kind = 'success';
        var box = document.createElement('div');
        box.className = 'bk-alert bk-alert--' + kind;
        box.setAttribute('role', kind === 'danger' ? 'alert' : 'status');
        box.textContent = text;
        flash.appendChild(box);
    });
})();

// tab aktif digeser ke tampilan bila deretan tab lebih lebar dari layar (ponsel)
(function () {
    var tabs = document.querySelector('.bk-tabs');
    var on = tabs && tabs.querySelector('.is-active');
    if (on && tabs.scrollWidth > tabs.clientWidth) {
        tabs.scrollLeft = Math.max(0, on.offsetLeft - tabs.offsetLeft - (tabs.clientWidth - on.offsetWidth) / 2);
    }
})();

/*
 * bulian-kampus: dialog konfirmasi area anggota (<dialog>, dengan confirm() sebagai cadangan)
 */
window.bkConfirm = function (opt) {
    var dlg = document.getElementById('bkDialog');
    if (!dlg || typeof dlg.showModal !== 'function') {
        return Promise.resolve(opt.list ? true : window.confirm(opt.title + '\n\n' + (opt.text || '')));
    }
    dlg.querySelector('h2').textContent = opt.title;
    dlg.querySelector('[data-bk-dialog-text]').textContent = opt.text || '';
    var list = dlg.querySelector('[data-bk-dialog-list]');
    list.innerHTML = '';
    (opt.list || []).forEach(function (row) {
        var li = document.createElement('li');
        li.textContent = row.text;
        if (row.cls) li.className = row.cls;
        list.appendChild(li);
    });
    list.hidden = !(opt.list && opt.list.length);
    var ok = dlg.querySelector('[data-bk-dialog-ok]');
    var cancel = dlg.querySelector('[data-bk-dialog-cancel]');
    ok.textContent = opt.ok;
    ok.hidden = !!opt.alertOnly;
    if (!cancel.hasAttribute('data-label')) cancel.setAttribute('data-label', cancel.textContent);
    cancel.textContent = opt.alertOnly && opt.close ? opt.close : cancel.getAttribute('data-label');
    dlg.classList.toggle('bk-dialog--danger', !!opt.danger);
    return new Promise(function (resolve) {
        dlg.addEventListener('close', function done() {
            dlg.removeEventListener('close', done);
            resolve(dlg.returnValue === 'ok');
        });
        dlg.returnValue = '';
        dlg.showModal();
        (opt.alertOnly ? cancel : ok).focus();
    });
};

/*
 * bulian-kampus: tab Keranjang — pilih semua, hapus terpilih, kosongkan, kirim pesanan
 * (POST sendReserve=1 ke proses bawaan, dengan token CSRF anggota)
 */
(function () {
    var form = document.getElementById('bkBasketForm');
    if (!form) return;
    var data = JSON.parse(document.getElementById('bkBasketData').textContent);
    var picks = form.querySelectorAll('.bk-basket__pick');
    var all = form.querySelector('[data-bk-all]');
    var removeBtn = form.querySelector('[data-bk-remove]');
    var picked = form.querySelector('[data-bk-picked]');
    function sync() {
        var n = 0;
        Array.prototype.forEach.call(picks, function (p) {
            if (p.checked) n++;
            p.closest('.bk-basket__item').classList.toggle('is-picked', p.checked);
        });
        removeBtn.disabled = n === 0;
        picked.textContent = n ? '(' + n + ')' : '';
        all.checked = n === picks.length;
        all.indeterminate = n > 0 && n < picks.length;
        return n;
    }
    Array.prototype.forEach.call(picks, function (p) { p.addEventListener('change', sync); });
    all.addEventListener('change', function () {
        Array.prototype.forEach.call(picks, function (p) { p.checked = all.checked; });
        sync();
    });
    sync();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var n = sync();
        if (!n) return;
        bkConfirm({ title: form.getAttribute('data-confirm-remove').replace('%n', n), text: form.getAttribute('data-confirm-remove-text'), ok: data.labels.remove, danger: true })
            .then(function (yes) { if (yes) form.submit(); });
    });

    var clear = document.getElementById('bkBasketClear');
    clear.addEventListener('submit', function (e) {
        e.preventDefault();
        bkConfirm({ title: clear.getAttribute('data-confirm'), text: clear.getAttribute('data-confirm-text'), ok: clear.getAttribute('data-ok'), danger: true })
            .then(function (yes) { if (yes) clear.submit(); });
    });

    var send = form.querySelector('[data-bk-reserve]');
    var token = form.querySelector('input[name="__member_csrf"]').value;
    function reserve() {
        if (send.disabled) return;
        bkConfirm({
            title: send.getAttribute('data-title'), text: send.getAttribute('data-text'), ok: data.labels.send,
            list: data.titles.map(function (t) { return { text: t.title } ; })
        }).then(function (yes) {
            if (!yes) return;
            var label = send.innerHTML;
            send.disabled = true;
            send.textContent = send.getAttribute('data-wait');
            fetch('index.php?p=member&sec=title_basket', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-SLiMS-Member-CSRF': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: 'sendReserve=1&__member_csrf=' + encodeURIComponent(token)
            }).then(function (res) {
                return res.text().then(function (body) { return { ok: res.ok, body: body }; });
            }).then(function (r) {
                var json = null;
                try { json = r.body.trim() ? JSON.parse(r.body) : null; } catch (err) { json = null; }
                if (!r.ok && !json) throw new Error('http');
                if (json === null) {
                    // pesanan lewat surel: hasilnya disimpan di sesi dan tampil setelah dimuat ulang
                    location.href = 'index.php?p=member&sec=title_basket';
                    return;
                }
                var rows = Array.isArray(json) ? json : [json];
                bkConfirm({
                    title: data.labels.results, alertOnly: true, close: data.labels.close,
                    list: rows.map(function (row) {
                        var bad = row.status === 'ERROR';
                        return { text: row.message || data.labels.sent, cls: bad ? 'is-error' : 'is-ok' };
                    })
                }).then(function () { location.href = 'index.php?p=member&sec=title_basket'; });
            }).catch(function () {
                send.disabled = false;
                send.innerHTML = label;
                bkConfirm({ title: send.getAttribute('data-fail'), alertOnly: true, close: data.labels.close });
            });
        });
    }
    send.addEventListener('click', reserve);
    if (location.hash === '#bk-reserve') reserve();
})();

/*
 * bulian-kampus: tab Akun saya — lihat/sembunyikan kata sandi, syarat kata sandi
 * (sama dengan simbio_security::validatePassword) dan cek pengulangan sebelum dikirim
 */
(function () {
    var form = document.getElementById('bkChangePassword');
    if (!form) return;
    Array.prototype.forEach.call(form.querySelectorAll('[data-bk-eye]'), function (btn) {
        btn.addEventListener('click', function () {
            var show = btn.getAttribute('aria-pressed') !== 'true';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            btn.getAttribute('data-bk-eye').split(' ').forEach(function (id) {
                document.getElementById(id).type = show ? 'text' : 'password';
            });
        });
    });
    var strong = form.getAttribute('data-strong') === '1';
    var min = parseInt(form.getAttribute('data-min'), 10) || 8;
    var cur = document.getElementById('bk-cur');
    var pw = document.getElementById('bk-new');
    var conf = document.getElementById('bk-conf');
    var msg = document.getElementById('bk-conf-msg');
    var meter = form.querySelector('.bk-strength');
    var tests = {
        len: function (v) { return v.length >= min; },
        upper: function (v) { return /[A-Z]/.test(v); },
        digit: function (v) { return /[0-9]/.test(v); },
        symbol: function (v) { return /[^a-zA-Z0-9]/.test(v); }
    };
    function rulesOk() {
        var v = pw.value, passed = 0, total = 0;
        Array.prototype.forEach.call(form.querySelectorAll('[data-rule]'), function (li) {
            var ok = tests[li.getAttribute('data-rule')](v);
            li.classList.toggle('is-ok', ok);
            total++;
            if (ok) passed++;
        });
        if (meter) meter.setAttribute('data-score', v ? Math.max(1, Math.round(passed / total * 4)) : 0);
        return !strong || passed === total;
    }
    function matchOk(showMsg) {
        var bad = conf.value !== '' && conf.value !== pw.value;
        if (showMsg || !bad) {
            msg.hidden = !bad;
            conf.parentNode.classList.toggle('is-invalid', bad);
        }
        return conf.value !== '' && !bad;
    }
    // pesan "belum sama" baru muncul setelah panjangnya menyamai, agar tombol tidak bergeser saat diklik
    pw.addEventListener('input', function () { rulesOk(); if (conf.value) matchOk(conf.value.length >= pw.value.length); });
    conf.addEventListener('input', function () { matchOk(conf.value.length >= pw.value.length); });
    rulesOk();
    form.addEventListener('submit', function (e) {
        var target = null;
        if (!cur.value) target = cur;
        else if (!pw.value || !rulesOk()) target = pw;
        else if (!matchOk(true)) target = conf;
        cur.parentNode.classList.toggle('is-invalid', target === cur);
        pw.parentNode.classList.toggle('is-invalid', target === pw);
        if (target) { e.preventDefault(); target.focus(); }
    });
})();
