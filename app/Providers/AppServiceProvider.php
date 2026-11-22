<?php

namespace App\Providers;

use App\Actions\M01\CreateAuthenticatedSession;
use App\Auth\DeviceAwareUserProvider;
use App\Enums\M01\RoleCode;
use App\Http\Middleware\EnsureBoundDevice;
use App\Jobs\M07\SendSimulatedPaymentWebhook;
use App\Jobs\M10\GenerateCertificatePdf;
use App\Listeners\RecordLearningXp;
use App\Listeners\SendDomainNotifications;
use App\Models\M02\TeacherProfile;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M04\Quiz;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M07\Payment;
use App\Models\M10\Certificate;
use App\Models\M10\QuizAttempt;
use App\Models\M11\Setting;
use App\Models\User;
use App\Policies\M01\UserPolicy;
use App\Policies\M02\TeacherProfilePolicy;
use App\Policies\M03\CoursePolicy;
use App\Policies\M04\QuestionBankPolicy;
use App\Policies\M04\QuestionPolicy;
use App\Policies\M05\CourseVersionPolicy;
use App\Policies\M07\OrderItemPolicy;
use App\Policies\M07\OrderPolicy;
use App\Policies\M07\PaymentPolicy;
use App\Policies\M09\LessonPolicy;
use App\Policies\M10\CertificatePolicy;
use App\Policies\M10\QuizAttemptPolicy;
use App\Policies\M10\QuizPolicy;
use App\Support\M10\CertificatePdfRenderer;
use App\Support\M10\DompdfCertificatePdfRenderer;
use App\Support\M10\MinimalCertificatePdfRenderer;
use App\Support\M25\AiProvider;
use App\Support\M25\FakeAiProvider;
use App\Support\M25\GeminiAiProvider;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CertificatePdfRenderer::class, fn (): CertificatePdfRenderer => class_exists(Dompdf::class)
            ? new DompdfCertificatePdfRenderer : new MinimalCertificatePdfRenderer);
        // M25: có khóa Gemini thì gọi AI thật (CR-255); chưa có khóa hoặc đang chạy test thì dùng bộ giả lập (CR-239).
        $this->app->bind(AiProvider::class, function (): AiProvider {
            $key = (string) config('services.gemini.key');
            if ($key === '' || $this->app->environment('testing')) {
                return new FakeAiProvider;
            }

            return new GeminiAiProvider($key, (string) config('services.gemini.model'), rtrim((string) config('services.gemini.endpoint'), '/'), (int) config('services.gemini.timeout', 30), array_values(array_filter(array_map('trim', explode(',', (string) config('services.gemini.fallback'))))));
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();

        config(['app.demo' => config('app.demo', config('services.payment_sim.demo'))]);
        Queue::createPayloadUsing(function (string $connection, ?string $queue, array $payload): array {
            $job = $payload['data']['command'] ?? null;

            return match (true) {
                $job instanceof SendSimulatedPaymentWebhook => ['edunest_confirmation' => ['payment_id' => $job->paymentId, 'body' => $job->payload]],
                $job instanceof GenerateCertificatePdf => ['uuid' => $job->token],
                default => [],
            };
        });
        Queue::looping(function (): void {
            Cache::put('edunest:worker-heartbeat', true, 120);
        });

        Auth::provider('edunest', fn ($app, array $config) => new DeviceAwareUserProvider($app['hash'], $config['model']));
        Event::listen(Login::class, [CreateAuthenticatedSession::class, 'handle']);
        Event::listen(Login::class, [EnsureBoundDevice::class, 'onLogin']);
        Event::subscribe(SendDomainNotifications::class);
        Event::subscribe(RecordLearningXp::class);
        // 09 P2.2: tuyến công khai 120 lượt/phút mỗi IP.
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(120)->by((string) $request->ip()));
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(600)->by((string) $request->ip()));
        RateLimiter::for('certificate-lookup', fn (Request $request) => Limit::perMinute(Setting::number('cert.lookup_per_minute'))
            ->by((string) $request->ip()));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        // P9.1: chặn nạp lười (N+1). Kiểm thử ném lỗi; local chỉ ghi nhật ký để trang demo không vỡ; sản phẩm tắt.
        Model::preventLazyLoading(! app()->isProduction());
        if (! app()->runningUnitTests()) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                Log::warning('Nạp lười quan hệ', ['model' => $model::class, 'relation' => $relation, 'path' => app()->runningInConsole() ? 'console' : request()->path()]);
            });
        }

        // BR-HT-57 (CR-109): mật khẩu tối thiểu 8 ký tự ở mọi môi trường.
        Password::defaults(fn (): Password => Password::min(8));
    }

    /** 04 §4: Policy theo model, Gate cho ability không gắn model. Không có Gate::before (04 §7 #2). */
    protected function configureAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(TeacherProfile::class, TeacherProfilePolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(CourseVersion::class, CourseVersionPolicy::class);
        Gate::policy(QuestionBank::class, QuestionBankPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderItem::class, OrderItemPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(QuizAttempt::class, QuizAttemptPolicy::class);
        Gate::policy(Certificate::class, CertificatePolicy::class);
        Gate::define('view-audit-logs', fn (User $u): bool => $u->hasEffectiveRole(RoleCode::QuanTri));
        Gate::define('view-revenue-report', fn (User $u): bool => $u->hasEffectiveRole(RoleCode::GiaoVu, RoleCode::QuanTri));
        Gate::define('toggle-grant-failure-mode', fn (User $u) => config()->boolean('app.demo')
            && $u->hasEffectiveRole(RoleCode::QuanTri));

        // NH-60 TaxonomyPolicy::create/update/delete/restore/forceDelete — chỉ Quản trị.
        Gate::define('manage-taxonomies', fn (User $u) => $u->hasEffectiveRole(RoleCode::QuanTri));
        // 09 §2.A — xem cấu hình (sửa theo SettingPolicy::update khi có khóa được phép).
        Gate::define('view-settings', fn (User $u) => $u->hasEffectiveRole(RoleCode::QuanTri));
        // CR-190: chỉ tài khoản có vai Giáo vụ/Quản trị thiết lập 2FA.
        Gate::define('setup-two-factor', fn (User $u) => $u->hasRole(RoleCode::GiaoVu) || $u->hasRole(RoleCode::QuanTri));
    }
}
