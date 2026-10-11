<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateStaffUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_new_staff_user_with_valid_input(): void
    {
        $this->artisan('admin:create', ['email' => 'newstaff@example.com', 'name' => 'New Staff', '--role' => 'staff'])
            ->expectsQuestion('Password (min 8 characters, letters and numbers)', 'Password123')
            ->expectsQuestion('Confirm password', 'Password123')
            ->expectsConfirmation('Proceed?', 'yes')
            ->assertExitCode(0);

        $user = User::where('email', 'newstaff@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('staff', $user->role->value);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Password123', $user->password));
    }

    public function test_can_create_an_admin_too(): void
    {
        $this->artisan('admin:create', ['email' => 'newadmin@example.com', 'name' => 'New Admin', '--role' => 'admin'])
            ->expectsQuestion('Password (min 8 characters, letters and numbers)', 'Password123')
            ->expectsQuestion('Confirm password', 'Password123')
            ->expectsConfirmation('Proceed?', 'yes')
            ->assertExitCode(0);

        $this->assertSame('admin', User::where('email', 'newadmin@example.com')->first()->role->value);
    }

    public function test_rejects_the_client_role(): void
    {
        $this->artisan('admin:create', ['email' => 'nope@example.com', 'name' => 'Nope', '--role' => 'client'])
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'nope@example.com']);
    }

    public function test_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('admin:create', ['email' => 'taken@example.com', 'name' => 'Someone', '--role' => 'staff'])
            ->assertExitCode(1);
    }

    public function test_rejects_mismatched_passwords(): void
    {
        $this->artisan('admin:create', ['email' => 'mismatch@example.com', 'name' => 'Mismatch', '--role' => 'staff'])
            ->expectsQuestion('Password (min 8 characters, letters and numbers)', 'Password123')
            ->expectsQuestion('Confirm password', 'SomethingElse1')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.com']);
    }

    public function test_rejects_a_weak_password(): void
    {
        $this->artisan('admin:create', ['email' => 'weak@example.com', 'name' => 'Weak', '--role' => 'staff'])
            ->expectsQuestion('Password (min 8 characters, letters and numbers)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    public function test_cancelling_the_confirmation_does_not_create_the_user(): void
    {
        $this->artisan('admin:create', ['email' => 'cancelled@example.com', 'name' => 'Cancelled', '--role' => 'staff'])
            ->expectsQuestion('Password (min 8 characters, letters and numbers)', 'Password123')
            ->expectsQuestion('Confirm password', 'Password123')
            ->expectsConfirmation('Proceed?', 'no')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['email' => 'cancelled@example.com']);
    }
}
