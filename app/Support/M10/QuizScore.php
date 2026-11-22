<?php

namespace App\Support\M10;

use App\Enums\M04\QuestionType;
use App\Models\M04\QuizQuestion;
use App\Models\M10\QuizAttempt;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

final class QuizScore
{
    /** @return list<array{id: int, content: string, is_correct: bool, position: int}> */
    public static function options(QuizQuestion $question): array
    {
        $options = $question->snapshot['options'] ?? [];
        if (! is_array($options)) {
            return [];
        }

        $result = [];
        foreach ($options as $option) {
            if (is_array($option) && is_int($option['id'] ?? null) && is_string($option['content'] ?? null)) {
                $result[] = ['id' => $option['id'], 'content' => $option['content'], 'is_correct' => ($option['is_correct'] ?? false) === true,
                    'position' => is_int($option['position'] ?? null) ? $option['position'] : 0];
            }
        }

        return $result;
    }

    /**
     * Câu trả lời đã chuẩn hóa để lưu. Lựa chọn: danh sách id đã sắp. Điền từ: một chuỗi (giữ nguyên chữ người học).
     * Sắp xếp: hoán vị đủ các id theo thứ tự người học chọn.
     *
     * @return list<int>|string|null
     */
    public static function answer(QuizQuestion $question, mixed $value): array|string|null
    {
        $type = $question->snapshot['type'] ?? null;
        if ($type === QuestionType::DienTu->value) {
            if (! is_string($value)) {
                return null;
            }
            $value = trim($value);

            return $value === '' || mb_strlen($value) > 500 ? null : $value;
        }
        if ($type === QuestionType::SapXep->value) {
            $allowed = array_column(self::options($question), 'id');
            if (! is_array($value) || ! array_is_list($value) || count($value) !== count($allowed)) {
                return null;
            }
            foreach ($value as $id) {
                if (! is_int($id)) {
                    return null;
                }
            }
            $sorted = $value;
            sort($sorted);
            $expected = $allowed;
            sort($expected);

            return $sorted === $expected ? $value : null;
        }
        if (! in_array($type, [QuestionType::MotDapAn->value, QuestionType::NhieuDapAn->value, QuestionType::DungSai->value], true)) {
            return null;
        }
        $ids = is_int($value) ? [$value] : $value;
        if (! is_array($ids) || ! array_is_list($ids) || $ids === [] || ($type !== QuestionType::NhieuDapAn->value && count($ids) !== 1)) {
            return null;
        }
        $allowed = array_column(self::options($question), 'id');
        $unique = [];
        foreach ($ids as $id) {
            if (! is_int($id) || ! in_array($id, $allowed, true) || isset($unique[$id])) {
                return null;
            }
            $unique[$id] = true;
        }
        $ids = array_keys($unique);
        sort($ids);

        return $ids;
    }

    public static function correct(QuizQuestion $question, mixed $value): bool
    {
        $type = $question->snapshot['type'] ?? null;
        if ($type === QuestionType::DienTu->value) {
            $given = self::answer($question, $value);
            if (! is_string($given)) {
                return false;
            }
            $accepted = array_map(fn (array $o) => self::normalize($o['content']), self::options($question));

            return in_array(self::normalize($given), $accepted, true);
        }
        if ($type === QuestionType::SapXep->value) {
            $given = self::answer($question, $value);
            $options = self::options($question);
            usort($options, fn (array $a, array $b) => $a['position'] <=> $b['position']);

            return is_array($given) && $given === array_column($options, 'id');
        }
        $ids = self::answer($question, $value);
        $expected = array_column(array_filter(self::options($question), fn (array $option) => $option['is_correct']), 'id');
        sort($expected);

        return $ids !== null && $ids === $expected;
    }

    /** CR-69: NFC, bỏ khoảng trắng hai đầu, gộp khoảng trắng, không phân biệt hoa/thường, giữ dấu. */
    public static function normalize(string $text): string
    {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    public static function ratio(QuizAttempt $attempt): ?BigRational
    {
        if ($attempt->score_earned === null || $attempt->score_max === null || BigRational::of($attempt->score_max)->isZero()) {
            return null;
        }

        return BigRational::of($attempt->score_earned)->dividedBy($attempt->score_max);
    }

    public static function display(?BigRational $ratio): ?string
    {
        return $ratio === null ? null : (string) $ratio->multipliedBy(100)->toScale(1, RoundingMode::HalfUp);
    }
}
