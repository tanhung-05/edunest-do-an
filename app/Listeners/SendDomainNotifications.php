<?php

namespace App\Listeners;

use App\Enums\M05\ReviewResult;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M15\NotificationKind;
use App\Events\M05\CourseVersionPublished;
use App\Events\M05\CourseVersionReviewed;
use App\Events\M07\OrderCompleted;
use App\Events\M07\OrderNeedsAttention;
use App\Events\M10\EnrollmentCompleted;
use App\Jobs\M07\GenerateInvoice;
use App\Models\M03\CourseAuthor;
use App\Models\M05\CourseVersion;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Support\M15\Notifier;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/** TV-11 (M15): nối sự kiện nghiệp vụ với chuông/email. Mọi việc chạy sau commit, khóa chống trùng theo đối tượng. */
final class SendDomainNotifications
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderCompleted::class, [self::class, 'orderCompleted']);
        $events->listen(OrderNeedsAttention::class, [self::class, 'orderNeedsAttention']);
        $events->listen(CourseVersionReviewed::class, [self::class, 'versionReviewed']);
        $events->listen(CourseVersionPublished::class, [self::class, 'versionPublished']);
        $events->listen(EnrollmentCompleted::class, [self::class, 'enrollmentCompleted']);
    }

    public function orderCompleted(OrderCompleted $event): void
    {
        $orderId = $event->order->id;
        DB::afterCommit(function () use ($orderId) {
            $order = Order::query()->with('items', 'user')->find($orderId);
            if ($order === null) {
                return;
            }
            GenerateInvoice::dispatch($order->id);
            $titles = $order->items->map(fn (OrderItem $i) => $i->course_title)->implode(', ');
            Notifier::send($order->user, NotificationKind::MuaThanhCong, 'Thanh toán thành công',
                'Đơn '.$order->public_code.' đã hoàn tất: '.$titles.'.', route('student.orders.show', $order), 'order-done:'.$order->id);
        });
    }

    public function orderNeedsAttention(OrderNeedsAttention $event): void
    {
        $orderId = $event->order->id;
        DB::afterCommit(function () use ($orderId) {
            $order = Order::query()->with('items', 'user')->find($orderId);
            if ($order === null) {
                return;
            }
            foreach ($order->items->where('status', OrderItemStatus::CapLoi) as $item) {
                /** @var OrderItem $item */
                Notifier::send($order->user, NotificationKind::CapQuyenLoi, 'Cấp quyền đang gặp lỗi',
                    'Khóa "'.$item->course_title.'" của đơn '.$order->public_code.' chưa cấp được quyền học. Giáo vụ đang xử lý; bạn không phải thanh toán lại.',
                    route('student.orders.show', $order), 'grant-fail:'.$item->id.':'.$item->grant_attempts);
                Notifier::academics(NotificationKind::CapQuyenLoi, 'Đơn cần xử lý: cấp quyền lỗi',
                    'Đơn '.$order->public_code.' có dòng "'.$item->course_title.'" cấp lỗi.', route('academic.orders.show', $order),
                    'grant-fail-staff:'.$item->id.':'.$item->grant_attempts);
            }
        });
    }

    public function versionReviewed(CourseVersionReviewed $event): void
    {
        DB::afterCommit(function () use ($event) {
            $version = CourseVersion::query()->with('course')->find($event->versionId);
            $author = $version === null ? null : CourseAuthor::query()->where('course_id', $version->course_id)->whereNotNull('current_key')->with('user')->first();
            if ($version === null || $author?->user === null) {
                return;
            }
            $returned = $event->result === ReviewResult::TraLai;
            Notifier::send($author->user, NotificationKind::KetQuaDuyet, $returned ? 'Phiên bản bị trả lại' : 'Phiên bản đã được duyệt',
                'Khóa "'.$version->course->title.'" bản '.$version->version_no.($returned ? ' bị trả lại, xem nhận xét để sửa.' : ' đã được duyệt.'),
                route('teacher.courses.show', $version->course_id), 'review:'.$version->id.':'.$event->result->value.':'.now()->getTimestamp());
        });
    }

    public function versionPublished(CourseVersionPublished $event): void
    {
        DB::afterCommit(function () use ($event) {
            $version = CourseVersion::query()->with('course')->find($event->versionId);
            if ($version === null) {
                return;
            }
            Enrollment::query()->where('course_id', $version->course_id)->where('course_version_id', '<>', $version->id)
                ->whereNull('revoked_at')->with('user')->each(function (Enrollment $e) use ($version) {
                    if ($e->user instanceof User) {
                        Notifier::send($e->user, NotificationKind::PhienBanMoi, 'Khóa học có phiên bản mới',
                            'Khóa "'.$version->course->title.'" vừa công bố bản '.$version->version_no.'. Bạn vẫn học tiếp bản đã ghi danh.',
                            route('student.courses.index'), 'version:'.$version->id.':'.$e->user_id);
                    }
                });
        });
    }

    public function enrollmentCompleted(EnrollmentCompleted $event): void
    {
        DB::afterCommit(function () use ($event) {
            $enrollment = Enrollment::query()->with(['user', 'course'])->find($event->enrollmentId);
            if ($enrollment === null) {
                return;
            }
            Notifier::send($enrollment->user, NotificationKind::ChungNhan, 'Bạn đã nhận chứng nhận',
                'Chúc mừng bạn hoàn thành khóa "'.$enrollment->course->title.'". Chứng nhận đã sẵn sàng.',
                route('student.certificates.index'), 'cert:'.$enrollment->id);
        });
    }
}
