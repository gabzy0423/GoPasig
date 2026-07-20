/* ============================================================
   GoPasig Admin — Route Geometry Manual Polyline Editor
   route-editor.js
   ============================================================ */

let isGeometryEditingMode = false;
let geometryHistory = [];
let geometryRedoStack = [];
let originalPolylineCoords = [];
let editFeatureGroup = null;
let editDrawControl = null;
let currentGeometryVersion = 0;

// Initialize on page load/ready
document.addEventListener('DOMContentLoaded', () => {
    // Listen for Leaflet Draw edit events on the main map instance
    window.addEventListener('routes-dashboard-loaded', () => {
        setupDrawEvents();
    });
});

function setupDrawEvents() {
    if (!routePreviewMapInstance) return;

    routePreviewMapInstance.on(L.Draw.Event.EDITED, (e) => {
        const layers = e.layers;
        layers.eachLayer((layer) => {
            if (layer instanceof L.Polyline) {
                pushGeometryToHistory();
                const latlngs = layer.getLatLngs().map(ll => [ll.lat, ll.lng]);
                updateGisStats(latlngs);
            }
        });
    });

    routePreviewMapInstance.on(L.Draw.Event.CREATED, (e) => {
        const layer = e.layer;
        if (layer instanceof L.Polyline && editFeatureGroup) {
            pushGeometryToHistory();
            editFeatureGroup.clearLayers();
            editFeatureGroup.addLayer(layer);
            const latlngs = layer.getLatLngs().map(ll => [ll.lat, ll.lng]);
            updateGisStats(latlngs);
        }
    });
}

function pushGeometryToHistory() {
    if (!editFeatureGroup) return;
    const currentCoords = getCurrentEditCoords();
    geometryHistory.push(JSON.parse(JSON.stringify(currentCoords)));
    geometryRedoStack = []; // Clear redo stack on new action
    updateUndoRedoButtons();
}

function getCurrentEditCoords() {
    let coords = [];
    if (editFeatureGroup) {
        editFeatureGroup.eachLayer((layer) => {
            if (layer instanceof L.Polyline) {
                coords = layer.getLatLngs().map(ll => [ll.lat, ll.lng]);
            }
        });
    }
    return coords;
}

function updateUndoRedoButtons() {
    const undoBtn = document.getElementById('btn-gis-undo');
    const redoBtn = document.getElementById('btn-gis-redo');
    if (undoBtn) undoBtn.disabled = geometryHistory.length === 0;
    if (redoBtn) redoBtn.disabled = geometryRedoStack.length === 0;
}

// ── EDIT MODE TOGGLE ─────────────────────────────────────────
function toggleGeometryEditing() {
    if (isGeometryEditingMode) {
        cancelGeometryEditing();
        return;
    }

    const route = routesDataDb.find(r => r.id.toString() === selectedRouteId.toString());
    if (!route) return;

    isGeometryEditingMode = true;
    currentGeometryVersion = route.geometry_version ?? 0;

    // Show toolbar
    document.getElementById('gis-editing-toolbar').classList.remove('hidden');
    document.getElementById('btn-edit-geometry').innerHTML = '<i class="ti ti-x"></i> Cancel Edit';

    // Clear undo/redo stacks
    geometryHistory = [];
    geometryRedoStack = [];
    updateUndoRedoButtons();

    // Prepare feature group for editing
    if (!editFeatureGroup) {
        editFeatureGroup = new L.FeatureGroup();
    }
    routePreviewMapInstance.addLayer(editFeatureGroup);

    // Copy current coordinates
    originalPolylineCoords = route.polyline_coordinates ? [...route.polyline_coordinates] : [];
    
    // Clear static polyline and draw editable one
    if (routePreviewPolyline && routePreviewMapInstance.hasLayer(routePreviewPolyline)) {
        routePreviewMapInstance.removeLayer(routePreviewPolyline);
    }

    const editableLine = L.polyline(originalPolylineCoords, {
        color: '#003F87',
        weight: 4
    });
    editFeatureGroup.addLayer(editableLine);

    // Initialize Draw Control
    if (!editDrawControl) {
        editDrawControl = new L.Control.Draw({
            edit: {
                featureGroup: editFeatureGroup,
                remove: false
            },
            draw: {
                polyline: {
                    shapeOptions: {
                        color: '#003F87',
                        weight: 4
                    }
                },
                polygon: false,
                circle: false,
                rectangle: false,
                marker: false,
                circlemarker: false
            }
        });
    }
    routePreviewMapInstance.addControl(editDrawControl);
    setupDrawEvents();

    updateGisStats(originalPolylineCoords);
}

function cancelGeometryEditing() {
    if (!isGeometryEditingMode) return;

    isGeometryEditingMode = false;
    document.getElementById('gis-editing-toolbar').classList.add('hidden');
    document.getElementById('btn-edit-geometry').innerHTML = '<i class="ti ti-map-pin"></i> Edit Geometry';

    // Remove edit controls and group
    if (editDrawControl) {
        routePreviewMapInstance.removeControl(editDrawControl);
    }
    if (editFeatureGroup) {
        editFeatureGroup.clearLayers();
        routePreviewMapInstance.removeLayer(editFeatureGroup);
    }

    // Restore original static preview
    renderRouteMap(selectedRouteId);
}

// ── UNDO / REDO ──────────────────────────────────────────────
function undoGeometryAction() {
    if (geometryHistory.length === 0 || !editFeatureGroup) return;

    const currentCoords = getCurrentEditCoords();
    geometryRedoStack.push(currentCoords);

    const prevCoords = geometryHistory.pop();
    setEditCoords(prevCoords);
    updateUndoRedoButtons();
    updateGisStats(prevCoords);
}

function redoGeometryAction() {
    if (geometryRedoStack.length === 0 || !editFeatureGroup) return;

    const currentCoords = getCurrentEditCoords();
    geometryHistory.push(currentCoords);

    const nextCoords = geometryRedoStack.pop();
    setEditCoords(nextCoords);
    updateUndoRedoButtons();
    updateGisStats(nextCoords);
}

function setEditCoords(coords) {
    editFeatureGroup.eachLayer((layer) => {
        if (layer instanceof L.Polyline) {
            layer.setLatLngs(coords);
        }
    });
}

// ── DOUGLAS-PEUCKER PREVIEW (SLIDER) ─────────────────────────
function updateSimplificationFromSlider() {
    const slider = document.getElementById('simplification-slider');
    const label = document.getElementById('simplification-label');
    if (!slider || !label || !editFeatureGroup) return;

    const percent = parseInt(slider.value);
    label.textContent = `${percent}%`;

    // Tolerance mapping: 0% = 0.0, 100% = 0.001 degrees
    const tolerance = (percent / 100) * 0.001;

    // Get original or current coordinates
    const baseCoords = originalPolylineCoords;
    if (baseCoords.length < 3) return;

    if (percent === 0) {
        setEditCoords(baseCoords);
        updateGisStats(baseCoords);
        return;
    }

    const simplified = simplifyDP(baseCoords.map(c => ({lat: c[0], lng: c[1] })), tolerance);
    const coordsArray = simplified.map(c => [c.lat, c.lng]);
    setEditCoords(coordsArray);
    updateGisStats(coordsArray);
}

function simplifyDP(points, tolerance) {
    if (points.length < 3) return points;
    let dmax = 0;
    let index = 0;
    let end = points.length - 1;
    for (let i = 1; i < end; i++) {
        let d = perpendicularDistance(points[i], points[0], points[end]);
        if (d > dmax) {
            index = i;
            dmax = d;
        }
    }
    if (dmax > tolerance) {
        let results1 = simplifyDP(points.slice(0, index + 1), tolerance);
        let results2 = simplifyDP(points.slice(index), tolerance);
        return results1.slice(0, results1.length - 1).concat(results2);
    } else {
        return [points[0], points[end]];
    }
}

function perpendicularDistance(p, start, end) {
    let x = p.lng;
    let y = p.lat;
    let x1 = start.lng;
    let y1 = start.lat;
    let x2 = end.lng;
    let y2 = end.lat;

    let dx = x2 - x1;
    let dy = y2 - y1;

    if (dx === 0 && dy === 0) {
        return Math.sqrt(Math.pow(x - x1, 2) + Math.pow(y - y1, 2));
    }

    let numerator = Math.abs(dy * x - dx * y + x2 * y1 - y2 * x1);
    let denominator = Math.sqrt(Math.pow(dx, 2) + Math.pow(dy, 2));
    return numerator / denominator;
}

// ── STATS & QUALITY QA INDICATOR ────────────────────────────
function updateGisStats(coords) {
    const totalVertices = coords.length;
    // Calculate simple distance
    let distanceKm = 0;
    if (totalVertices >= 2) {
        distanceKm = parseFloat(calculatePolylineDistance(coords));
    }
    
    // Update simple info on screen
    const badge = document.getElementById('route-geometry-version-badge');
    if (badge) {
        badge.textContent = `Version: ${currentGeometryVersion} (${totalVertices} pts, ${distanceKm.toFixed(2)} km)`;
    }
}

// ── SAVE TO DATABASE (AJAX PATCH) ───────────────────────────
async function saveEditedGeometry() {
    const coords = getCurrentEditCoords();
    if (coords.length < 2) {
        alert("The route geometry must have at least 2 points.");
        return;
    }

    if (!confirm("Are you sure you want to save the new route geometry?")) return;

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';
    
    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}/geometry`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                polyline_coordinates: coords,
                last_geometry_version: currentGeometryVersion
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert("Route geometry successfully saved!");
            isGeometryEditingMode = false;
            document.getElementById('gis-editing-toolbar').classList.add('hidden');
            document.getElementById('btn-edit-geometry').innerHTML = '<i class="ti ti-map-pin"></i> Edit Geometry';

            if (editDrawControl) {
                routePreviewMapInstance.removeControl(editDrawControl);
            }
            if (editFeatureGroup) {
                editFeatureGroup.clearLayers();
                routePreviewMapInstance.removeLayer(editFeatureGroup);
            }

            // Reload database fleet details
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else if (response.status === 409 && data.conflict) {
            // Concurrent Edit conflict prompt
            if (confirm("Conflict: " + data.message + "\n\nDo you want to reload the latest version? Your local changes will be lost.")) {
                isGeometryEditingMode = false;
                document.getElementById('gis-editing-toolbar').classList.add('hidden');
                document.getElementById('btn-edit-geometry').innerHTML = '<i class="ti ti-map-pin"></i> Edit Geometry';
                if (editDrawControl) routePreviewMapInstance.removeControl(editDrawControl);
                if (editFeatureGroup) {
                    editFeatureGroup.clearLayers();
                    routePreviewMapInstance.removeLayer(editFeatureGroup);
                }

                if (typeof loadDatabaseFleetData === 'function') {
                    await loadDatabaseFleetData();
                }
                syncRoutesWithDatabase();
                renderRoutesTab();
            }
        } else {
            alert("Error: " + (data.message || "Failed to save route geometry."));
        }
    } catch (error) {
        alert("Network connection error. Failed to save route geometry.");
        console.error("Geometry save error:", error);
    }
}

// ── VERSION HISTORY OVERLAY ──────────────────────────────────
let currentHistoryPage = 1;

async function openGeometryHistoryModal() {
    document.getElementById('rm-geometry-history-modal').classList.remove('hidden');
    currentHistoryPage = 1;
    loadGeometryHistory(currentHistoryPage);
}

function closeGeometryHistoryModal() {
    document.getElementById('rm-geometry-history-modal').classList.add('hidden');
}

async function loadGeometryHistory(page = 1) {
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';
    const tbody = document.getElementById('rm-geometry-history-table-body');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-400">Loading version history...</td></tr>';

    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}/geometry/history?page=${page}`);
        const data = await response.json();

        if (response.ok && data.success) {
            renderHistoryTable(data.history);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-rose-500">Failed to load history details.</td></tr>';
        }
    } catch (error) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-rose-500">Server error during history load.</td></tr>';
        console.error("Geometry history fetch error:", error);
    }
}

function renderHistoryTable(paginator) {
    const tbody = document.getElementById('rm-geometry-history-table-body');
    const paginationContainer = document.getElementById('rm-geometry-history-pagination');
    if (!tbody) return;

    const versions = paginator.data;
    if (!versions || versions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-400">No version history records found.</td></tr>';
        if (paginationContainer) paginationContainer.innerHTML = '';
        return;
    }

    tbody.innerHTML = versions.map((version) => {
        const createdDate = new Date(version.created_at).toLocaleString();
        const creatorName = version.creator ? `${version.creator.first_name || ''} ${version.creator.last_name || ''}`.trim() : 'System';
        const label = version.label || (version.restored_from_version ? `↩ Restored from v${version.restored_from_version}` : 'Manual Save');

        return `
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-2 font-bold text-slate-900">v${version.id}</td>
                <td class="p-2">${label}</td>
                <td class="p-2">${version.vertex_count}</td>
                <td class="p-2">${parseFloat(version.length_km).toFixed(2)} km</td>
                <td class="p-2">${creatorName}</td>
                <td class="p-2 text-slate-500">${createdDate}</td>
                <td class="p-2 text-right space-x-1">
                    <button onclick="previewHistoryVersion(${version.id})" class="text-blue-600 hover:underline font-bold">Preview</button>
                    <button onclick="restoreHistoryVersion(${version.id})" class="text-emerald-600 hover:underline font-bold">Restore</button>
                </td>
            </tr>
        `;
    }).join('');

    // Render pagination links
    if (paginationContainer) {
        paginationContainer.innerHTML = `
            <span>Showing Page ${paginator.current_page} of ${paginator.last_page}</span>
            <div class="flex gap-1">
                <button ${paginator.prev_page_url ? '' : 'disabled'} onclick="loadGeometryHistory(${paginator.current_page - 1})" class="px-2 py-1 bg-white border border-slate-200 rounded text-slate-650 hover:bg-slate-50 disabled:opacity-50 disabled:pointer-events-none">Prev</button>
                <button ${paginator.next_page_url ? '' : 'disabled'} onclick="loadGeometryHistory(${paginator.current_page + 1})" class="px-2 py-1 bg-white border border-slate-200 rounded text-slate-650 hover:bg-slate-50 disabled:opacity-50 disabled:pointer-events-none">Next</button>
            </div>
        `;
    }
}

// Preview specific version geometry temporarily as a dashed overlay
let historyPreviewLayer = null;

async function previewHistoryVersion(versionId) {
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';

    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}/geometry/history?version_id=${versionId}`);
        const data = await response.json();

        if (response.ok && data.success) {
            const version = data.version;
            
            // Remove previous preview layer
            if (historyPreviewLayer && routePreviewMapInstance.hasLayer(historyPreviewLayer)) {
                routePreviewMapInstance.removeLayer(historyPreviewLayer);
            }

            // Draw dashed polyline
            historyPreviewLayer = L.polyline(version.polyline_coordinates, {
                color: '#E24B4A',
                weight: 3,
                dashArray: '5, 10',
                opacity: 0.9
            }).addTo(routePreviewMapInstance);

            routePreviewMapInstance.fitBounds(historyPreviewLayer.getBounds(), { padding: [20, 20] });
            alert(`Previewing v${versionId} on the map. Close the history modal to revert.`);
        } else {
            alert("Failed to load preview details.");
        }
    } catch (error) {
        alert("Error loading version preview.");
        console.error("Preview load error:", error);
    }
}

// Restore a historical version
async function restoreHistoryVersion(versionId) {
    if (!confirm(`Are you sure you want to restore the route geometry to version v${versionId}?`)) return;

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';

    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}/geometry/restore`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ version_id: versionId })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert("Route geometry successfully restored!");
            closeGeometryHistoryModal();
            
            // Remove preview layer if any
            if (historyPreviewLayer && routePreviewMapInstance.hasLayer(historyPreviewLayer)) {
                routePreviewMapInstance.removeLayer(historyPreviewLayer);
            }
            historyPreviewLayer = null;

            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else {
            alert("Error: " + (data.message || "Failed to restore version."));
        }
    } catch (error) {
        alert("Failed to connect to the restore endpoint.");
        console.error("Restore error:", error);
    }
}

// ── IMPORT GEOMETRY (GEOJSON / POLYLINE) ────────────────────
function openGeometryImportModal() {
    document.getElementById('rm-geometry-import-modal').classList.remove('hidden');
    document.getElementById('gi-file').value = '';
    document.getElementById('gi-text').value = '';
}

function closeGeometryImportModal() {
    document.getElementById('rm-geometry-import-modal').classList.add('hidden');
}

async function handleGeometryImportSubmit(e) {
    if (e) e.preventDefault();

    const fileInput = document.getElementById('gi-file');
    const textInput = document.getElementById('gi-text');
    const route = routesDataDb.find(r => r.id.toString() === selectedRouteId.toString());
    const clientVersion = route ? route.geometry_version : 0;

    const formData = new FormData();
    formData.append('last_geometry_version', clientVersion);

    if (fileInput && fileInput.files.length > 0) {
        formData.append('file', fileInput.files[0]);
    } else if (textInput && textInput.value.trim() !== '') {
        formData.append('polyline_string', textInput.value.trim());
    } else {
        alert("Please select a file or paste an encoded polyline string to import.");
        return;
    }

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';

    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}/geometry/import`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: formData
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert("Route geometry successfully imported and saved!");
            closeGeometryImportModal();

            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else {
            alert("Failed to import geometry: " + (data.message || "Unknown validation error."));
        }
    } catch (error) {
        alert("Server error during import.");
        console.error("Import error:", error);
    }
}

// ── INTELLIGENT ROUTING PREVIEW WORKFLOW (PHASE 3C) ────────
let activeProposalSessionId = null;
let activeProposalPolyline = null;
let activeProposalExpiresAt = null;
let proposalExpiryInterval = null;

async function fetchProvidersTelemetry() {
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';
    try {
        const response = await fetch(`${baseUrl}/telemetry`);
        const data = await response.json();
        if (response.ok && data.success) {
            renderTelemetryBadges(data.telemetry);
        }
    } catch (error) {
        console.error("Telemetry fetch error:", error);
    }
}

function renderTelemetryBadges(telemetry) {
    const container = document.getElementById('routing-providers-health-container');
    if (!container) return;

    const providers = ['google', 'osrm'];
    container.innerHTML = providers.map(p => {
        const data = telemetry[p];
        if (!data) return '';

        let stateBadgeColor = 'bg-emerald-100 text-emerald-800 border-emerald-250';
        if (data.state === 'Open') {
            stateBadgeColor = 'bg-rose-100 text-rose-800 border-rose-250';
        } else if (data.state === 'Half-Open') {
            stateBadgeColor = 'bg-amber-100 text-amber-850 border-amber-250';
        }

        return `
            <span class="inline-flex items-center px-1.5 py-0.5 rounded border ${stateBadgeColor} font-mono font-bold select-none cursor-help text-[8px]" 
                  title="${data.provider.toUpperCase()} state is ${data.state}. Today requests: ${data.total_requests}. Quota left: ${data.quota_remaining}. Daily cost: $${data.daily_cost}">
                ${data.provider.toUpperCase()}: ${data.average_latency}ms
            </span>
        `;
    }).join('');
}

async function generateRoutePreview() {
    const providerSelect = document.getElementById('route-provider-select');
    const generateBtn = document.getElementById('btn-generate-route');
    if (!providerSelect || !generateBtn) return;

    const provider = providerSelect.value;
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Generating...';

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';

    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}/generate-preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ provider })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            activeProposalSessionId = data.session_id;
            activeProposalExpiresAt = new Date(data.expires_at);

            // Populate comparison metrics cards
            document.getElementById('metric-len-diff').textContent = `${parseFloat(data.comparison.length_difference_km).toFixed(3)} km`;
            document.getElementById('metric-vert-diff').textContent = data.comparison.vertex_difference;
            document.getElementById('metric-bbox-overlap').textContent = `${data.comparison.bounding_box_overlap_percent}%`;
            document.getElementById('metric-hausdorff').textContent = `${parseFloat(data.comparison.hausdorff_distance_meters).toFixed(2)}m`;

            // Populate Quality Score
            const qualityScoreEl = document.getElementById('metric-quality-score');
            qualityScoreEl.textContent = `${data.quality.score} (${data.quality.grade})`;
            // Color grade text
            qualityScoreEl.className = "font-extrabold text-[11px] " + 
                (data.quality.score >= 90 ? "text-emerald-600" : 
                (data.quality.score >= 50 ? "text-amber-600" : "text-rose-600"));

            // Populate Warnings & Recommendations list
            const warningsContainer = document.getElementById('preview-warnings-container');
            const warningsList = document.getElementById('preview-warnings-list');
            warningsList.innerHTML = '';
            
            const warnings = data.quality.warnings || [];
            const recs = data.quality.recommendations || [];

            if (warnings.length > 0 || recs.length > 0) {
                warnings.forEach(w => {
                    const li = document.createElement('li');
                    li.innerHTML = `<span class="text-amber-600 font-extrabold">⚠️</span> ${w}`;
                    warningsList.appendChild(li);
                });
                recs.forEach(r => {
                    const li = document.createElement('li');
                    li.innerHTML = `<span class="text-indigo-500 font-extrabold">💡</span> ${r}`;
                    warningsList.appendChild(li);
                });
                warningsContainer.classList.remove('hidden');
            } else {
                warningsContainer.classList.add('hidden');
            }

            // Reset Fréchet widget
            const frechetVal = document.getElementById('metric-frechet');
            const frechetBtn = document.getElementById('btn-run-frechet');
            
            if (data.comparison.advanced_analysis_performed) {
                frechetVal.textContent = `${parseFloat(data.comparison.frechet_similarity_percent).toFixed(1)}%`;
                frechetBtn.disabled = true;
                frechetBtn.textContent = 'Analyzed';
            } else {
                frechetVal.textContent = 'Not Run';
                frechetBtn.disabled = false;
                frechetBtn.textContent = 'Analyze Shape';
            }

            // Draw proposed geometry on map as indigo dashed polyline
            if (activeProposalPolyline && routePreviewMapInstance.hasLayer(activeProposalPolyline)) {
                routePreviewMapInstance.removeLayer(activeProposalPolyline);
            }

            activeProposalPolyline = L.polyline(data.generated_geometry, {
                color: '#4F46E5',
                weight: 4,
                dashArray: '6, 8',
                opacity: 0.9
            }).addTo(routePreviewMapInstance);

            routePreviewMapInstance.fitBounds(activeProposalPolyline.getBounds(), { padding: [30, 30] });

            // Show proposal card overlay
            document.getElementById('route-preview-proposal-card').classList.remove('hidden');

            // Start countdown timer
            startProposalExpiryTimer();
            // Fetch updated provider telemetry status
            fetchProvidersTelemetry();
        } else {
            alert("Failed to generate route preview: " + (data.message || "Unknown error"));
        }
    } catch (error) {
        alert("Server error while generating route preview.");
        console.error("Generate preview error:", error);
    } finally {
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="ti ti-rotate"></i> Generate';
    }
}

async function runFrechetAnalysis() {
    if (!activeProposalSessionId) return;

    const frechetVal = document.getElementById('metric-frechet');
    const frechetBtn = document.getElementById('btn-run-frechet');
    if (!frechetVal || !frechetBtn) return;

    frechetBtn.disabled = true;
    frechetBtn.innerHTML = '<i class="ti ti-loader animate-spin"></i> Analyzing...';

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';

    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}/advanced-analysis`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ session_id: activeProposalSessionId })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            frechetVal.textContent = `${parseFloat(data.comparison.frechet_similarity_percent).toFixed(1)}%`;
            frechetBtn.textContent = 'Analyzed';

            // Also update score and warnings in case quality adjusted
            const scoreEl = document.getElementById('metric-quality-score');
            scoreEl.textContent = `${data.quality.score} (${data.quality.grade})`;
            scoreEl.className = "font-extrabold text-[11px] " + 
                (data.quality.score >= 90 ? "text-emerald-600" : 
                (data.quality.score >= 50 ? "text-amber-600" : "text-rose-600"));
        } else {
            alert("Advanced shape comparison failed: " + (data.message || "Unknown error"));
            frechetBtn.disabled = false;
            frechetBtn.textContent = 'Analyze Shape';
        }
    } catch (error) {
        alert("Server error during shape analysis.");
        console.error("Advanced analysis error:", error);
        frechetBtn.disabled = false;
        frechetBtn.textContent = 'Analyze Shape';
    }
}

function startProposalExpiryTimer() {
    if (proposalExpiryInterval) clearInterval(proposalExpiryInterval);
    const label = document.getElementById('preview-expiry-label');
    if (!label) return;

    proposalExpiryInterval = setInterval(() => {
        if (!activeProposalExpiresAt) return;
        const diffMs = activeProposalExpiresAt - new Date();
        if (diffMs <= 0) {
            clearInterval(proposalExpiryInterval);
            label.textContent = 'Expired';
            alert("Route generation preview session has expired.");
            rejectRouteProposal(true); // silent / force clean up local state
            return;
        }
        const diffMin = Math.floor(diffMs / 60000);
        const diffSec = Math.floor((diffMs % 60000) / 1000);
        label.textContent = `Expires in ${diffMin}m ${diffSec}s`;
    }, 1000);
}

async function acceptRouteProposal() {
    if (!activeProposalSessionId) return;

    const route = routesDataDb.find(r => r.id.toString() === selectedRouteId.toString());
    const clientVersion = route ? (route.geometry_version ?? 0) : 0;

    if (!confirm("Are you sure you want to ACCEPT this generated geometry? This will update the official route layout.")) return;

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';

    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}/accept-preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                session_id: activeProposalSessionId,
                last_geometry_version: clientVersion
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert("Proposed route geometry successfully saved and applied!");
            cleanupProposalUI();

            // Refresh route data
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else if (response.status === 409 && data.conflict) {
            alert("Optimistic Concurrency Conflict: " + data.message);
            cleanupProposalUI();
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else {
            alert("Failed to accept proposal: " + (data.message || "Unknown error"));
        }
    } catch (error) {
        alert("Server error while accepting proposal.");
        console.error("Accept proposal error:", error);
    }
}

async function rejectRouteProposal(silent = false) {
    if (!activeProposalSessionId) {
        cleanupProposalUI();
        return;
    }

    if (!silent && !confirm("Are you sure you want to REJECT and discard this proposal?")) return;

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';

    try {
        if (!silent) {
            await fetch(`${baseUrl}/${selectedRouteId}/reject-preview`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    session_id: activeProposalSessionId
                })
            });
        }
    } catch (error) {
        console.error("Reject preview request failed:", error);
    } finally {
        cleanupProposalUI();
        renderRouteMap(selectedRouteId);
    }
}

function cleanupProposalUI() {
    activeProposalSessionId = null;
    activeProposalExpiresAt = null;
    if (proposalExpiryInterval) {
        clearInterval(proposalExpiryInterval);
        proposalExpiryInterval = null;
    }

    if (activeProposalPolyline && routePreviewMapInstance.hasLayer(activeProposalPolyline)) {
        routePreviewMapInstance.removeLayer(activeProposalPolyline);
    }
    activeProposalPolyline = null;

    const proposalCard = document.getElementById('route-preview-proposal-card');
    if (proposalCard) {
        proposalCard.classList.add('hidden');
    }
}

// Initial fetch on script load
setTimeout(fetchProvidersTelemetry, 1000);
setInterval(fetchProvidersTelemetry, 45000);
