<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpdateAiPolicyTrackerNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

    public $tracker;
    public $user;

    public $messageData;

    public function __construct($tracker, $user, $messageData)
    {
        $this->tracker = $tracker;
        $this->user = $user;
        $this->messageData = $messageData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'tracker_name' => $this->tracker->ai_policy_name,
            'tracker_id' => $this->tracker->id,
            'message' => $this->messageData,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Path to the logo image
        $logoPath = public_path('ai_dark_logo.png');

        return (new MailMessage)
            ->subject('AI policy tracker is updated')
            ->view('emails.updated-policy-notification', [
                'trackerName' => $this->tracker->ai_policy_name,
                'userName' => $this->user->name,
                'action' => url('/aipolicytracker/single-view/' . $this->tracker->id),
                'logo' => $logoPath,
                'messageData' => $this->messageData,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
