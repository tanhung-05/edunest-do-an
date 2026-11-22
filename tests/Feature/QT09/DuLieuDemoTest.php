<?php

use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M10\Certificate;
use App\Models\M12\ReportMetric;
use Database\Factories\M01\UserDeviceFactory;
use Database\Factories\M09\LessonProgressFactory;
use Database\Factories\M10\AttemptAnswerFactory;
use Database\Factories\M10\CertificateFactory;
use Database\Factories\M10\QuizAttemptFactory;
use Database\Factories\M10\QuizAttemptGrantFactory;
use Database\Factories\M12\ReportMetricFactory;
use Database\Seeders\CourseDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LearningDemoSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\HocTap;

pest()->group('P4');

it('Factory P4 gắn phiên bản, biên 90%, điểm và vé đúng mô hình', function () {
    $this->travelTo(HocTap::at('09:00:00'));
    $attempt = QuizAttemptFactory::new()->closed('0.6996')->create();
    $answer = AttemptAnswerFactory::new()->correct()->create(['quiz_attempt_id' => $attempt->id]);
    $progress = LessonProgressFactory::new()->watched(539950)->create(['enrollment_id' => $attempt->enrollment_id]);
    $grant = QuizAttemptGrantFactory::new()->create(['enrollment_id' => $attempt->enrollment_id]);
    $certificate = CertificateFactory::new()->pendingPdf()->published()->create();
    $device = UserDeviceFactory::new()->revoked()->create();
    $metric = ReportMetricFactory::new()->create();

    expect($attempt->passed)->toBeFalse()->and($attempt->score_earned)->toBe('0.6996')
        ->and($answer->quiz_question_id)->toBe($attempt->quiz()->firstOrFail()->items()->firstOrFail()->id)
        ->and($grant->quiz_id)->toBe($attempt->quiz_id)->and($progress->completed_at)->toBeNull()
        ->and($certificate->pdf_job_token)->toHaveLength(36)->and($certificate->completion_event_published_at)->not->toBeNull()
        ->and($device->revoked_at)->not->toBeNull()->and($metric->computed_at)->not->toBeNull();
});

it('Seed demo P4 chạy lặp, có hai bản ghi danh và bốn nhánh chứng nhận', function () {
    $this->travelTo(HocTap::at('09:00:00'));
    Storage::fake('local');
    $this->seed([DemoSeeder::class, CourseDemoSeeder::class, LearningDemoSeeder::class]);
    $this->seed(LearningDemoSeeder::class);
    $course = Course::query()->where('title', 'Hóa 12 nền tảng')->sole();
    $video = Lesson::query()->where('course_version_id', $course->current_version_id)->where('title', 'Este quanh ta (video)')->sole();
    Storage::disk('local')->assertExists($video->video_path);
    expect($video->video_duration_ms)->toBe(9674)
        ->and(Enrollment::query()->where('course_id', $course->id)->distinct()->count('course_version_id'))->toBe(2)
        ->and(Certificate::query()->where('pdf_status', CertificatePdfStatus::DaTao)->count())->toBe(1)
        ->and(Certificate::query()->where('pdf_status', CertificatePdfStatus::Loi)->count())->toBe(1)
        ->and(Certificate::query()->where('pdf_status', CertificatePdfStatus::ChoTao)->where('status', CertificateStatus::HieuLuc)->count())->toBe(1)
        ->and(Certificate::query()->where('status', CertificateStatus::DaThuHoi)->count())->toBe(1)
        ->and(ReportMetric::query()->where('metric', 'completion_rate')->where('scope_id', $course->id)->count())->toBe(1);
});
