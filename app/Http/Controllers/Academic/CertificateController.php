<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M10\RequestCertificatePdfRetry;
use App\Actions\M10\RevokeCertificate;
use App\Enums\M10\CertificateStatus;
use App\Models\M10\Certificate;
use App\Models\User;
use App\Policies\M10\CertificatePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CertificateController
{
    public function index(Request $request, CertificatePolicy $policy): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $policy->revoke($actor), 403);
        $search = trim($request->string('q')->toString());
        $rows = Certificate::query()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
            ->where('certificate_number', 'like', '%'.$search.'%')->orWhere('holder_name', 'like', '%'.$search.'%')))
            ->latest('id')->paginate(30)->withQueryString()->through(fn (Certificate $certificate) => [
                'number' => $certificate->certificate_number, 'holder' => $certificate->holder_name, 'course' => $certificate->course_title,
                'issued_on' => $certificate->issued_on->toDateString(), 'status' => $certificate->status->label(),
                'pdf_status' => $certificate->pdf_status->label(),
                'revoke_url' => $certificate->status === CertificateStatus::HieuLuc ? route('academic.certificates.revoke', $certificate) : null,
                'retry_url' => $policy->regeneratePdf($actor, $certificate) ? route('academic.certificates.regenerate-pdf', $certificate) : null,
            ]);

        return Inertia::render('academic/certificates/index', ['certificates' => $rows, 'q' => $search]);
    }

    public function revoke(Request $request, Certificate $certificate, RevokeCertificate $revoke): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $revoke->handle($actor, $certificate, (string) $data['reason']);

        return back()->with('status', 'Đã thu hồi chứng nhận.');
    }

    public function regeneratePdf(Request $request, Certificate $certificate, RequestCertificatePdfRetry $retry): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $retry->handle($actor, $certificate);

        return back()->with('status', 'Đã xếp yêu cầu tạo lại PDF.');
    }
}
