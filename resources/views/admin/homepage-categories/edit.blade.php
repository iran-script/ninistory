@extends('admin.layout')

@section('content')
    @php($configuredCount = $slots->filter(fn ($slot) => $slot->category?->is_active)->count())
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div><p class="text-sm text-slate-500">ناوبری صفحه اصلی</p><h1 class="text-3xl font-extrabold">دسته‌های منتخب صفحه اصلی</h1></div>
        <span class="rounded-full {{ $configuredCount === 4 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-4 py-2 text-sm font-bold">{{ $configuredCount }} از ۴ جایگاه فعال</span>
    </div>

    @if ($configuredCount < 4)
        <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">برای تکمیل ردیف چهارگانه، جایگاه‌های خالی را با دسته‌های فعال و غیرتکراری پر کنید. جایگاه‌های خالی در سایت نمایش داده نمی‌شوند.</div>
    @endif

    <form class="admin-card" method="post" enctype="multipart/form-data" action="{{ route('admin.homepage-categories.update') }}" data-homepage-slots-form>
        @csrf
        @method('put')
        <div class="mb-5 flex items-center justify-between gap-3"><div><h2 class="text-xl font-bold">چهار جایگاه قابل تنظیم</h2><p class="mt-1 text-sm text-slate-500">برای جابه‌جایی، کارت‌ها را بکشید و رها کنید.</p></div><span class="text-xs text-slate-500">نسبت پیشنهادی تصویر: ۴:۳</span></div>
        <div class="grid gap-4 xl:grid-cols-2" data-homepage-slot-list>
            @foreach (range(1, 4) as $position)
                @php($slot = $slots->get($position))
                <article class="rounded-2xl border border-[#eadde1] bg-[#fffaf9] p-4" draggable="true" data-homepage-slot data-position="{{ $position }}">
                    <div class="mb-3 flex items-center justify-between gap-3"><strong data-slot-label>جایگاه {{ $position }}</strong><button type="button" class="cursor-grab rounded-lg border px-3 py-1.5 text-sm" data-drag-handle aria-label="جابجایی جایگاه {{ $position }}">↕ جابه‌جایی</button></div>
                    <div class="grid gap-3 md:grid-cols-[1fr_160px]">
                        <div class="space-y-3">
                            <label class="block text-sm font-semibold">دسته فعال
                                <select name="slots[{{ $position }}][category_id]" class="mt-1 block min-h-11 w-full rounded-xl border border-[#e6d5da] bg-white p-2.5" data-slot-field="category_id">
                                    <option value="">بدون دسته</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old("slots.{$position}.category_id", $slot?->category_id) == $category->id)>{{ $category->parent?->name ? $category->parent->name.' / ' : '' }}{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-semibold">عنوان نمایشی اختیاری
                                <input name="slots[{{ $position }}][title]" value="{{ old("slots.{$position}.title", $slot?->title) }}" class="mt-1 block min-h-11 w-full rounded-xl border border-[#e6d5da] bg-white p-2.5" data-slot-field="title" placeholder="مثلاً دخترانه">
                            </label>
                            <label class="block text-sm font-semibold">تصویر اختصاصی اختیاری
                                <span class="mt-1 block text-xs font-normal text-slate-500">JPG، PNG یا WebP؛ حداقل ۴۰۰×۳۰۰</span>
                                <input type="file" name="slots[{{ $position }}][image]" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm" data-slot-field="image" data-slot-preview-input>
                            </label>
                        </div>
                        <div class="overflow-hidden rounded-2xl border border-[#eadde1] bg-[#fceff0]" data-slot-preview>
                            @if ($slot?->image_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($slot->image_path) }}" alt="پیش‌نمایش {{ $slot->title ?: $slot->category?->name }}" class="h-full min-h-32 w-full object-cover" data-slot-preview-image>
                            @elseif ($slot?->category?->image_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($slot->category->image_path) }}" alt="پیش‌نمایش {{ $slot->title ?: $slot->category->name }}" class="h-full min-h-32 w-full object-cover" data-slot-preview-image>
                            @else
                                <div class="grid min-h-32 place-items-center p-4 text-center text-xs text-slate-500" data-slot-preview-empty>با انتخاب دسته، پیش‌نمایش اینجا نمایش داده می‌شود.</div>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-5 flex flex-wrap gap-3 border-t border-[#efded7] pt-5"><button class="admin-button" type="submit">ذخیره چهار جایگاه</button><a class="admin-button admin-button--muted" href="{{ route('home') }}" target="_blank" rel="noreferrer">مشاهده صفحه اصلی</a></div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const list = document.querySelector('[data-homepage-slot-list]');
            if (!list) return;
            let dragged;
            const sync = () => list.querySelectorAll('[data-homepage-slot]').forEach((card, index) => {
                const position = index + 1;
                card.dataset.position = position;
                card.querySelector('[data-slot-label]').textContent = `جایگاه ${position}`;
                card.querySelector('[data-drag-handle]').setAttribute('aria-label', `جابجایی جایگاه ${position}`);
                card.querySelectorAll('[data-slot-field]').forEach((field) => {
                    const key = field.dataset.slotField;
                    field.name = `slots[${position}][${key}]`;
                });
            });
            list.querySelectorAll('[data-homepage-slot]').forEach((card) => {
                card.addEventListener('dragstart', () => { dragged = card; card.classList.add('opacity-50'); });
                card.addEventListener('dragend', () => { dragged = null; card.classList.remove('opacity-50'); sync(); });
                card.addEventListener('dragover', (event) => event.preventDefault());
                card.addEventListener('drop', (event) => { event.preventDefault(); if (dragged && dragged !== card) list.insertBefore(dragged, card); });
                card.querySelector('[data-slot-preview-input]')?.addEventListener('change', (event) => {
                    const file = event.target.files?.[0];
                    if (!file) return;
                    const preview = card.querySelector('[data-slot-preview]');
                    let image = preview.querySelector('[data-slot-preview-image]');
                    if (!image) { image = document.createElement('img'); image.dataset.slotPreviewImage = ''; image.className = 'h-full min-h-32 w-full object-cover'; preview.replaceChildren(image); }
                    image.src = URL.createObjectURL(file);
                    image.alt = 'پیش‌نمایش تصویر انتخاب‌شده';
                });
            });
            sync();
        })();
    </script>
@endpush
