// =========================================================================
// ==================== REPORTS & ANALYTICS MODULE ========================
// =========================================================================

// Global Chart.js instances tracker
let charts = {};
let analyticsEventsBound = false;

// Dynamic data variables with fallbacks
// Starts empty - populated exclusively by loadDatabaseAnalyticsData() from /admin/api/analytics.
// No fake fallback data: if the DB is empty, the UI shows an empty-state message.
let tripData = [];
let peakLoadTimelineData = [];

let busCardsData = []; // Populated from DB via loadDatabaseAnalyticsData()

let forecastData = []; // Populated from DB via loadDatabaseAnalyticsData()

let driverPerformanceData = [];
let routeComparisonData = [];
let stopBoardingData = [];
let hourlyRidershipData = [];
let historicalTrendData = [];
let kpisData = null;
let heatmapData = null;
let predictionRouteData = {};

let isAnalyticsDatabaseLoaded = false;

function applyAnalyticsPayload(data) {
    if (!data || !data.success) return false;

    kpisData = data.kpis || null;
    heatmapData = data.heatmap || null;
    tripData = Array.isArray(data.tripPaxTable) ? data.tripPaxTable : [];
    peakLoadTimelineData = Array.isArray(data.peakLoadTimeline) ? data.peakLoadTimeline : [];
    busCardsData = Array.isArray(data.busSummaryCards) ? data.busSummaryCards : [];
    forecastData = Array.isArray(data.forecastTable) ? data.forecastTable : [];
    driverPerformanceData = Array.isArray(data.driverPerformance) ? data.driverPerformance : [];
    routeComparisonData = Array.isArray(data.routeComparison) ? data.routeComparison : [];
    stopBoardingData = Array.isArray(data.stopBoarding) ? data.stopBoarding : [];
    hourlyRidershipData = Array.isArray(data.hourlyRidership) ? data.hourlyRidership : [];
    historicalTrendData = Array.isArray(data.historicalTrend) ? data.historicalTrend : [];

    // Keep legacy window consumers in sync with the shared analytics state.
    window.kpisData = kpisData;
    window.heatmapData = heatmapData;
    window.tripData = tripData;
    window.peakLoadTimelineData = peakLoadTimelineData;
    window.busCardsData = busCardsData;
    window.busSummaryCardsData = busCardsData;
    window.forecastData = forecastData;
    window.driverPerformanceData = driverPerformanceData;
    window.routeComparisonData = routeComparisonData;
    window.stopBoardingData = stopBoardingData;
    window.hourlyRidershipData = hourlyRidershipData;
    window.historicalTrendData = historicalTrendData;
    window.busCapacityLimit = data.busCapacityLimit || 45;

    // Forecast recommendation cards stay neutral until the demand/TripLog foundation is reliable.
    predictionRouteData = {
        all: {
            vol: 'No data',
            rec: 'No recommendation data',
            busiest: 'No reliable forecast data'
        }
    };

    routeComparisonData.forEach(r => {
        predictionRouteData[r.route] = {
            vol: 'No data',
            rec: 'No recommendation data',
            busiest: 'No reliable forecast data'
        };
    });

    isAnalyticsDatabaseLoaded = true;

    return true;
}

async function loadDatabaseAnalyticsData() {
    try {
        const url = (window.GoPasigConfig && window.GoPasigConfig.analyticsUrl) ? window.GoPasigConfig.analyticsUrl : '/admin/api/analytics';
        const response = await fetch(url);
        const data = await response.json();

        if (applyAnalyticsPayload(data)) {
            console.log("Analytics Controller data successfully fetched!");

            // 1. Update KPI widgets
            if (typeof updateAnalyticsKPIs === 'function') {
                updateAnalyticsKPIs();
            }

            // 2. Re-trigger rendering functions and Chart draws
            if (typeof initAnalyticsDashboard === 'function') {
                initAnalyticsDashboard();
            }
        }
    } catch (error) {
        console.error("Failed to load dynamic database analytics data:", error);
    }
}

// Fetch database records immediately on load
loadDatabaseAnalyticsData();
