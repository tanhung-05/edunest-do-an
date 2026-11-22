<?php

/*
 * P9.1: đếm số truy vấn SQL của các trang chính trên CSDL demo (chỉ đọc, mọi ghi bị cuộn lại).
 * Chạy: php scripts/dem-truy-van.php  (cần CSDL demo đã seed, APP_ENV=local).
 * Xem chi tiết một trang: php scripts/dem-truy-van.php /academic/enrollments  (in các câu SQL lặp lại).
 * Tài khoản nhân sự được giả lập 2FA trong giao dịch rồi cuộn lại, không đổi dữ liệu thật.
 */

use App\Actions\M01\CreateAuthenticatedSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\M01\UserDevice;
use App\Models\User;
use App\Support\M09\LearningFile;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$plan = [
    null => ['/', '/courses', '/bundles', '/exams', '/advisor'],
    'an.nguyen@edunest.test' => ['/student', '/student/courses', '/student/orders', '/student/achievements', '/student/flashcards',
        '/student/practice', '/student/live', '/community', '/community/posts/1', '/student/certificates', '/notifications', '/account/devices'],
    'phu.huynh@edunest.test' => ['/guardian'],
    'hoa.tran@edunest.test' => ['/teacher', '/teacher/courses', '/teacher/discussions', '/teacher/live-sessions', '/teacher/activities'],
    'lan.pham@edunest.test' => ['/academic', '/academic/courses', '/academic/orders', '/academic/enrollments', '/academic/certificates',
        '/academic/community', '/academic/bank-reviews', '/academic/bundles', '/academic/affiliates', '/academic/discussions', '/academic/exams', '/academic/reports'],
    'quang@edunest.test' => ['/admin', '/admin/users', '/admin/audit-logs', '/admin/revenue', '/admin/badges'],
];

$version = $app->make(HandleInertiaRequests::class)->version(Request::create('/'));
$only = $argv[1] ?? null;
$rows = [];
$sqls = [];
foreach ($plan as $email => $urls) {
    foreach ($urls as $url) {
        if ($only !== null && $url !== $only) {
            continue;
        }
        DB::beginTransaction();
        try {
            $user = null;
            if ($email !== '') {
                $user = User::query()->where('email', $email)->first();
                if ($user !== null && ! $user->hasTwoFactorConfirmed()) {
                    $user->forceFill(['two_factor_secret' => encrypt('demo'), 'two_factor_confirmed_at' => now()])->save();
                }
            }
            $count = 0;
            $sqls = [];
            DB::listen(function ($q) use (&$count, &$sqls) {
                $count++;
                $key = preg_replace('/\b\d+\b/', '?', $q->sql);
                $sqls[$key] = ($sqls[$key] ?? 0) + 1;
            });
            $cookies = [];
            if ($user !== null) {
                // Phiên giống đăng nhập thật: thế hệ phiên (CreateAuthenticatedSession) và thiết bị trình duyệt đã nhận.
                $device = UserDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->latest('id')->first();
                $plainCookie = 'dem-truy-van-'.$user->id;
                if ($device !== null) {
                    $device->forceFill(['cookie_hash' => LearningFile::cookieHash($user, $plainCookie)])->save();
                }
                $session = $app['session']->driver();
                $session->setId(null);
                $session->start();
                $session->put(Auth::guard('web')->getName(), $user->id);
                $session->put(CreateAuthenticatedSession::SESSION_KEY, $user->auth_generation);
                if ($device !== null) {
                    $session->put(LearningFile::DEVICE, $device->id);
                }
                $session->save();
                $key = $app['encrypter']->getKey();
                $name = config('session.cookie');
                $cookies[$name] = encrypt(CookieValuePrefix::create($name, $key).$session->getId(), false);
                $cookies[LearningFile::DEVICE] = encrypt(CookieValuePrefix::create(LearningFile::DEVICE, $key).$plainCookie, false);
            }
            $request = Request::create($url, 'GET', [], $cookies, [], ['HTTP_X_INERTIA' => 'true', 'HTTP_X_INERTIA_VERSION' => (string) $version, 'HTTP_ACCEPT' => 'text/html']);
            Auth::forgetGuards();
            $response = $kernel->handle($request);
            $rows[] = sprintf('%-4s %4d  %-32s %s', $response->getStatusCode(), $count, $url, $email ?: 'khach');
        } catch (Throwable $e) {
            $rows[] = sprintf('LOI  %-32s %s', $url, mb_substr($e->getMessage(), 0, 120));
        } finally {
            DB::rollBack();
            DB::flushQueryLog();
            DB::connection()->getEventDispatcher()->forget(QueryExecuted::class);
        }
    }
}
echo implode(PHP_EOL, $rows).PHP_EOL;
if ($only !== null) {
    arsort($sqls);
    foreach ($sqls as $sql => $n) {
        echo str_pad((string) $n, 4, ' ', STR_PAD_LEFT).'  '.mb_substr($sql, 0, 220).PHP_EOL;
    }
}
