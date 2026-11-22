<?php

namespace App\Support\M10;

use App\Models\M10\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;

/** CR-209: chứng nhận khổ A4 ngang, phông DejaVu có dấu tiếng Việt, mã QR dẫn tới trang xác minh công khai. */
final class DompdfCertificatePdfRenderer implements CertificatePdfRenderer
{
    public function render(Certificate $certificate): string
    {
        return Pdf::loadView('pdf.certificate', [
            'holder' => $certificate->holder_name,
            'course' => $certificate->course_title,
            'issuedOn' => $certificate->issued_on->format('d/m/Y'),
            'number' => $certificate->certificate_number,
            'url' => CertificateVerification::url($certificate),
            'qr' => base64_encode(CertificateVerification::qr($certificate)),
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->output();
    }
}
