<?php

namespace App\Enums\M05;

/** 02 L15 loại bài. */
enum LessonType: string
{
    case Video = 'VIDEO';
    case Pdf = 'PDF';
    case BaiDoc = 'BAI_DOC';
    case Quiz = 'QUIZ';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Pdf => 'PDF',
            self::BaiDoc => 'Bài viết',
            self::Quiz => 'Quiz',
        };
    }
}
