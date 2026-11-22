<?php

namespace App\Actions\M26;

use App\Enums\M08\EnrollmentValidity;
use App\Enums\M15\NotificationKind;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use App\Models\M26\LiveSession;
use App\Models\M26\LiveSessionJoin;
use App\Models\M26\LiveSessionRecording;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningLocks;
use App\Support\M15\Notifier;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * QT-28 (02e §4): lên lịch, sửa/dời/hủy (A1, A2), vào phòng (bước 5–7), nhắc (A3), bản ghi (A5, A6).
 * Ghi phía Giáo viên khóa `courses` → `live_sessions`; vào phòng và nhắc khóa `live_sessions` (chia sẻ) → (học viên, ghi danh).
 */
final class LiveSessions
{
    public const REMIND_BEFORE_MINUTES = 30;

    public const JOIN_BEFORE_MINUTES = 10;

    /** @param  array{title: string, description?: ?string, starts_at: CarbonImmutable, duration_minutes: int, meeting_url: string}  $data */
    public function schedule(User $teacher, Course $course, array $data, CarbonImmutable $at): LiveSession
    {
        self::validate($data);
        if ($data['starts_at']->lte($at)) {
            throw new DomainRejection('Giờ bắt đầu phải sau thời điểm hiện tại.', 'starts_at');
        }

        return Tx::run(function () use ($teacher, $course, $data) {
            $course = CourseLocks::course($course->id);
            AuthoringScope::ensure($teacher, $course);
            $session = LiveSession::query()->create([
                'course_id' => $course->id, 'title' => trim($data['title']), 'description' => self::clean($data['description'] ?? null),
                'starts_at' => $data['starts_at']->utc(), 'duration_minutes' => $data['duration_minutes'], 'meeting_url' => $data['meeting_url'],
                'created_by_id' => $teacher->id, 'updated_by_id' => $teacher->id,
            ])->refresh();
            Audit::log('LIVE_SESSION_SCHEDULED', $teacher, $session, null, ['starts_at' => $session->starts_at->toIso8601String()]);

            return $session;
        });
    }

    /**
     * A1: dời giờ, đổi thời lượng hoặc link khi `t < T − 10 phút`; sửa tên/mô tả khi `t < giờ kết thúc`. Dời giờ tăng `schedule_rev`.
     *
     * @param  array{title: string, description?: ?string, starts_at: CarbonImmutable, duration_minutes: int, meeting_url: string}  $data
     */
    public function update(User $teacher, LiveSession $session, array $data, CarbonImmutable $at): LiveSession
    {
        self::validate($data);

        return Tx::run(function () use ($teacher, $session, $data, $at) {
            $course = CourseLocks::course($session->course_id);
            AuthoringScope::ensure($teacher, $course);
            $s = LiveSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($s->cancelled_at !== null) {
                throw new DomainRejection('Buổi đã hủy.', 'general');
            }
            if ($at->gte($s->endsAt())) {
                throw new DomainRejection('Buổi đã kết thúc, chỉ còn tải bản ghi.', 'general');
            }
            $moved = ! $data['starts_at']->equalTo($s->starts_at);
            $scheduleChanged = $moved || $data['duration_minutes'] !== $s->duration_minutes || $data['meeting_url'] !== $s->meeting_url;
            if ($scheduleChanged && $at->gte($s->starts_at->subMinutes(self::JOIN_BEFORE_MINUTES))) {
                throw new DomainRejection('Chỉ dời giờ hoặc đổi link trước giờ bắt đầu hơn 10 phút.', 'starts_at');
            }
            if ($moved && $data['starts_at']->lte($at)) {
                throw new DomainRejection('Giờ bắt đầu mới phải sau thời điểm hiện tại.', 'starts_at');
            }
            $old = ['starts_at' => $s->starts_at->toIso8601String(), 'duration_minutes' => $s->duration_minutes, 'meeting_url' => $s->meeting_url];
            $s->forceFill([
                'title' => trim($data['title']), 'description' => self::clean($data['description'] ?? null),
                'starts_at' => $data['starts_at']->utc(), 'duration_minutes' => $data['duration_minutes'], 'meeting_url' => $data['meeting_url'],
                'schedule_rev' => $moved ? $s->schedule_rev + 1 : $s->schedule_rev, 'updated_by_id' => $teacher->id,
            ])->save();
            if ($scheduleChanged) {
                Audit::log('LIVE_SESSION_RESCHEDULED', $teacher, $s, $old, ['starts_at' => $s->starts_at->toIso8601String(),
                    'duration_minutes' => $s->duration_minutes, 'meeting_url' => $s->meeting_url]);
            }

            return $s;
        });
    }

    /** A2: hủy khi `t < giờ kết thúc`; dừng nhắc và dừng cấp link ngay khi ghi. */
    public function cancel(User $teacher, LiveSession $session, CarbonImmutable $at): LiveSession
    {
        return Tx::run(function () use ($teacher, $session, $at) {
            $course = CourseLocks::course($session->course_id);
            AuthoringScope::ensure($teacher, $course);
            $s = LiveSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($s->cancelled_at !== null) {
                throw new DomainRejection('Buổi đã hủy.', 'general');
            }
            if ($at->gte($s->endsAt())) {
                throw new DomainRejection('Buổi đã kết thúc, không hủy được.', 'general');
            }
            $s->forceFill(['cancelled_at' => $at, 'cancelled_by_id' => $teacher->id, 'updated_by_id' => $teacher->id])->save();
            Audit::log('LIVE_SESSION_CANCELLED', $teacher, $s, null, ['cancelled_at' => $at->toIso8601String()]);

            return $s;
        });
    }

    /** Bước 5–7: kiểm khoảng vào phòng, ghi danh `CON_HAN`; giữ giờ bấm đầu tiên; trả link hiện hành. */
    public function join(User $student, LiveSession $session, Enrollment $enrollment, CarbonImmutable $at): string
    {
        return Tx::run(function () use ($student, $session, $enrollment, $at) {
            $s = LiveSession::query()->whereKey($session->id)->sharedLock()->firstOrFail();
            [$student, $enrollment] = LearningLocks::student($student, $enrollment);
            self::requireLearner($student, $enrollment, $s, $at);
            if ($s->cancelled_at !== null) {
                throw new DomainRejection('Buổi đã bị hủy.', 'general');
            }
            if (! $s->joinable($at)) {
                throw new DomainRejection('Chỉ vào phòng được từ 10 phút trước giờ bắt đầu tới hết giờ kết thúc.', 'general');
            }
            LiveSessionJoin::query()->insertOrIgnore(['live_session_id' => $s->id, 'user_id' => $student->id, 'first_clicked_at' => $at->utc()]);

            return $s->meeting_url;
        });
    }

    /** A5: sau giờ kết thúc, đặt một tệp hiện hành; thay thì giữ tệp cũ và dấu vết. */
    public function uploadRecording(User $teacher, LiveSession $session, UploadedFile $file, CarbonImmutable $at): LiveSessionRecording
    {
        $path = $file->store('live-recordings/'.$session->id, 'local');
        if (! is_string($path)) {
            throw new DomainRejection('Không lưu được tệp.', 'file');
        }
        try {
            return Tx::run(function () use ($teacher, $session, $file, $path, $at) {
                $course = CourseLocks::course($session->course_id);
                AuthoringScope::ensure($teacher, $course);
                $s = LiveSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                if ($s->cancelled_at !== null) {
                    throw new DomainRejection('Buổi đã hủy, không có bản ghi.', 'file');
                }
                if ($at->lte($s->endsAt())) {
                    throw new DomainRejection('Chỉ tải bản ghi sau giờ kết thúc buổi.', 'file');
                }
                $old = LiveSessionRecording::query()->where('current_for_session_id', $s->id)->lockForUpdate()->first();
                $old?->forceFill(['current_for_session_id' => null, 'replaced_at' => $at, 'replaced_by_id' => $teacher->id])->save();
                $rec = LiveSessionRecording::query()->create([
                    'live_session_id' => $s->id, 'current_for_session_id' => $s->id, 'file_path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => (int) $file->getSize(), 'uploaded_by_id' => $teacher->id,
                ]);
                Audit::log($old === null ? 'LIVE_RECORDING_UPLOADED' : 'LIVE_RECORDING_REPLACED', $teacher, $s,
                    $old === null ? null : ['recording_id' => $old->id], ['recording_id' => $rec->id]);

                return $rec;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    /** A6: mỗi lần mở kiểm tệp còn hiện hành và ghi danh `CON_HAN`. */
    public function recording(User $student, LiveSessionRecording $recording, Enrollment $enrollment, CarbonImmutable $at): LiveSessionRecording
    {
        return Tx::run(function () use ($student, $recording, $enrollment, $at) {
            $s = LiveSession::query()->whereKey($recording->live_session_id)->sharedLock()->firstOrFail();
            [$student, $enrollment] = LearningLocks::student($student, $enrollment);
            self::requireLearner($student, $enrollment, $s, $at);
            $rec = LiveSessionRecording::query()->whereKey($recording->id)->firstOrFail();
            if ($rec->current_for_session_id !== $s->id) {
                throw DomainRejection::forbidden('Bản ghi này đã được thay, hãy tải lại trang.');
            }

            return $rec;
        });
    }

    /**
     * A3, CR-171/173: nhắc trong ứng dụng từ mốc T − 30 phút tới trước T, tối đa một chuông mỗi (buổi, lịch, học viên).
     * Từ T không phát bù. Kiểm lại buổi chưa hủy và ghi danh `CON_HAN` tại lần phát.
     */
    public function remind(CarbonImmutable $at): int
    {
        $sent = 0;
        $due = LiveSession::query()->whereNull('cancelled_at')->where('starts_at', '>', $at->utc())
            ->where('starts_at', '<=', $at->utc()->addMinutes(self::REMIND_BEFORE_MINUTES))->pluck('id');
        foreach ($due as $sessionId) {
            $enrollmentIds = Enrollment::query()->whereIn('course_id', LiveSession::query()->whereKey($sessionId)->select('course_id'))
                ->whereNull('revoked_at')->orderBy('id')->pluck('id');
            foreach ($enrollmentIds as $enrollmentId) {
                try {
                    $sent += Tx::run(function () use ($sessionId, $enrollmentId, $at) {
                        $s = LiveSession::query()->whereKey($sessionId)->sharedLock()->firstOrFail();
                        $e = Enrollment::query()->whereKey($enrollmentId)->sharedLock()->firstOrFail();
                        $user = User::query()->find($e->user_id);
                        if ($s->cancelled_at !== null || $at->gte($s->starts_at) || $at->lt($s->starts_at->subMinutes(self::REMIND_BEFORE_MINUTES))
                            || $user === null || ! $user->isActive() || EnrollmentValidityChecker::for($e, $at) !== EnrollmentValidity::ConHan) {
                            return 0;
                        }
                        Notifier::send($user, NotificationKind::BuoiTrucTiep, 'Sắp tới buổi học trực tiếp',
                            $s->title.' bắt đầu lúc '.$s->starts_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m').'.',
                            route('student.live.index'), 'live:'.$s->id.':'.$s->schedule_rev.':'.$user->id);

                        return 1;
                    });
                } catch (QueryException $ex) {
                    if (Tx::code($ex) !== 1062) {
                        throw $ex;
                    }
                }
            }
        }

        return $sent;
    }

    private static function requireLearner(User $student, Enrollment $enrollment, LiveSession $s, CarbonImmutable $at): void
    {
        if ($enrollment->user_id !== $student->id || $enrollment->course_id !== $s->course_id) {
            throw DomainRejection::forbidden('Bạn không học khóa của buổi này.');
        }
        $validity = EnrollmentValidityChecker::for($enrollment, $at);
        if ($validity !== EnrollmentValidity::ConHan) {
            throw DomainRejection::forbidden($validity === EnrollmentValidity::DaThuHoi ? 'Quyền học đã bị thu hồi.' : 'Quyền học đã hết hạn.');
        }
    }

    /** @param  array{title: string, description?: ?string, starts_at: CarbonImmutable, duration_minutes: int, meeting_url: string}  $data */
    private static function validate(array $data): void
    {
        if (trim($data['title']) === '' || mb_strlen($data['title']) > 150) {
            throw new DomainRejection('Tên buổi cần từ 1 đến 150 ký tự.', 'title');
        }
        if ($data['duration_minutes'] < 15 || $data['duration_minutes'] > 480) {
            throw new DomainRejection('Thời lượng từ 15 đến 480 phút.', 'duration_minutes');
        }
        if (! str_starts_with($data['meeting_url'], 'https://') || filter_var($data['meeting_url'], FILTER_VALIDATE_URL) === false
            || mb_strlen($data['meeting_url']) > 500) {
            throw new DomainRejection('Link phòng họp phải là địa chỉ https hợp lệ.', 'meeting_url');
        }
    }

    private static function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, 5000);
    }
}
