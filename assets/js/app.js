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

    const pickupDialog = document.getElementById('pickup-request-modal');
    const pickupInformationForm = document.getElementById('pickup-information-form');
    const pickupItemRows = document.getElementById('pickup-item-rows');
    const pickupItemsTarget = document.getElementById('request-pickup-items');
    const addPickupItemButton = document.getElementById('add-pickup-item');
    let pickupItemIndex = pickupItemRows?.querySelectorAll('.pickup-item-row').length || 0;
    const updatePickupItemChoices = function () {
        if (!pickupItemRows) return;
        const selects = Array.from(pickupItemRows.querySelectorAll('select[name="item_category[]"]'));
        const selectedValues = selects.map(function (select) { return select.value; }).filter(Boolean);
        selects.forEach(function (select) {
            Array.from(select.options).forEach(function (option) {
                option.disabled = Boolean(option.value && option.value !== select.value && selectedValues.includes(option.value));
            });
        });
        if (addPickupItemButton) addPickupItemButton.disabled = selects.length >= Math.max(1, selects[0]?.options.length - 1);
    };
    if (pickupItemRows) pickupItemRows.addEventListener('change', function (event) {
        if (event.target.matches('select[name="item_category[]"]')) updatePickupItemChoices();
    });
    const pickupMapDialog = document.getElementById('pickup-map-modal');
    const pickupForm = document.getElementById('pickup-request-form');
    const pickupMapElement = document.getElementById('pickup-modal-map');
    let pickupMap = null;
    let pinConfirmed = false;
    let pickupSubmitting = false;

    const showDialog = function (dialog) {
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else {
            dialog.setAttribute('open', '');
            dialog.classList.add('dialog-fallback-open');
            document.body.classList.add('dialog-fallback-active');
        }
    };
    const closeDialog = function (dialog) {
        if (!dialog) return;
        if (typeof dialog.close === 'function' && dialog.open) dialog.close();
        else {
            dialog.removeAttribute('open');
            dialog.classList.remove('dialog-fallback-open');
            document.body.classList.remove('dialog-fallback-active');
        }
    };
    const openPickup = function () {
        if (!pickupDialog || !pickupInformationForm) return;
        showDialog(pickupDialog);
        window.setTimeout(function () { pickupInformationForm.querySelector('input[name="full_name"]')?.focus(); }, 0);
    };
    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-open-pickup]');
        if (opener) {
            event.preventDefault();
            openPickup();
        }
        if (event.target.closest('[data-cancel-pickup]')) {
            if (pickupSubmitting) return;
            closeDialog(pickupMapDialog);
            closeDialog(pickupDialog);
        }
    });

    const serviceSelect = document.getElementById('service_type');
    const dateInput = document.getElementById('preferred_date');
    const serviceDescription = document.getElementById('service-description');
    const feeLabel = document.getElementById('pickup-fee');
    const destinationSelect = document.getElementById('pickup-destination');
    const pickupDistanceLabel = document.getElementById('pickup-distance');
    const baseFeeLabel = document.getElementById('pickup-base-fee');
    const distanceFeeLabel = document.getElementById('pickup-distance-fee');
    const pickupLatitude = document.getElementById('latitude');
    const pickupLongitude = document.getElementById('longitude');
    const estimateDistance = function (lat1, lng1, lat2, lng2) {
        const rad = function (value) { return value * Math.PI / 180; };
        const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
        return 6371.0088 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
    };
    const updateService = function () {
        if (!serviceSelect) return;
        const priority = serviceSelect.value === 'priority';
        const base = Number(priority ? serviceSelect.dataset.priorityFee : serviceSelect.dataset.saverFee);
        const middleRate = Number(priority ? serviceSelect.dataset.priorityMiddleRate : serviceSelect.dataset.saverMiddleRate);
        const outerRate = Number(priority ? serviceSelect.dataset.priorityOuterRate : serviceSelect.dataset.saverOuterRate);
        let distance = null;
        const destination = destinationSelect?.selectedOptions[0];
        if (destination?.value && pickupLatitude?.value && pickupLongitude?.value) {
            distance = estimateDistance(Number(pickupLatitude.value), Number(pickupLongitude.value), Number(destination.dataset.lat), Number(destination.dataset.lng));
        }
        const middleKm = distance === null ? 0 : Math.max(0, Math.min(distance, 8) - 3);
        const outerKm = distance === null ? 0 : Math.max(0, distance - 8);
        const distanceFee = middleKm * middleRate + outerKm * outerRate;
        const fee = base + distanceFee;
        if (baseFeeLabel) baseFeeLabel.textContent = '\u20b1' + base.toFixed(2);
        if (distanceFeeLabel) distanceFeeLabel.textContent = '\u20b1' + distanceFee.toFixed(2);
        if (pickupDistanceLabel) pickupDistanceLabel.textContent = distance === null ? (destination?.value ? 'Confirm your pickup pin to calculate' : 'No verified destination — base fee only') : distance.toFixed(2) + ' km straight-line estimate';
        if (feeLabel) feeLabel.textContent = '\u20b1' + fee.toFixed(2);
        if (feeLabel) { feeLabel.dataset.total = fee.toFixed(2); feeLabel.dataset.base = base.toFixed(2); feeLabel.dataset.distance = distance === null ? '' : distance.toFixed(2); feeLabel.dataset.surcharge = distanceFee.toFixed(2); }
        if (dateInput) dateInput.required = !priority;
        if (serviceDescription) serviceDescription.textContent = priority
            ? 'Urgent collection requested as soon as possible, subject to collector availability and location.'
            : 'Regular collection between 8:00 AM and 5:00 PM. Requests follow the regular schedule and may not be immediate.';
    };
    if (serviceSelect) serviceSelect.addEventListener('change', updateService);
    if (destinationSelect) destinationSelect.addEventListener('change', function () { updateService(); });
    if (pickupMapElement) pickupMapElement.addEventListener('ecoloop:pickuplocationchange', updateService);
    updateService();

    const copyPickupInformation = function () {
        if (!pickupInformationForm || !pickupForm) return;
        const values = new FormData(pickupInformationForm);
        ['full_name', 'contact_number', 'service_type', 'collection_point_id', 'pickup_address', 'preferred_date', 'preferred_time', 'notes'].forEach(function (name) {
            const target = pickupForm.elements[name];
            if (target) target.value = values.get(name) || '';
        });
        if (pickupItemsTarget) {
            pickupItemsTarget.replaceChildren();
            pickupItemRows?.querySelectorAll('.pickup-item-row').forEach(function (row) {
                const category = row.querySelector('select[name="item_category[]"]')?.value || '';
                const quantity = row.querySelector('input[name="quantity[]"]')?.value || '';
                [['item_categories[]', category], ['quantities[]', quantity]].forEach(function (pair) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = pair[0];
                    input.value = pair[1];
                    pickupItemsTarget.appendChild(input);
                });
            });
        }
    };
    const locationMessage = document.getElementById('pickup-location-message');
    const finalConfirmation = document.getElementById('pickup-final-confirmation');
    const submitPickupButton = document.getElementById('submit-pickup-request');
    const confirmLocationButton = document.getElementById('confirm-pickup-location');
    const coordinatesInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const coordinateLabel = document.getElementById('coord-value');
    const resetLocationConfirmation = function (message) {
        pinConfirmed = false;
        if (finalConfirmation) finalConfirmation.hidden = true;
        if (submitPickupButton) submitPickupButton.hidden = true;
        if (confirmLocationButton) confirmLocationButton.hidden = false;
        if (locationMessage) locationMessage.textContent = message || '';
    };
    const openPickupMap = function () {
        if (!pickupMapDialog) return;
        showDialog(pickupMapDialog);
        if (!pickupMap && typeof initializePickupMap === 'function' && pickupMapElement) {
            const savedLatitude = coordinatesInput?.value || '';
            const savedLongitude = longitudeInput?.value || '';
            pickupMap = initializePickupMap({
                mapId: 'pickup-modal-map',
                defaultLat: savedLatitude ? Number(savedLatitude) : 10.291998032473089,
                defaultLng: savedLongitude ? Number(savedLongitude) : 123.9347075919448,
                latInput: coordinatesInput,
                lngInput: longitudeInput,
                coordValue: coordinateLabel,
                editable: true,
                allowUnselected: true
            });
            pickupMapElement.addEventListener('ecoloop:pickuplocationchange', function () {
                resetLocationConfirmation('Location changed. Confirm the new pin to continue.');
            });
        }
        if (coordinatesInput?.value && longitudeInput?.value && coordinateLabel) {
            coordinateLabel.textContent = Number(coordinatesInput.value).toFixed(6) + ', ' + Number(longitudeInput.value).toFixed(6);
        }
        window.setTimeout(function () { if (pickupMap) pickupMap.invalidateSize(); }, 150);
    };
    if (pickupInformationForm) pickupInformationForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!pickupInformationForm.reportValidity()) return;
        copyPickupInformation();
        resetLocationConfirmation('Tap the map to mark your exact pickup location.');
        openPickupMap();
        closeDialog(pickupDialog);
    });
    if (addPickupItemButton && pickupItemRows) addPickupItemButton.addEventListener('click', function () {
        const firstRow = pickupItemRows.querySelector('.pickup-item-row');
        if (!firstRow) return;
        const row = firstRow.cloneNode(true);
        const index = pickupItemIndex++;
        row.querySelectorAll('select, input').forEach(function (field) {
            field.value = field.tagName === 'INPUT' ? '1' : '';
            const baseId = field.id.replace(/-\d+$/, '');
            field.id = baseId + '-' + index;
            const label = row.querySelector('label[for="' + baseId + '-0"]');
            if (label) label.htmlFor = field.id;
        });
        row.querySelector('[data-remove-pickup-item]')?.removeAttribute('hidden');
        pickupItemRows.appendChild(row);
        updatePickupItemChoices();
        row.querySelector('select')?.focus();
    });
    if (pickupItemRows) pickupItemRows.addEventListener('click', function (event) {
        const remove = event.target.closest('[data-remove-pickup-item]');
        if (!remove) return;
        remove.closest('.pickup-item-row')?.remove();
        const rows = pickupItemRows.querySelectorAll('.pickup-item-row');
        rows.forEach(function (row) {
            const removeButton = row.querySelector('[data-remove-pickup-item]');
            if (removeButton) removeButton.hidden = rows.length === 1;
        });
        updatePickupItemChoices();
    });
    const backToPickupInformation = document.getElementById('pickup-map-back');
    if (backToPickupInformation) backToPickupInformation.addEventListener('click', function () {
        resetLocationConfirmation('Return to the map and confirm your pickup pin before submitting.');
        closeDialog(pickupMapDialog);
        showDialog(pickupDialog);
    });
    if (confirmLocationButton) confirmLocationButton.addEventListener('click', function () {
        const lat = Number(coordinatesInput?.value);
        const lng = Number(longitudeInput?.value);
        if (!coordinatesInput?.value || !longitudeInput?.value || !Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
            if (locationMessage) locationMessage.textContent = 'Tap the map to place a pickup pin before continuing.';
            return;
        }
        pinConfirmed = true;
        const names = {
            name: document.getElementById('request_full_name')?.value || '',
            phone: document.getElementById('request_contact_number')?.value || '',
            address: document.getElementById('request_pickup_address')?.value || '',
            service: document.getElementById('request_service_type')?.value || ''
        };
        updateService();
        const serviceFee = feeLabel?.textContent || '';
        const serviceName = names.service === 'priority' ? 'Priority Collection' : 'Convenience (Saver)';
        document.getElementById('confirm-pickup-name').textContent = names.name;
        document.getElementById('confirm-pickup-phone').textContent = names.phone;
        document.getElementById('confirm-pickup-address').textContent = names.address;
        const chosenItems = Array.from(pickupItemRows?.querySelectorAll('.pickup-item-row') || []).map(function (row) {
            return row.querySelector('select')?.value + ' × ' + row.querySelector('input[type="number"]')?.value;
        });
        document.getElementById('confirm-pickup-items').textContent = chosenItems.join(', ');
        document.getElementById('confirm-pickup-service').textContent = serviceName + ' - ' + serviceFee;
        const chosenDestination = destinationSelect?.selectedOptions[0];
        const distanceText = pickupDistanceLabel?.textContent || 'No distance available';
        document.getElementById('confirm-pickup-distance').textContent = distanceText + (chosenDestination?.value ? ' to ' + chosenDestination.textContent.trim() : ' (base fee only)');
        document.getElementById('confirm-pickup-charges').textContent = '\u20b1' + (feeLabel?.dataset.base || '0.00') + ' base + \u20b1' + (feeLabel?.dataset.surcharge || '0.00') + ' distance = ' + serviceFee;
        document.getElementById('confirm-pickup-coordinates').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
        if (finalConfirmation) finalConfirmation.hidden = false;
        if (submitPickupButton) submitPickupButton.hidden = false;
        confirmLocationButton.hidden = true;
        if (locationMessage) locationMessage.textContent = 'Pickup location confirmed. Review your details, then submit the request.';
    });

    if (pickupForm) pickupForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const message = document.getElementById('pickup-form-message');
        if (!pinConfirmed || !coordinatesInput?.value || !longitudeInput?.value) {
            if (locationMessage) locationMessage.textContent = 'Confirm a map pin before submitting your pickup request.';
            return;
        }
        if (pickupSubmitting) return;
        pickupSubmitting = true;
        submitPickupButton.disabled = true;
        submitPickupButton.textContent = 'Submitting...';
        if (message) { message.hidden = true; message.textContent = ''; }
        document.querySelectorAll('[data-cancel-pickup], #pickup-map-back').forEach(function (button) { button.disabled = true; });
        try {
            const response = await fetch(pickupForm.action, { method: 'POST', body: new FormData(pickupForm), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'The pickup request could not be saved.');
            if (message) { message.className = 'alert alert-success'; message.textContent = 'Pickup request saved as Pending. Opening My Pickups...'; message.hidden = false; }
            window.setTimeout(function () { window.location.assign('my-pickups.php?created=' + encodeURIComponent(result.id)); }, 500);
        } catch (error) {
            pickupSubmitting = false;
            if (message) { message.className = 'alert alert-error'; message.textContent = error.message || 'The pickup request could not be saved. Please try again.'; message.hidden = false; }
            submitPickupButton.disabled = false;
            submitPickupButton.textContent = 'Submit Pickup Request';
            document.querySelectorAll('[data-cancel-pickup], #pickup-map-back').forEach(function (button) { button.disabled = false; });
        }
    });
    if (new URLSearchParams(window.location.search).get('openPickup') === '1') openPickup();
    const redemptionDialog = document.getElementById('redemption-modal');
    const redemptionForm = document.getElementById('redemption-form');
    document.querySelectorAll('[data-open-redemption]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!redemptionDialog || !redemptionForm) return;
            redemptionForm.elements.reward_id.value = button.dataset.rewardId;
            redemptionForm.elements.request_token.value = Array.from(crypto.getRandomValues(new Uint8Array(32)), function (value) { return value.toString(16).padStart(2, '0'); }).join('');
            document.getElementById('redemption-reward-name').textContent = button.dataset.rewardName;
            document.getElementById('redemption-cost').textContent = button.dataset.rewardCost;
            document.getElementById('redemption-balance').textContent = button.dataset.pointsBalance;
            document.getElementById('redemption-remaining').textContent = Math.max(0, Number(button.dataset.pointsBalance) - Number(button.dataset.rewardCost));
            const error = document.getElementById('redemption-form-message');
            if (error) {
                const shortage = Math.max(0, Number(button.dataset.rewardCost) - Number(button.dataset.pointsBalance));
                error.className = 'alert alert-error';
                error.textContent = shortage > 0 ? 'You need ' + shortage + ' more points to redeem this reward.' : '';
                error.hidden = shortage === 0;
            }
            const submit = document.getElementById('submit-redemption');
            submit.disabled = Number(button.dataset.pointsBalance) < Number(button.dataset.rewardCost) || Number(button.dataset.stock) < 1;
            submit.textContent = 'Confirm redemption';
            redemptionDialog.showModal();
        });
    });
    document.querySelectorAll('[data-close-redemption]').forEach(function (button) { button.addEventListener('click', function () { redemptionDialog?.close(); }); });
    if (redemptionForm) redemptionForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = document.getElementById('submit-redemption');
        const message = document.getElementById('redemption-form-message');
        if (submit.disabled) return;
        submit.disabled = true;
        submit.textContent = 'Processing…';
        if (message) { message.hidden = true; message.textContent = ''; }
        try {
            const response = await fetch(redemptionForm.action, { method: 'POST', body: new FormData(redemptionForm), headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Redemption could not be processed.');
            if (message) { message.className = 'alert alert-success'; message.textContent = 'Redemption submitted successfully. Updating your balance…'; message.hidden = false; }
            window.setTimeout(function () { window.location.reload(); }, 650);
        } catch (error) {
            if (message) { message.className = 'alert alert-error'; message.textContent = error.message || 'Redemption could not be processed.'; message.hidden = false; }
            submit.disabled = false;
            submit.textContent = 'Confirm redemption';
        }
    });

    const flash = document.querySelector('.alert');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.3s ease';
            setTimeout(() => flash.remove(), 350);
        }, 4000);
    }
});
