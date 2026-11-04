/**
 * Product inventory page: sends the form over Ajax, draws the table and
 * handles inline editing of rows.
 *
 * Every API response carries the full product list, so the table is always
 * redrawn from what the server saved.
 */
(function () {
    'use strict';

    const API_URL = 'api.php';
    const FIELDS = ['product_name', 'quantity_in_stock', 'price_per_item'];

    const form = document.getElementById('product-form');
    const tbody = document.getElementById('product-rows');
    const sumCell = document.getElementById('sum-total');
    const countLabel = document.getElementById('product-count');
    const flash = document.getElementById('flash');
    const maxNameLength = form.elements.product_name.maxLength;

    const money = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const wholeNumber = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
    const dateTime = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'medium' });
    const dateOnly = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });
    const timeOnly = new Intl.DateTimeFormat(undefined, { timeStyle: 'medium' });
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    /** Latest data from the server: { products: [...], sum_total: number } */
    let state = JSON.parse(document.getElementById('initial-data').textContent);

    /** The row being edited, if any: { id, row } */
    let editing = null;

    /* ---------- Server ---------- */

    async function send(action, fields, id) {
        const params = new URLSearchParams({ action: action });
        if (id) {
            params.set('id', String(id));
        }

        let response;
        try {
            response = await fetch(API_URL + '?' + params.toString(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(fields),
            });
        } catch (networkError) {
            throw requestError('Could not reach the server. Check your connection and try again.');
        }

        let body = null;
        try {
            body = await response.json();
        } catch (parseError) {
            // Not JSON (for example a PHP fatal error page); handled below.
        }

        if (!response.ok || !body) {
            throw requestError(
                (body && body.error) || 'The server returned an error (' + response.status + '). Please try again.',
                body && body.errors
            );
        }

        return body;
    }

    function requestError(message, fieldErrors) {
        const error = new Error(message);
        error.fieldErrors = fieldErrors || {};
        return error;
    }

    /* ---------- Table ---------- */

    function render(highlightId) {
        if (editing && !state.products.some(function (p) { return p.id === editing.id; })) {
            editing = null;
        }

        const rows = state.products.map(function (product) {
            // Keep the row being edited as it is, so typed values survive a redraw.
            return editing && editing.id === product.id ? editing.row : viewRow(product);
        });
        tbody.replaceChildren.apply(tbody, rows.length ? rows : [emptyRow()]);

        sumCell.textContent = money.format(state.sum_total);
        countLabel.textContent = state.products.length === 1
            ? '1 product'
            : wholeNumber.format(state.products.length) + ' products';

        if (highlightId) {
            highlight(rowFor(highlightId));
        }
    }

    function viewRow(product) {
        const row = document.createElement('tr');
        row.dataset.id = product.id;

        const name = document.createElement('th');
        name.scope = 'row';
        name.className = 'fw-normal text-break';
        name.textContent = product.product_name;

        const edit = button('Edit', 'btn btn-sm btn-outline-secondary', function () {
            startEditing(product.id);
        });
        edit.setAttribute('aria-label', 'Edit ' + product.product_name);

        row.append(
            name,
            cell(wholeNumber.format(product.quantity_in_stock), 'text-end num'),
            cell(money.format(product.price_per_item), 'text-end num'),
            submittedCell(product),
            cell(money.format(product.total_value), 'text-end num fw-semibold'),
            actionsCell([edit])
        );

        return row;
    }

    function emptyRow() {
        const row = document.createElement('tr');
        const message = cell('No products yet. Add the first one above.', 'text-center text-body-secondary py-4');
        message.colSpan = 6;
        row.append(message);
        return row;
    }

    function submittedCell(product) {
        const td = cell('', '');
        const submitted = new Date(product.submitted_at);

        // On narrow screens the time can move to a second line; the date stays on one line.
        const time = document.createElement('time');
        time.dateTime = product.submitted_at;
        time.title = dateTime.format(submitted);
        time.append(nowrap(dateOnly.format(submitted)), ' ', nowrap(timeOnly.format(submitted)));
        td.append(time);

        if (product.updated_at) {
            const badge = document.createElement('span');
            badge.className = 'badge rounded-pill text-bg-light border fw-normal ms-2';
            badge.textContent = 'edited';
            badge.title = 'Last edited ' + dateTime.format(new Date(product.updated_at));
            td.append(badge);
        }

        return td;
    }

    /* ---------- Editing a row ---------- */

    function startEditing(id) {
        const product = state.products.find(function (p) { return p.id === id; });
        if (!product) {
            return;
        }

        // Only one row is edited at a time; opening another discards the first.
        editing = { id: id, row: editRow(product) };
        render();

        const first = editing.row.querySelector('input');
        first.focus();
        first.select();
    }

    function stopEditing() {
        if (!editing) {
            return;
        }
        const id = editing.id;
        editing = null;
        render();
        focusEditButton(id);
    }

    function editRow(product) {
        const row = document.createElement('tr');
        row.dataset.id = product.id;
        row.className = 'editing';

        const inputs = {
            product_name: editInput('product_name', product.product_name, 'Product name'),
            quantity_in_stock: editInput('quantity_in_stock', String(product.quantity_in_stock), 'Quantity in stock'),
            price_per_item: editInput('price_per_item', Number(product.price_per_item).toFixed(2), 'Price per item'),
        };
        inputs.product_name.maxLength = maxNameLength;
        inputs.quantity_in_stock.inputMode = 'numeric';
        inputs.price_per_item.inputMode = 'decimal';

        // Live preview of the new total while typing; the server recalculates on save.
        const total = cell('', 'text-end num fw-semibold');
        function updateTotal() {
            const value = previewTotal(inputs.quantity_in_stock.value, inputs.price_per_item.value);
            total.textContent = value === null ? '—' : money.format(value);
        }
        updateTotal();
        row.addEventListener('input', updateTotal);

        const save = button('Save', 'btn btn-sm btn-primary', function () { saveEdit(product.id); });
        const cancel = button('Cancel', 'btn btn-sm btn-outline-secondary', stopEditing);

        row.append(
            fieldCell(inputs.product_name),
            fieldCell(inputs.quantity_in_stock),
            fieldCell(inputs.price_per_item),
            submittedCell(product),
            total,
            actionsCell([save, cancel])
        );

        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
                event.preventDefault();
                saveEdit(product.id);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                stopEditing();
            }
        });

        return row;
    }

    async function saveEdit(id) {
        if (!editing || editing.id !== id) {
            return;
        }
        const row = editing.row;

        clearErrors(row);
        setBusy(row, true);

        let payload;
        try {
            payload = await send('update', readFields(row), id);
        } catch (error) {
            setBusy(row, false);
            showFieldErrors(row, error.fieldErrors);
            showFlash(error.message, 'danger');
            return;
        }

        state = payload;
        editing = null;
        render(id);
        focusEditButton(id);
        showFlash(payload.message, 'success');
    }

    function editInput(name, value, label) {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = name;
        input.id = 'edit-' + name;
        input.value = value;
        input.autocomplete = 'off';
        input.className = 'form-control form-control-sm' + (name === 'product_name' ? '' : ' text-end');
        input.setAttribute('aria-label', label);
        input.setAttribute('aria-describedby', input.id + '-error');
        return input;
    }

    function fieldCell(input) {
        const td = document.createElement('td');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.id = input.id + '-error';
        td.append(input, feedback);
        return td;
    }

    function previewTotal(quantity, price) {
        quantity = quantity.trim();
        price = price.trim();
        if (!/^\d+$/.test(quantity) || !/^(?:\d+(?:\.\d{0,2})?|\.\d{1,2})$/.test(price)) {
            return null;
        }
        return Number(quantity) * Math.round(Number(price) * 100) / 100;
    }

    /* ---------- Add form ---------- */

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearErrors(form);
        setBusy(form, true);

        let payload;
        try {
            payload = await send('create', readFields(form));
        } catch (error) {
            setBusy(form, false);
            showFieldErrors(form, error.fieldErrors);
            showFlash(error.message, 'danger');
            return;
        }

        setBusy(form, false);
        state = payload;
        form.reset();
        render(payload.id);
        showFlash(payload.message, 'success');
        form.elements.product_name.focus();
    });

    /* ---------- Shared helpers ---------- */

    function readFields(container) {
        const fields = {};
        FIELDS.forEach(function (name) {
            fields[name] = container.querySelector('[name="' + name + '"]').value;
        });
        return fields;
    }

    function showFieldErrors(container, errors) {
        let first = null;
        FIELDS.forEach(function (name) {
            const input = container.querySelector('[name="' + name + '"]');
            if (!input || !errors[name]) {
                return;
            }
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            input.nextElementSibling.textContent = errors[name];
            first = first || input;
        });
        if (first) {
            first.focus();
            first.select();
        }
    }

    function clearErrors(container) {
        container.querySelectorAll('.is-invalid').forEach(clearFieldError);
    }

    function clearFieldError(input) {
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
    }

    // A field's error goes away as soon as it is changed.
    document.addEventListener('input', function (event) {
        if (event.target.classList && event.target.classList.contains('is-invalid')) {
            clearFieldError(event.target);
        }
    });

    function setBusy(container, busy) {
        container.querySelectorAll('input, button').forEach(function (element) {
            element.disabled = busy;
        });
        container.setAttribute('aria-busy', busy ? 'true' : 'false');
        const spinner = container.querySelector('.spinner-border');
        if (spinner) {
            spinner.classList.toggle('d-none', !busy);
        }
    }

    let flashTimer = null;

    function showFlash(message, tone) {
        clearTimeout(flashTimer);
        flash.className = 'flash alert shadow-sm alert-' + tone;
        flash.textContent = message;
        flashTimer = setTimeout(function () {
            flash.textContent = '';
        }, tone === 'success' ? 3000 : 6000);
    }

    function rowFor(id) {
        return tbody.querySelector('tr[data-id="' + id + '"]');
    }

    function focusEditButton(id) {
        const row = rowFor(id);
        const edit = row && row.querySelector('button');
        if (edit) {
            edit.focus();
        }
    }

    function highlight(row) {
        if (!row) {
            return;
        }
        row.classList.remove('row-flash');
        void row.offsetWidth; // Restart the animation if it is still running.
        row.classList.add('row-flash');
        row.addEventListener('animationend', function () {
            row.classList.remove('row-flash');
        }, { once: true });
        row.scrollIntoView({ block: 'nearest', behavior: reducedMotion.matches ? 'auto' : 'smooth' });
    }

    function cell(text, className) {
        const td = document.createElement('td');
        td.className = className || '';
        td.textContent = text;
        return td;
    }

    function nowrap(text) {
        const span = document.createElement('span');
        span.className = 'text-nowrap';
        span.textContent = text;
        return span;
    }

    function actionsCell(buttons) {
        const td = cell('', 'text-end text-nowrap');
        const group = document.createElement('div');
        group.className = 'd-inline-flex gap-2';
        group.append.apply(group, buttons);
        td.append(group);
        return td;
    }

    function button(label, className, onClick) {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = className;
        element.textContent = label;
        element.addEventListener('click', onClick);
        return element;
    }

    render();
})();
