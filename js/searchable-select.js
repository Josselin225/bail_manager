/* Transforme un <select class="js-search-select"> en champ en lecture seule
   avec une icône loupe : au clic, une popup de recherche s'ouvre pour choisir
   une valeur. Le <select> d'origine reste dans le DOM (masqué mais valide/
   soumis normalement au submit du formulaire). */
(function () {
    var activeSelect = null;
    var modalEl, bsModal, searchInput, listEl, titleEl;

    function selectedLabel(select) {
        var opt = select.options[select.selectedIndex];
        return (opt && opt.value !== '') ? opt.text : '';
    }

    function ensureModal() {
        if (modalEl) return;
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
              '<div class="modal-content border-0 shadow-lg">' +
                '<div class="modal-header">' +
                  '<h5 class="modal-title fw-bold mb-0" id="ssPickerTitle">Rechercher</h5>' +
                  '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
                '</div>' +
                '<div class="modal-body">' +
                  '<div class="input-group mb-3">' +
                    '<span class="input-group-text bg-light border-end-0"><i class="fa fa-search text-muted"></i></span>' +
                    '<input type="text" class="form-control border-start-0 ps-0" id="ssPickerSearch" placeholder="Tapez pour rechercher…" autocomplete="off">' +
                  '</div>' +
                  '<div id="ssPickerList" class="list-group" style="max-height:320px;overflow-y:auto;"></div>' +
                '</div>' +
              '</div>' +
            '</div>';
        document.body.appendChild(modalEl);
        bsModal      = new bootstrap.Modal(modalEl);
        searchInput  = modalEl.querySelector('#ssPickerSearch');
        listEl       = modalEl.querySelector('#ssPickerList');
        titleEl      = modalEl.querySelector('#ssPickerTitle');

        searchInput.addEventListener('input', function () {
            renderList(searchInput.value);
        });
        modalEl.addEventListener('shown.bs.modal', function () {
            searchInput.focus();
        });
    }

    function renderList(filter) {
        if (!activeSelect) return;
        listEl.innerHTML = '';
        var f = (filter || '').toLowerCase();
        var options = Array.prototype.slice.call(activeSelect.options).filter(function (o) {
            return o.value !== '';
        });
        var matches = options.filter(function (o) {
            return o.text.toLowerCase().indexOf(f) !== -1;
        });

        if (!matches.length) {
            var empty = document.createElement('div');
            empty.className = 'list-group-item text-muted small';
            empty.textContent = 'Aucun résultat';
            listEl.appendChild(empty);
            return;
        }

        matches.forEach(function (o) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            if (o.value === activeSelect.value) item.classList.add('active');
            item.textContent = o.text;
            item.addEventListener('click', function () {
                var select = activeSelect;
                var input  = select._ssVisibleInput;
                select.value = o.value;
                if (input) input.value = o.text;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                bsModal.hide();
            });
            listEl.appendChild(item);
        });
    }

    function openPicker(select) {
        ensureModal();
        activeSelect = select;
        titleEl.textContent = select.getAttribute('data-placeholder') || 'Rechercher…';
        searchInput.value = '';
        renderList('');
        bsModal.show();
    }

    function enhance(select) {
        if (select.dataset.enhanced) return;
        select.dataset.enhanced = '1';

        var container = select.closest('.input-group') || select.parentNode;

        // Le select d'origine sort du groupe visuel mais reste dans le formulaire
        // (masqué de façon accessible : toujours focusable pour la validation native).
        if (container.parentNode) {
            container.parentNode.insertBefore(select, container.nextSibling);
            container.parentNode.style.position = container.parentNode.style.position || 'relative';
        }
        select.classList.add('ss-hidden-select');

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control border-start-0 ps-0';
        input.style.cursor = 'pointer';
        input.readOnly = true;
        input.placeholder = select.getAttribute('data-placeholder') || 'Rechercher…';
        input.value = selectedLabel(select);
        select._ssVisibleInput = input;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary';
        btn.innerHTML = '<i class="fa fa-search"></i>';

        container.appendChild(input);
        container.appendChild(btn);

        input.addEventListener('click', function () { openPicker(select); });
        btn.addEventListener('click', function () { openPicker(select); });
    }

    function init() {
        document.querySelectorAll('select.js-search-select').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
