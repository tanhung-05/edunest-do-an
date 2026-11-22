<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email của sự kiện quan trọng, chạy trên hàng đợi `default` (BR-HT-45). */
final class EduMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $title, public readonly string $body, public readonly ?string $url = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('EduNest — '.$this->title)->line($this->body);

        return $this->url === null ? $mail : $mail->action('Mở EduNest', $this->url);
    }
}
