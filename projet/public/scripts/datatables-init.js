document.addEventListener('DOMContentLoaded', function () {

    // Configuration par défaut FR pour toutes les DataTables
    const defaultConfig = {
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
        },
        pageLength: 25,
        responsive: true,
        autoWidth: false,
        dom: '<"dt-top"lf>rt<"dt-bottom"ip>',
        initComplete: function () {
            this.api().columns().every(function () {
                const col = this;
                const header = $(col.header());
                header.attr('title', header.text());
            });
        }
    };

    // Initialiser toutes les tables marquées .dt-table
    document.querySelectorAll('table.dt-table').forEach(function (table) {
        const config = Object.assign({}, defaultConfig);

        // Lire les options depuis les data-attributes
        if (table.dataset.order) {
            config.order = JSON.parse(table.dataset.order);
        }
        if (table.dataset.pageLength) {
            config.pageLength = parseInt(table.dataset.pageLength);
        }
        if (table.dataset.noSearch === 'true') {
            config.searching = false;
        }
        if (table.dataset.noPaging === 'true') {
            config.paging = false;
        }

        // Colonnes non triables (ex: colonne Actions)
        const noSortCols = [];
        table.querySelectorAll('th[data-no-sort]').forEach(function (th) {
            noSortCols.push($(th).index());
        });
        if (noSortCols.length > 0) {
            config.columnDefs = [{ orderable: false, targets: noSortCols }];
        }

        $(table).DataTable(config);
    });
});
