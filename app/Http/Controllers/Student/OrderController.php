<?php

namespace App\Http\Controllers\Student;

use App\Actions\M07\CancelOrder;
use App\Actions\M07\ExpireOrder;
use App\Actions\M07\PlaceOrder;
use App\Actions\M07\StartPayment;
use App\Actions\M28\BankTransfers;
use App\Enums\M03\SaleStatus;
use App\Enums\M07\OrderStatus;
use App\Enums\M07\PaymentChannel;
use App\Http\Controllers\Controller;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Queries\M07\OpenOrders;
use App\Queries\M07\OrderView;
use App\Support\M06\PublicLessonAccess;
use App\Support\M07\MoMoGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class OrderController extends Controller
{
    public function index(Request $request, ExpireOrder $expire): Response
    {
        $user = $this->actor($request);
        Gate::authorize('viewAny', Order::class);
        Order::query()->where('user_id', $user->id)->where('status', OrderStatus::ChoThanhToan)
            ->where('expires_at', '<', now())->eachById(fn (Order $order) => $expire->handle($order->id));
        $orders = Order::query()->where('user_id', $user->id)->with('items')->latest('id')->paginate(20);

        // P10: số tóm tắt cho dải đầu trang (toàn bộ đơn của học viên, không chỉ trang hiện tại).
        $counts = Order::query()->where('user_id', $user->id)->selectRaw('status, count(*) as n, sum(total) as amount')->groupBy('status')->get()->keyBy('status');
        $count = fn (OrderStatus ...$st) => (int) collect($st)->sum(fn (OrderStatus $x) => $counts->get($x->value)->n ?? 0);

        return Inertia::render('student/orders/index', [
            'orders' => $orders->through(fn (Order $order) => OrderView::row($order)),
            'summary' => [
                'total' => (int) $counts->sum('n'),
                'done' => $count(OrderStatus::HoanTat),
                'open' => $count(OrderStatus::ChoThanhToan, OrderStatus::DangCapQuyen, OrderStatus::CanXuLy),
                'spent' => (int) ($counts->get(OrderStatus::HoanTat->value)->amount ?? 0),
            ],
        ]);
    }

    public function checkout(Request $request, string $slug): Response|RedirectResponse
    {
        $course = PublicLessonAccess::course($slug);
        Gate::authorize('create', [Order::class, $course]);
        $user = $this->actor($request);
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->first();
        if ($enrollment !== null) {
            return to_route('student.courses.index', ['course' => $course->id]);
        }
        $open = OpenOrders::current($user->id, [$course->id]);
        if ($open !== null) {
            return to_route('student.orders.show', $open);
        }

        return Inertia::render('student/orders/checkout', [
            'course' => [
                'slug' => $course->slug, 'title' => $course->title,
                'price' => $course->price, 'access_months' => $course->access_months,
            ],
            'selling' => $course->sale_status === SaleStatus::DangBan,
            // QT-26 bước 15a: nguồn giới thiệu lấy từ tham số của trang dẫn tới thao tác đặt.
            'ref' => is_string($request->query('ref')) ? mb_substr($request->query('ref'), 0, 32) : null,
        ]);
    }

    public function store(Request $request, string $slug, PlaceOrder $place): RedirectResponse
    {
        $course = PublicLessonAccess::course($slug);
        Gate::authorize('create', [Order::class, $course]);
        $result = $place->handle($this->actor($request), $course, is_string($request->input('ref')) ? mb_substr($request->input('ref'), 0, 32) : null);

        return $result instanceof Order
            ? to_route('student.orders.show', $result)
            : to_route('student.courses.index', ['course' => $result->course_id]);
    }

    public function show(Order $order, ExpireOrder $expire): Response
    {
        Gate::authorize('viewForStudent', $order);
        if ($order->status === OrderStatus::ChoThanhToan && now()->gt($order->expires_at)) {
            $order = $expire->handle($order->id);
        }

        return Inertia::render('student/orders/show', [
            'order' => OrderView::detail($order),
            // P8 M28 (QT-30): QR chuyển khoản khi đã đồng ý điều khoản; nút mô phỏng ngân hàng ở môi trường demo.
            'transfer' => BankTransfers::view($order),
            'transfer_simulator' => config()->boolean('services.payment_sim.demo') || app()->environment(['local', 'testing']),
            'momo_enabled' => MoMoGateway::enabled(),
        ]);
    }

    public function pay(Request $request, Order $order, StartPayment $start): SymfonyResponse
    {
        Gate::authorize('pay', $order);
        // P10 (CR-260): chọn MoMo bản thử khi đã cấu hình khóa; mặc định vẫn là cổng mô phỏng (D-09).
        $momo = $request->input('gateway') === PaymentChannel::MoMo->value && MoMoGateway::enabled();
        $payment = $start->handle($this->actor($request), $order, $request->boolean('terms'), $momo ? PaymentChannel::MoMo : PaymentChannel::MoPhong);
        if ($momo) {
            return Inertia::location(MoMoGateway::payUrl($payment));
        }

        return to_route('gateway.simulated.show', $payment);
    }

    public function cancel(Request $request, Order $order, CancelOrder $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $order);
        $cancel->handle($this->actor($request), $order);

        return to_route('student.orders.show', $order);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
