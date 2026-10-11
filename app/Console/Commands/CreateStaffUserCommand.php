<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a brand-new staff/admin account directly — this is the
 * intended way to provision the FIRST administrator and every staff
 * account after it, and it deliberately never touches the public
 * /api/v1/auth/register endpoint that clients use. Reasons that matters:
 *
 *   - Staff and clients are different audiences; an accounting firm's
 *     own employees shouldn't go through the same public sign-up form
 *     its customers use, even transiently.
 *   - Nothing here is reachable over HTTP. Running this requires actual
 *     server/CLI access (SSH, hosting console, etc.) — the same boundary
 *     as PromoteUserCommand, just for brand-new accounts instead of
 *     promoting existing ones.
 *   - The password is entered interactively (hidden input) and hashed on
 *     save; it's never a CLI argument, never logged, never committed
 *     anywhere. There is no hardcoded admin account in this codebase —
 *     every staff/admin user is created by someone who already has
 *     trusted access to this machine, one at a time, on purpose.
 *
 * PromoteUserCommand (`user:set-role`) still exists for the rarer case
 * of promoting an existing client account (e.g. a client who later joins
 * the firm) — this command is for creating a new staff/admin account
 * from nothing.
 *
 * Usage: php artisan admin:create someone@example.com "Jane Doe" --role=admin
 */
class CreateStaffUserCommand extends Command
{
    protected $signature = 'admin:create {email} {name} {--role=staff}';

    protected $description = 'Create a brand-new staff or admin account directly — never via the public registration endpoint';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $roleInput = $this->option('role');

        $role = UserRole::tryFrom($roleInput);
        if (! $role || $role === UserRole::Client) {
            $this->error('--role must be "staff" or "admin" (clients register themselves through the portal).');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $email, 'name' => $name],
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $password = $this->promptForPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $this->info("About to create: {$name} <{$email}> as \"{$role->value}\".");
        if (! $this->confirm('Proceed?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            // The operator running this command vouches for the email
            // address out of band — there's no separate verification
            // step for an account nobody self-registered.
            'email_verified_at' => now(),
        ])->save();

        $this->info("Done. {$user->email} created as {$role->value}.");

        return self::SUCCESS;
    }

    private function promptForPassword(): ?string
    {
        $password = $this->secret('Password (min 8 characters, letters and numbers)');
        $confirmation = $this->secret('Confirm password');

        if ($password !== $confirmation) {
            $this->error('Passwords did not match.');

            return null;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::min(8)->letters()->numbers()]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return null;
        }

        return $password;
    }
}
