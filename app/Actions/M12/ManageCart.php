<?php

namespace App\Actions\M12;

use App\Enums\M03\SaleStatus;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use App\Models\M12\Cart;
use App\Models\M12\CartItem;
use App\Models\M12\Wishlist;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;

/** QT-13 b5–7, A2, A3, A7 (NH-01). Ghi dưới gác `users` của học viên. */
final class ManageCart
{
    public function add(User $actor, Course $course): Cart
    {
        return Tx::run(function () use ($actor, $course) {
            $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            OrderLocks::student($student);
            $course = Course::query()->whereKey($course->id)->sharedLock()->firstOrFail();
            if (Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->exists()) {
                throw new DomainRejection('Bạn đã sở hữu khóa này.', 'course');
            }
            if ($course->sale_status !== SaleStatus::DangBan || $course->current_version_id === null) {
                throw new DomainRejection('Khóa học hiện không bán.', 'course');
            }
            $cart = Cart::query()->firstOrCreate(['user_id' => $student->id]);
            if ($cart->items()->where('course_id', $course->id)->exists()) {
                throw new DomainRejection('Khóa đã có trong giỏ.', 'course');
            }
            $cart->items()->create(['course_id' => $course->id, 'added_at' => now()]);

            return $cart;
        });
    }

    public function remove(User $actor, Course $course): void
    {
        Tx::run(function () use ($actor, $course) {
            $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            OrderLocks::student($student);
            $cart = Cart::query()->where('user_id', $student->id)->first();
            if ($cart !== null) {
                CartItem::query()->where('cart_id', $cart->id)->where('course_id', $course->id)->delete();
            }
        });
    }

    /** A7: thêm hoặc bỏ yêu thích; trả trạng thái sau thao tác. */
    public function toggleWishlist(User $actor, Course $course): bool
    {
        return Tx::run(function () use ($actor, $course) {
            $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            OrderLocks::student($student);
            $existing = Wishlist::query()->where('user_id', $student->id)->where('course_id', $course->id)->first();
            if ($existing !== null) {
                $existing->delete();

                return false;
            }
            Wishlist::query()->create(['user_id' => $student->id, 'course_id' => $course->id, 'created_at' => now()]);

            return true;
        });
    }
}
