<?php

namespace App\Http\Controllers\Student;

use App\Enums\M10\CertificatePdfStatus;
use App\Http\Controllers\Controller;
use App\Models\M10\Certificate;
use App\Models\User;
use App\Policies\M10\CertificatePolicy;
use App\Support\M09\LearningFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CertificateController extends Controller
{
    public function index(Request $request, CertificatePolicy $policy): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_if($request->has('user') && $request->integer('user') !== $user->id, 403);

        return Inertia::render('student/certificates/index', [
            'certificates' => Certificate::query()->whereHas('enrollment', fn ($q) => $q->where('user_id', $user->id))
                ->with('enrollment')->orderByDesc('id')->get()->map(fn (Certificate $certificate) => [
                    'number' => $certificate->certificate_number,
                    'course' => $certificate->course_title,
                    'issued_on' => $certificate->issued_on->toDateString(),
                    'status' => $certificate->status->label(),
                    'pdf_status' => $certificate->pdf_status->label(),
                    'download_url' => $policy->download($user, $certificate) && Storage::disk('local')->exists((string) $certificate->pdf_path)
                        ? LearningFile::signed($request, 'student.certificates.download', ['certificate' => $certificate->id]) : null,
                ]),
        ]);
    }

    public function download(Request $request, Certificate $certificate, CertificatePolicy $policy): BinaryFileResponse
    {
        LearningFile::verify($request);
        $certificate->refresh();
        $user = $request->user();
        abort_unless($user instanceof User && $policy->view($user, $certificate), 403);
        abort_if($certificate->revoked_at !== null, 403, 'Chứng nhận đã bị thu hồi.');
        $disk = Storage::disk('local');
        $path = $certificate->pdf_path;
        abort_if($certificate->pdf_status !== CertificatePdfStatus::DaTao || $path === null || ! $disk->exists($path), 404, 'Chưa có PDF.');
        abort_unless($policy->download($user, $certificate), 403);

        return response()->download($disk->path($path), $certificate->certificate_number.'.pdf', ['Cache-Control' => 'private, no-store', 'Content-Type' => 'application/pdf']);
    }
}
