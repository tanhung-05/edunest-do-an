<?php

namespace App\Support\M10;

use App\Models\M10\Certificate;

interface CertificatePdfRenderer
{
    public function render(Certificate $certificate): string;
}
