/**
 * Responsive Tables - TalChif
 * Auto-assigne data-label sur chaque <td> à partir des <th> du <thead>.
 * Gère les tables normales, DataTables, Turbo Frames et Turbo Drive.
 */
(function() {
    function applyDataLabels(scope) {
        var root = scope || document;
        var tables = root.querySelectorAll('table.table, table.dataTable');

        tables.forEach(function(table) {
            var headers = [];
            var ths = table.querySelectorAll('thead th');
            ths.forEach(function(th) {
                headers.push(th.textContent.trim());
            });

            if (headers.length === 0) return;

            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                var cells = row.querySelectorAll('td');
                cells.forEach(function(td, i) {
                    if (headers[i] && td.getAttribute('data-label') !== headers[i]) {
                        td.setAttribute('data-label', headers[i]);
                    }
                });
            });
        });
    }

    // Appliquer avec un léger délai pour laisser DataTables s'initialiser
    function applyWithDelay(scope) {
        applyDataLabels(scope);
        setTimeout(function() { applyDataLabels(scope); }, 500);
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        applyWithDelay();
    });

    // Turbo Frame navigation
    document.addEventListener('turbo:frame-render', function(e) {
        applyWithDelay(e.target);
    });

    // Turbo Drive navigation
    document.addEventListener('turbo:load', function() {
        applyWithDelay();
    });

    // Fallback: si le DOM est déjà prêt
    if (document.readyState !== 'loading') {
        applyWithDelay();
    }
})();
