<?php

namespace App\Http\Controllers\Gateway;

use App\Actions\M07\ResendPaymentConfirmation;
use App\Actions\M07\SendSimulatedPaymentConfirmation;
use App\Enums\M07\PaymentEventResult;
use App\Enums\M07\PaymentTxnStatus;
use App\Http\Controllers\Controller;
use App\Models\M07\Payment;
use App\Models\User;
use App\Queries\M07\OrderView;
use App\Support\M07\PaymentConfirmation;
use App\Support\M07\SimulatedConfirmations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class SimulatedPaymentController extends Controller
{
    public function show(Payment $payment): Response
    {
        Gate::authorize('viewSimulatedGateway', $payment);
        $original = SimulatedConfirmations::original($payment);

        return Inertia::render('gateway/simulated', [
            'payment' => OrderView::payment($payment, $payment->order),
            'selectedResult' => $original === null ? null : PaymentConfirmation::parse($original)->result->label(),
            'canConfirm' => $original === null && $payment->txn_status === PaymentTxnStatus::KhoiTao,
        ]);
    }

    public function confirm(Request $request, Payment $payment, SendSimulatedPaymentConfirmation $send): RedirectResponse
    {
        Gate::authorize('sendSimulatedConfirmation', $payment);
        $request->validate(['result' => ['required', Rule::enum(PaymentEventResult::class)]]);
        /** @var User $user */
        $user = $request->user();
        $send->handle($user, $payment, PaymentEventResult::from($request->string('result')->toString()));

        return to_route('gateway.simulated.show', $payment);
    }

    public function resend(Request $request, Payment $payment, ResendPaymentConfirmation $resend): RedirectResponse
    {
        Gate::authorize('resendConfirmation', $payment);
        /** @var User $user */
        $user = $request->user();
        $resend->handle($user, $payment);

        return to_route('gateway.simulated.show', $payment);
    }
}
