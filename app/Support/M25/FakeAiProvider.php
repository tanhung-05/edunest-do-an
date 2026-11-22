<?php

namespace App\Support\M25;

use RuntimeException;

/**
 * Bộ giả lập AI (CR-239): trả lời bằng cách tìm câu trong nội dung bài có chung từ khóa với câu hỏi, gợi ý khóa theo
 * từ khóa trong tên và mô tả, sinh câu Đúng/Sai từ các câu của bài. Không gọi mạng. Câu hỏi chứa "#loi-ai" giả lập lỗi
 * nhà cung cấp để kiểm CR-170.
 */
final class FakeAiProvider implements AiProvider
{
    public function answerFromLesson(string $question, string $lessonText): array
    {
        self::maybeFail($question);
        $keywords = self::keywords($question);
        $hits = [];
        foreach (self::sentences($lessonText) as $sentence) {
            $words = self::keywords($sentence);
            if ($keywords !== [] && count(array_intersect($keywords, $words)) >= min(2, count($keywords))) {
                $hits[] = $sentence;
            }
        }
        if ($hits === []) {
            return ['answer' => 'Nội dung bạn hỏi không có trong bài này. Bạn có thể chuyển câu hỏi cho giáo viên.', 'found' => false];
        }

        return ['answer' => 'Theo nội dung bài: '.implode(' ', array_slice($hits, 0, 3)), 'found' => true];
    }

    public function adviseCourses(string $question, array $catalog, array $history = []): array
    {
        self::maybeFail($question);
        $keywords = self::keywords($question);
        $scored = [];
        foreach ($catalog as $course) {
            $score = count(array_intersect($keywords, self::keywords($course['title'].' '.$course['summary'])));
            if ($score > 0) {
                $scored[] = [$score, $course];
            }
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);
        $picked = array_map(fn ($s) => $s[1], array_slice($scored, 0, 3));
        if ($picked === []) {
            // CR-257: lời chào hoặc câu chung chung vẫn được đáp và gợi ý vài khóa nổi bật (danh mục đã xếp khóa đông học viên trước).
            if ($catalog === []) {
                return ['answer' => 'Hiện chưa có khóa đang mở bán phù hợp. Bạn để lại lời nhắn cho nhân viên để được báo khi có khóa mới nhé.', 'course_ids' => []];
            }
            $featured = array_slice($catalog, 0, 3);
            $hello = preg_match('/\b(xin chào|chào|hello|hi|alo)\b/iu', $question) === 1;

            return ['answer' => ($hello ? 'Chào bạn! Mình là trợ lý tư vấn của EduNest. ' : 'Mình chưa thấy khóa khớp đúng yêu cầu này. ')
                .'Bạn cho mình biết môn, lớp và mục tiêu (ôn thi, lấy lại gốc, nâng cao) để mình gợi ý sát hơn. Dưới đây là vài khóa đang được học nhiều:',
                'course_ids' => array_map(fn ($c) => $c['id'], $featured)];
        }

        return ['answer' => 'Các khóa đang bán phù hợp: '.implode('; ', array_map(fn ($c) => $c['title'], $picked)).'.',
            'course_ids' => array_map(fn ($c) => $c['id'], $picked)];
    }

    public function suggestQuestions(string $lessonText, int $count): array
    {
        $out = [];
        foreach (self::sentences($lessonText) as $sentence) {
            if (mb_strlen($sentence) < 20 || mb_strlen($sentence) > 400) {
                continue;
            }
            $out[] = ['stem' => $sentence, 'is_true' => true, 'explanation' => 'Câu này lấy nguyên văn từ nội dung bài.'];
            if (count($out) >= $count) {
                break;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function sentences(string $text): array
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], '. ', $text)))));
        // Đoạn đã kết thúc bằng dấu câu thì bỏ dấu chấm thêm vào khi tách thẻ đóng.
        $plain = (string) preg_replace('/([.!?])(\s*\.)+/u', '$1', $plain);
        $parts = preg_split('/(?<=[.!?])\s+/u', $plain) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => mb_strlen($s) >= 8));
    }

    /** @return list<string> */
    private static function keywords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 3)));
    }

    private static function maybeFail(string $question): void
    {
        if (str_contains($question, '#loi-ai')) {
            throw new RuntimeException('Nhà cung cấp AI giả lập lỗi.');
        }
    }
}
