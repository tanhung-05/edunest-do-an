<?php

namespace Tests;

use App\Actions\M01\CreateAuthenticatedSession;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /** Seed vai và cấu hình mặc định một lần sau migrate:fresh (RefreshDatabase). */
    protected bool $seed = true;

    protected string $seeder = RoleSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /** Đăng nhập như qua CreateAuthenticatedSession: phiên mang thế hệ xác thực hiện hành. */
    public function signIn(User $user): static
    {
        $user->refresh();
        $user->forgetRoleCache();

        return $this->actingAs($user)->withSession([CreateAuthenticatedSession::SESSION_KEY => $user->auth_generation]);
    }
}
