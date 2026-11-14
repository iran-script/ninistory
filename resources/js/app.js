document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-product-configurator]').forEach((configurator) => {
        const select = configurator.querySelector('[data-variant-select]');
        if (!select) return;
        const price = configurator.querySelector('[data-variant-price]');
        const compare = configurator.querySelector('[data-variant-compare]');
        const stock = configurator.querySelector('[data-variant-stock]');
        const submit = configurator.querySelector('button[type="submit"]');
        const colorButtons = [...configurator.querySelectorAll('[data-color]')];
        const sizeButtons = [...configurator.querySelectorAll('[data-size]')];
        const format = (value) => new Intl.NumberFormat('fa-IR').format(Number(value || 0));
        const sync = (option) => {
            if (!option || !option.value) return;
            price.textContent = `${format(option.dataset.price)} تومان`;
            compare.textContent = Number(option.dataset.compare || 0) > Number(option.dataset.price || 0) ? `${format(option.dataset.compare)} تومان` : '';
            const quantity = Number(option.dataset.stock || 0);
            stock.textContent = quantity > 0 ? `موجودی: ${format(quantity)} عدد` : 'این ترکیب ناموجود است';
            if (submit) submit.disabled = quantity < 1;
            colorButtons.forEach((button) => button.classList.toggle('is-selected', button.dataset.color === option.dataset.color));
            sizeButtons.forEach((button) => button.classList.toggle('is-selected', button.dataset.size === option.dataset.size));
        };
        const chooseColor = (color) => {
            const option = [...select.options].find((candidate) => candidate.dataset.color === color && candidate.dataset.active === '1' && Number(candidate.dataset.stock) > 0)
                || [...select.options].find((candidate) => candidate.dataset.color === color && candidate.dataset.active === '1');
            if (option) { select.value = option.value; sync(option); }
            sizeButtons.forEach((button) => {
                button.disabled = ![...select.options].some((candidate) => candidate.dataset.color === color && candidate.dataset.size === button.dataset.size && candidate.dataset.active === '1' && Number(candidate.dataset.stock) > 0);
            });
        };
        colorButtons.forEach((button) => button.addEventListener('click', () => chooseColor(button.dataset.color)));
        sizeButtons.forEach((button) => button.addEventListener('click', () => {
            const color = colorButtons.find((candidate) => candidate.classList.contains('is-selected'))?.dataset.color;
            const option = [...select.options].find((candidate) => candidate.dataset.color === color && candidate.dataset.size === button.dataset.size && candidate.dataset.active === '1');
            if (option) { select.value = option.value; sync(option); }
        }));
        select.addEventListener('change', () => sync(select.selectedOptions[0]));
        const initial = [...select.options].find((option) => option.dataset.active === '1' && Number(option.dataset.stock) > 0) || select.options[0];
        if (initial) { select.value = initial.value; sync(initial); chooseColor(initial.dataset.color); }
    });
});
