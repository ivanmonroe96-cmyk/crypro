(function () {
    const formatAmount = (amount, currency) => {
        const numericAmount = Number(amount);
        if (Number.isNaN(numericAmount)) {
            return '';
        }

        return numericAmount.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ' + currency;
    };

    const formatStatus = (status) => {
        if (!status) {
            return '';
        }
        return status.charAt(0).toUpperCase() + status.slice(1);
    };

    const startCountdown = (container, expiresAt) => {
        const countdown = container.querySelector('.wcdg-countdown');
        window.clearInterval(container._wcdgCountdown);

        if (!countdown || !expiresAt) {
            return;
        }

        const tick = () => {
            const remaining = new Date(expiresAt.replace(' ', 'T') + 'Z').getTime() - Date.now();
            if (remaining <= 0) {
                countdown.textContent = 'Expired';
                window.clearInterval(container._wcdgCountdown);
                return;
            }

            const totalSeconds = Math.floor(remaining / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            countdown.textContent = 'Time left ' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        };

        tick();
        container._wcdgCountdown = window.setInterval(tick, 1000);
    };

    const updatePaymentBox = (container, payment) => {
        const requestWrap = container.querySelector('.wcdg-payment-request');
        if (requestWrap && 'hidden' in requestWrap) {
            requestWrap.hidden = false;
        }

        container.querySelector('.wcdg-reference').textContent = payment.reference;
        container.querySelector('.wcdg-status').textContent = formatStatus(payment.status);
        container.querySelector('.wcdg-status').className = 'wcdg-status wcdg-status-' + payment.status;
        container.querySelector('.wcdg-qr').src = payment.payment_qr_url;
        container.querySelector('.wcdg-crypto-amount').textContent = payment.crypto_amount + ' ' + payment.crypto_currency;
        container.querySelector('.wcdg-fiat-amount').textContent = formatAmount(payment.fiat_amount, payment.fiat_currency);
        container.querySelector('.wcdg-wallet-label').textContent = payment.wallet_label + ' - ' + payment.wallet_network;
        container.querySelector('.wcdg-address').value = payment.wallet_address;
        container.querySelector('.wcdg-expires-at').textContent = payment.expires_at || '';
        container.querySelector('.wcdg-open-wallet').href = payment.payment_uri || '#';
        if (requestWrap) {
            requestWrap.dataset.reference = payment.reference;
            requestWrap.dataset.amount = payment.crypto_amount;
        }

        startCountdown(container, payment.expires_at);
    };

    const pollStatus = (container, reference) => {
        window.clearInterval(container._wcdgPoll);
        container._wcdgPoll = window.setInterval(async () => {
            const response = await fetch(wcdgConfig.statusUrlBase + encodeURIComponent(reference) + '/status', {
                headers: {
                    Accept: 'application/json'
                }
            });

            if (!response.ok) {
                return;
            }

            const payment = await response.json();
            updatePaymentBox(container, payment);

            if (['paid', 'expired', 'failed', 'cancelled'].includes(payment.status)) {
                window.clearInterval(container._wcdgPoll);
            }
        }, 15000);
    };

    const bindPaymentContainer = (container) => {
        const form = container.querySelector('.wcdg-form');
        const message = container.querySelector('.wcdg-message');
        const copyButton = container.querySelector('.wcdg-copy-address');
        const copyAmountButton = container.querySelector('.wcdg-copy-amount');

        if (form && message) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                message.textContent = wcdgConfig.strings.creating;

                const formData = new FormData(form);
                formData.append('source', 'shortcode');
                const coinField = form.querySelector('[name="wallet_id"]');
                if (!formData.get('coin') && coinField) {
                    formData.append('coin', coinField.value);
                }

                const response = await fetch(wcdgConfig.createRequestUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json'
                    },
                    body: formData
                });

                const payload = await response.json();

                if (!response.ok) {
                    message.textContent = payload.message || wcdgConfig.strings.error;
                    return;
                }

                message.textContent = wcdgConfig.strings.created;
                updatePaymentBox(container, payload);
                pollStatus(container, payload.reference);
            });
        }

        if (copyButton) {
            copyButton.addEventListener('click', async () => {
                const addressField = container.querySelector('.wcdg-address');
                if (!addressField) {
                    return;
                }
                await navigator.clipboard.writeText(addressField.value);
                if (message) {
                    message.textContent = wcdgConfig.strings.copySuccess;
                }
            });
        }

        if (copyAmountButton) {
            copyAmountButton.addEventListener('click', async () => {
                const requestWrap = container.querySelector('.wcdg-payment-request');
                if (!requestWrap || !requestWrap.dataset.amount) {
                    return;
                }
                await navigator.clipboard.writeText(requestWrap.dataset.amount);
                if (message) {
                    message.textContent = wcdgConfig.strings.copyAmountSuccess;
                }
            });
        }

        const initialReference = container.dataset.reference || (container.querySelector('.wcdg-payment-request') && container.querySelector('.wcdg-payment-request').dataset.reference);
        if (initialReference) {
            pollStatus(container, initialReference);
            const expiresAt = container.querySelector('.wcdg-expires-at');
            if (expiresAt && expiresAt.textContent) {
                startCountdown(container, expiresAt.textContent);
            }
        }
    };

    document.querySelectorAll('.wcdg-payment-form, .wcdg-live-payment').forEach(bindPaymentContainer);
}());