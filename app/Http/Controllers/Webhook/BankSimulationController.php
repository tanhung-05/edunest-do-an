<?php

namespace App\Http\Controllers\Webhook;

use App\Actions\M28\BankTransfers;
use App\Http\Controllers\Controller;
use App\Support\DomainRejection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** QT-30: điểm vào của bộ mô phỏng ngân hàng — giao dịch nguồn (bước 3a–3b) và xác nhận có chữ ký HMAC (bước 4–6). */
final class BankSimulationController extends Controller
{
    public function transaction(Request $request, BankTransfers $transfers): JsonResponse
    {
        return $this->guard($request, fn (string $payload) => response()->json(
            $transfers->recordSource($payload, $request->header('X-Bank-Signature'))));
    }

    public function confirmation(Request $request, BankTransfers $transfers): JsonResponse
    {
        return $this->guard($request, function (string $payload) use ($request, $transfers) {
            $result = $transfers->confirm($payload, $request->header('X-Bank-Signature'));

            return response()->json(['outcome' => $result['outcome']], $result['http']);
        });
    }

    /** @param  callable(string): JsonResponse  $work */
    private function guard(Request $request, callable $work): JsonResponse
    {
        $payload = $request->getContent();
        if (strlen($payload) > 4096) {
            return response()->json(['message' => 'Nội dung quá giới hạn.'], 413);
        }
        try {
            return $work($payload);
        } catch (DomainRejection $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }
    }
}
