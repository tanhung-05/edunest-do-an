<?php

namespace App\Console\Commands;

use App\Actions\M13\ActivationCodes;
use App\Enums\M15\NotificationKind;
use App\Models\M08\Enrollment;
use App\Models\M12\CoursePromotion;
use App\Models\M12\Wishlist;
use App\Support\M12\Commerce;
use App\Support\M15\Notifier;
use Illuminate\Console\Command;

/**
 * Tác vụ nền P5 (02b §5): `reminders` = TV-10 nhắc hết hạn 7 và 1 ngày; `promotions` = TV-19 báo khóa yêu thích
 * bắt đầu khuyến mãi; `codes` = TV-18 mã kích hoạt quá hạn. Idempotent nhờ khóa chống trùng của thông báo.
 */
final class CommerceSchedules extends Command
{
    protected $signature = 'edunest:commerce {task : reminders|promotions|codes}';

    protected $description = 'Tác vụ nền P5: nhắc hết hạn, báo khuyến mãi, hết hạn mã kích hoạt';

    public function handle(ActivationCodes $codes): int
    {
        $task = $this->argument('task');
        $count = match ($task) {
            'reminders' => $this->reminders(),
            'promotions' => $this->promotions(),
            'codes' => $codes->expireOverdue(),
            default => -1,
        };
        if ($count < 0) {
            $this->error('Tác vụ không hợp lệ.');

            return self::INVALID;
        }
        $this->info($task.': '.$count);

        return self::SUCCESS;
    }

    /** QT-15 b5–6, A3: còn đúng 7 hoặc 1 ngày lịch VN tới ngày của `het_han_luc`; mỗi mốc một lần theo hạn hiện tại. */
    private function reminders(): int
    {
        $today = now()->setTimezone(Commerce::TZ)->startOfDay();
        $sent = 0;
        foreach ([7, 1] as $days) {
            $day = $today->addDays($days);
            Enrollment::query()->whereNull('revoked_at')->whereNotNull('het_han_luc')
                ->where('het_han_luc', '>=', $day->utc())->where('het_han_luc', '<', $day->addDay()->utc())
                ->where('het_han_luc', '>', now())->with(['user', 'course'])->orderBy('id')
                ->each(function (Enrollment $e) use ($days, &$sent) {
                    $until = $e->het_han_luc?->setTimezone(Commerce::TZ)->format('H:i d/m/Y');
                    Notifier::send($e->user, NotificationKind::SapHetHan, 'Sắp hết hạn truy cập',
                        'Khóa "'.$e->course->title.'" còn '.$days.' ngày truy cập (đến '.$until.'). Gia hạn để học tiếp.',
                        route('student.courses.index'), 'expiry:'.$e->id.':'.$e->het_han_luc?->getTimestamp().':'.$days);
                    $sent++;
                });
        }

        return $sent;
    }

    /** QT-13 A7.3, CR-127: một chuông mỗi người mỗi đợt; định danh đợt giữ nguyên khi sửa ngày. */
    private function promotions(): int
    {
        $sent = 0;
        CoursePromotion::query()->whereNull('notified_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->with('course')->orderBy('id')->each(function (CoursePromotion $p) use (&$sent) {
                Wishlist::query()->where('course_id', $p->course_id)->with('user')->orderBy('id')->each(function (Wishlist $w) use ($p, &$sent) {
                    if ($w->user !== null) {
                        Notifier::send($w->user, NotificationKind::KhuyenMai, 'Khóa yêu thích đang giảm giá',
                            '"'.$p->course->title.'" giảm còn '.Commerce::vnd($p->price).' đến hết '.$p->ends_on->format('d/m/Y').'.',
                            route('public.courses.show', $p->course->slug), 'promo:'.$p->id.':'.$w->user_id);
                        $sent++;
                    }
                });
                $p->forceFill(['notified_at' => now()])->save();
            });

        return $sent;
    }
}
