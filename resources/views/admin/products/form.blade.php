@extends('admin.layout')

@section('content')
    @if ($product->exists)<div class="admin-product-tools"><a class="admin-button admin-button--muted" target="_blank" rel="nofollow" href="{{ route('admin.products.preview', $product) }}">پیش‌نمایش واقعی محصول</a><form method="post" action="{{ route('admin.products.duplicate', $product) }}" onsubmit="return confirm('یک پیش‌نویس مستقل از این محصول ساخته شود؟ موجودی تنوع‌ها صفر خواهد بود.')">@csrf<button class="admin-button admin-button--soft" type="submit">ساخت کپی پیش‌نویس</button></form></div>@endif
    @if ($errors->any())<section class="admin-card admin-errors mb-5"><h2 class="font-bold">خطاهای فرم</h2><ul class="mt-2 list-disc pr-6">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
    <h1 class="mb-5 text-3xl font-extrabold">
        {{ $product->exists ? 'ویرایش محصول' : 'محصول تازه' }}
    </h1>
    <nav class="admin-section-nav" aria-label="بخش‌های ویرایش محصول"><a href="#product-basic">اطلاعات پایه</a><a href="#product-images">تصاویر</a><a href="#product-variants">تنوع و موجودی</a><a href="#product-seo">انتشار و SEO</a></nav>
    @if ($product->exists)
        <section class="admin-card mb-5 border-r-4 border-amber-500">
            <h2 class="mb-2 text-xl font-bold">چک‌لیست آمادگی محصول</h2>
            @if ($readiness['is_demo'])<p class="mb-2 text-amber-800">این محصول دموی محلی است و برای فروش واقعی تأیید نشده است.</p>@endif
            @if ($readiness['required']->isNotEmpty())<p class="font-bold">الزامی برای انتشار</p><ul class="list-disc pr-6">@foreach($readiness['required'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>@else<p class="text-green-700">الزامات انتشار تکمیل است.</p>@endif
            @if ($readiness['recommended']->isNotEmpty())<p class="mt-2 font-bold">پیشنهادهای کیفیت و SEO</p><ul class="list-disc pr-6">@foreach($readiness['recommended'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>@endif
            <div class="mt-3 rounded-xl bg-slate-50 p-3"><p>وضعیت تأیید merchant: <strong>{{ $product->merchant_verification_status ?? 'pending' }}</strong></p>@if($product->merchant_review_reason)<p class="text-sm">دلیل: {{ $product->merchant_review_reason }}</p>@endif<div class="mt-2 flex flex-wrap gap-2"><form method="post" action="{{ route('admin.products.merchant-verification', $product) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="admin-button" type="submit">تأیید merchant</button></form><form method="post" action="{{ route('admin.products.merchant-verification', $product) }}">@csrf<input type="hidden" name="decision" value="rejected"><input name="reason" placeholder="دلیل رد یا نیاز به اصلاح" class="rounded-lg border p-2"><button class="admin-button admin-button--muted" type="submit">بازگردانی به اصلاح</button></form></div></div>
        </section>
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
            <select name="category_id">
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
        <div class="admin-span"><h2 id="product-seo" class="admin-form-heading">توضیحات و نمایش در جستجو</h2></div>
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
            <select name="robots_directive"><option value="">پیش‌فرض</option><option value="index,follow" @selected(old('robots_directive',$product->robots_directive)==='index,follow')>index,follow</option><option value="noindex,follow" @selected(old('robots_directive',$product->robots_directive)==='noindex,follow')>noindex,follow</option><option value="noindex,nofollow" @selected(old('robots_directive',$product->robots_directive)==='noindex,nofollow')>noindex,nofollow</option></select>
        </label>
        <label class="admin-span">محتوای تکمیلی SEO
            <textarea name="seo_content" rows="4">{{ old('seo_content', $product->seo_content) }}</textarea>
        </label>
        <label>جدول سایز (JSON)
            <textarea name="size_chart" rows="3">{{ old('size_chart', $product->size_chart ? json_encode($product->size_chart, JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </label>
        <label><span>انتشار</span>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
        </label>
        <label><span>محصول ویژه</span>
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
        </label>
        <label class="admin-span" id="product-images-section">تصاویر گالری <span class="admin-help">حداکثر ۶ تصویر، JPG / PNG / WebP و تا ۵ مگابایت</span>
            <input id="product-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><progress id="image-upload-progress" class="hidden w-full" max="100" value="0"></progress><span id="image-upload-status" class="text-xs text-slate-500"></span>
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
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><strong>جدول اندازه‌گیری واقعی (سانتی‌متر)</strong><div class="flex gap-2"><select id="measurement-template" class="rounded-lg border p-2"><option value="set">ست لباس</option><option value="top">بلوز و سویشرت</option><option value="pants">شلوار</option><option value="dress">پیراهن</option><option value="jacket">کاپشن و کت</option><option value="custom">سفارشی</option></select><button type="button" id="apply-measurement-template" class="rounded-lg border px-3 py-2">بارگذاری ستون‌ها</button></div></div>
            <div class="overflow-x-auto"><table class="admin-variants min-w-[560px]"><thead id="measurement-head"></thead><tbody id="measurement-body"></tbody></table></div>
            <input type="hidden" name="measured_dimensions" id="measured-dimensions"><button type="button" id="add-measurement-row" class="mt-3 rounded-lg border px-3 py-2">افزودن ردیف اندازه</button>
            <p class="mt-2 text-xs text-slate-500">فقط اندازه‌های واقعی فروشنده را وارد کنید؛ همه مقادیر بر حسب سانتی‌متر و اعشار مجاز است.</p>
        </div>
        <label>Content review
            <select name="content_review_status"><option value="draft" @selected(old('content_review_status', $product->content_review_status) === 'draft')>Draft</option><option value="needs_review" @selected(old('content_review_status', $product->content_review_status) === 'needs_review')>Needs review</option><option value="approved" @selected(old('content_review_status', $product->content_review_status) === 'approved')>Approved</option></select>
        </label>

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
                        <label class="text-xs">زیرنویس تصویر
                            <input name="image_caption[{{ $image->id }}]" value="{{ old('image_caption.'.$image->id, $image->caption) }}">
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
            <h2 class="mb-3 text-xl font-bold">تنوع، قیمت و موجودی</h2>
            <h2 class="mb-3 text-xl font-bold">گونه‌ها</h2>
            <div class="admin-table-wrap">
                <table class="admin-variants">
                    <thead><tr><th>رنگ</th><th>اندازه</th><th>SKU</th><th>قیمت تومان</th><th>قیمت قبل</th><th>HEX</th><th>فعال</th></tr></thead>
                    <tbody>
                        @foreach (old('variants', $product->exists ? $product->variants->keyBy('id')->toArray() : [0 => []]) as $id => $variant)
                            <tr>
                                <td><input class="bulk-variant" type="checkbox" value="{{ $id }}" aria-label="انتخاب برای تغییر قیمت"></td>
                                <td><input name="variants[{{ $id }}][color_name]" required value="{{ data_get($variant, 'color_name') }}"></td>
                                <td><input name="variants[{{ $id }}][size]" required value="{{ data_get($variant, 'size') }}"></td>
                                <td><input name="variants[{{ $id }}][sku]" dir="ltr" value="{{ data_get($variant, 'sku') }}" placeholder="تولید خودکار"></td>
                                <td><input name="variants[{{ $id }}][price_toman]" required type="number" min="1" value="{{ data_get($variant, 'price_toman') }}"></td>
                                <td><input name="variants[{{ $id }}][compare_at_price_toman]" type="number" min="1" value="{{ data_get($variant, 'compare_at_price_toman') }}"></td>
                                <td><input name="variants[{{ $id }}][color_hex]" dir="ltr" value="{{ data_get($variant, 'color_hex') }}"></td>
                                <td><input name="variants[{{ $id }}][is_active]" value="1" type="checkbox" @checked(data_get($variant, 'is_active', true))></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="mt-3 rounded-lg border px-3 py-2"
                    onclick="const t=document.querySelector('#variant-template').innerHTML.replaceAll('__KEY__',crypto.randomUUID());document.querySelector('.admin-variants tbody').insertAdjacentHTML('beforeend',t)">
                افزودن گونه
            </button>
            <template id="variant-template">
                <tr>
                    <td><input name="variants[__KEY__][color_name]" required></td>
                    <td><input name="variants[__KEY__][size]" required></td>
                    <td><input name="variants[__KEY__][sku]" dir="ltr" placeholder="تولید خودکار"></td>
                    <td><input name="variants[__KEY__][price_toman]" required type="number" min="1"></td>
                    <td><input name="variants[__KEY__][compare_at_price_toman]" type="number" min="1"></td>
                    <td><input name="variants[__KEY__][color_hex]" dir="ltr"></td>
                    <td><input name="variants[__KEY__][is_active]" value="1" type="checkbox" checked></td>
                </tr>
            </template>
        </div>

        <div class="admin-span">
            <button class="admin-button" type="submit">ذخیره تغییرات</button>
            <button class="admin-button admin-button--soft" type="submit" name="save_as_draft" value="1">ذخیره به‌عنوان پیش‌نویس</button>
            <a class="admin-button admin-button--muted" href="{{ route('admin.products.index') }}">بازگشت</a>
        </div>
    </form>

    @if ($product->exists)
        <form id="bulk-pricing" class="admin-card mt-5 flex flex-wrap items-end gap-3" method="post" action="{{ route('admin.products.prices.preview', $product) }}">
            @csrf
            <label>قیمت جدید به تومان<input required min="1" type="number" name="price_toman" class="rounded-lg border p-2"></label>
            <input type="hidden" name="variant_ids[]" value="">
            <button class="admin-button" type="submit">پیش‌نمایش قیمت انتخابی</button><span class="text-sm text-slate-600">ابتدا تنوع‌ها را با چک‌باکس جدول انتخاب کنید.</span>
        </form>
    @endif

    @if ($product->exists)
        <section class="admin-card mt-5">
            <h2 class="mb-3 text-xl font-bold">تغییر موجودی</h2>
            @foreach ($product->variants as $variant)
                <form class="mb-3 flex flex-wrap items-end gap-2" method="post"
                      action="{{ route('admin.variants.stock', $variant) }}">
                    @csrf
                    @method('patch')
                    <span class="min-w-36">{{ $variant->color_name }} / {{ $variant->size }} — فعلی: {{ $variant->stock_quantity }}</span>
                    <label>موجودی جدید
                        <input required type="number" min="0" name="quantity" value="{{ $variant->stock_quantity }}">
                    </label>
                    <label>دلیل
                        <input required name="reason" minlength="3" maxlength="180">
                    </label>
                    <button class="admin-button">ثبت تغییر</button>
                </form>
            @endforeach
        </section>
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
(()=>{
    const form=document.querySelector('.admin-form');
    if(!form)return;
    let changed=false;
    form.addEventListener('input',()=>{changed=true});
    form.addEventListener('change',()=>{changed=true});
    form.addEventListener('submit',()=>{changed=false});
    window.addEventListener('beforeunload',event=>{if(changed){event.preventDefault();event.returnValue=''}});
    const imageInput=document.querySelector('#product-images');
    const previews=document.querySelector('#new-image-previews');
    const defaultAlt=@js($product->name ?: 'تصویر محصول');
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
            figure.append(image,caption,altWrap,captionWrap);
            previews.append(figure);
        });
        changed=true;
    });
})();
</script>@endpush
