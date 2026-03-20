(function () {
    'use strict';

    var fmtStatus = function (s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; };

    /* ── Timer ───────────────────────────────────── */
    var startCountdown = function (box, expiresAt) {
        var el = box.querySelector('.wcdg-countdown');
        clearInterval(box._wcdgTick);
        if (!el || !expiresAt) return;
        var tick = function () {
            var ms = new Date(expiresAt.replace(' ', 'T') + 'Z').getTime() - Date.now();
            if (ms <= 0) { el.textContent = 'Expired'; clearInterval(box._wcdgTick); return; }
            var m = Math.floor(ms / 60000), s = Math.floor((ms % 60000) / 1000);
            el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        };
        tick();
        box._wcdgTick = setInterval(tick, 1000);
    };

    /* ── Update the payment card ─────────────────── */
    var updateCard = function (box, p) {
        var req = box.querySelector('.wcdg-payment-request');
        if (req && req.hidden) req.hidden = false;

        /* Always use the clean dynamic QR (amount-aware) */
        var qrUrl = p.dynamic_payment_qr_url || p.payment_qr_url || '';

        var set = function (sel, txt) { var el = box.querySelector(sel); if (el) el.textContent = txt; };

        set('.wcdg-reference', p.reference || '');
        set('.wcdg-crypto-amount', (p.crypto_amount || '') + ' ' + (p.crypto_currency || ''));
        set('.wcdg-fiat-amount', '\u2248 ' + Number(p.fiat_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + (p.fiat_currency || ''));
        set('.wcdg-wallet-label', (p.wallet_label || '') + ' \u2013 ' + (p.wallet_network || ''));
        set('.wcdg-confirmations', (p.confirmations || 0) + ' / ' + (p.required_confirmations || 1));

        var statusEl = box.querySelector('.wcdg-status');
        if (statusEl) {
            statusEl.textContent = fmtStatus(p.status);
            statusEl.className = 'wcdg-status wcdg-status-' + (p.status || 'pending');
        }

        var qr = box.querySelector('.wcdg-qr');
        if (qr) qr.src = qrUrl;

        var addr = box.querySelector('.wcdg-address');
        if (addr) {
            if (addr.tagName === 'TEXTAREA' || addr.tagName === 'INPUT') addr.value = p.wallet_address || '';
            else addr.textContent = p.wallet_address || '';
        }

        var walletLink = box.querySelector('.wcdg-open-wallet');
        if (walletLink) walletLink.href = p.payment_uri || '#';

        if (req) {
            req.dataset.reference = p.reference || '';
            req.dataset.amount = p.crypto_amount || '';
        }

        box.dataset.status = p.status || 'pending';
        startCountdown(box, p.expires_at);
    };

    /* ── Poll status ─────────────────────────────── */
    var poll = function (box, ref) {
        clearInterval(box._wcdgPoll);
        box._wcdgPoll = setInterval(function () {
            fetch(wcdgConfig.statusUrlBase + encodeURIComponent(ref) + '/status', { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    updateCard(box, data);
                    if (['paid', 'expired', 'failed', 'cancelled'].indexOf(data.status) !== -1) {
                        clearInterval(box._wcdgPoll);
                        if (data.status === 'paid') {
                            var msg = box.querySelector('.wcdg-message');
                            if (msg) msg.textContent = wcdgConfig.strings.paid || 'Payment confirmed!';
                        }
                    }
                })
                .catch(function () {});
        }, 12000);
    };

    /* ── Bind one container ──────────────────────── */
    var bind = function (box) {
        var form = box.querySelector('.wcdg-form');
        var msg  = box.querySelector('.wcdg-message');

        /* Form submit (shortcode) */
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (msg) msg.textContent = wcdgConfig.strings.creating || 'Creating payment\u2026';
                var fd = new FormData(form);
                fd.append('source', 'shortcode');
                var coin = form.querySelector('[name="wallet_id"]');
                if (!fd.get('coin') && coin) fd.append('coin', coin.value);
                fetch(wcdgConfig.createRequestUrl, { method: 'POST', headers: { Accept: 'application/json' }, body: fd })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }); })
                    .then(function (res) {
                        if (!res.ok) { if (msg) msg.textContent = res.data.message || wcdgConfig.strings.error; return; }
                        if (msg) msg.textContent = '';
                        updateCard(box, res.data);
                        poll(box, res.data.reference);
                    })
                    .catch(function () { if (msg) msg.textContent = wcdgConfig.strings.error || 'An error occurred.'; });
            });
        }

        /* Copy buttons */
        var copyAddr = box.querySelector('.wcdg-copy-address');
        var copyAmt  = box.querySelector('.wcdg-copy-amount');
        if (copyAddr) copyAddr.addEventListener('click', function () {
            var a = box.querySelector('.wcdg-address');
            if (a) navigator.clipboard.writeText(a.value || a.textContent).then(function () { if (msg) msg.textContent = wcdgConfig.strings.copySuccess || 'Copied!'; });
        });
        if (copyAmt) copyAmt.addEventListener('click', function () {
            var req = box.querySelector('.wcdg-payment-request');
            if (req && req.dataset.amount) navigator.clipboard.writeText(req.dataset.amount).then(function () { if (msg) msg.textContent = wcdgConfig.strings.copyAmountSuccess || 'Amount copied!'; });
        });

        /* Auto-poll for WooCommerce panels with existing reference */
        var ref = box.dataset.reference;
        if (!ref) {
            var req = box.querySelector('.wcdg-payment-request');
            if (req) ref = req.dataset.reference;
        }
        if (ref) {
            poll(box, ref);
            var exp = box.querySelector('.wcdg-expires-at');
            if (exp && exp.textContent) startCountdown(box, exp.textContent);
        }
    };

    document.querySelectorAll('.wcdg-payment-form, .wcdg-live-payment').forEach(bind);
}());
