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
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            deferRender: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/fr.json',
                searchPlaceholder: "Rechercher...",
                lengthMenu: "Afficher _MENU_ éléments",
                info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
                infoEmpty: "Affichage de 0 à 0 sur 0 éléments",
                infoFiltered: "(filtré de _MAX_ éléments au total)",
                zeroRecords: "Aucun enregistrement trouvé",
                emptyTable: "Aucune donnée disponible",
                paginate: {
                    first: "Premier",
                    previous: "Précédent",
                    next: "Suivant",
                    last: "Dernier"
                }
            },
            responsive: true,
            autoWidth: false,
            // Style personnalisé
            dom: 'Blfrtip',
            // Classes personnalisées
            className: 'osea-datatable',
            // Boutons d'export
            buttons: [
                {
                    extend: 'excel',
                    text: 'Excel',
                    className: 'btn btn-sm btn-success',
                    exportOptions: {
                        title: 'Exporter en Excel'
                    }
                },
                {
                    extend: 'csv',
                    text: 'CSV',
                    className: 'btn btn-sm btn-info',
                    exportOptions: {
                        title: 'Exporter en CSV'
                    }
                },
                {
                    extend: 'print',
                    text: 'Imprimer',
                    className: 'btn btn-sm btn-secondary',
                    exportOptions: {
                        title: 'Imprimer le tableau'
                    }
                },
                {
                    extend: 'colvis',
                    text: 'Colonnes',
                    className: 'btn btn-sm btn-outline'
                }
            ],
            ...options
        };
    },

    // Initialiser un tableau avec style OSEA
    init: function(selector, options = {}) {
        const settings = this.getSettings(options);
        const table = new DataTable(selector, settings);
        
        // Appliquer le style OSEA
        this.applyStyle(table);
        
        return table;
    },

    // Appliquer le style OSEA aux DataTables
    applyStyle: function(table) {
        const wrapper = table.table().container();
        const tableEl = wrapper ? wrapper.querySelector('table') : null;
        if (tableEl) {
            tableEl.classList.add('table', 'osea-datatable');
        }
        this.customizeControls(table);
        this.customizePagination(table);
    },

    // Personnaliser les contrôles (vanilla JS pour ne pas dépendre de jQuery)
    customizeControls: function(table) {
        const wrapper = table.table().container();
        if (!wrapper || !wrapper.querySelector) return;

        const searchInput = wrapper.querySelector('input[type="search"]');
        if (searchInput) {
            searchInput.classList.add('form-control');
            searchInput.setAttribute('placeholder', 'Rechercher...');
        }

        const selects = wrapper.querySelectorAll('select');
        selects.forEach(function(sel) { sel.classList.add('form-control'); });

        const buttons = wrapper.querySelectorAll('.dt-button');
        buttons.forEach(function(btn) { btn.classList.add('btn', 'btn-sm'); });
    },

    // Personnaliser la pagination (vanilla JS)
    customizePagination: function(table) {
        const wrapper = table.table().container();
        if (!wrapper) return;

        wrapper.querySelectorAll('.paginate_button').forEach(function(btn) {
            btn.classList.add('btn', 'btn-sm');
            if (btn.classList.contains('current')) btn.classList.add('btn-primary');
            if (btn.classList.contains('disabled')) btn.classList.add('btn-disabled');
        });
    }
};

// Exporter pour utilisation globale
export default DataTableOS;

// Also make available globally for non-module scripts
window.DataTableOS = DataTableOS;
