<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\M24\Affiliate;
use App\Models\M24\AffiliateCommission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** QT-26 A8: cộng tác viên chỉ xem đường dẫn và hoa hồng của chính mình. */
final class AffiliateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $affiliate = Affiliate::query()->where('user_id', $user->id)->first();
        abort_if($affiliate === null, 404);

        return Inertia::render('student/affiliate', [
            'affiliate' => ['code' => $affiliate->code, 'rate_bp' => $affiliate->rate_bp, 'is_active' => $affiliate->is_active,
                'link' => '?ref='.$affiliate->code],
            'commissions' => AffiliateCommission::query()->where('affiliate_id', $affiliate->id)->with('order:id,public_code')->latest('id')->get()
                ->map(fn (AffiliateCommission $c) => ['id' => $c->id, 'order' => $c->order?->public_code, 'base_amount' => $c->base_amount,
                    'amount' => $c->amount, 'status' => $c->status->value, 'status_label' => $c->status->label(),
                    'recorded_at' => $c->recorded_at->toISOString()])->values(),
        ]);
    }
}
