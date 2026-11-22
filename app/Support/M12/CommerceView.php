<?php

namespace App\Support\M12;

use App\Enums\M03\SaleStatus;
use App\Models\M03\Course;
use App\Models\M12\CartItem;
use App\Models\M12\Wishlist;
use App\Models\User;
use App\Support\M03\CourseCoverUrl;

/** Dữ liệu hiển thị giá/khuyến mãi/giỏ cho giao diện P5 (MH-KH-03, MH-HV-11/12). */
final class CommerceView
{
    /** @return array<string, mixed> */
    public static function pricing(Course $course): array
    {
        [$price, $promotion] = Commerce::effectivePrice($course);

        return [
            'price' => $price,
            'list_price' => $course->price,
            'promotion' => $promotion === null ? null : [
                'id' => $promotion->id,
                'price' => $promotion->price,
                // BR-TT-62, CR-121: đếm ngược tới 00:00 giờ Việt Nam ngày kế ngày cuối.
                'ends_at' => $promotion->ends_at->toIso8601String(),
                'ends_on' => $promotion->ends_on->format('Y-m-d'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function courseCard(Course $course): array
    {
        return [
            'id' => $course->id,
            'slug' => $course->slug,
            'title' => $course->title,
            'cover' => CourseCoverUrl::for($course, $course->category?->name),
            'teacher' => $course->currentAuthor?->user?->name,
            'selling' => $course->sale_status === SaleStatus::DangBan,
            'rating_avg' => $course->rating_avg,
            'rating_count' => $course->rating_count,
            ...self::pricing($course),
        ];
    }

    /** @return array{in_cart: bool, wished: bool, cart_count: int} */
    public static function state(?User $user, Course $course): array
    {
        if ($user === null) {
            return ['in_cart' => false, 'wished' => false, 'cart_count' => 0];
        }
        $items = CartItem::query()->whereHas('cart', fn ($q) => $q->where('user_id', $user->id));

        return [
            'in_cart' => (clone $items)->where('course_id', $course->id)->exists(),
            'wished' => Wishlist::query()->where('user_id', $user->id)->where('course_id', $course->id)->exists(),
            'cart_count' => $items->count(),
        ];
    }
}
