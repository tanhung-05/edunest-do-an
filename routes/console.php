<?php

use App\Actions\M24\AffiliateCommissions;
use App\Actions\M26\LiveSessions;
use App\Console\Commands\CommerceSchedules;
use App\Console\Commands\DispatchDailyLearningReport;
use App\Console\Commands\ExamSchedules;
use App\Console\Commands\ExpireOverdueOrders;
use App\Console\Commands\FinalizeOverdueAttempts;
use App\Console\Commands\GuardianWeeklyReports;
use App\Console\Commands\PracticeSchedules;
use App\Console\Commands\PruneOperationalData;
use App\Console\Commands\RecoverEnrollmentCompletions;
use App\Console\Commands\RecoverGrantingOrders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(RecoverGrantingOrders::class)->everyMinute()->withoutOverlapping();
Schedule::command(ExpireOverdueOrders::class)->everyMinute()->withoutOverlapping();
Schedule::command(PruneOperationalData::class)->dailyAt('03:00')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
Schedule::command(FinalizeOverdueAttempts::class)->everyMinute()->withoutOverlapping();
Schedule::command(RecoverEnrollmentCompletions::class)->everyMinute()->withoutOverlapping();
// P5 (02b §5): TV-10, TV-18, TV-19.
Schedule::command(CommerceSchedules::class, ['reminders'])->dailyAt('08:00')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
Schedule::command(CommerceSchedules::class, ['codes'])->dailyAt('00:10')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
Schedule::command(CommerceSchedules::class, ['promotions'])->everyMinute()->withoutOverlapping();
Schedule::command(DispatchDailyLearningReport::class)->dailyAt('02:00')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
// P6 (02c §4): TV-12.
Schedule::command(ExamSchedules::class)->everyMinute()->withoutOverlapping();
// P7 (02d §4): TV-14.
Schedule::command(PracticeSchedules::class)->everyMinute()->withoutOverlapping();
// P8 (02e §4): TV-15 báo cáo tuần phụ huynh, 08:00 Chủ nhật giờ VN.
Schedule::command(GuardianWeeklyReports::class)->weeklyOn(0, '08:00')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
// P8 (02e §2): QT-26 A9 quét hoa hồng thiếu/hủy do hoàn khoản.
Schedule::call(fn () => app(AffiliateCommissions::class)->sweep())->name('edunest:affiliates')->everyFiveMinutes()->withoutOverlapping();
// P8 (02e §4): TV-20 nhắc buổi học trực tiếp trước 30 phút (CR-171/173).
Schedule::call(fn () => app(LiveSessions::class)->remind(CarbonImmutable::now()))->name('edunest:live-reminders')->everyMinute()->withoutOverlapping();

// CR-262: in cặp khóa VAPID mới để dán vào .env (Windows/Laragon: chạy với biến môi trường OPENSSL_CONF).
Artisan::command('edunest:vapid', function () {
    $keys = VAPID::createVapidKeys();
    $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
})->purpose('Sinh cặp khóa VAPID cho thông báo đẩy');
