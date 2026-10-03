<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Not wired to any route yet (no portal routes exist — see Step 18 of
 * the setup brief), so this exercises the middleware directly rather
 * than through an HTTP request.
 */
class EnsureUserHasRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_a_user_with_the_required_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $result = (new EnsureUserHasRole)->handle($request, fn ($req) => new Response('ok'), 'admin');

        $this->assertSame('ok', $result->getContent());
    }

    public function test_blocks_a_user_without_the_required_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::Client]);
        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);

        (new EnsureUserHasRole)->handle($request, fn ($req) => 'ok', 'admin', 'staff');
    }

    public function test_blocks_an_unauthenticated_request(): void
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn () => null);

        $this->expectException(HttpException::class);

        (new EnsureUserHasRole)->handle($request, fn ($req) => 'ok', 'admin');
    }
}
