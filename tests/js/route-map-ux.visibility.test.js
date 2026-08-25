import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync('public/js/route-map-ux.js', 'utf8');
let circleCount = 0;
let markerCount = 0;
let polylineCount = 0;
let removedCount = 0;

function createMap() {
    const container = { appendChild() {} };
    return { getContainer: () => container, removeLayer() { removedCount++; } };
}

const document = {
    head: { appendChild() {} },
    getElementById: () => null,
    createElement: () => ({
        style: {},
        setAttribute() {},
        querySelectorAll: () => [],
        remove() {},
    }),
};

const L = {
    latLngBounds: () => ({
        extend() {},
        isValid: () => false,
        pad() { return this; },
    }),
    polyline: () => ({
        addTo() { polylineCount++; return this; },
        getBounds: () => ({ isValid: () => false }),
    }),
    circle: () => ({
        addTo() { circleCount++; return this; },
        bindPopup() { return this; },
    }),
    marker: () => ({
        addTo() { markerCount++; return this; },
        bindPopup() { return this; },
    }),
    divIcon: options => options,
};

const window = { L };
vm.runInNewContext(source, { window, document, L, Set, Map, Array, Number, String, Boolean, Math, console });

const routes = ids => ids.map(id => {
    const offset = Number(id) * 0.001;
    return {
        id,
        name: 'Route ' + id,
        map_variant_geometries: [{
            direction: 'outbound',
            polyline_coordinates: [[14.55 + offset, 121.07], [14.56 + offset, 121.08]],
            stops: [
                { id: id * 10 + 1, name: 'Shared Stop A', lat: 14.55 + offset, lng: 121.07, sequence: 1, radius_meters: 30 },
                { id: id * 10 + 2, name: 'Shared Stop B', lat: 14.55 + offset, lng: 121.07, sequence: 2, radius_meters: 30 },
            ],
        }],
    };
});

const map = createMap();
let state = window.GoPasigRouteMapUX.mount({ map, routes: [] });
assert.equal(state.initializedVisibility, undefined);
assert.deepEqual([...state.visibleRoutes], []);

state = window.GoPasigRouteMapUX.mount({ map, routes: routes([5, 6, 7]) });
assert.deepEqual([...state.visibleRoutes].sort(), ['5', '6', '7']);
assert.equal(state.stopGeofences.length, 3);
assert.equal(state.markers.length, 3);

state.visibleRoutes.delete('6');
window.GoPasigRouteMapUX.mount({ map, routes: routes([5, 6, 7]) });
assert.deepEqual([...state.visibleRoutes].sort(), ['5', '7']);
assert.equal(state.stopGeofences.length, 2);
assert.equal(state.markers.length, 2);

window.GoPasigRouteMapUX.mount({ map, routes: routes([5, 6, 7, 8]) });
assert.deepEqual([...state.visibleRoutes].sort(), ['5', '7', '8']);
assert.equal(state.stopGeofences.length, 3);

window.GoPasigRouteMapUX.mount({ map, routes: routes([6, 7, 8]) });
assert.deepEqual([...state.visibleRoutes].sort(), ['7', '8']);
assert.equal(state.stopGeofences.length, 2);

const normalized = window.GoPasigRouteMapUX._normalizedStops(routes([5]));
assert.equal(normalized.length, 1);
assert.equal(normalized[0].memberships.length, 2);

const changedMap = createMap();
window.GoPasigRouteMapUX.mount({ map: changedMap, routes: routes([1, 2, 3, 4]) });
state = window.GoPasigRouteMapUX.mount({ map: changedMap, routes: routes([5, 6, 7]) });
assert.deepEqual([...state.visibleRoutes].sort(), ['5', '6', '7']);
assert.equal(state.stopGeofences.length, 3);
assert.ok(circleCount > 0);
assert.ok(removedCount > 0);

const headerControlledMap = createMap();
state = window.GoPasigRouteMapUX.mount({ map: headerControlledMap, routes: routes([5, 6, 7]), showControl: false });
assert.equal(state.control, null);
window.GoPasigRouteMapUX.setDirectionVisibility(headerControlledMap, 'outbound', false);
assert.equal(state.directions.outbound, false);
window.GoPasigRouteMapUX.setDirectionVisibility(headerControlledMap, 'outbound', true);
assert.equal(state.directions.outbound, true);

console.log('route-map-ux visibility tests passed');
