/*
 * bulian-kampus: logika kiosk buku tamu.
 * Konfigurasi datang dari window.BK_KIOSK (lihat visitor_template.php).
 */
(function () {
    var conf = window.BK_KIOSK || {};
    var T = conf.text || {};
    var $ = function (id) { return document.getElementById(id); };

    var input = $('bk-member-id');
    var scanForm = $('bk-scan-form');
    var state = $('bk-scan-state');
    var card = $('bk-result');
    var photo = $('bk-result-photo');
    var title = $('bk-result-title');
    var text = $('bk-result-text');
    var guest = $('bk-guest');
    var idle = { photo: photo.getAttribute('src'), title: title.textContent, text: text.textContent };

    var busy = false;
    var last = { code: '', at: 0, kind: '' };
    var resetTimer = null;

    // ---------------------------------------------------------------- helpers

    // Isi kartu bisa berupa ID polos (bawaan SLiMS), atau QR berisi URL /
    // awalan seperti "MEMBER:M0001". Ambil ID anggotanya saja.
    function normalize(raw) {
        var s = String(raw || '').replace(/[\u0000-\u001F\u007F]/g, '').trim();
        if (!s) return '';
        if (/^https?:\/\//i.test(s)) {
            try {
                var u = new URL(s);
                s = u.searchParams.get('member_id') || u.searchParams.get('memberID') || u.searchParams.get('id') || '';
            } catch (e) { return ''; }
        }
        s = s.replace(/^(slims|member|anggota)\s*[:=]\s*/i, '').trim();
        if (s.length > 40) return '';
        return s;
    }

    // pesan dari server boleh mengandung HTML; tampilkan sebagai teks saja
    function plain(html) {
        var doc = new DOMParser().parseFromString('<body>' + String(html || '') + '</body>', 'text/html');
        return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function setState(kind, message) {
        state.setAttribute('data-kind', kind);
        state.lastChild.textContent = ' ' + message;
    }

    function show(kind, heading, message, image) {
        card.setAttribute('data-state', kind);
        title.textContent = heading;
        text.textContent = message;
        if (image) photo.setAttribute('src', image);
        clearTimeout(resetTimer);
        resetTimer = setTimeout(function () {
            card.setAttribute('data-state', 'idle');
            title.textContent = idle.title;
            text.textContent = idle.text;
            photo.setAttribute('src', idle.photo);
            setState('ready', T.ready);
        }, 6000);
    }

    function speak(message) {
        if (!conf.voice || !window.speechSynthesis) return;
        try {
            var u = new SpeechSynthesisUtterance(message);
            u.lang = conf.lang;
            speechSynthesis.cancel();
            speechSynthesis.speak(u);
        } catch (e) {}
    }

    function focusInput() {
        if (guest && guest.open) return;
        if (document.activeElement !== input) input.focus({ preventScroll: true });
    }

    // ------------------------------------------------------------- check-in

    function checkIn(memberId, institution) {
        if (busy) return;
        busy = true;
        setState('busy', T.wait);

        var data = new FormData();
        data.append('memberID', memberId);
        data.append('institution', institution || '');
        data.append('counter', 1);
        data.append(conf.csrfName, conf.csrfToken);

        fetch(conf.url, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-Response-With': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (res.new_token) conf.csrfToken = res.new_token;
                var raw = String(res.message || '');
                var message = plain(raw);
                var kind = 'ok';
                if (res.code === 'expired' || raw.indexOf('visitor-error') !== -1) kind = 'warn';
                if (res.status === 'error') kind = 'error';
                // server baru mengirim code; versi lama hanya teks pesan
                var needInstitution = res.code ? res.code === 'need_institution' : message === plain(T.needInstitution);
                if (!institution && needInstitution) {
                    // ID tidak terdaftar: arahkan ke form tamu
                    kind = 'warn';
                    if (guest) { guest.open = true; $('bk-guest-name').value = memberId; $('bk-guest-inst').focus(); }
                    message = T.guest;
                }
                var image = res.image ? conf.imageBase + encodeURIComponent(res.image).replace(/%2F/g, '/') : null;
                show(kind, kind === 'ok' ? T.welcome : T.attention, message, image);
                setState(kind === 'ok' ? 'ok' : 'warn', message);
                if (last.code === memberId) last.kind = kind;
                if (kind === 'ok') speak(message);
            })
            .catch(function () {
                show('error', T.attention, T.error, null);
                setState('error', T.error);
            })
            .finally(function () {
                busy = false;
                input.value = '';
                focusInput();
            });
    }

    function submitCode(raw) {
        var code = normalize(raw);
        input.value = '';
        if (!code) {
            show('warn', T.attention, T.invalid, null);
            setState('warn', T.invalid);
            return;
        }
        if (conf.repeatSeconds > 0 && code === last.code && Date.now() - last.at < conf.repeatSeconds * 1000) {
            // kamera membaca kartu yang sama berkali-kali; biarkan hasil pertama tetap tampil
            if (card.getAttribute('data-state') !== 'idle' || last.kind !== 'ok') return;
            show('ok', T.welcome, T.repeat, null);
            setState('ok', T.repeat);
            return;
        }
        if (guest && guest.open) return;
        last = { code: code, at: Date.now(), kind: 'busy' };
        checkIn(code, '');
    }

    scanForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        submitCode(input.value);
    });

    var guestForm = $('bk-guest-form');
    if (guestForm) {
        guestForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var name = $('bk-guest-name').value.trim();
            var inst = $('bk-guest-inst').value.trim();
            if (!name || !inst) return;
            checkIn(name, inst);
            guestForm.reset();
            guest.open = false;
        });
    }

    // pemindai USB mengetik ke elemen yang sedang fokus; jaga fokus di kolom ID
    input.addEventListener('blur', function () { setTimeout(focusInput, 400); });
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest('#bk-guest, #bk-cam-toggle, a, button, input, summary')) focusInput();
    });

    // ---------------------------------------------------------------- kamera

    var camBtn = $('bk-cam-toggle');
    var camView = $('bk-cam-view');
    var scanner = null;

    function stopCamera() {
        if (!scanner) return Promise.resolve();
        var s = scanner;
        scanner = null;
        return s.stop().catch(function () {}).then(function () {
            camView.hidden = true;
            camBtn.textContent = T.camOff;
        });
    }

    if (camBtn) {
        camBtn.addEventListener('click', function () {
            if (scanner) { stopCamera(); return; }
            if (!window.isSecureContext) { show('warn', T.attention, T.camInsecure, null); return; }
            if (typeof Html5Qrcode === 'undefined') { show('error', T.attention, T.camFail, null); return; }
            camView.hidden = false;
            var F = Html5QrcodeSupportedFormats;
            scanner = new Html5Qrcode('bk-cam-view', {
                formatsToSupport: [F.QR_CODE, F.CODE_128, F.CODE_39, F.EAN_13],
                verbose: false
            });
            scanner.start(
                { facingMode: 'user' },
                { fps: 8, qrbox: function (w, h) { return { width: Math.floor(w * 0.85), height: Math.floor(h * 0.85) }; } },
                function (decoded) { if (!busy) submitCode(decoded); },
                function () {}
            ).then(function () {
                camBtn.textContent = T.camOn;
            }).catch(function () {
                scanner = null;
                camView.hidden = true;
                show('error', T.attention, T.camFail, null);
            });
        });
    }

    // ----------------------------------------------------------------- jam

    var clock = $('bk-clock');
    function tick() {
        if (!clock) return;
        var d = new Date();
        clock.textContent = d.toLocaleDateString(conf.lang, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) +
            ' · ' + d.toLocaleTimeString(conf.lang, { hour: '2-digit', minute: '2-digit' });
    }
    tick();
    setInterval(tick, 30000);

    setState('ready', T.ready);
    focusInput();
})();
