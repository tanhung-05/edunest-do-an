<?php

namespace App\Support\M05;

/** Lọc HTML của trình soạn (02 L12/L15 "HTML đã lọc"): giữ thẻ định dạng, bỏ script, thuộc tính on*, URL javascript:. */
final class HtmlSanitizer
{
    private const ALLOWED = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><blockquote><a><code><pre><hr><table><thead><tbody><tr><th><td><sub><sup>';

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, self::ALLOWED);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*(javascript|data|vbscript):[^"\'\s>]*\2/i', '$1="#"', $html) ?? '';
        $html = trim($html);

        return $html === '' ? null : $html;
    }

    /** Nội dung có chữ thật (bỏ thẻ và khoảng trắng). */
    public static function hasText(?string $html): bool
    {
        return $html !== null && trim(html_entity_decode(strip_tags($html))) !== '';
    }
}
