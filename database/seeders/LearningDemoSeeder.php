<?php

namespace Database\Seeders;

use App\Actions\M05\CreateDraftVersion;
use App\Actions\M05\EditDraftContent;
use App\Actions\M05\VersionTransitions;
use App\Actions\M10\CompleteEnrollment;
use App\Enums\M01\RoleCode;
use App\Enums\M05\LessonType;
use App\Enums\M08\EnrollmentSource;
use App\Enums\M08\LearningStatus;
use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Jobs\M10\GenerateCertificatePdf;
use App\Jobs\M12\AggregateDailyReportMetrics;
use App\Models\M01\Role;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Support\M03\AuthoringScope;
use App\Support\M10\CertificatePdfRenderer;
use App\Support\Tx;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class LearningDemoSeeder extends Seeder
{
    private const CHAPTER = 'Chương mở đầu. Video bài giảng';

    private const VIDEO_DURATION_MS = 9674;

    // Clip H.264 do EduNest tạo, gồm một thanh tiến độ trên nền màu.
    private const VIDEO = <<<'BASE64'
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
BASE64;

    public function run(): void
    {
        $course = Course::query()->where('title', 'Hóa 12 nền tảng')->whereNotNull('current_version_id')->oldest('id')->first();
        $author = User::query()->where('email', 'hoa.tran@edunest.test')->firstOrFail();
        if ($course === null || ! AuthoringScope::isCurrentAuthor($author, $course)) {
            return;
        }

        $staff = User::query()->where('email', 'lan.pham@edunest.test')->firstOrFail();
        $reviewer = User::query()->where('email', 'minh@edunest.test')->firstOrFail();
        $version = $course->currentVersion()->firstOrFail();
        if (! $version->sections()->where('title', self::CHAPTER)->exists()) {
            if ($course->openVersion()->exists()) {
                return;
            }
            $version = $this->publish($course, $version, $author, $staff, $reviewer);
        }

        $old = $course->versions()->where('version_no', 1)->firstOrFail();
        $scenarios = [
            'dang-hoc' => ['name' => 'Nguyễn Đang Học', 'version' => $version],
            'het-han' => ['name' => 'Lê Hết Hạn', 'version' => $old, 'expired' => true],
            'da-tao' => ['name' => 'Phạm Hoàn Thành', 'version' => $old, 'certificate' => true],
            'cho-tao' => ['name' => 'Vũ Chờ Chứng Nhận', 'version' => $version, 'certificate' => true],
            'pdf-loi' => ['name' => 'Trần Tạo Lại', 'version' => $version, 'certificate' => true],
            'thu-hoi' => ['name' => 'Đỗ Thu Hồi', 'version' => $version, 'certificate' => true],
        ];
        foreach ($scenarios as $key => $scenario) {
            $student = $this->student($key, $scenario['name']);
            $enrollment = Enrollment::query()->firstOrCreate(['user_id' => $student->id, 'course_id' => $course->id], [
                'course_version_id' => $scenario['version']->id, 'learning_status' => LearningStatus::DangHoc,
                'source' => EnrollmentSource::MaKichHoat, 'granted_at' => now()->subDays(30),
                'het_han_luc' => ($scenario['expired'] ?? false) ? now()->subDay() : now()->addYear(),
            ]);
            if (($scenario['certificate'] ?? false) && ! $enrollment->certificate()->exists()) {
                $this->certificate($enrollment, $key, $staff);
            }
        }

        // Mốc cắt của job là giây nguyên, kỳ nửa mở [.., cutoff): lấy giây kế tiếp để tính cả dữ liệu vừa seed.
        $at = now()->floorSecond()->addSecond();
        (new AggregateDailyReportMetrics($at->setTimezone('Asia/Ho_Chi_Minh')->toDateString(), $at))->handle();
    }

    private function publish(Course $course, CourseVersion $source, User $author, User $staff, User $reviewer): CourseVersion
    {
        $path = 'lessons/video/demo-p4.mp4';
        $disk = Storage::disk('local');
        $bytes = base64_decode(self::VIDEO, true);
        if (! $disk->put($path, $bytes)) {
            throw new \RuntimeException('Không ghi được clip học demo P4.');
        }

        return Tx::run(function () use ($course, $source, $author, $staff, $reviewer, $path): CourseVersion {
            $draft = app(CreateDraftVersion::class)->handle($author, $course)['version'];
            $edit = app(EditDraftContent::class);
            $chapter = $edit->addSection($author, $draft->id, self::CHAPTER);
            $lesson = $edit->addLesson($author, $chapter->id, 'Este quanh ta (video)', LessonType::Video);
            $lesson->forceFill(['video_path' => $path, 'video_duration_ms' => self::VIDEO_DURATION_MS, 'is_required' => true])->save();
            for ($position = $source->sections()->count(); $position > 0; $position--) {
                $edit->move($author, 'section', $chapter->id, 'up');
            }
            $flow = app(VersionTransitions::class);
            $flow->submit($author, $draft);
            $flow->approve($reviewer, $draft->refresh());

            return $flow->publish($staff, $draft->refresh());
        });
    }

    private function student(string $key, string $name): User
    {
        $student = User::query()->firstOrCreate(['email' => $key.'.p4@edunest.test'], [
            'name' => $name, 'password' => DemoSeeder::PASSWORD,
        ]);
        if ($student->wasRecentlyCreated) {
            $student->forceFill(['email_verified_at' => now()])->save();
        }
        $student->roles()->syncWithoutDetaching([Role::idFor(RoleCode::HocVien) => ['assigned_at' => now()]]);

        return $student;
    }

    private function certificate(Enrollment $enrollment, string $state, User $staff): void
    {
        $enrollment->forceFill(['learning_status' => LearningStatus::HoanThanh, 'completed_at' => now()->subDay()])->save();
        $certificate = app(CompleteEnrollment::class)->handle($enrollment);
        if ($certificate === null) {
            return;
        }
        $token = $certificate->refresh()->pdf_job_token;
        if ($state === 'thu-hoi') {
            $certificate->update(['status' => CertificateStatus::DaThuHoi, 'revoked_at' => now(),
                'revoked_by_id' => $staff->id, 'revoked_reason' => 'Kết quả bài kiểm tra cuối khóa bị hủy do vi phạm quy chế thi, thu hồi theo quy định.', 'pdf_job_token' => null]);
        }
        if ($state !== 'cho-tao' && $token !== null) {
            $job = new GenerateCertificatePdf($certificate->id, $token);
            if ($state === 'da-tao') {
                $job->handle(app(CertificatePdfRenderer::class));
            } elseif ($state === 'pdf-loi') {
                $job->failed(new \RuntimeException('Dữ liệu demo PDF lỗi.'));
            }
            DB::table(config()->string('queue.connections.database.table'))->where('queue', 'default')->where('payload->uuid', $token)->delete();
        }
        if ($state === 'pdf-loi' && $certificate->refresh()->pdf_status !== CertificatePdfStatus::Loi) {
            $certificate->update(['pdf_status' => CertificatePdfStatus::Loi]);
        }
    }
}
