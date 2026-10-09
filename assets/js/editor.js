/* Copyright (c) 2026 QuixDevs. GPL-2.0-or-later. */
document.addEventListener('DOMContentLoaded', function () {
    const fields = document.querySelector('.qpc-fields');
    if (!fields) return;
    const mode = fields.querySelector('#qpc-mode');
    const enabled = fields.querySelector('[name="qpc[enabled]"]');
    const publish = fields.querySelector('#qpc-publish');
    const expire = fields.querySelector('#qpc-expire');
    function update() {
        fields.querySelectorAll('[data-qpc-action]').forEach(function (row) {
            const relevant = mode.value === 'both' || mode.value === row.dataset.qpcAction;
            row.hidden = !relevant;
            row.querySelector('input').required = relevant && enabled.checked;
        });
        const invalid = enabled.checked && mode.value === 'both' && publish.value && expire.value && expire.value <= publish.value;
        expire.setCustomValidity(invalid ? quixdevsProductClock.invalid : '');
        fields.querySelector('.qpc-validation').textContent = invalid ? quixdevsProductClock.invalid : '';
    }
    fields.addEventListener('input', update);
    update();
});
// WooCommerce initializes its native tabs in jQuery.ready; run after those handlers.
jQuery(function () {
    if (window.location.hash === '#quixdevs-productclock') {
        const link = document.querySelector('a[href="#quixdevs-productclock"]');
        if (link) link.click();
    }
});
