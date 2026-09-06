document.querySelectorAll('[data-stock-in-form]').forEach((form) => {
    const items = form.querySelector('[data-stock-in-items]');
    const template = form.querySelector('[data-stock-in-template]');
    const addButton = form.querySelector('[data-add-stock-in-item]');
    const maximum = Number(form.dataset.maxItems || 100);

    const rows = () => [...items.querySelectorAll('[data-stock-in-item]')];

    const reindex = () => {
        rows().forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
        });
        rows().forEach((row) => {
            row.querySelector('[data-remove-stock-in-item]').disabled = rows().length === 1;
        });
        addButton.disabled = rows().length >= maximum;
    };

    const updateMetadata = (select) => {
        const option = select.selectedOptions[0];
        const output = select.closest('[data-stock-in-item]').querySelector('[data-stock-in-metadata]');
        output.textContent = option?.value
            ? `${option.dataset.product} · ${option.dataset.identity} · Unit: ${option.dataset.unit} · ${option.dataset.mode} · Current stock: ${option.dataset.stock}`
            : 'Select a variant to see its unit, quantity mode, and current stock.';
    };

    form.addEventListener('change', (event) => {
        if (event.target.matches('[data-stock-in-variant]')) {
            updateMetadata(event.target);
        }
    });

    form.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-stock-in-item]');
        if (remove && rows().length > 1) {
            remove.closest('[data-stock-in-item]').remove();
            reindex();
        }
    });

    addButton.addEventListener('click', () => {
        if (rows().length >= maximum) return;
        items.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(rows().length)));
        reindex();
    });

    rows().forEach((row) => updateMetadata(row.querySelector('[data-stock-in-variant]')));
    reindex();
});
