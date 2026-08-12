import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                   // css
                   'resources/scss/icons.scss',
                   'resources/scss/app.scss',
                   'node_modules/glightbox/dist/css/glightbox.min.css',
                   'node_modules/flatpickr/dist/flatpickr.min.css',
                   'node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                   'node_modules/datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css',
                   'node_modules/datatables.net-keytable-bs5/css/keyTable.bootstrap5.min.css',
                   'node_modules/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css',
                   'node_modules/datatables.net-select-bs5/css/select.bootstrap5.min.css',
                   'node_modules/simple-datatables/dist/style.css',
                   'node_modules/jsvectormap/dist/jsvectormap.min.css',
                   
                   // Javascript
                   'resources/js/app.js',  
                   'resources/js/pages/crm-dashboard.init.js',
                   'resources/js/pages/analytics-dashboard.init.js',
                   'resources/js/pages/ecommerce-dashboard.init.js',
                   'resources/js/pages/widgets.init.js',
                   'resources/js/pages/glightbox.init.js',
                   'resources/js/pages/form-picker.js',
                   'resources/js/pages/datatable.init.js',
                   'resources/js/pages/coming-soon.init.js',
   
                   // Calendar
                   'resources/js/pages/demo.calendar.js',
   
                   // Chart
                   'resources/js/pages/apexcharts-line.init.js',
                   'resources/js/pages/apexcharts-column.init.js',
                   'resources/js/pages/apexcharts-bar.init.js',
                   'resources/js/pages/apexcharts-mixed.init.js',
                   'resources/js/pages/apexcharts-boxplot.init.js',
                   'resources/js/pages/apexcharts-bubble.init.js',
                   'resources/js/pages/apexcharts-candlestick.init.js',
                   'resources/js/pages/apexcharts-funnel.init.js',
                   'resources/js/pages/apexcharts-heatmap.init.js',
                   'resources/js/pages/apexcharts-pie.init.js',
                   'resources/js/pages/apexcharts-polararea.init.js',
                   'resources/js/pages/apexcharts-radar.init.js',
                   'resources/js/pages/apexcharts-radialbar.init.js',
                   'resources/js/pages/apexcharts-range-area.init.js',
                   'resources/js/pages/apexcharts-scatter.init.js',
                   'resources/js/pages/apexcharts-treemap.init.js',
   
                   // Google and Vector Map
                   'resources/js/pages/vector-maps.init.js',
                   'resources/js/pages/google-maps-init.js',
   
                   // Vector Map js
                   'resources/js/pages/iraq-map.js',
                   'resources/js/pages/canada-map.js',
                   'resources/js/pages/russia-map.js',
                   'resources/js/pages/spain-map.js',
                   'resources/js/pages/us-mill-en-map.js',
                   'resources/js/pages/us-lcc-en-map.js',
                   'resources/js/pages/us-merc-en-map.js',
                   'resources/js/pages/vector-maps.init.js',
   
                   // Simple Datatables
                   'resources/js/pages/simple-datatables.init.js',
            ],
            refresh: true,
        }),
    ],
});
