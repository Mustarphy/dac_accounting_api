<?php

namespace App\Providers;

use App\Models\AccountingRequest;
use App\Policies\AccountingRequestPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Rate limits enforced here are the real security control (Step 12 of
     * the setup brief) — any frontend-side submit-button throttling is
     * just UX, not relied on for protection. Keyed by email+IP where a
     * known identifier exists, so one abusive IP can't be used to lock
     * out unrelated accounts, and one leaked/guessed email can't be used
     * to bypass IP-based limits from many addresses.
     */
    public function boot(): void
    {
        Gate::policy(AccountingRequest::class, AccountingRequestPolicy::class);

        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn ($request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('refresh', fn ($request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('forgot-password', fn ($request) => Limit::perMinute(3)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('resend-verification', fn ($request) => Limit::perMinute(2)->by($request->user()?->id ?? $request->ip()));

        RateLimiter::for('verify-email', fn ($request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('document-upload', fn ($request) => Limit::perMinute(20)->by($request->user()?->id ?? $request->ip()));
    }
}
