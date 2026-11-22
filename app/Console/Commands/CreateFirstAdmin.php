<?php

namespace App\Console\Commands;

use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\User;
use App\Support\Tx;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * CR-108, BR-HT-52 — tạo Quản trị đầu tiên trên máy chủ (không có đường tạo Quản trị qua đăng ký).
 * Tài khoản phải đổi mật khẩu và thiết lập 2FA ở lần đăng nhập đầu (A5, A6).
 */
class CreateFirstAdmin extends Command
{
    protected $signature = 'edunest:admin:create {email} {--name=Quản trị} {--password=}';

    protected $description = 'Tạo tài khoản Quản trị (dùng khi cài đặt hệ thống)';

    public function handle(): int
    {
        $email = User::normalizeEmail((string) $this->argument('email'));
        $password = (string) ($this->option('password') ?: $this->secret('Mật khẩu ban đầu (≥ 8 ký tự)'));
        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Tx::run(function () use ($email, $password) {
            Role::lockAdminGuard();
            $user = new User(['name' => (string) $this->option('name'), 'email' => $email, 'password' => Hash::make($password)]);
            $user->email_verified_at = now();
            $user->status = AccountStatus::HoatDong;
            $user->must_change_password = true;
            $user->save();
            $user->roles()->attach(Role::idFor(RoleCode::QuanTri), ['assigned_by_id' => null, 'assigned_at' => now()]);
        });

        $this->info("Đã tạo Quản trị {$email}. Lần đăng nhập đầu phải đổi mật khẩu và thiết lập 2FA.");

        return self::SUCCESS;
    }
}
