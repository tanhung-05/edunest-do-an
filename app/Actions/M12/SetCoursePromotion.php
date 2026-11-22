<?php

namespace App\Actions\M12;

use App\Models\M03\Course;
use App\Models\M12\CoursePromotion;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M12\Commerce;
use App\Support\Tx;

/** QT-13 b1–2, A11, A12 — KM-1. Khóa `users` (người thao tác) → `courses`. */
final class SetCoursePromotion
{
    /** @param array{price: int, starts_on: string, ends_on: string} $data */
    public function handle(User $actor, Course $course, array $data, ?CoursePromotion $promotion = null): CoursePromotion
    {
        return Tx::run(function () use ($actor, $course, $data, $promotion) {
            $locked = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            OrderLocks::academic($locked);
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($promotion !== null && $promotion->course_id !== $course->id) {
                throw new DomainRejection('Đợt khuyến mãi không thuộc khóa này.', 'general', 404);
            }
            if ($data['price'] >= $course->price) {
                throw new DomainRejection('Giá khuyến mãi phải nhỏ hơn giá bán '.Commerce::vnd($course->price).'.', 'price');
            }
            if ($data['ends_on'] <= $data['starts_on']) {
                throw new DomainRejection('Ngày kết thúc phải sau ngày bắt đầu.', 'ends_on');
            }
            $startsAt = Commerce::vnDayStart($data['starts_on']);
            $endsAt = Commerce::vnDayEndExclusive($data['ends_on']);
            $overlap = CoursePromotion::query()->where('course_id', $course->id)
                ->when($promotion !== null, fn ($q) => $q->whereKeyNot($promotion->id))
                ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
            if ($overlap) {
                throw new DomainRejection('Khoảng ngày trùng với một đợt khuyến mãi khác của khóa.', 'starts_on');
            }

            $old = $promotion?->only(['price', 'starts_on', 'ends_on']);
            $promotion ??= new CoursePromotion(['course_id' => $course->id, 'created_by_id' => $actor->id]);
            $promotion->fill([
                'price' => $data['price'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'updated_by_id' => $actor->id,
            ])->save();
            Audit::log('COURSE_PROMOTION_SET', $actor, $course, $old === null ? null : array_map('strval', $old),
                ['promotion_id' => $promotion->id, 'price' => $data['price'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on']]);

            return $promotion;
        });
    }
}
