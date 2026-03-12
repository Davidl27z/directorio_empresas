    </main>
    <footer class="site-footer text-center py-3 mt-5">
        <p>&copy; 2024 Directorio de Empresas. Todos los derechos reservados.</p>
    </footer>
    <script src="<?= $base_url ?>/js/bootstrap.bundle.min.js"></script>
    <?php if (!isset($base_url)) $base_url = ''; ?>
    <!-- Ensure Chart.js is available as a fallback and then load main.js -->
    <?php if (!empty($require_chartjs)): ?>
        <script src="<?= $base_url ?>/js/chart.min.js"></script>
    <?php endif; ?>
    <script src="<?= $base_url ?>/js/main.js"></script>
    <script>
    // Fallback: if initStackedBarChart isn't defined (main.js failed or empty), create chart directly
    (function(){
        function createDirectChart(){
            try {
                if (typeof Chart === 'undefined') {
                    console.error('[footer] Chart.js not available');
                    return;
                }
                // Expecting global arrays: _labels, _aprobadas, _pendientes
                if (typeof _labels === 'undefined') {
                    // admin/dashboard.php defines these before footer; if not present, skip
                    return;
                }
                var canvas = document.getElementById('categoriesChart');
                if (!canvas) return;
                var ctx = canvas.getContext('2d');
                if (window._charts && window._charts['categoriesChart']) {
                    try { window._charts['categoriesChart'].destroy(); } catch(e){}
                }
                window._charts = window._charts || {};
                window._charts['categoriesChart'] = new Chart(ctx, {
                    type: 'bar',
                    data: { labels: _labels, datasets: [ { label: 'Aprobadas', data: _aprobadas, backgroundColor: 'rgba(40,167,69,0.9)' }, { label: 'Pendientes', data: _pendientes, backgroundColor: 'rgba(255,193,7,0.9)' } ] },
                    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top'}}, scales:{ x:{stacked:true}, y:{stacked:true, beginAtZero:true} } }
                });
                console.debug('[footer] Direct chart created');
            } catch (e) { console.error('[footer] createDirectChart error', e); }
        }

        // If helper exists, prefer it; otherwise fallback after DOM ready
        if (typeof initStackedBarChart === 'function') {
            // nothing here; admin/dashboard.js will call it
        } else {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', createDirectChart);
            } else {
                createDirectChart();
            }
        }
    })();
    </script>
</body>
</html>