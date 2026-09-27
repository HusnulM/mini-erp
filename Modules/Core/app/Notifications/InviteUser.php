<?php

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invitation for a new tenant user: a link to choose a password. */
class InviteUser extends Notification
{
    public function __construct(
        public string $url,
        public string $companyName,
        public string $invitedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Undangan ke {$this->companyName}")
            ->greeting("Halo {$notifiable->name},")
            ->line("{$this->invitedBy} mengundang Anda ke sistem {$this->companyName}. Username Anda: {$notifiable->username}.")
            ->action('Buat password', $this->url)
            ->line('Link berlaku '.config('auth.passwords.users.expire').' menit. Minta undangan ulang ke admin bila sudah kedaluwarsa.');
    }
}
