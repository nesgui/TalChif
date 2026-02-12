// DataTables configuration pour OSEA - Style personnalisé
// Note: DataTables is loaded via CDN in templates, not as ES module

// Configuration globale DataTables avec style OSEA
// Les tableaux reçoivent des données déjà paginées côté serveur (admin users, événements organisateur).
// Le tri/recherche/export DataTables s'appliquent à la page courante uniquement.
// Pour des millions de lignes, prévoir serverSide: true + endpoint AJAX (non implémenté ici).
window.DataTableOS = {
    // Style personnalisé pour les DataTables
    getSettings: function(options = {}) {
        return {
            // Configuration de base : pagination côté client sur la page courante
            paging: true,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            pageLength: 25,
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false,
            responsive: true,
            // Style OSEA
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/French.json',
            },
            // Style personnalisé pour les boutons
            dom: '<"top"f>rt<"bottom"lip>',
            // Options supplémentaires fusionnées
            ...options
        };
    },

    // Configuration pour les exports
    getExportSettings: function() {
        return {
            extend: 'collection',
            text: 'Exporter',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-excel',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-pdf',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Imprimer',
                    className: 'btn btn-print',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ]
        };
    },

    // Initialisation automatique des tableaux avec classe .datatable
    initAll: function() {
        const tables = document.querySelectorAll('.datatable');
        tables.forEach(table => {
            if (!$.fn.DataTable.isDataTable(table)) {
                $(table).DataTable(this.getSettings());
            }
        });
    }
};

// Initialiser quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        window.DataTableOS.initAll();
    }
});
