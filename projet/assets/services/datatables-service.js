// DataTables configuration pour OSEA - Style personnalisé
import DataTable from 'datatables.net-dt';
import 'datatables.net-responsive-dt';
import 'datatables.net-buttons-dt';
import 'datatables.net-buttons/js/dataTables.buttons.min.js';

// Configuration globale DataTables avec style OSEA
window.DataTableOS = {
    // Style personnalisé pour les DataTables
    getSettings: function(options = {}) {
        return {
            // Configuration de base
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Tous"]],
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
        // Ajouter les classes OSEA
        $(table.table()).addClass('table osea-datatable');
        
        // Personnaliser les contrôles
        this.customizeControls(table);
        
        // Personnaliser la pagination
        this.customizePagination(table);
    },

    // Personnaliser les contrôles
    customizeControls: function(table) {
        const wrapper = table.table().container();
        
        // Style pour la recherche
        $(wrapper).find('input[type="search"]').addClass('form-control w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors');
        
        // Style pour le sélecteur de longueur
        $(wrapper).find('select').addClass('form-control px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors');
        
        // Style pour les boutons
        $(wrapper).find('.dt-button').addClass('btn btn-sm');
    },

    // Personnaliser la pagination
    customizePagination: function(table) {
        const wrapper = table.table().container();
        
        // Classes pour la pagination
        $(wrapper).find('.paginate_button').addClass('btn btn-sm');
        $(wrapper).find('.paginate_button.current').addClass('btn-primary');
        $(wrapper).find('.paginate_button.disabled').addClass('btn:disabled');
    }
};

// Exporter pour utilisation globale
export default DataTableOS;
