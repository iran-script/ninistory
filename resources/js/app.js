document.addEventListener('DOMContentLoaded', () => {
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>\'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    }[character]));
    document.querySelectorAll('[data-live-search]').forEach((form) => {
        const input = form.querySelector('input[name="q"]');
        if (!input) return;
        const results = document.createElement('div');
        results.className = 'absolute inset-x-0 top-full z-50 mt-2 hidden max-h-96 overflow-y-auto rounded-2xl border border-[var(--color-border)] bg-white p-2 text-right shadow-xl';
        results.setAttribute('role', 'listbox');
        results.setAttribute('aria-live', 'polite');
        results.id = `${input.id}-results`;
        form.append(results);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', results.id);
        let timer;
        let request;
        let requestId = 0;
        let activeIndex = -1;
        const close = () => { results.classList.add('hidden'); input.setAttribute('aria-expanded', 'false'); activeIndex = -1; };
        const message = (text) => { results.innerHTML = `<p class="px-3 py-3 text-sm text-slate-600" role="status">${escapeHtml(text)}</p>`; results.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); };
        const render = (payload, query) => {
            const rows = payload.results || [];
            const items = rows.map((product, index) => `<a href="${escapeHtml(product.url)}" class="flex min-h-16 items-center gap-3 rounded-xl px-2 py-2 text-sm hover:bg-pink-50 focus:bg-pink-50 focus:outline-none" role="option" id="search-option-${input.id}-${index}" data-search-option><span class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-pink-50">${product.thumbnail ? `<img src="${escapeHtml(product.thumbnail)}" alt="" class="h-full w-full object-contain" width="48" height="48" loading="lazy">` : '<span class="grid h-full place-items-center text-xs text-slate-400">تصویر ندارد</span>'}</span><span class="min-w-0 flex-1"><strong class="block truncate">${escapeHtml(product.name)}</strong><span class="block truncate text-xs text-slate-500">${escapeHtml(product.category || '')}</span><span class="mt-1 block text-xs font-bold text-pink-600">${escapeHtml(product.price_label || '')} · ${escapeHtml(product.availability_label || '')}</span></span></a>`).join('');
            const empty = rows.length ? '' : '<p class="px-3 py-3 text-sm text-slate-600" role="status">محصولی پیدا نشد</p>';
            const all = `<a href="${escapeHtml(payload.all_results_url)}" class="mt-1 block rounded-xl px-3 py-3 text-sm font-bold text-pink-600 hover:bg-pink-50 focus:bg-pink-50 focus:outline-none" role="option" data-search-all>مشاهده همه نتایج برای «${escapeHtml(query)}»</a>`;
            results.innerHTML = items + empty + all; results.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); activeIndex = -1;
        };
        const run = async () => {
            const query = input.value.trim();
            const currentRequest = ++requestId;
            if (request) request.abort();
            if (query.length < 2) { close(); return; }
            message('در حال جست‌وجو...'); request = new AbortController();
            try {
                const response = await fetch(`/search/autocomplete?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal: request.signal });
                if (!response.ok) throw new Error('search failed');
                const payload = await response.json();
                if (currentRequest === requestId) render(payload, query);
            } catch (error) {
                if (error.name !== 'AbortError' && currentRequest === requestId) message('جست‌وجو در دسترس نیست؛ همه نتایج را ببینید');
            }
        };
        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 300); });
        input.addEventListener('keydown', (event) => {
            const options = [...results.querySelectorAll('[data-search-option], [data-search-all]')];
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'ArrowDown' && options.length) { event.preventDefault(); activeIndex = (activeIndex + 1) % options.length; options[activeIndex].focus(); }
            if (event.key === 'ArrowUp' && options.length) { event.preventDefault(); activeIndex = (activeIndex - 1 + options.length) % options.length; options[activeIndex].focus(); }
            if (event.key === 'Enter' && activeIndex >= 0 && options[activeIndex]) { event.preventDefault(); options[activeIndex].click(); }
        });
        document.addEventListener('click', (event) => { if (!form.contains(event.target)) close(); });
    });

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
