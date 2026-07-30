(function () {
    'use strict';

    function cloneRowAndClear(container, rowSelector) {
        var rows = container.querySelectorAll(rowSelector);
        var template = rows[rows.length - 1];
        var clone = template.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) {
            input.value = '';
        });
        container.appendChild(clone);
        return clone;
    }

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
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var specsContainer = document.getElementById('specs-rows');
        var attrContainer = document.getElementById('attr-rows');
        var addSpecButton = document.getElementById('add-spec-row');
        var addAttrButton = document.getElementById('add-attr-row');

        if (specsContainer) {
            specsContainer.querySelectorAll('.spec-row').forEach(wireRemoveButton);
        }
        if (attrContainer) {
            attrContainer.querySelectorAll('.attr-row').forEach(wireRemoveButton);
        }

        if (addSpecButton && specsContainer) {
            addSpecButton.addEventListener('click', function () {
                wireRemoveButton(cloneRowAndClear(specsContainer, '.spec-row'));
            });
        }
        if (addAttrButton && attrContainer) {
            addAttrButton.addEventListener('click', function () {
                wireRemoveButton(cloneRowAndClear(attrContainer, '.attr-row'));
            });
        }
    });
})();
