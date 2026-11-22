<?php

namespace App\Http\Controllers\Webhook;

use App\Actions\M07\ReconcilePayment;
use App\Enums\M07\PaymentEventOutcome;
use App\Http\Controllers\Controller;
use App\Support\DomainRejection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentSimulationController extends Controller
{
    public function __invoke(Request $request, ReconcilePayment $reconcile): JsonResponse
    {
        $payload = $request->getContent();
        if (strlen($payload) > 4096) {
            return response()->json(['message' => 'Nội dung xác nhận quá giới hạn.'], 413);
        }

        try {
            $event = $reconcile->handle($payload, $request->header('X-Payment-Signature'));
        } catch (DomainRejection $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $status = match ($event->outcome) {
            PaymentEventOutcome::TuChoiChuKy => 403,
            PaymentEventOutcome::TuChoiMaLa => 404,
            default => 200,
        };

        return response()->json(['outcome' => $event->outcome->value], $status);
    }
}
