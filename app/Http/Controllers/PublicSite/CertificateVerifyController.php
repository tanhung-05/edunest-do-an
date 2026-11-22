<?php

namespace App\Http\Controllers\PublicSite;

use App\Models\M10\Certificate;
use App\Support\M10\CertificateVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CertificateVerifyController
{
    public function verify(Request $request): Response|RedirectResponse
    {
        if ($request->query->has('number')) {
            $number = strtoupper(trim($request->string('number')->toString()));
            abort_if($number === '', 404, 'Không tìm thấy chứng nhận.');

            return to_route('public.certificates.show', ['number' => $number]);
        }

        return Inertia::render('public/certificates/verify');
    }

    public function show(string $number): Response
    {
        $number = strtoupper(trim($number));
        abort_unless(preg_match('/^\d{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/D', $number) === 1, 404, 'Không tìm thấy chứng nhận.');
        $certificate = Certificate::query()->where('certificate_number', $number)->first();
        abort_if($certificate === null, 404, 'Không tìm thấy chứng nhận.');

        return Inertia::render('public/certificates/show', [
            'certificate' => [
                'holder_name' => $certificate->holder_name,
                'course_title' => $certificate->course_title,
                'issued_on' => $certificate->issued_on->toDateString(),
                'number' => $certificate->certificate_number,
                'status' => $certificate->status->label(),
            ],
            'qr_svg' => CertificateVerification::qr($certificate),
        ]);
    }
}
