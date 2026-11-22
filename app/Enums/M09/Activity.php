<?php

namespace App\Enums\M09;

enum Activity: string
{
    case ViewLesson = 'View_Lesson';
    case StartQuizAttempt = 'Start_Quiz_Attempt';
    case ContinueQuizAttempt = 'Continue_Quiz_Attempt';
    case RecordWatchBeat = 'Record_Watch_Beat';
    case CompleteLesson = 'Complete_Lesson';
    case ViewHistory = 'View_History';
    case ViewCertificate = 'View_Certificate';
    case DownloadCertificate = 'Download_Certificate';

    public function label(): string
    {
        return match ($this) {
            self::ViewLesson => 'Xem bài',
            self::StartQuizAttempt => 'Bắt đầu lượt',
            self::ContinueQuizAttempt => 'Tiếp tục lượt',
            self::RecordWatchBeat => 'Ghi mốc xem',
            self::CompleteLesson => 'Hoàn thành bài',
            self::ViewHistory => 'Xem lịch sử',
            self::ViewCertificate => 'Xem chứng nhận',
            self::DownloadCertificate => 'Tải chứng nhận',
        };
    }
}
