@extends('admin.layout')

@section('content')
    <section class="admin-card ai-settings-page">
        <div class="admin-form-heading">
            <h1>تنظیمات هوش مصنوعی</h1>
            <p>تولید پیشنهادهای متنی به‌صورت اختیاری و فقط پس از تأیید شما انجام می‌شود.</p>
        </div>
        <form method="post" action="{{ route('admin.ai-settings.update') }}" class="admin-form ai-settings-form">
            @csrf
            @method('put')
            <label class="ai-settings-form__wide"><span>فعال‌سازی هوش مصنوعی</span><input type="checkbox" name="ai_enabled" value="1" @checked(old('ai_enabled', $settings->ai_enabled))></label>
            <label>سرویس‌دهنده<input value="چابکان" readonly aria-readonly="true"></label>
            <label>مدل هوش مصنوعی<input name="ai_model" value="{{ old('ai_model', $settings->ai_model ?: 'openai/gpt-4o-mini') }}" dir="ltr" required></label>
            <label class="ai-settings-form__wide">کلید API <input type="password" name="ai_api_key" value="" autocomplete="new-password" dir="ltr" placeholder="{{ $settings->ai_api_key ? 'برای حفظ کلید ذخیره‌شده خالی بگذارید' : 'کلید چابکان را وارد کنید' }}"></label>
            <p class="ai-settings-form__wide" role="status">وضعیت: {{ $settings->ai_api_key ? 'کلید API ذخیره شده است' : 'کلیدی ثبت نشده است' }}</p>
            @if($settings->ai_api_key)<label class="ai-settings-form__wide"><input type="checkbox" name="remove_api_key" value="1"> حذف امن کلید ذخیره‌شده</label>@endif
            <label>حداکثر طول خروجی<input type="number" name="ai_max_output_length" min="800" max="8000" value="{{ old('ai_max_output_length', $settings->ai_max_output_length ?: 1600) }}"></label>
            <label>زمان انتظار (ثانیه)<input type="number" name="ai_timeout_seconds" min="5" max="90" value="{{ old('ai_timeout_seconds', $settings->ai_timeout_seconds ?: 30) }}"></label>
            <label class="ai-settings-form__wide">لحن پیش‌فرض<input name="ai_default_tone" maxlength="120" value="{{ old('ai_default_tone', $settings->ai_default_tone ?: 'دوستانه، حرفه‌ای و مناسب فروشگاه پوشاک کودک') }}"></label>
            <div class="admin-span ai-settings-actions"><button class="admin-button" type="submit">ذخیره تنظیمات</button><button class="admin-button admin-button--muted" type="button" data-ai-connection-test>تست اتصال به هوش مصنوعی</button><span data-ai-connection-status role="status" aria-live="polite"></span></div>
        </form>
    </section>
    <script>
        document.querySelector('[data-ai-connection-test]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const status = document.querySelector('[data-ai-connection-status]');
            button.disabled = true;
            status.textContent = 'در حال بررسی اتصال…';
            try {
                const response = await fetch(@json(route('admin.ai-settings.test')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' } });
                const body = await response.json();
                status.textContent = response.ok ? `${body.message} مدل: ${body.model} · ${body.response_time_ms}ms` : body.message;
            } catch {
                status.textContent = 'ارتباط با سرور برقرار نشد.';
            } finally {
                button.disabled = false;
            }
        });
    </script>
@endsection
