document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Hide' : 'Show';
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        });
    });

    const registrationMap = document.getElementById('registration-location-map');
    if (registrationMap && typeof initializePickupMap === 'function') {
        initializePickupMap({
            mapId: 'registration-location-map',
            defaultLat: 14.5995,
            defaultLng: 120.9842,
            latInput: document.getElementById('latitude'),
            lngInput: document.getElementById('longitude'),
            editable: true,
            allowUnselected: true
        });
    }

    const flash = document.querySelector('.alert');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.3s ease';
            setTimeout(() => flash.remove(), 350);
        }, 4000);
    }
});
