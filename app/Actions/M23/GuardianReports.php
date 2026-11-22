<?php

namespace App\Actions\M23;

use App\Models\M23\GuardianLink;
use App\Models\User;
use App\Notifications\GuardianWeeklyMail;
use App\Queries\M23\ChildReport;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * QT-25 A3 (BR-MR-08, CR-99, CR-234): sáng Chủ nhật gửi báo cáo tuần thứ Hai–Chủ nhật (giờ VN) cho từng cặp còn hiệu lực.
 * Dưới khóa cặp: kiểm lại liên kết, ghi một dòng (cặp, tuần) duy nhất rồi mới xếp email; chạy lại không gửi lặp.
 */
final class GuardianReports
{
    public function sendWeek(?CarbonImmutable $at = null): int
    {
        $at ??= CarbonImmutable::now();
        $vn = $at->setTimezone('Asia/Ho_Chi_Minh');
        $weekStart = $vn->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
        $sent = 0;
        GuardianLink::query()->whereNull('removed_at')->orderBy('id')->pluck('id')->each(function (int $id) use ($at, $weekStart, &$sent) {
            $sent += $this->sendOne($id, $weekStart, $at) ? 1 : 0;
        });

        return $sent;
    }

    private function sendOne(int $linkId, string $weekStart, CarbonImmutable $at): bool
    {
        try {
            $payload = Tx::run(function () use ($linkId, $weekStart, $at) {
                $link = GuardianLink::query()->whereKey($linkId)->lockForUpdate()->first();
                if ($link === null || $link->removed_at !== null) {
                    return null;
                }
                $guardian = User::query()->find($link->guardian_id);
                $student = User::query()->find($link->student_id);
                if ($guardian === null || $student === null || GuardianLinks::active($guardian, $student->id) === null) {
                    return null;
                }
                DB::table('guardian_weekly_reports')->insert(['guardian_id' => $guardian->id, 'student_id' => $student->id,
                    'week_start' => $weekStart, 'sent_at' => $at]);

                return [$guardian, $student, ChildReport::for($student, $at)];
            });
        } catch (QueryException $e) {
            if (Tx::code($e) === 1062) {
                return false;
            }
            throw $e;
        }
        if ($payload === null) {
            return false;
        }
        [$guardian, $student, $report] = $payload;
        $guardian->notify(new GuardianWeeklyMail($student->id, 'Báo cáo tuần của '.$student->name, self::lines($report),
            route('guardian.children.show', $student->id)));

        return true;
    }

    /**
     * @param  array{student: array{id: int, name: string}, streak: int, courses: list<array<string, mixed>>, exams: list<array<string, mixed>>}  $report
     * @return list<string>
     */
    public static function lines(array $report): array
    {
        $lines = ['Chuỗi ngày học hiện tại: '.$report['streak'].' ngày.'];
        foreach ($report['courses'] as $c) {
            $expires = is_string($c['expires_at']) ? CarbonImmutable::parse($c['expires_at'])->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') : 'vĩnh viễn';
            $lines[] = sprintf('Khóa %s: hoàn thành %d/%d bài bắt buộc, %s, hạn %s.', $c['course'], $c['completed'], $c['required'], $c['validity'], $expires);
            foreach ($c['quizzes'] as $q) {
                $lines[] = sprintf('  Quiz "%s": %s', $q['title'], $q['best_percent'] === null ? 'chưa có điểm' : $q['best_percent'].'%');
            }
        }
        foreach ($report['exams'] as $e) {
            $lines[] = $e['published']
                ? sprintf('Thi thử "%s": %s%%%s.', $e['title'], $e['score_percent'] ?? '—', $e['rank'] !== null ? ', hạng '.$e['rank'] : '')
                : sprintf('Thi thử "%s": đã nộp, chờ công bố.', $e['title']);
        }

        return $lines;
    }
}
