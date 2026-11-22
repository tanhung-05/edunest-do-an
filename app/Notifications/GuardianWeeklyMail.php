<?php

namespace App\Notifications;

use App\Actions\M23\GuardianLinks;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** QT-25 A3: email báo cáo tuần cho Phụ huynh; kiểm lại liên kết ngay lúc gửi (BR-MR-07/08, BR-HT-60). */
final class GuardianWeeklyMail extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $lines */
    public function __construct(public readonly int $studentId, public readonly string $title, public readonly array $lines, public readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User && GuardianLinks::active($notifiable, $this->studentId) !== null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('EduNest — '.$this->title);
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action('Xem trên EduNest', $this->url);
    }
}
