<?php

namespace App\Queries\M19;

use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentReconcileStatus;
use App\Enums\M07\PaymentTxnStatus;
use App\Enums\M12\CouponSlotStatus;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M07\Payment;
use App\Models\M08\Enrollment;
use App\Models\M12\CouponRedemption;
use App\Models\M13\ActivationCode;
use App\Models\M19\CoursePageView;
use App\Support\M10\QuizScore;
use Brick\Math\BigRational;
use Carbon\CarbonImmutable;

/**
 * QT-20 (MH-GVU-01 mở rộng): số liệu theo khoảng ngày giờ VN, tính trực tiếp (khác báo cáo đêm P4).
 * Doanh thu theo BR-BC-04 (khoản `HOP_LE`, ngày thanh toán thành công); phễu theo CR-113/148; không lộ từng câu quiz (BR-BC-23).
 */
final class AcademicDashboard
{
    public const TZ = 'Asia/Ho_Chi_Minh';

    public const MAX_DAYS = 366;

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} khoảng ngày VN hợp lệ (mặc định 30 ngày gần nhất) */
    public static function range(?string $from, ?string $to): array
    {
        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        $parse = function (?string $value): ?CarbonImmutable {
            if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return null;
            }
            $d = CarbonImmutable::createFromFormat('!Y-m-d', $value, self::TZ);

            return $d instanceof CarbonImmutable && $d->format('Y-m-d') === $value ? $d : null;
        };
        $end = $parse($to) ?? $today;
        $start = $parse($from) ?? $end->subDays(29);
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }
        if ($start->diffInDays($end) >= self::MAX_DAYS) {
            $start = $end->subDays(self::MAX_DAYS - 1);
        }

        return [$start, $end];
    }

    /** @return array<string, mixed> */
    public static function build(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $from = $start->startOfDay()->utc();
        $to = $end->addDay()->startOfDay()->utc();
        $byMonth = $start->diffInDays($end) > 62;
        $bucket = fn (CarbonImmutable $at) => $at->setTimezone(self::TZ)->format($byMonth ? 'Y-m' : 'Y-m-d');

        $series = [];
        for ($d = $start; $d->lte($end); $d = $byMonth ? $d->startOfMonth()->addMonth() : $d->addDay()) {
            $series[$d->format($byMonth ? 'Y-m' : 'Y-m-d')] = 0;
        }
        $payments = Payment::query()->where('txn_status', PaymentTxnStatus::ThanhCong)->where('reconcile_status', PaymentReconcileStatus::HopLe)
            ->where('succeeded_at', '>=', $from)->where('succeeded_at', '<', $to);
        foreach ($payments->lazyById() as $payment) {
            if ($payment->succeeded_at !== null) {
                $key = $bucket(CarbonImmutable::instance($payment->succeeded_at));
                $series[$key] = ($series[$key] ?? 0) + (int) ($payment->received_amount ?? 0);
            }
        }

        // Phễu (CR-113/148): xem → đơn mua lần đầu HOAN_TAT (đếm đơn) → bắt đầu học → hoàn thành, cùng khoảng ngày.
        $views = CoursePageView::query()->whereBetween('view_date', [$start->toDateString(), $end->toDateString()])->count();
        $firstOrders = Order::query()->where('status', OrderStatus::HoanTat)->where('first_completed_at', '>=', $from)->where('first_completed_at', '<', $to)
            ->whereHas('items', fn ($q) => $q->where('kind', OrderItemKind::MuaKhoa));
        $orders = (clone $firstOrders)->count();
        $starts = Enrollment::query()->where('started_at', '>=', $from)->where('started_at', '<', $to)->count();
        $completions = Enrollment::query()->where('completed_at', '>=', $from)->where('completed_at', '<', $to)->count();

        $top = OrderItem::query()->where('kind', OrderItemKind::MuaKhoa)->whereIn('order_id', (clone $firstOrders)->select('id'))
            ->groupBy('course_id', 'course_title')->selectRaw('course_id, course_title, COUNT(*) AS sold, SUM(final_amount) AS amount')
            ->orderByDesc('sold')->orderByDesc('amount')->limit(5)->toBase()->get()
            ->map(fn ($r) => ['course_id' => (int) $r->course_id, 'title' => (string) $r->course_title, 'sold' => (int) $r->sold, 'amount' => (int) $r->amount])->all();

        // CR-216: tỷ lệ gia hạn = dòng GIA_HAN đã cấp trong kỳ / (dòng đó + ghi danh chưa thu hồi có hạn kết thúc rơi vào kỳ).
        $renewed = OrderItem::query()->where('kind', OrderItemKind::GiaHan)->where('status', OrderItemStatus::DaCap)
            ->where('granted_at', '>=', $from)->where('granted_at', '<', $to)->count();
        $expiring = Enrollment::query()->whereNull('revoked_at')->where('het_han_luc', '>=', $from)->where('het_han_luc', '<', $to)->count();
        $due = $renewed + $expiring;

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'granularity' => $byMonth ? 'month' : 'day',
            'revenue' => ['total' => array_sum($series), 'label' => 'Mô phỏng', 'series' => array_map(fn ($k, $v) => ['key' => (string) $k, 'amount' => $v], array_keys($series), $series)],
            'funnel' => [
                'views' => $views, 'orders' => $orders, 'starts' => $starts, 'completions' => $completions,
                'conversion' => $views === 0 ? null : QuizScore::display(BigRational::of($orders)->dividedBy($views)),
            ],
            'top_courses' => $top,
            'coupons_used' => CouponRedemption::query()->where('status', CouponSlotStatus::DaDung)->where('used_at', '>=', $from)->where('used_at', '<', $to)->count(),
            'codes_redeemed' => ActivationCode::query()->where('redeemed_at', '>=', $from)->where('redeemed_at', '<', $to)->count(),
            'renewal' => ['renewed' => $renewed, 'due' => $due, 'rate' => $due === 0 ? null : QuizScore::display(BigRational::of($renewed)->dividedBy($due))],
            'orders_cxl' => Order::query()->where('status', OrderStatus::CanXuLy)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<list<string|int|float|null>>>
     */
    public static function sheets(array $data): array
    {
        $f = $data['funnel'];
        $revenue = [['Kỳ', 'Doanh thu (đ, mô phỏng)']];
        foreach ($data['revenue']['series'] as $row) {
            $revenue[] = [$row['key'], $row['amount']];
        }
        $top = [['Khóa', 'Số đơn', 'Doanh số (đ)']];
        foreach ($data['top_courses'] as $row) {
            $top[] = [$row['title'], $row['sold'], $row['amount']];
        }

        return [
            'Tổng quan' => [
                ['Chỉ số', 'Giá trị'], ['Từ ngày', $data['from']], ['Đến ngày', $data['to']],
                ['Doanh thu (đ, mô phỏng)', $data['revenue']['total']],
                ['Lượt xem trang khóa', $f['views']], ['Đơn mua lần đầu hoàn tất', $f['orders']], ['Tỷ lệ chuyển đổi (%)', $f['conversion']],
                ['Bắt đầu học', $f['starts']], ['Hoàn thành', $f['completions']],
                ['Mã giảm giá đã dùng', $data['coupons_used']], ['Mã kích hoạt đã đổi', $data['codes_redeemed']],
                ['Gia hạn', $data['renewal']['renewed']], ['Tỷ lệ gia hạn (%)', $data['renewal']['rate']],
            ],
            'Doanh thu' => $revenue,
            'Khóa bán chạy' => $top,
        ];
    }
}
