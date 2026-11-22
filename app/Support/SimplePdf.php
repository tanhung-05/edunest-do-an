<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * PDF một trang chỉ có chữ (Helvetica, bỏ dấu). Dự phòng khi chưa cài Dompdf (CR-209), dùng cho hóa đơn P5.
 */
final class SimplePdf
{
    /** @param list<string> $lines */
    public static function render(array $lines): string
    {
        $content = "BT /F1 11 Tf 50 780 Td 17 TL\n";
        foreach ($lines as $line) {
            $text = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], Str::ascii($line, 'vi'));
            $content .= "($text) Tj T*\n";
        }
        $content .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
