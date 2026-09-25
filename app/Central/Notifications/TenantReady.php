<?php

namespace App\Central\Notifications;

use App\Central\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Sistem siap" email with the tenant login link (TDD §7, finalize step). */
class TenantReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tenant $tenant)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function loginUrl(): string
    {
        return $this->tenant->url('login');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Sistem {$this->tenant->name} siap digunakan")
            ->greeting("Halo {$this->tenant->owner_name},")
            ->line("Sistem untuk {$this->tenant->name} sudah siap di {$this->tenant->primaryDomain()}.")
            ->line("Masuk dengan email {$this->tenant->owner_email} dan password yang Anda buat saat pendaftaran.")
            ->action('Masuk ke sistem', $this->loginUrl());

        if ($this->tenant->trial_ends_at) {
            $message->line('Masa trial berlaku sampai '.$this->tenant->trial_ends_at->translatedFormat('d F Y').'.');
        }

        return $message;
    }
}
