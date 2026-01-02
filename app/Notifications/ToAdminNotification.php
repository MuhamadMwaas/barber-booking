<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log as FacadesLog;

class ToAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

    public string $title;
    public string $message;
    public array $data;
    public array $params;
    public function __construct($title,$message,$data,$params)
    {
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->params = $params;
        FacadesLog::info("PhoneNotification created with title: " . $title);
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */


    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title_key' => $this->title,
            'message_key' => $this->message,
            'data' => $this->data,
            'params'=> $this->params,
        ];
    }
}
