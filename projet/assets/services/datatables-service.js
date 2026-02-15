// DataTables configuration pour TalChif - Style personnalisé
// Version sans modules ES : on utilise la version jQuery
// chargée via les CDN dans base.html.twig (jQuery + DataTables + Buttons).
//
// IMPORTANT :
// - On suppose que jQuery et DataTables sont disponibles globalement
//   (window.jQuery / window.$ et $.fn.DataTable).
// - Ce fichier est chargé en type "module", mais on n'utilise ici que l'API globale.
//
// Objectif :
// - Fournir une API simple `DataTableOS.init(selector, options)` pour initialiser
//   les tableaux avec les réglages et le style TalChif.

const DataTableOS = {
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
            className: 'talchif-datatable',
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

    // Initialiser un tableau avec style TalChif
    init: function(selector, options = {}) {
        try {
            console.log('Initialisation de DataTables (TalChif):', selector);

            if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
                console.error('DataTables (jQuery) n\'est pas chargé. Vérifiez les scripts CDN dans base.html.twig.');
                return null;
            }

            const settings = this.getSettings(options);
            const table = jQuery(selector).DataTable(settings);

            // Appliquer le style TalChif
            this.applyStyle(table);

            console.log('DataTables initialisé avec succès (TalChif)');
            return table;
        } catch (error) {
            console.error('Erreur lors de l\'initialisation de DataTables:', error);
            return null;
        }
    },

    // Appliquer le style TalChif aux DataTables
    applyStyle: function(table) {
        try {
            const wrapper = table.table().container();
            const tableEl = wrapper ? wrapper.querySelector('table') : null;
            if (tableEl) {
                tableEl.classList.add('table', 'talchif-datatable');
            }
            this.customizeControls(table);
            this.customizePagination(table);
        } catch (error) {
            console.error('Erreur lors de l\'application du style:', error);
        }
    },

    // Personnaliser les contrôles (vanilla JS pour ne pas dépendre de jQuery dans le DOM)
    customizeControls: function(table) {
        try {
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
        } catch (error) {
            console.error('Erreur lors de la personnalisation des contrôles:', error);
        }
    },

    // Personnaliser la pagination (vanilla JS)
    customizePagination: function(table) {
        try {
            const wrapper = table.table().container();
            if (!wrapper) return;

            wrapper.querySelectorAll('.paginate_button').forEach(function(btn) {
                btn.classList.add('btn', 'btn-sm');
                if (btn.classList.contains('current')) btn.classList.add('btn-primary');
                if (btn.classList.contains('disabled')) btn.classList.add('btn-disabled');
            });
        } catch (error) {
            console.error('Erreur lors de la personnalisation de la pagination:', error);
        }
    }
};

// Exporter pour utilisation dans les modules
export default DataTableOS;

// Le rendre aussi disponible globalement (au cas où on l'appelle sans import)
window.DataTableOS = DataTableOS;

