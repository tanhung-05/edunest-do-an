<?php

namespace App\Support\M10;

use App\Models\M10\Certificate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class CertificateVerification
{
    public static function url(Certificate $certificate): string
    {
        return route('public.certificates.show', ['number' => $certificate->certificate_number]);
    }

    public static function qr(Certificate $certificate): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(180), new SvgImageBackEnd)))
            ->writeString(self::url($certificate));
    }
}
