<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Services\Auth\EmailVerificationService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    /**
     * @see JWTSubject
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Deliberately empty: keep the JWT payload minimal (just the subject
     * claim). Role and verification status are looked up fresh from the
     * database on each request via GET /auth/me, not trusted from an
     * unverifiable client-held claim.
     *
     * @see JWTSubject
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Send the email verification notification — overridden so the link
     * points at the frontend with our own opaque token instead of
     * Laravel's default signed backend route. The token itself is created
     * by EmailVerificationService, which calls this indirectly via
     * notify().
     */
    public function sendEmailVerificationNotification()
    {
        app(EmailVerificationService::class)->sendVerificationEmail($this);
    }

    /**
     * Overridden so the reset link points at the frontend's
     * /reset-password page instead of a Laravel web route. Laravel's
     * built-in password broker still generates/hashes/expires $token.
     *
     * Signature (no type hints, #[SensitiveParameter]) must match
     * Illuminate\Auth\Passwords\CanResetPassword exactly or PHP throws a
     * fatal "Declaration must be compatible" error.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
