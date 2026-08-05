(function () {
    'use strict';

    function wireRemoveButton(row) {
        var button = row.querySelector('.remove-row');
        if (!button) {
            return;
        }
        button.addEventListener('click', function () {
            var container = row.parentElement;
            if (container.children.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(function (input) {
                    input.value = '';
                });
                row.querySelectorAll('select').forEach(function (select) {
                    select.selectedIndex = 0;
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('po-item-rows');
        var addButton = document.getElementById('add-po-item-row');

        if (!container) {
            return;
        }

        container.querySelectorAll('.po-item-row').forEach(wireRemoveButton);

        if (addButton) {
            addButton.addEventListener('click', function () {
                var rows = container.querySelectorAll('.po-item-row');
                var template = rows[rows.length - 1];
                var clone = template.cloneNode(true);
                clone.querySelectorAll('input').forEach(function (input) {
                    input.value = '';
                });
                clone.querySelectorAll('select').forEach(function (select) {
                    select.selectedIndex = 0;
                });
                container.appendChild(clone);
                wireRemoveButton(clone);
            });
        }
    });
})();
