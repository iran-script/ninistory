<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

#[Signature('admin:create')]
#[Description('Create a Super Admin account or grant administrator access to an existing account')]
class CreateAdminAccount extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->ask('Administrator email')));
        if (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        $existingUser = $this->findByEmail($email);
        if ($existingUser) {
            return $this->grantExistingUser($existingUser);
        }

        $name = trim((string) $this->ask('Administrator name'));
        if ($name === '' || mb_strlen($name) > 255) {
            $this->error('Enter a name up to 255 characters.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Administrator password (at least 12 characters)');
        if (mb_strlen($password) < 12) {
            $this->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }

        $passwordConfirmation = (string) $this->secret('Confirm administrator password');
        if (! hash_equals($password, $passwordConfirmation)) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }
        $passwordHash = Hash::make($password);
        unset($password, $passwordConfirmation);

        try {
            DB::transaction(function () use ($name, $email, $passwordHash): void {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $passwordHash,
                ]);

                $user->forceFill(['is_admin' => true])->save();
            });
        } catch (QueryException $exception) {
            $existingUser = $this->findByEmail($email);
            if (! $existingUser) {
                throw $exception;
            }

            return $this->grantExistingUser($existingUser);
        }

        $this->info('Super Admin account created. Sign in at /admin.');

        return self::SUCCESS;
    }

    private function grantExistingUser(User $user): int
    {
        if ($user->is_admin) {
            $this->info("{$user->email} already has administrator access.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Promote existing customer {$user->name} ({$user->email}) to administrator?", false)) {
            $this->info('No changes were made.');

            return self::SUCCESS;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("Administrator access granted to {$user->email}.");

        return self::SUCCESS;
    }

    private function findByEmail(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }
}
