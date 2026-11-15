<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateAdminAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_admin_that_can_sign_in_and_open_protected_routes(): void
    {
        $email = fake()->unique()->safeEmail();
        $name = fake()->name();
        $password = Str::random(40);

        $this->artisan('admin:create')
            ->expectsQuestion('Administrator email', $email)
            ->expectsQuestion('Administrator name', $name)
            ->expectsQuestion('Administrator password (at least 12 characters)', $password)
            ->expectsQuestion('Confirm administrator password', $password)
            ->expectsOutputToContain('Super Admin account created.')
            ->assertSuccessful();

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));

        $this->post(route('admin.login.store'), ['email' => $email, 'password' => $password])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.products.create'))->assertOk();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_customer_is_not_promoted_without_confirmation(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        $this->artisan('admin:create')
            ->expectsQuestion('Administrator email', $customer->email)
            ->expectsConfirmation("Promote existing customer {$customer->name} ({$customer->email}) to administrator?", false)
            ->expectsOutputToContain('No changes were made.')
            ->assertSuccessful();

        $this->assertFalse($customer->fresh()->is_admin);
        $this->assertDatabaseCount('users', 1);
        $this->actingAs($customer->fresh())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_confirmed_existing_customer_is_promoted_without_duplicate_or_password_reset(): void
    {
        $password = Str::random(40);
        $customer = User::factory()->create([
            'is_admin' => false,
            'password' => Hash::make($password),
        ]);

        $this->artisan('admin:create')
            ->expectsQuestion('Administrator email', $customer->email)
            ->expectsConfirmation("Promote existing customer {$customer->name} ({$customer->email}) to administrator?", 'yes')
            ->expectsOutputToContain('Administrator access granted')
            ->assertSuccessful();

        $this->assertTrue($customer->fresh()->is_admin);
        $this->assertTrue(Hash::check($password, $customer->fresh()->password));
        $this->assertDatabaseCount('users', 1);
        $this->post(route('admin.login.store'), ['email' => $customer->email, 'password' => $password])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_command_rejects_mismatched_passwords_without_creating_an_account(): void
    {
        $email = fake()->unique()->safeEmail();

        $this->artisan('admin:create')
            ->expectsQuestion('Administrator email', $email)
            ->expectsQuestion('Administrator name', fake()->name())
            ->expectsQuestion('Administrator password (at least 12 characters)', Str::random(40))
            ->expectsQuestion('Confirm administrator password', Str::random(40))
            ->expectsOutputToContain('The passwords do not match.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }
}
