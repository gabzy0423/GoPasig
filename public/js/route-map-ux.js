(function (window) {
    'use strict';

    const stateKey = '__goPasigRouteMapUx';
    const palette = ['#378ADD', '#639922', '#BA7517', '#E24B4A', '#0F6E56', '#DC2626'];

    function esc(value) {
        return String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[c]));
    }

    function routeColor(route, index) {
        return route.color || palette[index % palette.length];
    }

    function variantsFor(route) {
        return Array.isArray(route.map_variant_geometries) ? route.map_variant_geometries : [];
    }

    function directionVisible(state, direction) {
        const value = String(direction || '').toLowerCase();
        if (value.includes('in')) return state.directions.inbound;
        if (value.includes('out')) return state.directions.outbound;
        return true;
    }

    function ensureStyles() {
        if (document.getElementById('gopasig-route-map-ux-styles')) return;
        const style = document.createElement('style');
        style.id = 'gopasig-route-map-ux-styles';
        style.textContent = `
            .gopasig-route-map-ux { background:#fff; border:1px solid rgba(15,23,42,.14); border-radius:8px; box-shadow:0 2px 8px rgba(15,23,42,.12); color:#172033; font:12px/1.35 system-ui,sans-serif; max-width:min(290px,calc(100vw - 32px)); padding:9px 10px; }
            .gopasig-route-map-ux__title { font-weight:700; margin-bottom:5px; }
            .gopasig-route-map-ux__note { color:#64748b; font-size:10px; margin-bottom:7px; }
            .gopasig-route-map-ux__routes { display:flex; flex-wrap:wrap; gap:4px; }
            .gopasig-route-map-ux button { background:#f8fafc; border:1px solid #cbd5e1; border-radius:5px; color:#334155; cursor:pointer; font:inherit; padding:4px 6px; }
            .gopasig-route-map-ux button[aria-pressed="true"] { background:#e0f2fe; border-color:#0284c7; color:#075985; }
            .gopasig-route-map-ux__directions { border-top:1px solid #e2e8f0; display:flex; gap:9px; margin-top:7px; padding-top:6px; }
            .gopasig-route-map-ux__direction { align-items:center; display:flex; gap:4px; }
            .gopasig-route-map-ux__direction input { accent-color:#0284c7; }
            .gopasig-route-stop { align-items:center; background:#fff; border:2px solid #334155; border-radius:50%; box-shadow:0 1px 4px rgba(15,23,42,.25); display:flex; font:700 9px/1 system-ui,sans-serif; height:20px; justify-content:center; width:20px; }
            .gopasig-route-stop--terminal { border-radius:5px; height:24px; width:24px; }
            .gopasig-route-stop-geofence { pointer-events:auto; }
        `;
        document.head.appendChild(style);
    }

    function popupFor(stop) {
        const memberships = stop.memberships || [];
        const rows = memberships.map(item => `<div><b>${esc(item.routeName)}</b> &middot; ${esc(item.direction)} &middot; stop ${esc(item.sequence)} of ${esc(item.total)}</div>`).join('');
        const radius = stop.radius ?? Math.max(...memberships.map(item => Number(item.radius) || 0), 100);
        return `<div style="font:12px/1.4 system-ui,sans-serif;min-width:170px"><strong>${esc(stop.name)}</strong>${rows}<div>Geofence radius: ${esc(radius)} m</div></div>`;
    }

    function normalizedStops(routes) {
        const grouped = new Map();
        routes.forEach((route) => variantsFor(route).forEach(variant => {
            const stops = Array.isArray(variant.stops) ? variant.stops : [];
            stops.forEach(stop => {
                const lat = Number(stop.lat), lng = Number(stop.lng);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                const key = `${lat.toFixed(7)},${lng.toFixed(7)}`;
                const item = grouped.get(key) || { lat, lng, name: stop.name, memberships: [] };
                item.memberships.push({
                    routeId: String(route.id),
                    routeName: route.name,
                    direction: variant.direction || 'unknown',
                    sequence: stop.sequence,
                    total: stops.length,
                    radius: stop.radius_meters ?? 100,
                    stopType: stop.stop_type || null,
                    stopId: stop.id || null,
                    stopName: stop.name || null
                });
                if (!item.name) item.name = stop.name;
                grouped.set(key, item);
            });
        }));
        return Array.from(grouped.values());
    }

    function mount(options) {
        if (!options || !options.map || !window.L) return null;
        ensureStyles();
        const map = options.map;
        let state = map[stateKey];
        if (!state) {
            state = map[stateKey] = { routes: [], lines: [], markers: [], stopGeofences: [], visibleRoutes: new Set(), knownRouteIds: new Set(), directions: { outbound: true, inbound: true }, fitted: false, control: null };
        }
        state.routes = Array.isArray(options.routes) ? options.routes : [];
        state.compact = options.compact !== false;
        state.showControl = options.showControl !== false;
        if (!(state.knownRouteIds instanceof Set)) state.knownRouteIds = new Set();
        if (!Array.isArray(state.stopGeofences)) state.stopGeofences = [];

        const routeIds = new Set(state.routes.map(route => String(route.id)));
        if (routeIds.size > 0) {
            if (!state.initializedVisibility) {
                state.visibleRoutes = new Set(routeIds);
                state.initializedVisibility = true;
            } else {
                state.visibleRoutes = new Set(
                    [...state.visibleRoutes].filter(id => routeIds.has(id))
                );
                routeIds.forEach(id => {
                    if (!state.knownRouteIds.has(id)) state.visibleRoutes.add(id);
                });
            }
            state.knownRouteIds = routeIds;
        }
        if (state.showControl) {
            state.control = state.control || createControl(map, state);
        } else if (state.control) {
            state.control.remove();
            state.control = null;
        }
        render(map, state, Boolean(options.fitOnFirstRender));
        return state;
    }

    function createControl(map, state) {
        const control = document.createElement('div');
        control.className = 'gopasig-route-map-ux';
        control.setAttribute('role', 'group');
        control.setAttribute('aria-label', 'Official route visibility');
        control.style.position = 'absolute';
        control.style.left = '12px';
        control.style.top = '12px';
        control.style.zIndex = '1001';
        map.getContainer().appendChild(control);
        return control;
    }

    function renderControl(state) {
        if (!state.control) return;

        const routes = state.routes.filter(route => variantsFor(route).length || route.polyline_coordinates?.length);
        state.control.innerHTML = `<div class="gopasig-route-map-ux__title">Official routes</div><div class="gopasig-route-map-ux__note">Schematic visualization based on official stop coordinates.</div><div class="gopasig-route-map-ux__routes">${routes.map(route => `<button type="button" data-route="${esc(route.id)}" aria-pressed="${state.visibleRoutes.has(String(route.id))}">${esc(route.name)}</button>`).join('')}</div><div class="gopasig-route-map-ux__directions"><label class="gopasig-route-map-ux__direction"><input type="checkbox" data-direction="outbound" ${state.directions.outbound ? 'checked' : ''}> OUT solid</label><label class="gopasig-route-map-ux__direction"><input type="checkbox" data-direction="inbound" ${state.directions.inbound ? 'checked' : ''}> IN dashed</label></div>`;
        state.control.querySelectorAll('[data-route]').forEach(button => button.addEventListener('click', () => {
            const id = String(button.dataset.route);
            state.visibleRoutes.has(id) ? state.visibleRoutes.delete(id) : state.visibleRoutes.add(id);
            render(state.map, state, false);
        }));
        state.control.querySelectorAll('[data-direction]').forEach(input => input.addEventListener('change', () => {
            state.directions[input.dataset.direction] = input.checked;
            render(state.map, state, false);
        }));
    }

    function render(map, state, fit) {
        if (!map) return;
        state.map = map;
        state.lines.forEach(layer => map.removeLayer(layer));
        state.markers.forEach(layer => map.removeLayer(layer));
        (state.stopGeofences || []).forEach(layer => map.removeLayer(layer));
        state.lines = []; state.markers = []; state.stopGeofences = [];
        renderControl(state);
        const bounds = L.latLngBounds([]);
        state.routes.forEach((route, index) => {
            if (!state.visibleRoutes.has(String(route.id))) return;
            const variants = variantsFor(route);
            const items = variants.length ? variants : [{ polyline_coordinates: route.polyline_coordinates, direction: 'outbound' }];
            items.forEach(variant => {
                const coords = variant.polyline_coordinates || [];
                const direction = String(variant.direction || '').toLowerCase();
                if (!coords.length || !directionVisible(state, direction)) return;
                const line = L.polyline(coords, { color: routeColor(route, index), weight: 4, opacity: .9, dashArray: direction.includes('in') ? '8 7' : null }).addTo(map);
                state.lines.push(line); bounds.extend(line.getBounds());
            });
        });
        normalizedStops(state.routes).forEach(stop => {
            const visibleMemberships = stop.memberships.filter(item => state.visibleRoutes.has(String(item.routeId)) && directionVisible(state, item.direction));
            if (!visibleMemberships.length) return;

            const visibleStop = { ...stop, memberships: visibleMemberships };
            const radius = Math.max(...visibleMemberships.map(item => Number(item.radius) || 0), 100);
            visibleStop.radius = radius;

            const circle = L.circle([stop.lat, stop.lng], {
                radius,
                color: '#7c3aed',
                weight: 1,
                opacity: .35,
                fillColor: '#7c3aed',
                fillOpacity: .045,
                dashArray: '4 6',
                className: 'gopasig-route-stop-geofence'
            }).addTo(map);
            circle.bindPopup(popupFor(visibleStop));
            state.stopGeofences.push(circle);

            const terminal = visibleMemberships.some(item => item.sequence === 1 || item.sequence === item.total);
            const marker = L.marker([stop.lat, stop.lng], { icon: L.divIcon({ className: '', html: `<span class="gopasig-route-stop ${terminal ? 'gopasig-route-stop--terminal' : ''}">${terminal ? 'T' : esc(visibleMemberships[0].sequence)}</span>`, iconSize: terminal ? [24,24] : [20,20], iconAnchor: terminal ? [12,12] : [10,10] }) }).addTo(map);
            marker.bindPopup(popupFor(visibleStop));
            state.markers.push(marker); bounds.extend([stop.lat, stop.lng]);
        });
        if (fit && !state.fitted && bounds.isValid()) { map.fitBounds(bounds.pad(.08)); state.fitted = true; }
    }

    function setRouteFilter(map, routeId) {
        const state = map && map[stateKey];
        if (!state) return;
        state.visibleRoutes = routeId === 'all' ? new Set(state.routes.map(route => String(route.id))) : new Set([String(routeId)]);
        render(map, state, false);
    }

    function setDirectionVisibility(map, direction, visible) {
        const state = map && map[stateKey];
        const normalizedDirection = String(direction || '').toLowerCase();
        if (!state || !Object.hasOwn(state.directions, normalizedDirection)) return;

        state.directions[normalizedDirection] = Boolean(visible);
        render(map, state, false);
    }

    window.GoPasigRouteMapUX = { mount, setRouteFilter, setDirectionVisibility, _normalizedStops: normalizedStops };
})(window);
