<?php

namespace Database\Factories\M01;

use App\Models\M01\UserDevice;
use App\Models\User;
use App\Support\M09\LearningFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<UserDevice> */
final class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cookie_hash' => hash('sha256', Str::random(64)),
            'first_seen_at' => now(), 'last_active_at' => now(),
        ];
    }

    public function forCookie(User $user, string $cookie): static
    {
        return $this->state(['user_id' => $user->id, 'cookie_hash' => LearningFile::cookieHash($user, $cookie)]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
