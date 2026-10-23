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
    };

    let marker = null;
    const latValue = config.latInput && config.latInput.value ? Number(config.latInput.value) : null;
    const lngValue = config.lngInput && config.lngInput.value ? Number(config.lngInput.value) : null;

    if (latValue && lngValue) {
        marker = L.marker([latValue, lngValue], { draggable: !!config.editable }).addTo(map);
        updateCoordinateValue(latValue, lngValue);
    } else {
        marker = L.marker([defaultLat, defaultLng], { draggable: !!config.editable }).addTo(map);
        updateCoordinateValue(defaultLat, defaultLng);
    }

    if (config.editable) {
        marker.on('dragend', function () {
            const point = marker.getLatLng();
            updateCoordinateValue(point.lat, point.lng);
        });

        map.on('click', function (event) {
            marker.setLatLng(event.latlng);
            updateCoordinateValue(event.latlng.lat, event.latlng.lng);
        });
    }

    if (Array.isArray(config.markers) && config.markers.length > 0) {
        config.markers.forEach(function (item) {
            const point = [Number(item.lat), Number(item.lng)];
            const popup = '<strong>' + item.reference_number + '</strong><br>' + item.address + '<br>' + item.status;
            L.marker(point).addTo(map).bindPopup(popup);
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
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 14);
                updateCoordinateValue(lat, lng);
            }, function () {
                alert('Your browser denied location access. You can still click the map to choose a pickup location.');
            });
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

        markers.forEach(function (markerData) {
            const marker = L.marker([Number(markerData.lat), Number(markerData.lng)]).addTo(map);
            marker.bindPopup('<strong>' + markerData.reference_number + '</strong><br>' + markerData.address + '<br>' + markerData.status + ' (' + markerData.service_type + ')');
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
