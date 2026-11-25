import * as FilePond from 'filepond';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginImageExifOrientation from 'filepond-plugin-image-exif-orientation';
import TomSelect from 'tom-select';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css';
import 'tom-select/dist/css/tom-select.css';

FilePond.registerPlugin(
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImagePreview,
    FilePondPluginImageExifOrientation,
);

const normalizeSelectText = (value) => String(value ?? '')
    .normalize('NFKC')
    .replace(/[يى]/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/[\s\u200c\u200d]+/g, ' ')
    .trim()
    .toLocaleLowerCase();

window.initSearchableSelects = (root = document) => {
    root.querySelectorAll('select[data-tom-select]').forEach((select) => {
        if (select.tomselect) return;

        new TomSelect(select, {
            create: false,
            allowEmptyOption: true,
            maxOptions: 200,
            dropdownParent: 'body',
            searchField: ['text', 'value'],
            score: (search) => (option) => normalizeSelectText(`${option.text} ${option.value}`).includes(normalizeSelectText(search)) ? 1 : 0,
            render: {
                no_results: () => '<div class="no-results">گزینه‌ای پیدا نشد</div>',
            },
            onDropdownOpen: (dropdown) => dropdown.setAttribute('dir', 'rtl'),
        });
    });
};

window.initSearchableSelects();

document.addEventListener('DOMContentLoaded', () => {
    const productImageInput = document.querySelector('[data-filepond]');
    const uploadStatus = document.querySelector('#image-upload-status');
    const primaryImageSelect = document.querySelector('#primary-upload-image');
    const primaryImageIndex = document.querySelector('#cover-image-index');
    if (productImageInput) {
        FilePond.setOptions({
            labelIdle: 'تصاویر را اینجا رها کنید یا <span class="filepond--label-action">انتخاب کنید</span>',
            labelFileTypeNotAllowed: 'این نوع فایل پشتیبانی نمی‌شود.',
            fileValidateTypeLabelExpectedTypes: 'فقط JPG، PNG و WebP',
            labelMaxFileSizeExceeded: 'حجم فایل بیش از حد مجاز است.',
            labelMaxFileSize: 'حداکثر حجم هر تصویر ۵ مگابایت است.',
            labelMaxTotalFileSizeExceeded: 'مجموع حجم تصاویر بیش از حد مجاز است.',
            labelMaxTotalFileSize: 'حداکثر مجموع حجم تصاویر ۱۸ مگابایت است.',
            labelFileRemoveError: 'حذف تصویر انجام نشد.',
        });
        let uploadedFiles = [];
        const updatePrimaryImage = () => {
            const selectedIndex = uploadedFiles.findIndex((file) => file.id === primaryImageSelect?.value);
            if (primaryImageIndex) primaryImageIndex.value = selectedIndex < 0 ? '' : String(selectedIndex);
        };
        primaryImageSelect?.addEventListener('change', updatePrimaryImage);
        FilePond.create(productImageInput, {
            allowMultiple: true,
            allowReorder: true,
            allowProcess: false,
            storeAsFile: true,
            maxFiles: Number(productImageInput.dataset.maxFiles || 6),
            maxFileSize: '5MB',
            maxTotalFileSize: '18MB',
            acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp'],
            labelFileProcessing: 'آماده‌سازی تصویر',
            onupdatefiles: (files) => {
                uploadedFiles = files;
                const currentSelection = primaryImageSelect?.value;
                primaryImageSelect?.replaceChildren(new Option('انتخاب اولین تصویر', ''));
                files.forEach((file, index) => primaryImageSelect?.add(new Option(`${index + 1}. ${file.filename}`, file.id)));
                if (currentSelection && files.some((file) => file.id === currentSelection)) {
                    primaryImageSelect.value = currentSelection;
                } else if (files[0]) {
                    primaryImageSelect.value = files[0].id;
                }
                updatePrimaryImage();
                if (uploadStatus) uploadStatus.textContent = files.length ? `${files.length} تصویر آماده ارسال است.` : '';
            },
            onaddfile: (error, file) => {
                if (error && uploadStatus) uploadStatus.textContent = `${file.filename}: ${error.main}`;
            },
        });
    }

    document.querySelectorAll('form.admin-form').forEach((form) => form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        if (productImageInput?.files?.length && uploadStatus) uploadStatus.textContent = 'در حال ارسال تصاویر…';
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
    }));
    const normalizeMoneyDigits = (value) => String(value ?? '')
        .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
        .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
    const groupMoneyDigits = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const formatMoneyInput = (input) => {
        const raw = input.value;
        const caret = input.selectionStart ?? raw.length;
        const digitsBeforeCaret = normalizeMoneyDigits(raw.slice(0, caret)).replace(/\D/g, '').length;
        const digits = normalizeMoneyDigits(raw).replace(/\D/g, '');
        input.value = groupMoneyDigits(digits);
        let position = 0;
        let seen = 0;
        while (position < input.value.length && seen < digitsBeforeCaret) {
            if (/\d/.test(input.value[position])) seen += 1;
            position += 1;
        }
        input.setSelectionRange(position, position);
    };
    const initMoneyInputs = (root = document) => root.querySelectorAll('[data-money-input]').forEach((input) => {
        if (input.dataset.moneyBound) return;
        input.dataset.moneyBound = '1';
        formatMoneyInput(input);
        input.addEventListener('input', () => formatMoneyInput(input));
        input.addEventListener('blur', () => formatMoneyInput(input));
    });
    window.initMoneyInputs = initMoneyInputs;
    document.querySelectorAll('input:not([type="hidden"])[name="price_toman"], input:not([type="hidden"])[name="base_fee_toman"], input:not([type="hidden"])[name="fee_toman"], input:not([type="hidden"])[name="free_over_toman"], input:not([type="hidden"])[name="weight_surcharge_toman"], input:not([type="hidden"])[name="min_order_toman"], input:not([type="hidden"])[name="max_order_toman"], input:not([type="hidden"])[name="min_price"], input:not([type="hidden"])[name="max_price"], input:not([type="hidden"])[name$="[price_toman]"], input:not([type="hidden"])[name$="[compare_at_price_toman]"]').forEach((input) => {
        input.type = 'text';
        input.inputMode = 'numeric';
        input.dir = 'ltr';
        input.dataset.moneyInput = '1';
    });
    initMoneyInputs();
    document.querySelectorAll('[data-campaign-slider]').forEach((slider) => {
        const slides = [...slider.querySelectorAll('[data-campaign-slide]')];
        const dots = [...slider.querySelectorAll('[data-campaign-dot]')];
        const previous = slider.querySelector('[data-campaign-prev]');
        const next = slider.querySelector('[data-campaign-next]');
        if (slides.length < 2) return;
        let active = 0;
        let timer;
        let touchStartX = null;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const render = (index) => {
            active = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => {
                const isActive = slideIndex === active;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', String(!isActive));
            });
            dots.forEach((dot, dotIndex) => {
                const isActive = dotIndex === active;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-selected', String(isActive));
            });
        };
        const stop = () => { if (timer) window.clearTimeout(timer); timer = null; };
        const start = () => {
            stop();
            if (!reducedMotion && !document.hidden) {
                const duration = Math.max(3, Math.min(15, Number(slides[active].dataset.duration || 6)));
                timer = window.setTimeout(() => { render(active + 1); start(); }, duration * 1000);
            }
        };
        const move = (step) => { render(active + step); start(); };
        previous?.addEventListener('click', () => move(-1));
        next?.addEventListener('click', () => move(1));
        dots.forEach((dot) => dot.addEventListener('click', () => { render(Number(dot.dataset.campaignDot)); start(); }));
        slider.addEventListener('mouseenter', stop);
        slider.addEventListener('mouseleave', start);
        slider.addEventListener('focusin', stop);
        slider.addEventListener('focusout', start);
        slider.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') { event.preventDefault(); move(1); }
            if (event.key === 'ArrowRight') { event.preventDefault(); move(-1); }
        });
        slider.addEventListener('touchstart', (event) => { touchStartX = event.changedTouches[0]?.clientX ?? null; stop(); }, { passive: true });
        slider.addEventListener('touchend', (event) => {
            const endX = event.changedTouches[0]?.clientX ?? null;
            if (touchStartX !== null && endX !== null && Math.abs(endX - touchStartX) > 45) move(endX < touchStartX ? 1 : -1);
            touchStartX = null;
            start();
        }, { passive: true });
        document.addEventListener('visibilitychange', () => { document.hidden ? stop() : start(); });
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(([entry]) => entry.isIntersecting ? start() : stop(), { threshold: 0.2 });
            observer.observe(slider);
        } else start();
        render(0);
    });
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
            const categories = payload.categories || [];
            const items = rows.map((product, index) => `<a href="${escapeHtml(product.url)}" class="flex min-h-16 items-center gap-3 rounded-xl px-2 py-2 text-sm hover:bg-pink-50 focus:bg-pink-50 focus:outline-none" role="option" id="search-option-${input.id}-${index}" data-search-option><span class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-pink-50">${product.thumbnail ? `<img src="${escapeHtml(product.thumbnail)}" alt="" class="h-full w-full object-contain" width="48" height="48" loading="lazy">` : '<span class="grid h-full place-items-center text-xs text-slate-400">تصویر ندارد</span>'}</span><span class="min-w-0 flex-1"><strong class="block truncate">${escapeHtml(product.name)}</strong><span class="block truncate text-xs text-slate-500">${escapeHtml(product.category || '')}</span><span class="mt-1 block text-xs font-bold text-pink-600">${escapeHtml(product.price_label || '')} · ${escapeHtml(product.availability_label || '')}</span></span></a>`).join('');
            const categoryItems = categories.map((category, index) => `<a href="${escapeHtml(category.url)}" class="block rounded-xl px-3 py-2 text-sm hover:bg-pink-50 focus:bg-pink-50 focus:outline-none" role="option" data-search-option><span class="text-xs text-slate-500">دسته‌بندی</span><strong class="ms-2">${escapeHtml(category.name)}</strong></a>`).join('');
            const empty = rows.length || categories.length ? '' : '<p class="px-3 py-3 text-sm text-slate-600" role="status">محصول یا دسته‌ای پیدا نشد</p>';
            const all = `<a href="${escapeHtml(payload.all_results_url)}" class="mt-1 block rounded-xl px-3 py-3 text-sm font-bold text-pink-600 hover:bg-pink-50 focus:bg-pink-50 focus:outline-none" role="option" data-search-all>مشاهده همه نتایج برای «${escapeHtml(query)}»</a>`;
            results.innerHTML = categoryItems + items + empty + all; results.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); activeIndex = -1;
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

    const navigationTree = (container) => {
        try { return JSON.parse(container.dataset.navigationTree || '[]'); } catch { return []; }
    };
    const nodeImage = (node, className, alt = '') => {
        if (!node.image_url) return null;
        const image = document.createElement('img');
        image.className = className;
        image.src = node.image_url;
        image.alt = alt || node.name;
        image.loading = 'lazy';
        image.decoding = 'async';
        return image;
    };
    const createLink = (node, className = '') => {
        const link = document.createElement('a');
        link.href = node.url;
        link.className = className;
        link.textContent = node.name;
        return link;
    };
    const navigationIcon = (name) => {
        const paths = { chevron: '<path d="m15 5-7 7 7 7"/>', sparkle: '<path d="m12 3 1.4 5.6L19 10l-5.6 1.4L12 17l-1.4-5.6L5 10l5.6-1.4L12 3Z"/>' };
        return `<svg class="kidoki-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.chevron}</svg>`;
    };
    const renderNavigation = (container, mobile = false) => {
        const roots = navigationTree(container);
        let path = [];
        const findNode = (id) => {
            const search = (nodes) => { for (const node of nodes) { if (String(node.id) === String(id)) return node; const found = search(node.children || []); if (found) return found; } return null; };
            return search(roots);
        };
        const render = (target = container) => {
            target.replaceChildren();
            const current = path[path.length - 1];
            const nodes = current ? (current.children || []) : roots;
            const header = document.createElement('div');
            header.className = mobile ? 'mobile-nav__header' : 'mega-menu__path';
            if (current && path.length > 0) {
                const back = document.createElement('button');
                back.type = 'button'; back.className = mobile ? 'mobile-nav__back' : 'mega-menu__back';
                back.textContent = 'بازگشت'; back.setAttribute('data-nav-back', 'true');
                back.addEventListener('click', () => { path.pop(); renderCurrent(); }); header.append(back);
            }
            const title = document.createElement('strong'); title.textContent = current?.name || 'دسته‌بندی‌ها'; header.append(title);
            target.append(header);
            if (current) { target.append(createLink(current, mobile ? 'mobile-nav__all' : 'mega-menu__all')); }
            if (!nodes.length) { const empty = document.createElement('p'); empty.className = 'navigation-empty'; empty.textContent = 'زیر دسته‌ای برای نمایش وجود ندارد.'; target.append(empty); return; }
            const list = document.createElement('div'); list.className = mobile ? 'mobile-nav__list' : 'mega-menu__list';
            nodes.forEach((node) => {
                const row = document.createElement('div'); row.className = mobile ? 'mobile-nav__row' : 'mega-menu__row';
                const image = nodeImage(node, mobile ? 'mobile-nav__thumb' : 'mega-menu__thumb'); if (image) row.append(image);
                row.append(createLink(node, mobile ? 'mobile-nav__link' : 'mega-menu__link'));
                if ((node.children || []).length) { const enter = document.createElement('button'); enter.type='button'; enter.className = mobile ? 'mobile-nav__enter' : 'mega-menu__enter'; enter.innerHTML=navigationIcon('chevron'); enter.setAttribute('aria-label', `ورود به ${node.name}`); enter.addEventListener('click', (event) => { event.stopPropagation(); path.push(node); renderCurrent(); }); row.append(enter); }
                list.append(row);
            });
            target.append(list);
        };
        const renderCurrent = () => render(mobile ? container : container.querySelector('.mega-menu__children') || container);
        if (!mobile) {
            const rootsPanel = container.querySelector('.mega-menu__roots');
            const childrenPanel = container.querySelector('.mega-menu__children');
            const featurePanel = container.querySelector('.mega-menu__feature');
            roots.forEach((node, index) => { const button=document.createElement('button'); button.type='button'; button.className='mega-menu__root'; button.setAttribute('aria-selected', index===0?'true':'false'); const image=nodeImage(node,'mega-menu__root-thumb'); if(image) button.append(image); const text=document.createElement('span'); text.textContent=node.name; button.append(text); const icon=document.createElement('span'); icon.className='mega-menu__root-chevron'; icon.innerHTML=navigationIcon('chevron'); button.append(icon); button.addEventListener('click',(event)=>{ event.stopPropagation(); path=[node]; rootsPanel.querySelectorAll('button').forEach((item)=>item.setAttribute('aria-selected','false')); button.setAttribute('aria-selected','true'); renderDesktop(); }); rootsPanel.append(button); });
            const renderFeature = () => { const node=path[path.length-1] || roots[0]; featurePanel.replaceChildren(); if(!node) return; const image=nodeImage(node,'mega-menu__feature-image'); if(image) featurePanel.append(image); else { const fallback=document.createElement('div'); fallback.className='mega-menu__feature-fallback'; fallback.innerHTML=navigationIcon('sparkle'); featurePanel.append(fallback); } const title=document.createElement('h3'); title.textContent=node.name; const desc=document.createElement('p'); desc.textContent=node.description || 'انتخاب‌های لطیف و دوست‌داشتنی برای کوچولوها'; featurePanel.append(title,desc,createLink(node,'mega-menu__feature-link')); };
            const renderDesktop = () => { if (!path.length && roots[0]) path=[roots[0]]; render(childrenPanel); renderFeature(); };
            rootsPanel.closest('[data-desktop-navigation]').render = renderDesktop;
            renderDesktop();
        } else { renderCurrent(); }
        return { reset: () => { path=[]; if (mobile) renderCurrent(); else container.render(); } };
    };
    const desktopNavigation = document.querySelector('[data-desktop-navigation]');
    const desktopRenderer = desktopNavigation ? renderNavigation(desktopNavigation) : null;
    const mobileNavigation = document.querySelector('[data-mobile-navigation]');
    const mobileRenderer = mobileNavigation ? renderNavigation(mobileNavigation, true) : null;
    const megaItems = [...document.querySelectorAll('[data-mega-item]')];
    const closeMegaMenus = () => megaItems.forEach((item) => { const trigger=item.querySelector('[data-mega-trigger]'); const menu=item.querySelector('[data-mega-menu]'); if(trigger&&menu){trigger.setAttribute('aria-expanded','false');menu.hidden=true;} });
    const openMegaMenu = (item) => { closeMegaMenus(); const trigger=item.querySelector('[data-mega-trigger]'); const menu=item.querySelector('[data-mega-menu]'); if(trigger&&menu){trigger.setAttribute('aria-expanded','true');menu.hidden=false;desktopRenderer?.reset();} };
    megaItems.forEach((item) => { const trigger=item.querySelector('[data-mega-trigger]'); trigger?.addEventListener('click',()=>{ trigger.getAttribute('aria-expanded')==='true'?closeMegaMenus():openMegaMenu(item); }); trigger?.addEventListener('keydown',(event)=>{if(event.key==='ArrowDown'){event.preventDefault();openMegaMenu(item);item.querySelector('.mega-menu__root')?.focus();} if(event.key==='Escape'){closeMegaMenus();trigger.focus();}}); });
    document.addEventListener('click',(event)=>{const insideMega=event.composedPath().some((target)=>target instanceof Element && target.closest('[data-mega-item]'));if(!insideMega) closeMegaMenus();});
    const mobileMenu = document.querySelector('[data-mobile-menu]');
    const mobileMenuPanel = mobileMenu?.querySelector('.mobile-category-drawer__panel');
    const mobileMenuOpen = document.querySelector('[data-mobile-menu-open]');
    let mobileMenuReturnFocus;
    const closeMobileMenu = () => { if(!mobileMenu) return; mobileMenu.setAttribute('aria-hidden','true');mobileMenu.classList.remove('is-open');document.body.classList.remove('mobile-menu-open');document.querySelectorAll('[data-mobile-menu-open]').forEach((button)=>button.setAttribute('aria-expanded','false'));mobileMenuReturnFocus?.focus?.(); };
    mobileMenuOpen?.addEventListener('click',()=>{mobileMenuReturnFocus=mobileMenuOpen;mobileMenu?.classList.add('is-open');mobileMenu?.setAttribute('aria-hidden','false');document.body.classList.add('mobile-menu-open');document.querySelectorAll('[data-mobile-menu-open]').forEach((button)=>button.setAttribute('aria-expanded','true'));mobileRenderer?.reset();mobileMenuPanel?.focus();});
    mobileMenu?.querySelectorAll('[data-mobile-menu-close]').forEach((button)=>button.addEventListener('click',closeMobileMenu));
    document.addEventListener('keydown',(event)=>{if(event.key==='Escape'){if(mobileMenu?.classList.contains('is-open')) closeMobileMenu(); else closeMegaMenus();}});

    const drawer = document.querySelector('[data-cart-drawer]');
    const drawerPanel = drawer?.querySelector('.cart-drawer__panel');
    const drawerItem = drawer?.querySelector('[data-cart-item]');
    const drawerTotal = drawer?.querySelector('[data-cart-total]');
    let drawerReturnFocus;
    const closeCartDrawer = () => {
        if (!drawer) return;
        drawer.classList.add('hidden');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cart-drawer-open');
        drawerReturnFocus?.focus?.();
    };
    const openCartDrawer = (payload, trigger) => {
        if (!drawer || !drawerItem) return;
        drawerReturnFocus = trigger;
        const item = payload.item || {};
        const status = drawer.querySelector('[data-cart-status]');
        if (status) status.textContent = 'به سبد خرید اضافه شد!';
        drawerItem.innerHTML = '';
        if (item.thumbnail) {
            const image = document.createElement('img');
            image.src = item.thumbnail;
            image.alt = '';
            image.width = 88;
            image.height = 112;
            image.className = 'h-28 w-22 rounded-2xl bg-[#fbf2ef] object-contain';
            drawerItem.append(image);
        }
        const details = document.createElement('div');
        details.className = 'min-w-0 flex-1';
        const name = document.createElement('strong');
        name.className = 'block text-base';
        name.textContent = item.name || 'محصول';
        const variant = document.createElement('p');
        variant.className = 'mt-2 text-sm text-[var(--color-muted)]';
        variant.textContent = `${item.color || ''} · ${item.size || ''} · تعداد ${item.quantity || 1}`;
        const price = document.createElement('p');
        price.className = 'mt-2 font-extrabold text-[var(--color-action)]';
        price.textContent = item.price_label || '';
        details.append(name, variant, price);
        drawerItem.append(details);
        if (drawerTotal) drawerTotal.textContent = `${groupMoneyDigits(normalizeMoneyDigits(payload.cart_total_toman || 0).replace(/\D/g, ''))} تومان`;
        document.querySelectorAll('[data-cart-count]').forEach((counter) => { counter.textContent = payload.cart_count ?? 0; });
        drawer.classList.remove('hidden');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cart-drawer-open');
        drawerPanel?.focus();
    };
    drawer?.querySelectorAll('[data-cart-close]').forEach((button) => button.addEventListener('click', closeCartDrawer));
    document.querySelectorAll('[data-cart-add-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = form.querySelector('button[type="submit"]');
            if (submit?.disabled) return;
            form.querySelector('[data-cart-error]')?.remove();
            const originalLabel = submit?.textContent;
            if (submit) { submit.disabled = true; submit.textContent = 'در حال افزودن…'; }
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || 'افزودن به سبد خرید انجام نشد.');
                openCartDrawer(payload, submit);
            } catch (error) {
                const message = error.name === 'TypeError' ? 'ارتباط با فروشگاه برقرار نشد؛ دوباره تلاش کنید.' : error.message;
                let notice = form.querySelector('[data-cart-error]');
                if (!notice) {
                    notice = document.createElement('p');
                    notice.className = 'text-sm text-red-700';
                    notice.setAttribute('data-cart-error', '');
                }
                notice.textContent = message;
                if (!notice.parentElement) form.prepend(notice);
            } finally {
                if (submit) { submit.disabled = false; submit.textContent = originalLabel; }
            }
        });
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drawer && !drawer.classList.contains('hidden')) closeCartDrawer(); });

    document.querySelectorAll('[data-banner-image-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const preview = document.querySelector(`[data-banner-preview-image="${input.dataset.bannerPreview}"]`);
            if (!preview || !file) return;
            if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
            preview.dataset.objectUrl = URL.createObjectURL(file);
            preview.src = preview.dataset.objectUrl;
            preview.classList.remove('hidden');
        });
    });
    const bannerTitle = document.querySelector('input[name="title"]');
    const bannerSubtitle = document.querySelector('input[name="subtitle"]');
    const bannerPreviewTitle = document.querySelector('[data-banner-preview-title]');
    const bannerPreviewSubtitle = document.querySelector('[data-banner-preview-subtitle]');
    bannerTitle?.addEventListener('input', () => { if (bannerPreviewTitle) bannerPreviewTitle.textContent = bannerTitle.value || 'عنوان کمپین'; });
    bannerSubtitle?.addEventListener('input', () => { if (bannerPreviewSubtitle) bannerPreviewSubtitle.textContent = bannerSubtitle.value || 'زیرعنوان کمپین'; });

    const filterPanel = document.querySelector('.catalog-filters');
    const filterBackdrop = document.querySelector('[data-filter-close].catalog-filter-backdrop');
    const filterOpen = document.querySelector('[data-filter-open]');
    const closeFilters = () => {
        filterPanel?.classList.remove('is-open');
        filterBackdrop?.classList.add('hidden');
        document.body.classList.remove('filters-open');
    };
    filterOpen?.addEventListener('click', () => {
        filterPanel?.classList.add('is-open');
        filterBackdrop?.classList.remove('hidden');
        document.body.classList.add('filters-open');
        filterPanel?.querySelector('input, select, button')?.focus();
    });
    document.querySelectorAll('[data-filter-close]:not(.catalog-filter-backdrop)').forEach((button) => button.addEventListener('click', closeFilters));
    filterBackdrop?.addEventListener('click', closeFilters);

    document.querySelectorAll('[data-product-configurator]').forEach((configurator) => {
        const select = configurator.querySelector('[data-variant-select]');
        if (!select) return;
        const price = configurator.querySelector('[data-variant-price]');
        const compare = configurator.querySelector('[data-variant-compare]');
        const stock = configurator.querySelector('[data-variant-stock]');
        const submit = configurator.querySelector('button[type="submit"]');
        const gallery = configurator.querySelector('[data-gallery-main]');
        const colorButtons = [...configurator.querySelectorAll('[data-color]')];
        const sizeButtons = [...configurator.querySelectorAll('[data-size]')];
        const format = (value) => groupMoneyDigits(normalizeMoneyDigits(value || 0).replace(/\D/g, ''));
        const sync = (option) => {
            if (!option || !option.value) { if (submit) submit.disabled = true; return; }
            price.textContent = `${format(option.dataset.price)} تومان`;
            compare.textContent = Number(option.dataset.compare || 0) > Number(option.dataset.price || 0) ? `${format(option.dataset.compare)} تومان` : '';
            const quantity = Number(option.dataset.stock || 0);
            stock.textContent = quantity > 0 ? `موجودی: ${format(quantity)} عدد` : 'این ترکیب ناموجود است';
            if (submit) submit.disabled = quantity < 1;
            if (gallery && option.dataset.images) {
                const images = JSON.parse(option.dataset.images || '[]');
                if (images.length) {
                    const image = gallery.querySelector('img');
                    if (image) image.src = images[0];
                }
            }
            colorButtons.forEach((button) => button.classList.toggle('is-selected', button.dataset.color === option.dataset.color));
            sizeButtons.forEach((button) => button.classList.toggle('is-selected', button.dataset.size === option.dataset.size));
        };
        const chooseColor = (color) => {
            const option = [...select.options].find((candidate) => candidate.dataset.color === color && candidate.dataset.active === '1' && Number(candidate.dataset.stock) > 0)
                || [...select.options].find((candidate) => candidate.dataset.color === color && candidate.dataset.active === '1');
            if (option) { select.value = option.value; sync(option); } else { select.value = ''; sync(null); }
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
        const initial = [...select.options].find((option) => option.dataset.active === '1' && Number(option.dataset.stock) > 0);
        if (initial) { select.value = initial.value; sync(initial); chooseColor(initial.dataset.color); }
    });

    const categoryWorkspace = document.querySelector('[data-category-workspace]');
    if (categoryWorkspace) {
        const nodes = () => [...categoryWorkspace.querySelectorAll('[data-category-node]')];
        const editor = categoryWorkspace.querySelector('[data-category-editor-form]');
        const setField = (name, value) => { const field = editor?.querySelector(`[data-category-field="${name}"]`); if (field) field.type === 'checkbox' ? field.checked = value === '1' : field.value = value ?? ''; };
        const updateParentOptions = (node = null) => {
            const select = editor?.querySelector('[data-category-field="parent_id"]');
            if (!select) return;
            const blocked = new Set(node ? [node.dataset.categoryId, ...[...node.querySelectorAll('[data-category-node]')].map((item) => item.dataset.categoryId)] : []);
            [...select.options].forEach((option) => { option.disabled = blocked.has(option.value); });
        };
        const clearEditor = () => {
            if (!editor) return;
            editor.action = editor.dataset.storeUrl || editor.action;
            editor.querySelector('[data-category-method]').value = 'POST';
            categoryWorkspace.querySelector('[data-category-editor-title]').textContent = 'دسته‌بندی تازه';
            categoryWorkspace.querySelector('[data-category-editor-help]').textContent = 'یک دسته اصلی بسازید یا آن را زیر دسته‌ای موجود قرار دهید.';
            setField('name', ''); setField('slug', ''); setField('parent_id', ''); setField('sort_order', '0'); setField('description', ''); setField('is_active', '1');
            updateParentOptions();
            categoryWorkspace.querySelector('[data-category-full-edit]')?.classList.add('hidden');
            categoryWorkspace.querySelector('[data-category-danger]')?.classList.add('hidden');
            categoryWorkspace.querySelector('[data-category-impact]')?.classList.add('hidden');
            const image = categoryWorkspace.querySelector('[data-category-image-preview]');
            if (image) { image.src = ''; image.classList.add('hidden'); }
            nodes().forEach((node) => node.querySelector('[data-category-row]')?.classList.remove('is-selected'));
        };
        const selectCategory = async (node) => {
            if (!editor) return;
            nodes().forEach((item) => item.querySelector('[data-category-row]')?.classList.toggle('is-selected', item === node));
            editor.action = node.dataset.categoryUpdateUrl;
            editor.querySelector('[data-category-method]').value = 'PUT';
            categoryWorkspace.querySelector('[data-category-editor-title]').textContent = node.dataset.categoryName;
            categoryWorkspace.querySelector('[data-category-editor-help]').textContent = `/${node.dataset.categorySlug} · ${node.dataset.categoryActive === '1' ? 'قابل مشاهده در فروشگاه' : 'غیرفعال'}`;
            setField('name', node.dataset.categoryName); setField('slug', node.dataset.categorySlug); setField('parent_id', node.dataset.parentId); setField('sort_order', node.dataset.sortOrder); setField('description', node.dataset.categoryDescription); setField('is_active', node.dataset.categoryActive);
            updateParentOptions(node);
            const fullEdit = categoryWorkspace.querySelector('[data-category-full-edit]');
            if (fullEdit) { fullEdit.href = node.dataset.categoryEditUrl; fullEdit.classList.remove('hidden'); }
            const deleteForm = categoryWorkspace.querySelector('[data-category-delete-form]');
            if (deleteForm) { deleteForm.action = node.dataset.categoryDeleteUrl; categoryWorkspace.querySelector('[data-category-danger]')?.classList.remove('hidden'); }
            const image = categoryWorkspace.querySelector('[data-category-image-preview]');
            if (image) { image.src = node.dataset.categoryImage; image.alt = node.dataset.categoryName; image.classList.toggle('hidden', !node.dataset.categoryImage); }
            const impact = categoryWorkspace.querySelector('[data-category-impact]');
            if (impact) {
                impact.classList.remove('hidden');
                impact.textContent = 'در حال محاسبه وابستگی‌ها…';
                try {
                    const response = await fetch(`/admin/categories/${node.dataset.categoryId}/impact`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await response.json();
                    impact.innerHTML = `<strong>خلاصه اثر تغییر</strong><span>${data.direct_products} محصول مستقیم</span><span>${data.descendant_categories} زیر‌دسته · ${data.descendant_products} محصول وابسته</span><span>${data.homepage_slots} جایگاه صفحه اصلی</span>`;
                } catch { impact.textContent = 'خلاصه وابستگی در دسترس نیست.'; }
            }
        };
        editor.dataset.storeUrl = editor.action;
        categoryWorkspace.querySelector('[data-category-new]')?.addEventListener('click', clearEditor);
        categoryWorkspace.querySelectorAll('[data-category-select]').forEach((button) => button.addEventListener('click', () => selectCategory(button.closest('[data-category-node]'))));
        categoryWorkspace.querySelectorAll('[data-category-toggle]').forEach((button) => button.addEventListener('click', (event) => {
            event.stopPropagation();
            const children = button.closest('[data-category-node]')?.querySelector(':scope > [data-category-level]');
            if (!children) return;
            children.hidden = !children.hidden;
            button.textContent = children.hidden ? '‹' : '⌄';
        }));
        categoryWorkspace.querySelector('[data-category-search]')?.addEventListener('input', (event) => {
            const query = event.target.value.trim().toLocaleLowerCase();
            nodes().forEach((node) => {
                const match = !query || `${node.dataset.categoryName} ${node.dataset.categorySlug}`.toLocaleLowerCase().includes(query);
                node.hidden = !match && !node.querySelector(`[data-category-node][data-category-name*="${CSS.escape(query)}"]`);
            });
        });
        nodes().forEach((node) => {
            const row = node.querySelector('[data-category-row]');
            row?.addEventListener('keydown', (event) => {
                if (!event.altKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
                event.preventDefault();
                const sibling = event.key === 'ArrowUp' ? node.previousElementSibling : node.nextElementSibling;
                if (sibling) event.key === 'ArrowUp' ? sibling.before(node) : sibling.after(node);
            });
            row?.addEventListener('dragstart', () => { node.dataset.dragging = '1'; });
            row?.addEventListener('dragend', () => { delete node.dataset.dragging; });
        });
        categoryWorkspace.querySelectorAll('[data-category-row]').forEach((row) => row.addEventListener('dragover', (event) => event.preventDefault()));
        categoryWorkspace.querySelectorAll('[data-category-row]').forEach((row) => row.addEventListener('drop', (event) => {
            event.preventDefault();
            const dragged = categoryWorkspace.querySelector('[data-dragging]');
            const target = row.closest('[data-category-node]');
            if (!dragged || !target || dragged === target || dragged.contains(target)) return;
            let children = target.querySelector(':scope > [data-category-level]');
            if (!children) { children = document.createElement('ul'); children.className = 'category-tree__children'; children.dataset.categoryLevel = ''; target.append(children); }
            children.append(dragged);
        }));
        categoryWorkspace.querySelector('[data-category-reorder-form]')?.addEventListener('submit', () => {
            const container = categoryWorkspace.querySelector('[data-category-reorder-inputs]');
            container.innerHTML = '';
            nodes().forEach((node, index) => {
                const parent = node.parentElement.closest('[data-category-node]')?.dataset.categoryId || '';
                [['id', node.dataset.categoryId], ['parent_id', parent], ['sort_order', index]].forEach(([name, value]) => {
                    const input = document.createElement('input'); input.type = 'hidden'; input.name = `categories[${index}][${name}]`; input.value = value; container.append(input);
                });
            });
        });
    }
});
