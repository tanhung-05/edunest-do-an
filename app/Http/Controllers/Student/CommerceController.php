<?php

namespace App\Http\Controllers\Student;

use App\Actions\M08\PlaceRenewalOrder;
use App\Actions\M12\ApplyCoupon;
use App\Actions\M12\ManageCart;
use App\Actions\M12\PlaceCartOrder;
use App\Actions\M13\RedeemActivationCode;
use App\Actions\M14\CourseRatings;
use App\Enums\M07\InvoicePdfStatus;
use App\Enums\M07\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M07\Invoice;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\M12\Cart;
use App\Models\M12\CartItem;
use App\Models\M12\Wishlist;
use App\Models\M13\ActivationAttemptWindow;
use App\Models\User;
use App\Support\M12\Commerce;
use App\Support\M12\CommerceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** MH-HV-11/12/13, nút Gia hạn (MH-HV-02), áp mã và hóa đơn (MH-HV-09), đánh giá (MH-KH-03). */
final class CommerceController extends Controller
{
    public function cart(Request $request): Response
    {
        $user = $this->actor($request);
        $cart = Cart::query()->where('user_id', $user->id)->first();
        $items = $cart === null ? collect() : CartItem::query()->where('cart_id', $cart->id)->with(['course.currentAuthor.user', 'course.category:id,name'])->orderBy('id')->get();
        // A2/AC-13-16: khóa đã sở hữu rời giỏ khi mở giỏ.
        $owned = Enrollment::query()->where('user_id', $user->id)->whereIn('course_id', $items->pluck('course_id'))->pluck('course_id')->all();
        if ($owned !== [] && $cart !== null) {
            CartItem::query()->where('cart_id', $cart->id)->whereIn('course_id', $owned)->delete();
            $items = $items->reject(fn (CartItem $i) => in_array($i->course_id, $owned, true));
        }
        Commerce::primePromotions($items->map(fn (CartItem $i) => $i->course)->filter());
        $lines = $items->map(fn (CartItem $i) => CommerceView::courseCard($i->course))->values();

        return Inertia::render('student/cart/index', [
            'items' => $lines,
            'subtotal' => (int) $lines->sum('price'),
            'removedOwned' => count($owned),
            'blocked' => $request->session()->get('cart.blocked', []),
            'excluded' => $request->session()->get('cart.excluded', []),
        ]);
    }

    public function addToCart(Request $request, Course $course, ManageCart $cart): RedirectResponse
    {
        $cart->add($this->actor($request), $course);

        return back()->with('status', 'Đã thêm "'.$course->title.'" vào giỏ.');
    }

    public function removeFromCart(Request $request, Course $course, ManageCart $cart): RedirectResponse
    {
        $cart->remove($this->actor($request), $course);

        return back()->with('status', 'Đã bỏ khóa khỏi giỏ.');
    }

    public function checkoutCart(Request $request, PlaceCartOrder $place): RedirectResponse
    {
        $result = $place->handle($this->actor($request));
        if ($result['order'] === null) {
            return to_route('student.cart.index')->with('cart.blocked', array_map(fn (Order $o) => [
                'code' => $o->public_code, 'status' => $o->status->label(), 'url' => route('student.orders.show', $o),
            ], $result['blocked']));
        }

        return to_route('student.orders.show', $result['order'])->with('status', $result['excluded'] === []
            ? 'Đã tạo đơn từ giỏ hàng.' : 'Đã tạo đơn. Một số khóa bị loại: '.implode(' ', $result['excluded']));
    }

    public function wishlist(Request $request): Response
    {
        $user = $this->actor($request);
        $rows = Wishlist::query()->where('user_id', $user->id)->with(['course.currentAuthor.user', 'course.category:id,name'])->latest('id')->get();

        Commerce::primePromotions($rows->map(fn (Wishlist $w) => $w->course)->filter());

        return Inertia::render('student/wishlist/index', [
            'items' => $rows->map(fn (Wishlist $w) => CommerceView::courseCard($w->course))->values(),
        ]);
    }

    public function toggleWishlist(Request $request, Course $course, ManageCart $cart): RedirectResponse
    {
        $on = $cart->toggleWishlist($this->actor($request), $course);

        return back()->with('status', $on ? 'Đã thêm vào khóa yêu thích.' : 'Đã bỏ khỏi khóa yêu thích.');
    }

    public function activation(Request $request): Response
    {
        $user = $this->actor($request);
        $window = ActivationAttemptWindow::query()->where('user_id', $user->id)->first();
        $locked = $window?->locked_until !== null && now()->lt($window->locked_until);

        return Inertia::render('student/activation/create', [
            'lockedUntil' => $locked ? $window->locked_until->toIso8601String() : null,
            'redeemed' => $request->session()->get('activation.redeemed'),
        ]);
    }

    public function redeem(Request $request, RedeemActivationCode $redeem): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $enrollment = $redeem->handle($this->actor($request), $data['code']);
        $course = $enrollment->course()->firstOrFail();

        return to_route('student.activation.create')->with('activation.redeemed', [
            'course' => $course->title, 'url' => route('student.courses.index', ['course' => $course->id]),
            'expires_at' => $enrollment->het_han_luc?->toIso8601String(),
        ]);
    }

    public function renew(Request $request, Enrollment $enrollment, PlaceRenewalOrder $place): RedirectResponse
    {
        $order = $place->handle($this->actor($request), $enrollment);

        return to_route('student.orders.show', $order);
    }

    public function applyCoupon(Request $request, Order $order, ApplyCoupon $apply): RedirectResponse
    {
        Gate::authorize('pay', $order);
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $order = $apply->handle($this->actor($request), $order, $data['code']);

        return to_route('student.orders.show', $order)->with('status', 'Đã áp mã, giảm '.Commerce::vnd($order->discount_total).'.');
    }

    public function invoice(Request $request, Order $order): StreamedResponse
    {
        Gate::authorize('viewForStudent', $order);
        $invoice = Invoice::query()->where('order_id', $order->id)->first();
        abort_if($order->first_completed_at === null || $invoice === null || $invoice->pdf_status !== InvoicePdfStatus::SanSang
            || $invoice->pdf_path === null || $order->status === OrderStatus::ChoThanhToan, 404, 'Hóa đơn chưa sẵn sàng.');

        return Storage::disk('local')->download($invoice->pdf_path, $invoice->invoice_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function rate(Request $request, Course $course, CourseRatings $ratings): RedirectResponse
    {
        $data = $request->validate([
            'stars' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        $ratings->submit($this->actor($request), $course, (int) $data['stars'], $data['comment'] ?? null);

        return back()->with('status', 'Đã lưu đánh giá của bạn.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
