<?php

namespace App\Console\Commands;

use App\Actions\M01\ResetStaffTwoFactor;
use App\Models\User;
use App\Support\DomainRejection;
use Illuminate\Console\Command;

/**
 * QT-01 A17 (BR-HT-54, CR-108) — lệnh quản trị trên máy chủ đặt lại 2FA khi Quản trị mất cả thiết bị
 * và mã khôi phục. Nhật ký với actor_type = LENH_MAY_CHU từ P1.
 */
class ResetTwoFactorCommand extends Command
{
    protected $signature = 'edunest:admin:reset-2fa {email}';

    protected $description = 'Đặt lại 2FA của tài khoản Giáo vụ/Quản trị (lệnh trên máy chủ)';

    public function handle(ResetStaffTwoFactor $action): int
    {
        $user = User::query()->where('email', User::normalizeEmail((string) $this->argument('email')))->first();
        if ($user === null) {
            $this->error('Không tìm thấy tài khoản.');

            return self::FAILURE;
        }
        try {
            $action->handle(null, $user);
        } catch (DomainRejection $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Đã đặt lại 2FA của {$user->email}. Lần đăng nhập kế tiếp phải thiết lập lại.");

        return self::SUCCESS;
    }
}
