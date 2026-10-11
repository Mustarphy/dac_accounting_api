<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The only way a user ever becomes staff/admin: run by someone with
 * direct server/CLI access (SSH, hosting console, etc.), never exposed
 * over HTTP. `role` is deliberately excluded from mass-assignment
 * everywhere else in the app — this command is the sole, intentional
 * exception, and it requires an already-registered account plus
 * explicit confirmation, specifically so there's no way to create or
 * promote an account through the API.
 *
 * Usage: php artisan user:set-role someone@example.com staff
 */
class PromoteUserCommand extends Command
{
    protected $signature = 'user:set-role {email} {role}';

    protected $description = 'Set a user\'s role (client, staff, admin) — the only sanctioned way to grant staff/admin access';

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleInput = $this->argument('role');

        $role = UserRole::tryFrom($roleInput);
        if (! $role) {
            $this->error('Invalid role. Must be one of: '.implode(', ', array_map(fn ($c) => $c->value, UserRole::cases())));

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user found with email {$email}. They must register through the portal first.");

            return self::FAILURE;
        }

        $this->info("User: {$user->name} <{$user->email}>");
        $this->info("Current role: {$user->role->value}");

        if (! $this->confirm("Set this user's role to \"{$role->value}\"?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $user->forceFill(['role' => $role])->save();
        $this->info("Done. {$user->email} is now {$role->value}.");

        return self::SUCCESS;
    }
}
