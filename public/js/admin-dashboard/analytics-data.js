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
let demandForecastData = null;

let driverPerformanceData = [];
let routeComparisonData = [];
let stopBoardingData = [];
let hourlyRidershipData = [];
let historicalTrendData = [];
let historicalDemandData = null;
let maintenanceLogRecordsData = [];
let maintenanceSummaryData = null;
let kpisData = null;
let heatmapData = null;
let predictionRouteData = {};

let isAnalyticsDatabaseLoaded = false;
let analyticsReportingPeriod = {
    preset: 'today',
    label: 'Today',
    start: null,
    end: null
};

function padAnalyticsDatePart(value) {
    return String(value).padStart(2, '0');
}

function formatAnalyticsLocalDate(date) {
    return `${date.getFullYear()}-${padAnalyticsDatePart(date.getMonth() + 1)}-${padAnalyticsDatePart(date.getDate())}`;
}

function getAnalyticsPresetRange(preset) {
    const today = new Date();
    const start = new Date(today);
    const end = new Date(today);
    let label = 'Today';

    if (preset === 'yesterday') {
        start.setDate(today.getDate() - 1);
        end.setDate(today.getDate() - 1);
        label = 'Yesterday';
    } else if (preset === 'last_7_days') {
        start.setDate(today.getDate() - 6);
        label = 'Last 7 Days';
    } else if (preset === 'last_30_days') {
        start.setDate(today.getDate() - 29);
        label = 'Last 30 Days';
    } else if (preset === 'this_month') {
        start.setDate(1);
        label = 'This Month';
    } else if (preset === 'last_month') {
        start.setDate(1);
        start.setMonth(today.getMonth() - 1);
        end.setDate(0);
        label = 'Last Month';
    } else if (preset === 'this_year') {
        start.setMonth(0, 1);
        label = 'This Year';
    }

    return {
        preset,
        label,
        start: formatAnalyticsLocalDate(start),
        end: formatAnalyticsLocalDate(end)
    };
}

function setAnalyticsReportingPeriod(period) {
    analyticsReportingPeriod = period;
    window.analyticsReportingPeriod = analyticsReportingPeriod;
}

setAnalyticsReportingPeriod(getAnalyticsPresetRange('today'));

function applyAnalyticsPayload(data) {
    if (!data || !data.success) return false;

    kpisData = data.kpis || null;
    heatmapData = data.heatmap || null;
    tripData = Array.isArray(data.tripPaxTable) ? data.tripPaxTable : [];
    peakLoadTimelineData = Array.isArray(data.peakLoadTimeline) ? data.peakLoadTimeline : [];
    busCardsData = Array.isArray(data.busSummaryCards) ? data.busSummaryCards : [];
    forecastData = Array.isArray(data.forecastTable) ? data.forecastTable : [];
    demandForecastData = data.demandForecast || null;
    driverPerformanceData = Array.isArray(data.driverPerformance) ? data.driverPerformance : [];
    routeComparisonData = Array.isArray(data.routeComparison) ? data.routeComparison : [];
    stopBoardingData = Array.isArray(data.stopBoarding) ? data.stopBoarding : [];
    hourlyRidershipData = Array.isArray(data.hourlyRidership) ? data.hourlyRidership : [];
    historicalTrendData = Array.isArray(data.historicalTrend) ? data.historicalTrend : [];
    historicalDemandData = data.historicalDemand || null;
    maintenanceLogRecordsData = Array.isArray(data.maintenanceLogRecords) ? data.maintenanceLogRecords : [];
    maintenanceSummaryData = data.maintenanceSummary || null;

    // Keep legacy window consumers in sync with the shared analytics state.
    window.kpisData = kpisData;
    window.heatmapData = heatmapData;
    window.tripData = tripData;
    window.peakLoadTimelineData = peakLoadTimelineData;
    window.busCardsData = busCardsData;
    window.busSummaryCardsData = busCardsData;
    window.forecastData = forecastData;
    window.demandForecastData = demandForecastData;
    window.driverPerformanceData = driverPerformanceData;
    window.routeComparisonData = routeComparisonData;
    window.stopBoardingData = stopBoardingData;
    window.hourlyRidershipData = hourlyRidershipData;
    window.historicalTrendData = historicalTrendData;
    window.historicalDemandData = historicalDemandData;
    window.maintenanceLogRecordsData = maintenanceLogRecordsData;
    window.maintenanceSummaryData = maintenanceSummaryData;
    window.busCapacityLimit = data.busCapacityLimit || 45;

    const summaryDisplay = (summary) => {
        if (!summary || summary.status === 'no_official_service') {
            return {
                vol: 'No official service',
                rec: 'Not applicable',
                busiest: 'No official operating window tomorrow'
            };
        }

        if (summary.expected_commuters === null || summary.expected_commuters === undefined) {
            return {
                vol: 'Insufficient history',
                rec: 'Not issued',
                busiest: `${summary.ready_slots || 0} of ${summary.service_slots || 0} service slots ready`
            };
        }

        const peak = summary.peak || {};
        return {
            vol: `${summary.expected_commuters} expected check-ins`,
            rec: `${summary.peak_minimum_buses || 0} at peak`,
            busiest: peak.time_slot
                ? `${peak.direction_label} | ${peak.time_slot} | ${peak.confidence_label} confidence`
                : summary.status_label
        };
    };

    predictionRouteData = {
        all: summaryDisplay(demandForecastData && demandForecastData.overall_summary)
    };

    const routeForecasts = demandForecastData && Array.isArray(demandForecastData.route_summaries)
        ? demandForecastData.route_summaries
        : [];
    routeForecasts.forEach(summary => {
        predictionRouteData[summary.route_name] = summaryDisplay(summary);
    });

    isAnalyticsDatabaseLoaded = true;

    if (typeof updateLayoutExportButton === 'function') {
        updateLayoutExportButton();
    }

    return true;
}

async function loadDatabaseAnalyticsData() {
    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.analyticsUrl) ? window.GoPasigConfig.analyticsUrl : '/admin/api/analytics';
        const url = new URL(baseUrl, window.location.origin);
        if (analyticsReportingPeriod.start && analyticsReportingPeriod.end) {
            url.searchParams.set('start', analyticsReportingPeriod.start);
            url.searchParams.set('end', analyticsReportingPeriod.end);
            url.searchParams.set('period', analyticsReportingPeriod.preset);
        }
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
