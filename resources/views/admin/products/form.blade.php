@extends('admin.layout')

@section('content')
    @if ($product->exists)<div class="admin-product-tools"><a class="admin-button admin-button--muted" target="_blank" rel="nofollow" href="{{ route('admin.products.preview', $product) }}">پیش‌نمایش واقعی محصول</a><form method="post" action="{{ route('admin.products.duplicate', $product) }}" onsubmit="return confirm('یک پیش‌نویس مستقل از این محصول ساخته شود؟ موجودی تنوع‌ها صفر خواهد بود.')">@csrf<button class="admin-button admin-button--soft" type="submit">ساخت کپی پیش‌نویس</button></form></div>@endif
    @if ($errors->any())<section class="admin-card admin-errors mb-5" data-validation-summary role="alert" aria-live="assertive" tabindex="-1"><h2 class="font-bold">خطاهای فرم</h2><ul class="mt-2 list-disc pr-6">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
    <h1 class="mb-5 text-3xl font-extrabold">
        {{ $product->exists ? 'ویرایش محصول' : 'محصول تازه' }}
    </h1>
    <nav class="admin-section-nav" aria-label="بخش‌های ویرایش محصول"><a href="#product-basic">اطلاعات پایه</a><a href="#product-images">تصاویر</a><a href="#product-variants">تنوع و موجودی</a><a href="#product-seo">انتشار و SEO</a></nav>
    @if ($product->exists)

    @endif

    <form class="admin-card admin-form" method="post" enctype="multipart/form-data"
          action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
        @csrf
        @if ($product->exists)
            @method('put')
        @endif

        <div class="admin-span"><h2 id="product-basic" class="admin-form-heading">اطلاعات پایه و دسته‌بندی</h2></div>
        <label>نام محصول
            <input required name="name" value="{{ old('name', $product->name) }}">
        </label>
        <label>Slug
            <input name="slug" dir="ltr" value="{{ old('slug', $product->slug) }}">
        </label>
        <label>SKU محصول
            <input name="sku" dir="ltr" value="{{ old('sku', $product->sku) }}">
        </label>
        <label>دسته‌بندی
            <select name="category_id" data-tom-select>
                <option value="">انتخاب دسته</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>برند
            <input name="brand" value="{{ old('brand', $product->brand) }}">
        </label>
        <label>جنس پارچه
            <input name="material" value="{{ old('material', $product->material) }}">
        </label>
        <label>راهنمای سن
            <input name="age_guidance" value="{{ old('age_guidance', $product->age_guidance) }}">
        </label>
        <div class="admin-span ai-seo-tools"><h2 id="product-seo" class="admin-form-heading">توضیحات و نمایش در جستجو</h2><button class="admin-button admin-button--soft" type="button" data-ai-seo-generate>تولید سئو با هوش مصنوعی</button><p class="ai-seo-status" data-ai-seo-status role="status" aria-live="polite"></p><section class="ai-seo-preview" data-ai-seo-preview hidden aria-label="پیش‌نمایش پیشنهادهای سئو"><h3>پیش‌نمایش قابل ویرایش؛ برای هر فیلد، اعمال را جداگانه انتخاب کنید</h3><div data-ai-seo-fields></div><div class="ai-seo-actions"><button type="button" class="admin-button" data-ai-seo-apply>اعمال پیشنهادهای انتخاب‌شده</button><button type="button" class="admin-button admin-button--muted" data-ai-seo-cancel>انصراف</button></div></section></div>
        <label>عنوان SEO
            <input name="meta_title" value="{{ old('meta_title', $product->meta_title) }}">
        </label>
        <label>Canonical URL
            <input dir="ltr" name="canonical_url" value="{{ old('canonical_url', $product->canonical_url) }}">
        </label>
        <label class="admin-span">توضیح کوتاه
            <input name="short_description" value="{{ old('short_description', $product->short_description) }}">
        </label>
        <label class="admin-span">توضیحات
            <textarea name="description" rows="4">{{ old('description', $product->description) }}</textarea>
        </label>
        <label class="admin-span">روش نگهداری
            <textarea name="care_instructions" rows="3">{{ old('care_instructions', $product->care_instructions) }}</textarea>
        </label>
        <label>توضیح SEO
            <input name="meta_description" value="{{ old('meta_description', $product->meta_description) }}">
        </label>
        <label>دستور ربات
            <select name="robots_directive" data-tom-select><option value="">پیش‌فرض</option><option value="index,follow" @selected(old('robots_directive',$product->robots_directive)==='index,follow')>index,follow</option><option value="noindex,follow" @selected(old('robots_directive',$product->robots_directive)==='noindex,follow')>noindex,follow</option><option value="noindex,nofollow" @selected(old('robots_directive',$product->robots_directive)==='noindex,nofollow')>noindex,nofollow</option></select>
        </label>
        <label class="admin-span">محتوای تکمیلی SEO
            <textarea name="seo_content" rows="4">{{ old('seo_content', $product->seo_content) }}</textarea>
        </label>
        <label>جدول سایز (JSON)
            <textarea name="size_chart" rows="3">{{ old('size_chart', $product->size_chart ? json_encode($product->size_chart, JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </label>
        <fieldset class="admin-span admin-publication" aria-describedby="publication-help">
            <legend>وضعیت انتشار</legend>
            <p id="publication-help">انتخاب وضعیت فقط هنگام ذخیره فرم اعمال می‌شود.</p>
            <div class="admin-publication__options" role="radiogroup" aria-label="وضعیت انتشار محصول">
                <label class="admin-publication__option">
                    <input type="radio" name="is_active" value="1" @checked((string) old('is_active', $product->is_active ? '1' : '0') === '1')>
                    <span><strong>منتشر کردن محصول</strong><small>محصول پس از ذخیره در فروشگاه قابل نمایش خواهد بود.</small></span>
                </label>
                <label class="admin-publication__option">
                    <input type="radio" name="is_active" value="0" @checked((string) old('is_active', $product->is_active ? '1' : '0') !== '1')>
                    <span><strong>ذخیره به‌صورت پیش‌نویس</strong><small>محصول ذخیره می‌شود اما در فروشگاه نمایش داده نمی‌شود.</small></span>
                </label>
            </div>
        </fieldset>
        <label><span>محصول ویژه</span>
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
        </label>
        <label class="admin-span product-image-upload" id="product-images-section">تصاویر گالری <span class="admin-help">حداکثر ۶ تصویر، JPG / PNG / WebP و تا ۵ مگابایت برای هر تصویر</span>
            <input id="product-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-filepond data-max-files="{{ max(0, 6 - $product->images->count()) }}"><span id="image-upload-status" class="text-xs text-slate-500"></span>
            <span class="admin-image-meta"><span>تصویر جلد برای عکس‌های تازه</span><select id="primary-upload-image" aria-label="انتخاب تصویر جلد از عکس‌های تازه"><option value="">انتخاب اولین تصویر</option></select></span>
            <input type="hidden" name="cover_image_index" id="cover-image-index" value="">
            <span id="new-image-previews" class="admin-image-previews" aria-live="polite"></span>
        </label>

        <div class="admin-span"><h2 class="text-xl font-bold">اطلاعات پارچه و اندازه‌گیری واقعی</h2></div>
        <label>Composition
            <input name="composition" value="{{ old('composition', $product->composition) }}">
        </label>
        <label>Season
            <input name="season" value="{{ old('season', $product->season) }}">
        </label>
        <label class="admin-span">Fit information
            <textarea name="fit_information" rows="3">{{ old('fit_information', $product->fit_information) }}</textarea>
        </label>
        <label class="admin-span">Selling points
            <textarea name="selling_points" rows="3">{{ old('selling_points', $product->selling_points) }}</textarea>
        </label>
        <div class="admin-span rounded-xl border p-3" id="measurement-editor" data-measurements="{{ e(json_encode(old('measured_dimensions', $product->measured_dimensions ?: []), JSON_UNESCAPED_UNICODE)) }}">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><strong>جدول اندازه‌گیری واقعی (سانتی‌متر)</strong><div class="flex gap-2"><select id="measurement-template" class="rounded-lg border p-2" data-tom-select><option value="set">ست لباس</option><option value="top">بلوز و سویشرت</option><option value="pants">شلوار</option><option value="dress">پیراهن</option><option value="jacket">کاپشن و کت</option><option value="custom">سفارشی</option></select><button type="button" id="apply-measurement-template" class="rounded-lg border px-3 py-2">بارگذاری ستون‌ها</button></div></div>
            <div class="overflow-x-auto"><table class="admin-variants min-w-[560px]"><thead id="measurement-head"></thead><tbody id="measurement-body"></tbody></table></div>
            <input type="hidden" name="measured_dimensions" id="measured-dimensions"><button type="button" id="add-measurement-row" class="mt-3 rounded-lg border px-3 py-2">افزودن ردیف اندازه</button>
            <p class="mt-2 text-xs text-slate-500">فقط اندازه‌های واقعی فروشنده را وارد کنید؛ همه مقادیر بر حسب سانتی‌متر و اعشار مجاز است.</p>
        </div>

        @if ($product->exists && $product->images->isNotEmpty())
            <div class="admin-span flex flex-wrap gap-3">
                @foreach ($product->images as $image)
                    <figure class="gallery-item w-32" draggable="true" data-image-id="{{ $image->id }}">
                        <img class="aspect-square w-full rounded-xl object-cover"
                             src="{{ asset('storage/' . $image->path) }}" alt="{{ $image->alt_text }}">
                        <figcaption class="mt-1 text-xs">{{ $image->caption }}</figcaption>
                        <button class="text-sm text-pink-700" type="submit" form="cover-image-{{ $image->id }}">{{ $image->is_cover ? 'جلد فعلی' : 'انتخاب به‌عنوان جلد' }}</button>
                        <label class="text-xs">متن جایگزین
                            <input name="image_alt[{{ $image->id }}]" value="{{ old('image_alt.'.$image->id, $image->alt_text) }}">
                        </label>
                        <label class="text-xs">رنگ تصویر
                            <input name="image_color[{{ $image->id }}]" value="{{ old('image_color.'.$image->id, $image->color_name) }}">
                        </label>
                        <label class="text-xs">زیرنویس تصویر
                            <input name="image_caption[{{ $image->id }}]" value="{{ old('image_caption.'.$image->id, $image->caption) }}">
                        </label>
                        <label class="text-xs">Variant image
                            <select name="image_variant[{{ $image->id }}]" data-tom-select><option value="">General color image</option>@foreach($product->variants as $variant)<option value="{{ $variant->id }}" @selected(old('image_variant.'.$image->id, $image->product_variant_id) == $variant->id)>{{ $variant->color_name }} · {{ $variant->size }}</option>@endforeach</select>
                        </label>
                        <label class="text-xs">ترتیب
                            <input type="number" min="0" max="65535"
                                   name="image_order[{{ $image->id }}]" value="{{ $image->sort_order }}">
                        </label>
                        <button class="text-sm text-red-700" type="submit"
                                form="delete-image-{{ $image->id }}"
                                onclick="return confirm('تصویر حذف شود؟')">حذف تصویر</button>
                        <div class="flex gap-1"><button type="button" class="gallery-up rounded border px-2" aria-label="انتقال تصویر به بالا">↑</button><button type="button" class="gallery-down rounded border px-2" aria-label="انتقال تصویر به پایین">↓</button></div>
                    </figure>
                @endforeach
            </div>
        @endif

        <div class="admin-span" id="product-variants">
            <input type="hidden" name="variants_complete" value="1" disabled data-variants-complete>
            <h2 class="mb-3 text-xl font-bold">تنوع، قیمت و موجودی</h2>
            <h2 class="mb-3 text-xl font-bold">گونه‌ها</h2>
            <div class="admin-table-wrap">
                <table id="variant-editor-table" class="admin-variants">
                    <thead><tr><th>انتخاب</th><th>رنگ</th><th>اندازه</th><th>SKU</th><th>قیمت تومان</th><th>قیمت قبل</th><th>رنگ‌نما (HEX)</th><th>فعال</th><th>حذف</th></tr></thead>
                    <tbody>
                        @foreach (old('variants', $product->exists ? $product->variants->keyBy('id')->toArray() : [0 => []]) as $id => $variant)
                            <tr>
                                <td data-label="انتخاب"><input type="hidden" name="variants[{{ $id }}][variant_id]" value="{{ $product->exists && $product->variants->contains('id', (int) $id) ? $id : data_get($variant, 'variant_id') }}"><input class="bulk-variant" type="checkbox" value="{{ $id }}" aria-label="انتخاب برای تغییر قیمت"></td>
                                <td data-label="رنگ"><input name="variants[{{ $id }}][color_name]" required value="{{ data_get($variant, 'color_name') }}"></td>
                                <td data-label="اندازه"><input name="variants[{{ $id }}][size]" required value="{{ data_get($variant, 'size') }}"></td>
                                <td data-label="SKU"><input name="variants[{{ $id }}][sku]" dir="ltr" value="{{ data_get($variant, 'sku') }}" placeholder="تولید خودکار"></td>
                                <td data-label="قیمت تومان"><input name="variants[{{ $id }}][price_toman]" required type="text" inputmode="numeric" data-money-input dir="ltr" value="{{ filled(data_get($variant, 'price_toman')) ? \App\Support\Money::format(data_get($variant, 'price_toman')) : '' }}"></td>
                                <td data-label="قیمت قبل"><input name="variants[{{ $id }}][compare_at_price_toman]" type="text" inputmode="numeric" data-money-input dir="ltr" value="{{ filled(data_get($variant, 'compare_at_price_toman')) ? \App\Support\Money::format(data_get($variant, 'compare_at_price_toman')) : '' }}"></td>
                                <td data-label="رنگ‌نما"><div class="flex items-center gap-2"><input type="color" class="variant-color-picker h-10 w-12 cursor-pointer rounded-lg border p-1" value="{{ data_get($variant, 'color_hex') ?: '#000000' }}" aria-label="انتخاب رنگ"><span class="variant-color-swatch h-6 w-6 rounded-full border border-slate-300" style="background-color: {{ data_get($variant, 'color_hex') ?: '#000000' }}"></span><input class="variant-color-hex w-28" name="variants[{{ $id }}][color_hex]" dir="ltr" value="{{ data_get($variant, 'color_hex') }}" placeholder="#RRGGBB" pattern="^#[0-9A-Fa-f]{6}$" inputmode="text" aria-label="کد HEX رنگ"></div></td>
                                <td data-label="فعال"><input name="variants[{{ $id }}][is_active]" value="1" type="checkbox" @checked(data_get($variant, 'is_active', true))>@if (filled(data_get($variant, 'updated_at')))<input type="hidden" name="variants[{{ $id }}][updated_at]" value="{{ \Illuminate\Support\Carbon::parse(data_get($variant, 'updated_at'))->toISOString() }}">@endif</td>
                                <td data-label="حذف"><button type="button" class="text-red-700 underline" data-remove-variant>حذف تنوع</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="mt-3 rounded-lg border px-3 py-2"
                    onclick="const t=document.querySelector('#variant-template').innerHTML.replaceAll('__KEY__',crypto.randomUUID());document.querySelector('#variant-editor-table tbody').insertAdjacentHTML('beforeend',t);window.initVariantColorPickers?.();window.initMoneyInputs?.(document.querySelector('#variant-editor-table tbody tr:last-child'))">
                افزودن گونه
            </button>
            <button type="button" class="mt-3 rounded-lg border px-3 py-2" data-generate-variant-combinations>تولید خودکار ترکیب رنگ و سایز</button>
            <template id="variant-template">
                <tr>
                    <td data-label="انتخاب"><input type="hidden" name="variants[__KEY__][variant_id]" value=""><input class="bulk-variant" type="checkbox" value="__KEY__" aria-label="انتخاب برای تغییر قیمت"></td>
                    <td data-label="رنگ"><input name="variants[__KEY__][color_name]" required></td>
                    <td data-label="اندازه"><input name="variants[__KEY__][size]" required></td>
                    <td data-label="SKU"><input name="variants[__KEY__][sku]" dir="ltr" placeholder="تولید خودکار"></td>
                    <td data-label="قیمت تومان"><input name="variants[__KEY__][price_toman]" required type="text" inputmode="numeric" data-money-input dir="ltr"></td>
                    <td data-label="قیمت قبل"><input name="variants[__KEY__][compare_at_price_toman]" type="text" inputmode="numeric" data-money-input dir="ltr"></td>
                    <td data-label="رنگ‌نما"><div class="flex items-center gap-2"><input type="color" class="variant-color-picker h-10 w-12 cursor-pointer rounded-lg border p-1" value="#000000" aria-label="انتخاب رنگ"><span class="variant-color-swatch h-6 w-6 rounded-full border border-slate-300" style="background-color: #000000"></span><input class="variant-color-hex w-28" name="variants[__KEY__][color_hex]" dir="ltr" placeholder="#RRGGBB" pattern="^#[0-9A-Fa-f]{6}$" inputmode="text" aria-label="کد HEX رنگ"></div></td>
                    <td data-label="فعال"><input name="variants[__KEY__][is_active]" value="1" type="checkbox" checked></td>
                    <td data-label="حذف"><button type="button" class="text-red-700 underline" data-remove-variant>حذف تنوع</button></td>
                </tr>
            </template>
        </div>

        <div class="admin-span">
            <button class="admin-button" type="submit">ذخیره تغییرات</button>
            <button class="admin-button admin-button--soft" type="submit" name="save_as_draft" value="1">ذخیره به‌عنوان پیش‌نویس</button>
            <a class="admin-button admin-button--muted" href="{{ route('admin.products.index') }}">بازگشت</a>
        </div>
    </form>

    <script>
        (() => {
            const form = document.querySelector('.admin-form[enctype="multipart/form-data"]');
            const button = document.querySelector('[data-ai-seo-generate]');
            const status = document.querySelector('[data-ai-seo-status]');
            const preview = document.querySelector('[data-ai-seo-preview]');
            const fieldsContainer = document.querySelector('[data-ai-seo-fields]');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let suggestions = null;
            const targetFields = { seo_title: 'meta_title', meta_description: 'meta_description', short_description: 'short_description', description: 'description', suggested_slug: 'slug', image_alt: 'image_alt' };
            const targetFor = (key) => key === 'image_alt' ? form.querySelector('input[name^="image_alt["]') : form.elements[targetFields[key]];
            const collect = (selector) => [...form.querySelectorAll(selector)].map((field) => field.value.trim()).filter(Boolean);
            button?.addEventListener('click', async () => {
                button.disabled = true;
                status.textContent = 'در حال تولید محتوای سئو…';
                preview.hidden = true;
                const payload = {
                    name: form.elements.name.value,
                    category_id: form.elements.category_id.value || null,
                    material: form.elements.material.value,
                    composition: form.elements.composition?.value || '',
                    age_guidance: form.elements.age_guidance?.value || '',
                    season: form.elements.season?.value || '',
                    fit_information: form.elements.fit_information?.value || '',
                    brand: form.elements.brand?.value || 'Kidoki',
                    description: form.elements.description.value,
                    short_description: form.elements.short_description.value,
                    features: (form.elements.selling_points?.value || '').split(/[\n،,]+/).map((item) => item.trim()).filter(Boolean),
                    colors: collect('input[name^="variants["][name$="[color_name]"]'),
                    sizes: collect('input[name^="variants["][name$="[size]"]'),
                };
                try {
                    const response = await fetch(@json(route('admin.products.seo-generate')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: JSON.stringify(payload) });
                    const body = await response.json();
                    if (!response.ok) throw new Error(body.message || 'تولید محتوا انجام نشد.');
                    suggestions = body.suggestions;
                    fieldsContainer.replaceChildren();
                    Object.entries({ seo_title: 'عنوان SEO', meta_description: 'توضیح SEO', short_description: 'توضیح کوتاه', description: 'توضیحات کامل', suggested_slug: 'Slug پیشنهادی', image_alt: 'متن جایگزین تصویر', keywords: 'عبارت‌های جست‌وجو' }).forEach(([key, label]) => {
                        const value = Array.isArray(suggestions[key]) ? suggestions[key].join('، ') : suggestions[key];
                        if (!value) return;
                        const row = document.createElement('label');
                        row.className = 'ai-seo-preview__field';
                        const title = document.createElement('span'); title.textContent = label;
                        const input = document.createElement(key === 'description' ? 'textarea' : 'input');
                        input.value = value; input.dataset.aiSeoValue = key;
                        const applyLabel = document.createElement('span');
                        if (targetFields[key]) { const target = targetFor(key); const apply = document.createElement('input'); apply.type = 'checkbox'; apply.checked = !target || !target.value.trim(); apply.dataset.aiSeoApply = key; applyLabel.append(apply, document.createTextNode(' اعمال این پیشنهاد')); }
                        else { applyLabel.textContent = 'پیش‌نمایش؛ فیلد متناظر در فرم موجود نیست'; }
                        row.append(title, input, applyLabel); fieldsContainer.append(row);
                    });
                    preview.hidden = false;
                    status.textContent = 'پیشنهادها آماده بازبینی هستند؛ هنوز در محصول اعمال نشده‌اند.';
                } catch (error) {
                    status.textContent = error.message;
                } finally {
                    button.disabled = false;
                }
            });
            document.querySelector('[data-ai-seo-apply]')?.addEventListener('click', () => {
                fieldsContainer.querySelectorAll('[data-ai-seo-apply]:checked').forEach((checkbox) => {
                    const value = fieldsContainer.querySelector(`[data-ai-seo-value="${checkbox.dataset.aiSeoApply}"]`)?.value;
                    const target = targetFor(checkbox.dataset.aiSeoApply);
                    if (target && value !== undefined) target.value = value;
                });
                status.textContent = 'پیشنهادهای انتخاب‌شده در فرم اعمال شدند. محصول هنوز ذخیره نشده است.';
                preview.hidden = true;
            });
            document.querySelector('[data-ai-seo-cancel]')?.addEventListener('click', () => { preview.hidden = true; status.textContent = 'پیشنهادها لغو شدند.'; });
        })();
    </script>

    @if ($product->exists)
        <form id="bulk-pricing" class="admin-card mt-5 flex flex-wrap items-end gap-3" method="post" action="{{ route('admin.products.prices.preview', $product) }}">
            @csrf
            <label>قیمت جدید به تومان<input required min="1" type="number" name="price_toman" class="rounded-lg border p-2"></label>
            <input type="hidden" name="variant_ids[]" value="">
            <button class="admin-button" type="submit">پیش‌نمایش قیمت انتخابی</button><span class="text-sm text-slate-600">ابتدا تنوع‌ها را با چک‌باکس جدول انتخاب کنید.</span>
        </form>
    @endif

    @if ($product->exists)
        <form id="bulk-inventory-form" class="admin-card mt-5" method="post" action="{{ route('admin.products.inventory.update', $product) }}">
            @csrf
            <h2 class="mb-3 text-xl font-bold">تغییر موجودی</h2>
            @foreach ($product->variants as $variant)
                <div class="inventory-row mb-3 flex flex-wrap items-end gap-2 rounded-xl border border-transparent p-2 transition" data-inventory-row>
                    <span class="min-w-36">{{ $variant->color_name }} / {{ $variant->size }} — فعلی: <strong>{{ $variant->stock_quantity }}</strong></span>
                    <label>موجودی جدید
                        <input required type="number" min="0" max="1000000" name="inventory[{{ $variant->id }}][quantity]" value="{{ old('inventory.'.$variant->id.'.quantity', $variant->stock_quantity) }}" data-original="{{ $variant->stock_quantity }}" data-inventory-quantity>
                    </label>
                    <input type="hidden" name="inventory[{{ $variant->id }}][updated_at]" value="{{ $variant->updated_at?->toISOString() }}">
                    <span class="text-xs text-slate-500" data-delta></span>
                </div>
            @endforeach
            <div class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4">
                <label>دلیل تغییرات
                    <input required name="reason" minlength="3" maxlength="180" value="{{ old('reason') }}">
                </label>
                <button class="admin-button" type="submit" data-inventory-submit>ثبت تغییرات موجودی</button>
            </div>
        </form>
    @endif

    @if ($product->exists)
        @foreach ($product->images as $image)
            <form id="delete-image-{{ $image->id }}" class="hidden" method="post"
                  action="{{ route('admin.products.images.destroy', [$product, $image]) }}">
                @csrf
                @method('delete')
            </form>
            <form id="cover-image-{{ $image->id }}" class="hidden" method="post" action="{{ route('admin.products.images.cover', [$product, $image]) }}">@csrf</form>
        @endforeach
    @endif
@endsection
@push('scripts')<script>
(()=>{const root=document.querySelector('#measurement-editor');if(!root)return;const head=document.querySelector('#measurement-head'),body=document.querySelector('#measurement-body'),hidden=document.querySelector('#measured-dimensions');const templates={set:['size','sleeve_length','shirt_length','waist_circumference','pants_rise','pants_length'],top:['size','sleeve_length','garment_length','shoulder_width'],pants:['size','waist_circumference','pants_rise','pants_length'],dress:['size','sleeve_length','shoulder_width','waist_circumference','garment_length'],jacket:['size','sleeve_length','shoulder_width','garment_length','waist_circumference'],custom:['size']};const labels={size:'سایز',sleeve_length:'قد آستین',shirt_length:'قد بلوز',waist_circumference:'دور کمر',pants_rise:'فاق شلوار',pants_length:'قد شلوار',shoulder_width:'سرشانه',garment_length:'قد لباس'};let cols=templates.set;let rows=[];try{rows=JSON.parse(root.dataset.measurements||'[]')}catch(e){};const normalize=v=>v.replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g,d=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));const render=()=>{head.innerHTML='<tr>'+cols.map(c=>'<th>'+labels[c]+'</th>').join('')+'<th></th></tr>';body.innerHTML=rows.map((row,i)=>'<tr>'+cols.map(c=>'<td><input class="w-24 rounded-lg border p-2" inputmode="decimal" data-row="'+i+'" data-col="'+c+'" value="'+(row[c]??'')+'"></td>').join('')+'<td><button type="button" data-remove="'+i+'" class="text-red-700">حذف</button></td></tr>').join('');hidden.value=JSON.stringify(rows);body.querySelectorAll('input').forEach(input=>input.addEventListener('input',()=>{rows[input.dataset.row][input.dataset.col]=normalize(input.value);hidden.value=JSON.stringify(rows)}));body.querySelectorAll('[data-remove]').forEach(button=>button.addEventListener('click',()=>{rows.splice(Number(button.dataset.remove),1);render()}))};document.querySelector('#apply-measurement-template').addEventListener('click',()=>{cols=templates[document.querySelector('#measurement-template').value];rows=rows.map(row=>Object.fromEntries(cols.map(c=>[c,row[c]??''])));render()});document.querySelector('#add-measurement-row').addEventListener('click',()=>{rows.push(Object.fromEntries(cols.map(c=>[c,''])));render()});render()})();
</script><script>document.querySelectorAll('.gallery-item').forEach(item=>{item.querySelector('.gallery-up')?.addEventListener('click',()=>{const previous=item.previousElementSibling;if(previous?.classList.contains('gallery-item')){item.parentNode.insertBefore(item,previous);syncGallery()}});item.querySelector('.gallery-down')?.addEventListener('click',()=>{const next=item.nextElementSibling;if(next?.classList.contains('gallery-item')){item.parentNode.insertBefore(next,item);syncGallery()}});item.addEventListener('dragstart',event=>event.dataTransfer.setData('text/plain',item.dataset.imageId));item.addEventListener('dragover',event=>event.preventDefault());item.addEventListener('drop',event=>{event.preventDefault();const dragged=[...document.querySelectorAll('.gallery-item')].find(candidate=>candidate.dataset.imageId===event.dataTransfer.getData('text/plain'));if(dragged&&dragged!==item)item.parentNode.insertBefore(dragged,item);syncGallery()})});const syncGallery=()=>document.querySelectorAll('.gallery-item').forEach((item,index)=>{const input=item.querySelector('input[name^="image_order"]');if(input)input.value=index});</script>@endpush
@if($product->exists)@push('scripts')<script>document.querySelector('#bulk-pricing')?.addEventListener('submit',event=>{const form=event.currentTarget;form.querySelectorAll('input[name="variant_ids[]"]').forEach(input=>{if(input.value==='')input.remove()});document.querySelectorAll('.bulk-variant:checked').forEach(box=>{const input=document.createElement('input');input.type='hidden';input.name='variant_ids[]';input.value=box.value;form.appendChild(input)});if(!form.querySelector('input[name="variant_ids[]"]')){event.preventDefault();alert('حداقل یک تنوع را انتخاب کنید.')}});</script>@endpush @endif
@push('scripts')<script>
window.initVariantColorPickers=()=>document.querySelectorAll('.variant-color-picker').forEach(picker=>{if(picker.dataset.bound)return;picker.dataset.bound='1';const wrapper=picker.closest('div');const hex=wrapper.querySelector('.variant-color-hex');const swatch=wrapper.querySelector('.variant-color-swatch');const valid=value=>/^#[0-9a-fA-F]{6}$/.test(value);const sync=(value,source)=>{if(!valid(value))return;if(source!=='hex')hex.value=value.toUpperCase();picker.value=value;swatch.style.backgroundColor=value};picker.addEventListener('input',()=>sync(picker.value,'picker'));hex.addEventListener('input',()=>{const value=hex.value.trim();if(valid(value))sync(value,'hex')});hex.addEventListener('blur',()=>hex.setCustomValidity(hex.value&&!valid(hex.value)?'کد رنگ باید با قالب #RRGGBB باشد.':''))});window.initVariantColorPickers();
document.querySelector('#variant-editor-table')?.addEventListener('click',event=>{const button=event.target.closest('[data-remove-variant]');if(!button)return;const row=button.closest('tr');if(row.querySelector('input[name$="[variant_id]"]')?.value&&!window.confirm('این تنوع در ذخیره بعدی غیرفعال می‌شود؛ ادامه می‌دهید؟'))return;row.remove()});
document.querySelector('[data-generate-variant-combinations]')?.addEventListener('click',()=>{const table=document.querySelector('#variant-editor-table tbody');const rows=[...table.querySelectorAll('tr')];const colors=[...new Set(rows.map(row=>row.querySelector('input[name$="[color_name]"]')?.value.trim()).filter(Boolean))];const sizes=[...new Set(rows.map(row=>row.querySelector('input[name$="[size]"]')?.value.trim()).filter(Boolean))];if(!colors.length||!sizes.length){alert('ابتدا دست‌کم یک رنگ و یک سایز در ردیف‌ها وارد کنید.');return}const existing=new Set(rows.map(row=>{const color=row.querySelector('input[name$="[color_name]"]')?.value.trim().toLocaleLowerCase();const size=row.querySelector('input[name$="[size]"]')?.value.trim().toLocaleLowerCase();return color&&size?`${color}|${size}`:''}));let added=0;colors.forEach(color=>sizes.forEach(size=>{const key=`${color.toLocaleLowerCase()}|${size.toLocaleLowerCase()}`;if(existing.has(key))return;const keyId=crypto.randomUUID();const template=document.querySelector('#variant-template').innerHTML.replaceAll('__KEY__',keyId);table.insertAdjacentHTML('beforeend',template);const row=table.lastElementChild;row.querySelector('input[name$="[color_name]"]').value=color;row.querySelector('input[name$="[size]"]').value=size;existing.add(key);added++;window.initVariantColorPickers?.();window.initMoneyInputs?.(row)}));alert(added?`${added} ترکیب تازه افزوده شد؛ SKU، قیمت و وضعیت را بررسی کنید.`:'همه ترکیب‌های رنگ و سایز از قبل وجود دارند.')});
const inventoryForm=document.querySelector('#bulk-inventory-form');inventoryForm?.querySelectorAll('[data-inventory-quantity]').forEach(input=>input.addEventListener('input',()=>{const row=input.closest('[data-inventory-row]');const delta=Number(input.value)-Number(input.dataset.original);row.classList.toggle('border-amber-300',delta!==0);row.classList.toggle('bg-amber-50',delta!==0);row.querySelector('[data-delta]').textContent=delta===0?'':'تغییر پیشنهادی: '+(delta>0?'+':'')+delta}));inventoryForm?.addEventListener('submit',event=>{const button=event.currentTarget.querySelector('[data-inventory-submit]');if(button.disabled){event.preventDefault();return}button.disabled=true;button.textContent='در حال ثبت...'});
(()=>{
    const form=document.querySelector('.admin-form');
    if(!form)return;
    const completeMarker=form.querySelector('[data-variants-complete]');
    form.addEventListener('submit',()=>{if(completeMarker)completeMarker.disabled=false});
    let changed=false;
    form.addEventListener('input',()=>{changed=true});
    form.addEventListener('change',()=>{changed=true});
    form.addEventListener('submit',()=>{changed=false});
    window.addEventListener('beforeunload',event=>{if(changed){event.preventDefault();event.returnValue=''}});
    const imageInput=document.querySelector('#product-images');
    const previews=document.querySelector('#new-image-previews');
    const defaultAlt=@js($product->name ?: 'تصویر محصول');
    const imageVariants=@js($product->variants->map(fn ($variant) => ['id'=>$variant->id,'label'=>$variant->color_name.' · '.$variant->size])->values());
    imageInput?.addEventListener('change',()=>{
        previews.replaceChildren();
        [...imageInput.files].forEach((file,index)=>{
            if(!file.type.startsWith('image/'))return;
            const figure=document.createElement('figure');
            const image=document.createElement('img');
            image.src=URL.createObjectURL(file);
            image.alt=file.name;
            const caption=document.createElement('figcaption');
            caption.textContent=file.name;
            const altField=document.createElement('input');
            altField.type='text';
            altField.name=`image_alt[${index}]`;
            altField.value=defaultAlt;
            altField.setAttribute('aria-label','متن جایگزین تصویر '+(index+1));
            const altLabel=document.createElement('span');
            altLabel.textContent='متن جایگزین';
            const captionField=document.createElement('input');
            captionField.type='text';
            captionField.name=`image_caption[${index}]`;
            captionField.setAttribute('aria-label','زیرنویس تصویر '+(index+1));
            const captionLabel=document.createElement('span');
            captionLabel.textContent='زیرنویس';
            const altWrap=document.createElement('div');
            altWrap.className='admin-image-meta';
            altWrap.append(altLabel,altField);
            const captionWrap=document.createElement('div');
            captionWrap.className='admin-image-meta';
            captionWrap.append(captionLabel,captionField);
            const colorField=document.createElement('input');
            colorField.name=`image_color[${index}]`;
            colorField.placeholder='رنگ تصویر (مثلاً صورتی)';
            colorField.setAttribute('aria-label','رنگ تصویر '+(index+1));
            const colorWrap=document.createElement('div');
            colorWrap.className='admin-image-meta';
            colorWrap.append('رنگ',colorField);
            const variantSelect=document.createElement('select');
            variantSelect.name=`image_variant[${index}]`;
            variantSelect.dataset.tomSelect='';
            variantSelect.add(new Option('تصویر عمومی رنگ',''));
            imageVariants.forEach((variant)=>variantSelect.add(new Option(variant.label,variant.id)));
            const variantWrap=document.createElement('div');
            variantWrap.className='admin-image-meta';
            variantWrap.append('تنوع اختیاری',variantSelect);
            variantSelect.addEventListener('change',()=>{const chosen=imageVariants.find((variant)=>String(variant.id)===variantSelect.value);if(chosen)colorField.value=chosen.label.split(' · ')[0]});
            figure.append(image,caption,altWrap,captionWrap,colorWrap,variantWrap);
            previews.append(figure);
            window.initSearchableSelects?.(figure);
        });
        changed=true;
    });
})();
</script>@endpush
