<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M07\AcceptLatePayment;
use App\Actions\M07\ExpireOrder;
use App\Actions\M07\MarkPaymentRefunded;
use App\Actions\M08\RegrantOrderItem;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\M07\Order;
use App\Models\M07\OrderItem;
use App\Models\M07\Payment;
use App\Models\User;
use App\Queries\M07\OrderView;
use App\Queries\M07\WorkQueues;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewForAcademic', Order::class);
        $term = trim($request->string('q')->limit(200, '')->toString());
        $status = OrderStatus::tryFrom($request->string('status')->toString());
        $all = Order::query()->with(['user', 'items'])->latest('id');
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $all->where(fn ($q) => $q->where('public_code', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
        }
        if ($status !== null) {
            $all->where('status', $status);
        }

        $row = fn (Order $order) => OrderView::row($order, true);

        return Inertia::render('academic/orders/index', [
            'orders' => $all->paginate(20)->withQueryString()->through($row),
            'needsAttention' => WorkQueues::orders()->paginate(20, ['*'], 'attention_page')->withQueryString()->through($row),
            'refunds' => WorkQueues::refunds()->paginate(20, ['*'], 'refund_page')->withQueryString()
                ->through(fn (Payment $payment) => OrderView::payment($payment, $payment->order)),
            'filters' => ['q' => $term, 'status' => $status?->value],
            'statuses' => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], OrderStatus::cases()),
        ]);
    }

    public function show(Order $order, ExpireOrder $expire): Response
    {
        Gate::authorize('viewForAcademic', Order::class);
        Gate::authorize('viewAudit', $order);
        if ($order->status === OrderStatus::ChoThanhToan && now()->gt($order->expires_at)) {
            $order = $expire->handle($order->id);
        }

        return Inertia::render('academic/orders/show', ['order' => OrderView::detail($order, true), 'audit' => OrderView::audit($order)]);
    }

    public function regrant(Request $request, Order $order, OrderItem $item, RegrantOrderItem $regrant): RedirectResponse
    {
        Gate::authorize('regrant', $item);
        $updated = $regrant->handle($this->actor($request), $order, $item);
        $response = to_route('academic.orders.show', $order);

        return $updated->status === OrderItemStatus::DaCap ? $response : $response->withErrors(['general' => 'Cấp lại chưa thành công.']);
    }

    public function acceptLate(Request $request, Order $order, Payment $payment, AcceptLatePayment $accept): RedirectResponse
    {
        Gate::authorize('acceptLate', $payment);
        $accept->handle($this->actor($request), $order, $payment);

        return to_route('academic.orders.show', $order);
    }

    public function markRefunded(Request $request, Order $order, Payment $payment, MarkPaymentRefunded $refund): RedirectResponse
    {
        Gate::authorize('markRefunded', $payment);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500'], 'confirmed' => ['nullable', 'boolean']]);
        $refund->handle($this->actor($request), $order, $payment, (string) ($data['reason'] ?? ''), (bool) ($data['confirmed'] ?? false));

        return to_route('academic.orders.show', $order);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
