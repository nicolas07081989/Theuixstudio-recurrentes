(function () {
    function initDatafastWidgetToggle() {
        var hasSavedCards = document.querySelector('.wpwl-group-registration') ||
            document.querySelector('.wpwl-wrapper-registrations') ||
            document.querySelector('.wpwl-registrations');

        if (!hasSavedCards || document.querySelector('.dfwr-widget-toggle')) {
            return;
        }

        document.body.classList.add('dfwr-hide-manual-widget');
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'dfwr-widget-toggle';
        toggle.textContent = 'Usar otra tarjeta';
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('dfwr-hide-manual-widget');
            toggle.textContent = document.body.classList.contains('dfwr-hide-manual-widget')
                ? 'Usar otra tarjeta'
                : 'Usar tarjeta guardada';
        });

        var form = document.querySelector('.wpwl-form');
        if (form && form.parentNode) {
            form.parentNode.insertBefore(toggle, form);
        }
    }

    document.addEventListener('DOMContentLoaded', initDatafastWidgetToggle);
    window.addEventListener('load', initDatafastWidgetToggle);
})();
