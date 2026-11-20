<?php

namespace App\Services\Ai;

use App\Models\SeoSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ChabokanAiService
{
    private const ENDPOINT = 'https://ai.chabokan.net/v1/chat/completions';

    public function testConnection(SeoSetting $settings): float
    {
        $startedAt = microtime(true);
        $this->complete($settings, 'You are a service connectivity check. Reply with exactly: OK', 'Reply with the single word OK.');

        return round((microtime(true) - $startedAt) * 1000, 1);
    }

    /** @param array<string, mixed> $product */
    public function generateProductSeo(SeoSetting $settings, array $product): array
    {
        $schema = [
            'seo_title' => 'string',
            'meta_description' => 'string',
            'short_description' => 'string',
            'description' => 'string',
            'keywords' => ['string'],
            'image_alt' => 'string',
            'suggested_slug' => 'string',
        ];
        $context = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $prompt = 'با لحن '.$settings->ai_default_tone.' برای فروشگاه پوشاک کودک کیدوکی، محتوای طبیعی فارسی تولید کن. هیچ ویژگی، جنس، موجودی، تخفیف یا تضمینی را حدس نزن. خروجی فقط یک JSON معتبر مطابق این ساختار باشد: '.json_encode($schema, JSON_UNESCAPED_UNICODE)."\nاطلاعات محصول: {$context}";
        $content = $this->complete($settings, 'You write accurate Persian ecommerce SEO for a premium children clothing boutique. Return only valid JSON. Do not invent product facts. Keep the total response concise.', $prompt);
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            $start = strpos($content, '{');
            $end = strrpos($content, '}');
            $decoded = $start !== false && $end > $start ? json_decode(substr($content, $start, $end - $start + 1), true) : null;
        }
        if (! is_array($decoded)) {
            throw new RuntimeException('پاسخ سرویس هوش مصنوعی قابل پردازش نبود.');
        }

        $result = [
            'seo_title' => $this->text($decoded['seo_title'] ?? null, 255),
            'meta_description' => $this->text($decoded['meta_description'] ?? null, 320),
            'short_description' => $this->text($decoded['short_description'] ?? null, 500),
            'description' => $this->text($decoded['description'] ?? null, 10000),
            'keywords' => array_slice(array_map(fn ($value): string => $this->text($value, 80), is_array($decoded['keywords'] ?? null) ? $decoded['keywords'] : []), 0, 12),
            'image_alt' => $this->text($decoded['image_alt'] ?? null, 180),
            'suggested_slug' => str($this->text($decoded['suggested_slug'] ?? null, 180))->slug()->toString(),
        ];
        $remainingCharacters = (int) $settings->ai_max_output_length;
        foreach (['seo_title' => 80, 'meta_description' => 160, 'short_description' => 160] as $field => $fieldLimit) {
            $result[$field] = mb_substr($result[$field], 0, min($fieldLimit, $remainingCharacters));
            $remainingCharacters -= mb_strlen($result[$field]);
        }
        $descriptionLimit = max(0, $remainingCharacters - 180);
        $result['description'] = mb_substr($result['description'], 0, min(10000, $descriptionLimit));
        $remainingCharacters -= mb_strlen($result['description']);
        foreach (['image_alt' => 100, 'suggested_slug' => 80] as $field => $fieldLimit) {
            $result[$field] = mb_substr($result[$field], 0, min($fieldLimit, $remainingCharacters));
            $remainingCharacters -= mb_strlen($result[$field]);
        }
        $result['keywords'] = array_values(array_filter(array_map(function (string $keyword) use (&$remainingCharacters): string {
            $bounded = mb_substr($keyword, 0, max(0, min(80, $remainingCharacters)));
            $remainingCharacters -= mb_strlen($bounded);

            return $bounded;
        }, $result['keywords'])));
        if ($result['seo_title'] === '' || $result['meta_description'] === '' || $result['short_description'] === '' || $result['description'] === '') {
            throw new RuntimeException('پاسخ سرویس هوش مصنوعی ناقص بود؛ دوباره تلاش کنید.');
        }

        return $result;
    }

    private function complete(SeoSetting $settings, string $system, string $user): string
    {
        if (! $settings->ai_enabled || blank($settings->ai_api_key)) {
            throw new RuntimeException('هوش مصنوعی فعال نیست یا کلید API ثبت نشده است.');
        }

        try {
            $response = Http::acceptJson()
                ->withToken($settings->ai_api_key)
                ->connectTimeout(5)
                ->timeout((int) $settings->ai_timeout_seconds)
                ->post(self::ENDPOINT, [
                    'model' => $settings->ai_model,
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
                    'stream' => false,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('اتصال به سرویس هوش مصنوعی برقرار نشد یا زمان پاسخ به پایان رسید.');
        }
        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('کلید API یا دسترسی سرویس معتبر نیست.');
        }
        if ($response->status() === 429) {
            throw new RuntimeException('محدودیت درخواست سرویس فعال است؛ کمی بعد دوباره تلاش کنید.');
        }
        if ($response->serverError()) {
            throw new RuntimeException('سرویس هوش مصنوعی موقتاً در دسترس نیست.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('درخواست سرویس هوش مصنوعی پذیرفته نشد؛ تنظیم مدل را بررسی کنید.');
        }
        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('سرویس هوش مصنوعی پاسخ خالی برگرداند.');
        }

        return mb_substr(trim($content), 0, 30000);
    }

    private function text(mixed $value, int $limit): string
    {
        return is_string($value) ? mb_substr(trim(strip_tags($value)), 0, $limit) : '';
    }
}
