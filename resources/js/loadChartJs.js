// Loads Chart.js from CDN on demand (only the report pages that actually
// render charts need it) and caches the loading promise so multiple
// components mounting at once don't inject the script twice.
let chartPromise = null;

export function loadChartJs() {
    if (window.Chart) return Promise.resolve(window.Chart);
    if (chartPromise) return chartPromise;

    chartPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js';
        script.onload = () => resolve(window.Chart);
        script.onerror = reject;
        document.head.appendChild(script);
    });

    return chartPromise;
}
