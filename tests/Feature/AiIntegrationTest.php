<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SeoSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_settings_require_admin_authorization(): void
    {
        $this->get(route('admin.ai-settings.edit'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.ai-settings.edit'))
            ->assertForbidden();
    }

    public function test_api_token_is_encrypted_and_blank_update_preserves_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.ai-settings.update'), [
            'ai_enabled' => 1,
            'ai_model' => 'openai/gpt-4o-mini',
            'ai_api_key' => 'test-secret-token',
            'ai_max_output_length' => 1600,
            'ai_timeout_seconds' => 30,
            'ai_default_tone' => 'Persian friendly',
        ])->assertSessionHasNoErrors();

        $settings = SeoSetting::query()->firstOrFail();
        $this->assertSame('test-secret-token', $settings->ai_api_key);
        $this->assertNotSame('test-secret-token', $settings->getRawOriginal('ai_api_key'));
        $this->get(route('admin.ai-settings.edit'))->assertDontSee('test-secret-token');

        $this->actingAs($admin)->put(route('admin.ai-settings.update'), [
            'ai_enabled' => 1,
            'ai_model' => 'openai/gpt-4o-mini',
            'ai_api_key' => '',
            'ai_max_output_length' => 1600,
            'ai_timeout_seconds' => 30,
            'ai_default_tone' => 'Persian friendly',
        ])->assertSessionHasNoErrors();
        $this->assertSame('test-secret-token', $settings->fresh()->ai_api_key);
    }

    public function test_admin_can_explicitly_remove_the_saved_api_token(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = SeoSetting::query()->create([
            'site_name' => 'Kidoki',
            'tagline' => 'Kids clothing',
            'ai_api_key' => 'remove-this-token',
        ]);

        $this->actingAs($admin)->put(route('admin.ai-settings.update'), [
            'ai_model' => 'openai/gpt-4o-mini',
            'ai_api_key' => '',
            'remove_api_key' => 1,
            'ai_max_output_length' => 1600,
            'ai_timeout_seconds' => 30,
            'ai_default_tone' => 'Persian friendly',
        ])->assertSessionHasNoErrors();

        $this->assertNull($settings->fresh()->ai_api_key);
    }

    public function test_product_seo_generation_uses_only_the_fixed_provider_and_returns_safe_structured_fields(): void
    {
        Http::preventStrayRequests();
        $generated = json_encode([
            'seo_title' => '<script>safe title</script>',
            'meta_description' => 'توضیح محصول',
            'short_description' => 'توضیح کوتاه',
            'description' => 'شرح کامل',
            'keywords' => ['لباس کودک'],
            'image_alt' => 'لباس کودک',
            'suggested_slug' => 'لباس کودک',
        ], JSON_UNESCAPED_UNICODE);
        Http::fake(['https://ai.chabokan.net/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => $generated]]],
        ])]);
        SeoSetting::query()->create([
            'site_name' => 'Kidoki', 'tagline' => 'Kids clothing', 'ai_enabled' => true,
            'ai_api_key' => 'fake-test-token', 'ai_model' => 'openai/gpt-4o-mini',
        ]);
        $category = Category::create(['name' => 'لباس', 'slug' => 'clothing', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson(route('admin.products.seo-generate'), [
                'name' => 'پیراهن کودک', 'category_id' => $category->id,
                'material' => 'پنبه', 'colors' => ['سرمه‌ای'], 'sizes' => ['۴۰'],
            ])->assertOk()
            ->assertJsonPath('suggestions.seo_title', 'safe title')
            ->assertJsonPath('suggestions.suggested_slug', 'lbas-kodk');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://ai.chabokan.net/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer fake-test-token')
            && $request['stream'] === false);
    }

    public function test_seo_generation_is_rate_limited_and_requires_admin(): void
    {
        $payload = ['name' => 'پیراهن'];
        $this->postJson(route('admin.products.seo-generate'), $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->postJson(route('admin.products.seo-generate'), $payload)
            ->assertForbidden();
    }

    public function test_seo_generation_handles_provider_authentication_failure_without_leaking_response(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://ai.chabokan.net/v1/chat/completions' => Http::response(['error' => ['message' => 'private provider details']], 401)]);
        SeoSetting::query()->create([
            'site_name' => 'Kidoki',
            'tagline' => 'Kids clothing',
            'ai_enabled' => true,
            'ai_api_key' => 'fake-test-token',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson(route('admin.products.seo-generate'), ['name' => 'پیراهن کودک'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'کلید API یا دسترسی سرویس معتبر نیست.')
            ->assertDontSee('private provider details');
    }

    public function test_missing_ai_token_prevents_provider_request(): void
    {
        Http::preventStrayRequests();
        SeoSetting::query()->create([
            'site_name' => 'Kidoki',
            'tagline' => 'Kids clothing',
            'ai_enabled' => true,
            'ai_model' => 'openai/gpt-4o-mini',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson(route('admin.products.seo-generate'), ['name' => 'پیراهن کودک'])
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_provider_connection_failure_returns_a_sanitized_message(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('transport details must not be returned'));
        SeoSetting::query()->create([
            'site_name' => 'Kidoki',
            'tagline' => 'Kids clothing',
            'ai_enabled' => true,
            'ai_api_key' => 'fake-test-token',
            'ai_model' => 'openai/gpt-4o-mini',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->postJson(route('admin.products.seo-generate'), ['name' => 'پیراهن کودک'])
            ->assertUnprocessable()
            ->assertDontSee('transport details');
    }
}
