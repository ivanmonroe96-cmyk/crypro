(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var table = document.getElementById('wcdg-wallet-table');
        var addButton = document.getElementById('wcdg-add-wallet');
        var template = document.getElementById('wcdg-wallet-row-template');
        var newRowIndex = 0;

        /* Add a blank wallet row from the template */
        if (addButton && template && table) {
            addButton.addEventListener('click', function () {
                newRowIndex++;
                var html = template.innerHTML.replace(/__INDEX__/g, 'new-' + newRowIndex);
                var tbody = table.querySelector('tbody');
                var holder = document.createElement('tbody');
                holder.innerHTML = html;
                while (holder.firstElementChild) {
                    tbody.appendChild(holder.firstElementChild);
                }
                var lastRow = tbody.lastElementChild;
                var symbolInput = lastRow ? lastRow.querySelector('input[name$="[symbol]"]') : null;
                if (symbolInput) {
                    symbolInput.focus();
                }
            });
        }

        if (!table) {
            return;
        }

        /* Remove a wallet row */
        table.addEventListener('click', function (event) {
            var removeButton = event.target.closest('.wcdg-remove-wallet');
            if (!removeButton) {
                return;
            }

            var row = removeButton.closest('tr');
            if (row && window.confirm(wcdgAdminConfig.confirmRemove)) {
                row.remove();
            }
        });

        /* Static QR image picker via the WordPress media library */
        table.addEventListener('click', function (event) {
            var uploadButton = event.target.closest('.wcdg-upload-qr');
            if (!uploadButton || typeof wp === 'undefined' || !wp.media) {
                return;
            }

            var field = uploadButton.closest('.wcdg-qr-upload-field');
            var input = field ? field.querySelector('input[type="url"]') : null;
            if (!input) {
                return;
            }

            var frame = wp.media({
                title: wcdgAdminConfig.chooseQrTitle,
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first();
                if (attachment) {
                    input.value = attachment.get('url');
                }
            });

            frame.open();
        });
    });
}());
