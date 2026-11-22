<?php

namespace App\Support\M09;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P9.2: phát tệp riêng tư (đĩa `local`) an toàn. Tài liệu bổ sung nhận mọi loại tệp, nên chỉ loại an toàn được mở ngay
 * trong trình duyệt; loại khác (HTML, SVG, XML, ...) trả `application/octet-stream` dạng tải về để không chạy mã
 * trên miền của ứng dụng. Luôn kèm `nosniff` và không lưu đệm dùng chung.
 */
final class SafeFileResponse
{
    private const INLINE = '#^(application/pdf|image/(png|jpeg|gif|webp)|video/(mp4|webm)|audio/[\w.+-]+|text/plain)$#';

    public static function inline(string $mime): bool
    {
        return preg_match(self::INLINE, $mime) === 1;
    }

    public static function make(string $absolutePath, string $mime, ?string $downloadName = null, string $fallbackName = 'tai-lieu'): BinaryFileResponse
    {
        $inline = self::inline($mime);
        $response = response()->file($absolutePath, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        // BinaryFileResponse mặc định đặt `public`; đặt lại sau khi tạo để proxy không lưu đệm dùng chung (ADR-009).
        $response->headers->set('Cache-Control', 'private, no-store');
        if ($downloadName !== null || ! $inline) {
            $response->setContentDisposition($inline ? 'inline' : 'attachment', $downloadName ?? $fallbackName, $fallbackName);
        }

        return $response;
    }
}
