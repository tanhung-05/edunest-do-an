<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\M28\BankTransferReview;
use App\Models\User;
use App\Support\Audit;
use App\Support\M07\OrderLocks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** QT-30 A4, CR-179: Giáo vụ đọc 5 trường của bản X4 đang chờ tra soát; mỗi lần xem ghi nhật ký truy cập, 0 thay đổi. */
final class BankReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        OrderLocks::academic($user);
        $reviews = BankTransferReview::query()->where('status', 'CHO_TRA_SOAT')->orderByDesc('id')->limit(100)->get();
        foreach ($reviews as $review) {
            Audit::log('BANK_REVIEW_VIEWED', $user, $review);
        }

        return Inertia::render('academic/bank-reviews/index', [
            'reviews' => $reviews->map(fn (BankTransferReview $r) => [
                'id' => $r->id, 'bank_code' => $r->bank_code, 'received_at' => $r->received_at->toIso8601String(),
                'amount' => $r->amount, 'memo' => $r->memo_masked, 'reason' => $r->reason,
            ])->values(),
        ]);
    }
}
