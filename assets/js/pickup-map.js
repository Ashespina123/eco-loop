function initializePickupMap(config) {
    const mapElement = document.getElementById(config.mapId);
    if (!mapElement || typeof L === 'undefined') {
        return;
    }

    const defaultLat = Number(config.defaultLat ?? 14.5995);
    const defaultLng = Number(config.defaultLng ?? 121.0244);
    const map = L.map(mapElement, { zoomControl: true, scrollWheelZoom: true }).setView([defaultLat, defaultLng], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    const centerIcon = L.divIcon({ className: 'dropoff-center-marker', html: '<span aria-hidden="true">♻</span>', iconSize: [34, 34], iconAnchor: [17, 17] });

    const updateCoordinateValue = function (lat, lng) {
        if (config.latInput) {
            config.latInput.value = Number(lat).toFixed(6);
        }
        if (config.lngInput) {
            config.lngInput.value = Number(lng).toFixed(6);
        }
        if (config.coordValue) {
            config.coordValue.textContent = Number(lat).toFixed(6) + ', ' + Number(lng).toFixed(6);
        }
        mapElement.dispatchEvent(new CustomEvent('ecoloop:pickuplocationchange', {
            bubbles: true,
            detail: { latitude: Number(lat), longitude: Number(lng) }
        }));
    };

    let marker = null;
    const latValue = config.latInput && config.latInput.value ? Number(config.latInput.value) : null;
    const lngValue = config.lngInput && config.lngInput.value ? Number(config.lngInput.value) : null;

    if (latValue !== null && lngValue !== null && Number.isFinite(latValue) && Number.isFinite(lngValue)) {
        marker = L.marker([latValue, lngValue], { draggable: !!config.editable }).addTo(map);
        updateCoordinateValue(latValue, lngValue);
    } else if (!config.allowUnselected) {
        marker = L.marker([defaultLat, defaultLng], { draggable: !!config.editable }).addTo(map);
        updateCoordinateValue(defaultLat, defaultLng);
    } else {
        if (config.coordValue) config.coordValue.textContent = 'No pin selected (optional)';
    }

    if (config.editable) {
        if (marker) marker.on('dragend', function () {
            const point = marker.getLatLng();
            updateCoordinateValue(point.lat, point.lng);
        });

        map.on('click', function (event) {
            if (!marker) marker = L.marker(event.latlng, { draggable: true }).addTo(map);
            marker.setLatLng(event.latlng);
            updateCoordinateValue(event.latlng.lat, event.latlng.lng);
        });
    }

    if (Array.isArray(config.markers) && config.markers.length > 0) {
        config.markers.forEach(function (item) {
            const point = [Number(item.lat), Number(item.lng)];
            const popup = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = item.reference_number || item.name || 'Location';
            popup.appendChild(title);
            popup.appendChild(document.createElement('br'));
            popup.appendChild(document.createTextNode(item.address || ''));
            if (item.accepted_categories) {
                popup.appendChild(document.createElement('br'));
                popup.appendChild(document.createTextNode('Accepts: ' + item.accepted_categories));
            }
            if (item.operating_hours) {
                popup.appendChild(document.createElement('br'));
                popup.appendChild(document.createTextNode('Hours: ' + item.operating_hours));
            }
            if (item.contact_details) {
                popup.appendChild(document.createElement('br'));
                popup.appendChild(document.createTextNode('Contact: ' + item.contact_details));
            }
            if (item.status) {
                popup.appendChild(document.createElement('br'));
                popup.appendChild(document.createTextNode(item.status));
            }
            const marker = L.marker(point, { icon: item.status === 'Verified drop-off center' ? centerIcon : undefined }).addTo(map);
            if (item.status === 'Verified drop-off center') {
                const directions = document.createElement('a');
                directions.href = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(point[0] + ',' + point[1]);
                directions.target = '_blank'; directions.rel = 'noopener'; directions.textContent = 'Get directions';
                popup.appendChild(document.createElement('br')); popup.appendChild(directions);
            }
            marker.bindPopup(popup);
        });
        const group = L.featureGroup(config.markers.map(function (item) {
            return L.marker([Number(item.lat), Number(item.lng)]);
        }));
        map.fitBounds(group.getBounds(), { padding: [24, 24] });
    }

    const gpsButton = document.getElementById('use-my-location');
    if (gpsButton && navigator.geolocation) {
        gpsButton.addEventListener('click', function () {
            navigator.geolocation.getCurrentPosition(function (position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                if (!marker) marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 14);
                updateCoordinateValue(lat, lng);
            }, function () {
                const message = document.getElementById('pickup-location-message');
                if (message) message.textContent = 'Location access was denied or unavailable. Tap the map to choose the pickup point manually.';
            });
        });
    } else if (gpsButton) {
        gpsButton.disabled = true;
        const message = document.getElementById('pickup-location-message');
        if (message) message.textContent = 'Browser location is unavailable. Tap the map to choose the pickup point manually.';
    }

    const clearButton = config.clearButtonId ? document.getElementById(config.clearButtonId) : null;
    if (clearButton) {
        clearButton.addEventListener('click', function () {
            if (marker) map.removeLayer(marker);
            marker = null;
            if (config.latInput) config.latInput.value = '';
            if (config.lngInput) config.lngInput.value = '';
            if (config.coordValue) config.coordValue.textContent = 'No pin selected (optional)';
        });
    }

    return map;
}

document.addEventListener('DOMContentLoaded', function () {
    const pickupMap = document.getElementById('user-pickup-map');
    if (!pickupMap) {
        return;
    }

    const markers = pickupMap.dataset.markers ? JSON.parse(pickupMap.dataset.markers) : [];
    if (typeof L !== 'undefined' && markers.length > 0) {
        const map = L.map('user-pickup-map', { zoomControl: true }).setView([14.5995, 121.0244], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        const centerIcon = L.divIcon({ className: 'dropoff-center-marker', html: '<span aria-hidden="true">♻</span>', iconSize: [34, 34], iconAnchor: [17, 17] });

        markers.forEach(function (markerData) {
            const marker = L.marker([Number(markerData.lat), Number(markerData.lng)], { icon: markerData.status === 'Verified drop-off center' ? centerIcon : undefined }).addTo(map);
            const popup = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = markerData.reference_number || 'Pickup';
            popup.appendChild(title);
            popup.appendChild(document.createElement('br'));
            popup.appendChild(document.createTextNode(markerData.address || ''));
            if (markerData.accepted_categories) {
                popup.appendChild(document.createElement('br'));
                popup.appendChild(document.createTextNode('Accepts: ' + markerData.accepted_categories));
            }
            if (markerData.operating_hours) {
                popup.appendChild(document.createElement('br'));
                popup.appendChild(document.createTextNode('Hours: ' + markerData.operating_hours));
            }
            if (markerData.contact_details) {
                popup.appendChild(document.createElement('br'));
                popup.appendChild(document.createTextNode('Contact: ' + markerData.contact_details));
            }
            if (markerData.status === 'Verified drop-off center') {
                const directions = document.createElement('a');
                directions.href = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(markerData.lat + ',' + markerData.lng);
                directions.target = '_blank'; directions.rel = 'noopener'; directions.textContent = 'Get directions';
                popup.appendChild(document.createElement('br')); popup.appendChild(directions);
            }
            popup.appendChild(document.createElement('br'));
            popup.appendChild(document.createTextNode((markerData.status || '') + (markerData.service_type ? ' (' + markerData.service_type + ')' : '')));
            marker.bindPopup(popup);
        });

        const bounds = markers.map(function (markerData) {
            return [Number(markerData.lat), Number(markerData.lng)];
        });
        const featureGroup = L.featureGroup(bounds.map(function (point) {
            return L.marker(point);
        }));
        map.fitBounds(featureGroup.getBounds(), { padding: [20, 20] });
    }
});
