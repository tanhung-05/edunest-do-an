<?php

use App\Actions\M01\LockAccount;
use App\Actions\M03\ReassignCourseAuthor;
use App\Actions\M09\MarkLessonCompleted;
use App\Actions\M09\RecordWatchBeat;
use App\Actions\M10\CompleteEnrollment;
use App\Actions\M10\FinalizeAttempt;
use App\Actions\M10\GrantExtraQuizAttempt;
use App\Actions\M10\RecoverEnrollmentCompletions;
use App\Actions\M10\RevokeCertificate;
use App\Actions\M10\SaveAttemptAnswer;
use App\Actions\M10\StartQuizAttempt;
use App\Actions\M10\SubmitQuizAttempt;
use App\Jobs\M10\GenerateCertificatePdf;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M10\Certificate;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M03\CourseLocks;
use App\Support\M10\CertificatePdfRenderer;
use App\Support\TestBarrier;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || ! str_starts_with(DB::connection()->getDatabaseName(), 'edunest_test')) {
    exit(2);
}

$args = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
$dir = getenv('EDUNEST_BARRIER_DIR');
$clock = $dir.DIRECTORY_SEPARATOR.'clock';
CarbonImmutable::setTestNow(static function () use ($clock, $args): CarbonImmutable {
    clearstatcache(true, $clock);

    return CarbonImmutable::parse(isset($args['follow_clock']) && is_file($clock) ? file_get_contents($clock) : $args['at']);
});
if (isset($args['disk'])) {
    config(['filesystems.disks.local.root' => $args['disk']]);
}

$actor = fn () => User::findOrFail($args['actor']);
$enrollment = fn () => Enrollment::findOrFail($args['enrollment']);
$quiz = fn () => Quiz::findOrFail($args['quiz']);
$attempt = fn () => QuizAttempt::findOrFail($args['attempt']);
$lesson = fn () => Lesson::findOrFail($args['lesson']);
touch($dir.DIRECTORY_SEPARATOR.$args['side'].'.ready');

try {
    $value = match ($argv[1]) {
        'start' => app(StartQuizAttempt::class)->handle($actor(), $enrollment(), $quiz())->id,
        'finalize' => app(FinalizeAttempt::class)->handle($attempt())->close_reason?->value,
        'save' => app(SaveAttemptAnswer::class)->handle($actor(), $attempt(), QuizQuestion::findOrFail($args['question']), $args['answer'])->id,
        'submit' => app(SubmitQuizAttempt::class)->handle($actor(), $attempt())->close_reason?->value,
        'lock' => app(LockAccount::class)->handle($actor(), User::findOrFail($args['target']))->id,
        'mark' => app(MarkLessonCompleted::class)->handle($actor(), $enrollment(), $lesson())->id,
        'complete' => app(CompleteEnrollment::class)->handle($enrollment())?->id,
        'beat' => app(RecordWatchBeat::class)->handle($actor(), $enrollment(), $lesson(), $args['position'], CarbonImmutable::parse($args['received']))['counted'],
        'grant' => app(GrantExtraQuizAttempt::class)->handle($actor(), $enrollment(), $quiz(), 'Cho làm lại')->id,
        'reassign' => Tx::run(function () use ($args, $actor) {
            $course = CourseLocks::course($args['course']);
            TestBarrier::reach('reassign-course-author.locked');

            return app(ReassignCourseAuthor::class)->handle($actor(), $course, $args['new_author'])->id;
        }),
        'recover' => app(RecoverEnrollmentCompletions::class)->recover($args['certificate']),
        'pdf' => (new GenerateCertificatePdf($args['certificate'], $args['token']))->handle(app(CertificatePdfRenderer::class)),
        'pdf-failed' => (new GenerateCertificatePdf($args['certificate'], $args['token']))->failed(new RuntimeException('Hết lần thử')),
        'revoke' => app(RevokeCertificate::class)->handle($actor(), Certificate::findOrFail($args['certificate']), 'Thu hồi'),
        default => throw new InvalidArgumentException('Kịch bản đồng thời không hợp lệ.'),
    };
    echo json_encode(['value' => $value], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (DomainRejection $e) {
    echo json_encode(['status' => $e->status, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL;
}
