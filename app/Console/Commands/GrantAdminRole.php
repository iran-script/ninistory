<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdminRole extends Command
{
    protected $signature = 'admin:grant {email : Existing authorized user email}';
    protected $description = 'Grant catalog administrator access to an existing authorized user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) { $this->error('No existing user matches that email.'); return self::FAILURE; }
        if (! $this->confirm("Grant administrator access to {$user->email}?", false)) return self::FAILURE;
        $user->forceFill(['is_admin' => true])->save();
        $this->info('Administrator access granted.');
        return self::SUCCESS;
    }
}
