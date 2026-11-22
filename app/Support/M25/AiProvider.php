<?php

namespace App\Support\M25;

/**
 * M25: cổng tới nhà cung cấp mô hình. Chỉ nhận nội dung cần thiết cho yêu cầu (BR-MR-18): câu hỏi, nội dung bài,
 * danh mục khóa; không có email, họ tên hay dữ liệu cá nhân. Có khóa `GEMINI_API_KEY` thì dùng `GeminiAiProvider`
 * (CR-255), không có khóa và khi chạy test thì dùng bộ giả lập `FakeAiProvider`.
 */
interface AiProvider
{
    /** @return array{answer: string, found: bool} */
    public function answerFromLesson(string $question, string $lessonText): array;

    /**
     * @param  list<array{id: int, title: string, summary: string}>  $catalog
     * @param  list<array{role: string, content: string}>  $history  vài lượt gần nhất của hội thoại tư vấn (USER/AI)
     * @return array{answer: string, course_ids: list<int>}
     */
    public function adviseCourses(string $question, array $catalog, array $history = []): array;

    /** @return list<array{stem: string, is_true: bool, explanation: string}> */
    public function suggestQuestions(string $lessonText, int $count): array;
}
