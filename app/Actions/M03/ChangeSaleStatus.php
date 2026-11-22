<?php

namespace App\Actions\M03;

use App\Enums\M03\SaleStatus;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\CourseLocks;
use App\Support\Tx;

/**
 * BAN-2..4 (03 §4): StartSelling `CHUA_BAN`→`DANG_BAN`, StopSelling `DANG_BAN`→`NGUNG_BAN`,
 * ResumeSelling `NGUNG_BAN`→`DANG_BAN`. Không áp E6 (CR-15). Khóa courses.
 */
final class ChangeSaleStatus
{
    public function start(User $actor, Course $course): Course
    {
        return $this->move($actor, $course, SaleStatus::ChuaBan, SaleStatus::DangBan, 'COURSE_SELL_START', true,
            'Chỉ bật bán được khóa chưa bán.');
    }

    public function stop(User $actor, Course $course): Course
    {
        return $this->move($actor, $course, SaleStatus::DangBan, SaleStatus::NgungBan, 'COURSE_SELL_STOP', false,
            'Chỉ ngừng bán được khóa đang bán.');
    }

    public function resume(User $actor, Course $course): Course
    {
        return $this->move($actor, $course, SaleStatus::NgungBan, SaleStatus::DangBan, 'COURSE_SELL_RESUME', true,
            'Chỉ bán lại được khóa đang ngừng bán.');
    }

    private function move(User $actor, Course $course, SaleStatus $from, SaleStatus $to, string $action, bool $needsPublished, string $wrongState): Course
    {
        return Tx::run(function () use ($actor, $course, $from, $to, $action, $needsPublished, $wrongState) {
            $course = CourseLocks::course($course->id);
            if ($course->sale_status !== $from) {
                throw new DomainRejection($wrongState, 'sale_status');
            }
            if ($needsPublished && ! CourseVersion::query()->where('course_id', $course->id)->whereNotNull('published_key')->exists()) {
                throw new DomainRejection('Khóa chưa có bản đã công bố.', 'sale_status');
            }
            $course->forceFill(['sale_status' => $to])->save();
            Audit::log($action, $actor, $course, ['sale_status' => $from->value], ['sale_status' => $to->value]);

            return $course;
        });
    }
}
