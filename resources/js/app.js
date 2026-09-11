const navToggle = document.querySelector('[data-nav-toggle]');
const navDrawer = document.querySelector('[data-nav-drawer]');
const navBackdrop = document.querySelector('[data-nav-backdrop]');
const navClose = document.querySelector('[data-nav-close]');
const navMobileBar = document.querySelector('[data-nav-mobile-bar]');
const appContent = document.querySelector('[data-app-content]');

if (navToggle && navDrawer && navBackdrop && navClose) {
    const desktopMedia = window.matchMedia('(min-width: 64rem)');

    const closeDrawer = (returnFocus = true) => {
        navDrawer.classList.add('-translate-x-full');
        navDrawer.setAttribute('aria-hidden', 'true');
        navDrawer.inert = true;
        navBackdrop.classList.add('pointer-events-none', 'opacity-0');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.setAttribute('aria-label', 'Open navigation');
        document.body.classList.remove('overflow-hidden');
        if (navMobileBar) navMobileBar.inert = false;
        if (appContent) appContent.inert = false;
        if (returnFocus && !desktopMedia.matches) navToggle.focus();
    };

    const openDrawer = () => {
        if (desktopMedia.matches) return;
        navDrawer.classList.remove('-translate-x-full');
        navDrawer.setAttribute('aria-hidden', 'false');
        navDrawer.inert = false;
        navBackdrop.classList.remove('pointer-events-none', 'opacity-0');
        navToggle.setAttribute('aria-expanded', 'true');
        navToggle.setAttribute('aria-label', 'Close navigation');
        document.body.classList.add('overflow-hidden');
        if (appContent) appContent.inert = true;
        if (navMobileBar) navMobileBar.inert = true;
        navClose.focus();
    };

    navToggle.addEventListener('click', () => {
        if (navToggle.getAttribute('aria-expanded') === 'true') closeDrawer();
        else openDrawer();
    });
    navClose.addEventListener('click', () => closeDrawer());
    navBackdrop.addEventListener('click', () => closeDrawer());
    navDrawer.querySelectorAll('[data-nav-link]').forEach((link) => {
        link.addEventListener('click', () => closeDrawer(false));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && navToggle.getAttribute('aria-expanded') === 'true') {
            closeDrawer();
        }
    });
    desktopMedia.addEventListener('change', (event) => {
        if (event.matches) closeDrawer(false);
    });
}

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
            ? `${option.dataset.product} · ${option.dataset.identity} · Unit: ${option.dataset.unit} · ${option.dataset.mode} · Current stock: ${option.dataset.stockDisplay}`
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

document.querySelectorAll('[data-pos]').forEach((pos) => {
    const form = pos.querySelector('[data-pos-form]');
    const cart = pos.querySelector('[data-pos-cart]');
    const empty = pos.querySelector('[data-pos-empty]');
    const totalOutput = pos.querySelector('[data-pos-total]');
    const tenderInput = pos.querySelector('[data-pos-tender]');
    const changeOutput = pos.querySelector('[data-pos-change]');
    const checkout = pos.querySelector('[data-pos-checkout]');

    const rows = () => [...cart.querySelectorAll('[data-pos-cart-row]')];
    const parseScaled = (value, scale) => {
        const match = String(value).trim().match(new RegExp(`^(\\d+)(?:\\.(\\d{1,${scale}}))?$`));
        if (!match) return null;
        return BigInt(match[1]) * (10n ** BigInt(scale)) + BigInt((match[2] || '').padEnd(scale, '0'));
    };
    const money = (cents) => `₱${cents / 100n}.${String(cents % 100n).padStart(2, '0')}`;

    const reindex = () => rows().forEach((row, index) => {
        row.querySelector('[data-pos-id-input]').name = `items[${index}][product_variant_id]`;
        row.querySelector('[data-pos-quantity]').name = `items[${index}][quantity]`;
        row.querySelector('[data-pos-price-input]').name = `items[${index}][expected_unit_price]`;
    });

    const update = () => {
        let total = 0n;
        let valid = rows().length > 0;
        rows().forEach((row) => {
            const quantity = parseScaled(row.querySelector('[data-pos-quantity]').value, 3);
            const price = parseScaled(row.dataset.price, 2);
            const line = quantity !== null && price !== null && quantity > 0n ? (quantity * price + 500n) / 1000n : null;
            row.querySelector('[data-pos-line]').textContent = line === null ? '—' : money(line);
            if (line === null) valid = false;
            else total += line;
        });
        totalOutput.textContent = money(total);
        const tender = parseScaled(tenderInput.value, 2);
        changeOutput.textContent = tender !== null && tender >= total ? money(tender - total) : '₱0.00';
        empty.hidden = rows().length !== 0;
        checkout.disabled = !valid;
        reindex();
    };

    const createRow = (button) => {
        const row = document.createElement('div');
        row.className = 'rounded-lg border border-slate-200 p-3';
        row.dataset.posCartRow = '';
        for (const field of ['id', 'product', 'identity', 'unit', 'mode', 'stock', 'price']) row.dataset[field] = button.dataset[field];

        const heading = document.createElement('div');
        heading.className = 'flex justify-between gap-3';
        const identity = document.createElement('div');
        const name = document.createElement('p');
        name.className = 'font-semibold';
        name.textContent = button.dataset.product;
        const detail = document.createElement('p');
        detail.className = 'text-xs text-slate-500';
        detail.textContent = `${button.dataset.identity} · ${button.dataset.unit}`;
        identity.append(name, detail);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.posRemove = '';
        remove.className = 'text-sm font-semibold text-red-700';
        remove.textContent = 'Remove';
        heading.append(identity, remove);

        const controls = document.createElement('div');
        controls.className = 'mt-3 grid grid-cols-2 gap-3';
        const label = document.createElement('label');
        label.innerHTML = '<span class="text-xs font-medium text-slate-600">Quantity</span>';
        const quantity = document.createElement('input');
        quantity.value = '1';
        quantity.inputMode = 'decimal';
        quantity.required = true;
        quantity.dataset.posQuantity = '';
        quantity.className = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2';
        label.append(quantity);
        const price = document.createElement('div');
        const priceLabel = document.createElement('span');
        priceLabel.className = 'text-xs font-medium text-slate-600';
        priceLabel.textContent = 'Current price';
        const priceValue = document.createElement('p');
        priceValue.className = 'mt-2 font-semibold';
        priceValue.textContent = money(parseScaled(button.dataset.price, 2));
        price.append(priceLabel, priceValue);
        controls.append(label, price);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.value = button.dataset.id;
        idInput.dataset.posIdInput = '';
        const priceInput = document.createElement('input');
        priceInput.type = 'hidden';
        priceInput.value = button.dataset.price;
        priceInput.dataset.posPriceInput = '';
        const estimate = document.createElement('p');
        estimate.className = 'mt-2 text-right text-sm text-slate-600';
        estimate.append('Estimate: ');
        const line = document.createElement('span');
        line.dataset.posLine = '';
        estimate.append(line);
        row.append(heading, controls, idInput, priceInput, estimate);
        cart.append(row);
    };

    pos.addEventListener('click', (event) => {
        const add = event.target.closest('[data-pos-add]');
        if (add && !add.disabled) {
            const existing = rows().find((row) => row.dataset.id === add.dataset.id);
            if (existing) {
                const quantity = existing.querySelector('[data-pos-quantity]');
                const parsed = parseScaled(quantity.value, 3);
                quantity.value = parsed === null ? '1' : String((parsed + 1000n) / 1000n);
            } else createRow(add);
            update();
        }
        const remove = event.target.closest('[data-pos-remove]');
        if (remove) {
            remove.closest('[data-pos-cart-row]').remove();
            update();
        }
    });
    form.addEventListener('input', update);

    const search = document.querySelector('[data-pos-search]');
    search?.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        pos.querySelectorAll('[data-pos-product]').forEach((product) => {
            product.hidden = term !== '' && !product.dataset.search.includes(term);
        });
    });
    update();
});
