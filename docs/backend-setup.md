# DAC Accounting — Backend Setup

Laravel API backend for the DAC Accounting client portal, built to the exact
contract the Next.js frontend already ships against (see the frontend's
`docs/auth-api-contract.md`). This document covers environment setup,
architecture decisions, and how to run/test the backend.

## 1. Environment

| | Installed |
|---|---|
| PHP | **8.5.11** — the machine this was set up on only had PHP 8.2.12 (XAMPP), which is below Laravel 13's own minimum (`^8.3`). PHP 8.5 was downloaded as the official portable Windows NTS build from php.net and installed to `C:\php85`, **not** on the system PATH — it's used explicitly via `PATH="/c/php85:$PATH"` (bash) or by calling `C:\php85\php.exe` directly, so it doesn't interfere with the existing XAMPP PHP 8.2 install used elsewhere on this machine. |
| Laravel | **13.34.0** |
| Composer | **2.10.1** |
| Database | **MySQL-compatible** — MariaDB 10.4.27 via the existing local XAMPP install (`C:\xampp82\mysql`), already running on port 3306. |

### Required PHP extensions

All confirmed enabled (`php -m`): Ctype, cURL, DOM, Fileinfo, Filter, Hash,
Mbstring, OpenSSL, PCRE, PDO (+ `pdo_mysql` for the app, `pdo_sqlite` for the
test suite — see §7), Session, Tokenizer, XML, plus `zip` (Composer) and
`bcmath` (ships enabled by default in the `php.ini-development` template
this was based on). Configured in `C:\php85\php.ini`, copied from
`php.ini-development` (local/dev defaults — use `php.ini-production` as the
base if/when this moves off a dev machine).

**If you set up a fresh machine**, the extensions above are the only ones
this app needs. Don't enable more than that.

## 2. Database

A dedicated, scoped MySQL user and database were created (not using `root`
in the app's `.env`):

```sql
CREATE DATABASE dac_accounting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dac_accounting'@'127.0.0.1' IDENTIFIED BY '<generated password, in .env>';
GRANT ALL PRIVILEGES ON dac_accounting.* TO 'dac_accounting'@'127.0.0.1';
```

`.env` holds the real generated password; it is gitignored by Laravel's
default `.gitignore` and was never committed. `.env.example` has the same
keys with blank/placeholder values.

Run migrations with:

```bash
php artisan migrate
```

### Tables beyond Laravel's defaults

- `users` — Laravel's default, plus a `role` column (string, default
  `client`) added via a separate migration rather than editing the
  original `create_users_table` migration (that one had already run).
- `refresh_tokens` — opaque refresh tokens for the cookie-based refresh
  flow (see §4). Only a SHA-256 hash of the token is stored, never the raw
  value.
- `email_verification_tokens` — same idea, for the frontend's existing
  single-opaque-token email verification contract (see §5).

## 3. Why JWT, not Sanctum

The frontend was already built (in an earlier phase of this project)
against a JWT access-token-in-memory + HttpOnly-cookie-refresh-token
architecture — see the frontend's `docs/auth-api-contract.md`, written
before this backend existed. Laravel Sanctum's SPA mode assumes a
same-site, session-cookie-based model (`Laravel\Sanctum` cookie + CSRF
token), which is a different architecture than what the frontend already
implements and tests against. Rather than retrofit the frontend to a
different auth model, this backend implements the contract the frontend
already has: **`tymon/jwt-auth` 2.3.0** issues and verifies the JWT access
token; a custom, DB-backed opaque refresh token (not jwt-auth's own
refresh-via-old-token mechanism — see §4) handles the long-lived session
half. Sanctum was not installed.

## 4. Authentication architecture

```
Access token   → tymon/jwt-auth JWT, 15 min TTL (JWT_TTL)
                  Issued via JWTAuth::fromUser($user); never persisted server-side.
                  Blacklisted immediately on logout (auth('api')->logout()).

Refresh token  → opaque random 64-char string (Str::random(64))
                  Only its SHA-256 hash is stored, in `refresh_tokens`.
                  Carried in an HttpOnly, SameSite=Lax cookie named
                  `dac_refresh_token` (must match the frontend's
                  src/proxy.ts REFRESH_TOKEN_COOKIE exactly).
                  Rotated on every /auth/refresh call: the presented
                  token is revoked and a new one issued, so a stolen
                  cookie that's already been used once is a dead end.
```

**Why not jwt-auth's built-in refresh?** It works by presenting the
existing (possibly expired, within a grace window) JWT back to the server,
which doesn't map cleanly onto an HttpOnly-cookie-based flow or support
clean server-side revocation of one specific session. A DB-backed opaque
token does both naturally, and is what the frontend's contract already
describes.

**Cookie encryption**: `dac_refresh_token` is explicitly excluded from
Laravel's `EncryptCookies` middleware (`bootstrap/app.php`). It's already
an unguessable random value whose hash — never the raw value — is what's
persisted, so Laravel's cookie encryption would be a redundant second
layer. More importantly, the frontend's `proxy.ts` needs to check for this
cookie's mere *presence* (an optimistic, non-authoritative route guard) —
it can't do that if the value is encrypted with a Laravel-specific key.

**JWT payload**: deliberately minimal (`getJWTCustomClaims()` returns
`[]`) — just the subject (user ID). Role and verification status are
looked up fresh from the database on every request via `GET /auth/me`,
never trusted from the token itself.

### Session lifecycle

| Endpoint | What happens |
|---|---|
| `POST /auth/register` | Creates the user (`role` defaults to `client`, not mass-assignable), queues a verification email, immediately issues a session (same as login) — the user can use the portal right away in an "unverified" state. |
| `POST /auth/login` | Verifies credentials with a single generic error message regardless of whether the email exists; issues a new access token + refresh token. |
| `POST /auth/refresh` | Reads the refresh cookie, looks up its hash, rotates it (revoke old, issue new), returns a new access token. |
| `POST /auth/logout` | Revokes the presented refresh token in the DB and blacklists the current access token (`auth('api')->logout()`) so it stops working immediately rather than waiting out its 15-minute natural expiry. Clears the cookie. Still returns 200 even if the session was already invalid. |
| `GET /auth/me` | Returns the authenticated user. |

### Roles

`App\Enums\UserRole` (`admin` / `staff` / `client`), a `role` column on
`users`, and a `role` middleware alias (`App\Http\Middleware\EnsureUserHasRole`,
usage: `->middleware('role:admin,staff')`) are in place but **not yet
applied to any route** — there's no portal to protect yet. This is the
foundation, not the whole authorization system; every future protected
action still needs an explicit `role:` middleware or Policy/Gate check.
`role` is deliberately excluded from `User`'s fillable attributes so it
can never be set via registration input.

## 5. Email verification & password reset

**Email verification** uses a custom opaque-token flow
(`App\Services\Auth\EmailVerificationService`), matching the frontend's
existing `POST /auth/email/verify { token }` contract — **not** Laravel's
default signed-URL `MustVerifyEmail` convention (id/hash/expires/signature
query params), since the frontend was already built against the simpler
single-token version. `User` still implements `MustVerifyEmail` (for
`hasVerifiedEmail()`/`markEmailAsVerified()` and compatibility with
Laravel's `verified` middleware); only
`sendEmailVerificationNotification()` is overridden to use our own token
and notification class. Verification links point at
`{FRONTEND_URL}/verify-email?token=...`. Resend
(`POST /auth/email/resend`) always sends to the *authenticated* user's own
email — the request body's `email` field is accepted (for contract
compatibility) but never used as the send target, so a caller can't be
tricked into verifying/emailing an address they don't own.

**Password reset** is built entirely on **Laravel's own password broker**
(`Password::sendResetLink()` / `Password::reset()`) — token generation,
hashing, expiry (`config/auth.php`: 60 min), and throttling are all
Laravel's existing, well-tested machinery. Only
`User::sendPasswordResetNotification()` is overridden, to point the
emailed link at `{FRONTEND_URL}/reset-password?token=...&email=...`
instead of a (non-existent) Laravel web route. `/auth/password/forgot`
returns the identical generic message whether or not the email exists, to
avoid account enumeration.

Both flows currently send mail via `MAIL_MAILER=log` (writes to
`storage/logs/laravel.log` instead of actually sending) and queue the
notification job via `QUEUE_CONNECTION=database` — **a queue worker
(`php artisan queue:work`) must be running for emails to actually go out**,
including in local dev. DAC will need to configure real SMTP credentials
via `.env` (`MAIL_*`) before launch; nothing in the mail configuration is
hardcoded.

## 6. API structure, response shape, security baseline

```
routes/api.php                           /api/v1/auth/* (see §8 for versioning)
app/Http/Controllers/Api/V1/Auth/        AuthController, EmailVerificationController,
                                          PasswordResetController — thin, delegate to...
app/Services/Auth/                       TokenService, EmailVerificationService
app/Http/Requests/Auth/                  Form Requests — one per endpoint, field names
                                          matching the frontend's TypeScript types exactly
                                          (e.g. `passwordConfirmation`, not `password_confirmation`)
app/Http/Resources/UserResource.php      Matches the frontend's AuthUser type exactly
app/Enums/UserRole.php
app/Models/{User,RefreshToken,EmailVerificationToken}.php
app/Notifications/{VerifyEmail,ResetPassword}Notification.php
```

**Response envelope**, enforced via `Controller::success()`/`failure()`
helpers and custom exception rendering in `bootstrap/app.php`:

```json
{ "success": true,  "message": "...", "data": { ... } }
{ "success": false, "message": "...", "errors": { "field": ["..."] } }
```

Validation (422), authentication (401), authorization (403), not-found
(404), and rate-limit (429) exceptions all render in this shape. Any
other unexpected exception (500) shows a generic message in production
(`APP_DEBUG=false`) — Laravel's normal detailed JSON output still comes
through locally for debugging.

**CORS** (`config/cors.php`): credentialed requests allowed only from
`FRONTEND_URL` — never a wildcard (which the CORS spec forbids combining
with credentials anyway). Verified via a real OPTIONS preflight during
testing.

**Rate limiting** (`AppServiceProvider::boot()`), the actual security
control (frontend-side submit-button disabling is UX only):

| Limiter | Limit | Keyed by |
|---|---|---|
| `login` | 5/min | email + IP |
| `register` | 5/min | IP |
| `refresh` | 20/min | IP |
| `forgot-password` | 3/min | email + IP |
| `resend-verification` | 2/min | user ID |
| `verify-email` | 10/min | IP |

**Other security decisions**: passwords hashed via Laravel's `hashed`
Eloquent cast (bcrypt); `role` and `revoked_at` excluded from
mass-assignment (the latter via `#[Fillable]` omission — see the gotcha in
§9); JWT secret generated via `php artisan jwt:secret`, lives only in
`.env`, never sent to the frontend; no sensitive values logged.

## 7. Environment variables

See `.env.example` for the full list with comments. Backend-specific
additions beyond Laravel's defaults:

```
FRONTEND_URL=                       # CORS origin + email link base URL
JWT_SECRET=                         # via `php artisan jwt:secret` — never hand-write this
JWT_TTL=15                          # access token lifetime, minutes
REFRESH_COOKIE_NAME=dac_refresh_token   # MUST match frontend's src/proxy.ts
REFRESH_COOKIE_DOMAIN=              # shared parent domain in production, e.g. .dacaccounting.com; blank for local dev
REFRESH_COOKIE_SECURE=false         # true in production (HTTPS)
REFRESH_COOKIE_SAMESITE=lax
REFRESH_TOKEN_TTL_DAYS=7
REFRESH_TOKEN_REMEMBER_TTL_DAYS=30  # when rememberMe=true at login
EMAIL_VERIFICATION_TTL_HOURS=24
```

Testing uses `DB_CONNECTION=sqlite` / `:memory:` (phpunit.xml) regardless
of the app's own MySQL default — standard Laravel practice for fast,
isolated test runs. That's why `pdo_sqlite`/`sqlite3` are enabled in
`php.ini` alongside `pdo_mysql`.

## 8. API versioning

**One convention throughout: `/api/v1/...`**, e.g. `/api/v1/auth/login`.
Registered via `routes/api.php`'s `Route::prefix('v1')` group. The
frontend's `NEXT_PUBLIC_API_URL` is expected to already include `/api/v1`
(e.g. `http://localhost:8000/api/v1`), so the frontend's relative endpoint
paths (`/auth/login`, etc. — see its `src/lib/auth/endpoints.ts`) combine
correctly without any frontend code changes.

## 9. Known gotchas hit during setup (useful if you extend this)

- **`update()` silently drops non-fillable attributes.** `revoked_at` is
  deliberately not in `RefreshToken`'s `#[Fillable]` (it should never be
  settable via mass-assignment from request input), so revoking a token
  uses `forceFill(['revoked_at' => now()])->save()`, not `update()`. If
  you add a new internal-only column anywhere, remember `update()` won't
  touch it unless it's fillable.
- **Laravel's default guest-redirect assumes a `login` web route exists.**
  This is a pure API with none, so `bootstrap/app.php` sets
  `$middleware->redirectGuestsTo(fn () => null)` — otherwise an
  unauthenticated request to a protected route throws
  `RouteNotFoundException` instead of a clean 401.
- **`User::sendPasswordResetNotification()`'s override must match the
  parent's exact signature** (`Illuminate\Auth\Passwords\CanResetPassword`)
  — no type hints, `#[\SensitiveParameter]` on `$token` — or PHP throws a
  fatal "Declaration must be compatible" error on every request that
  touches the User model.
- **`auth('api')->logout()`, not `JWTAuth::parseToken()->invalidate()`,**
  for blacklisting the access token on logout — the latter didn't reliably
  blacklist the token already resolved by the `auth:api` middleware for
  the same request in testing.
- **Laravel's test client sends no cookies on JSON requests by default**
  (`postJson()`/`getJson()` mirror a real `fetch()` without
  `credentials: 'include'`) — call `$this->withCredentials()` first. It
  also has its own cookie-encryption flag independent of the app's
  `EncryptCookies` config, so cookies excluded from app-side encryption
  must be sent via `withUnencryptedCookies()`, not `withCookie()` (which
  always encrypts).

## 10. Local development

```bash
# One-time setup (adjust the PATH prefix if PHP 8.5 isn't on your PATH)
PATH="/c/php85:$PATH" composer install
cp .env.example .env            # then fill in DB_* and FRONTEND_URL
PATH="/c/php85:$PATH" php artisan key:generate
PATH="/c/php85:$PATH" php artisan jwt:secret
PATH="/c/php85:$PATH" php artisan migrate

# Run the app
PATH="/c/php85:$PATH" php artisan serve --port=8000
PATH="/c/php85:$PATH" php artisan queue:work   # required for emails to actually send
```

## 11. Testing

```bash
PATH="/c/php85:$PATH" php artisan test     # 31 tests, all passing as of this setup
PATH="/c/php85:$PATH" vendor/bin/pint      # code style (auto-fix)
PATH="/c/php85:$PATH" vendor/bin/pint --test   # code style (check only)
```

Coverage: full register/login/me/logout/refresh lifecycle including token
rotation and post-logout invalidation of *both* the refresh token and the
access token; rate limiting; email verification (valid/already-used/invalid
token, resend ownership enforcement); password reset (valid/single-use/invalid
token, enumeration-safe forgot-password, mismatched confirmation rejected
before the broker is even touched); the `role` middleware in isolation
(no route uses it yet).

## 12. Remaining manual configuration for DAC

- Real SMTP credentials (`MAIL_*`) before launch — currently logs instead
  of sending.
- `REFRESH_COOKIE_DOMAIN` and `FRONTEND_URL` for the real production
  domains once known (see the frontend's `docs/auth-api-contract.md` §2
  for why the refresh cookie needs a shared parent domain).
- `REFRESH_COOKIE_SECURE=true` in production (requires HTTPS).
- A queue worker running in production (e.g. via Supervisor) — mail won't
  send without one.
- No git repository exists yet for this backend (`dac-accounting-backend/`
  was empty before this setup) — set one up before more work accumulates
  uncommitted.
- Client portal features (letters, invoices, notifications, admin/staff
  management) are intentionally not started — only the `role` column and
  middleware foundation exists, per the setup brief's instruction not to
  prematurely build them.
