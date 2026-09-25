<?php

namespace App\Central\Notifications;

use App\Central\Models\ProvisioningRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to operators (owner/support) when a run fails permanently. */
class ProvisioningFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProvisioningRun $run)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->run->tenant;

        return (new MailMessage)
            ->error()
            ->subject("[Provisioning gagal] {$tenant->name} ({$tenant->slug})")
            ->line("Provisioning tenant {$tenant->name} ({$tenant->slug}) gagal setelah ".config('erp.provisioning.max_attempts').' percobaan.')
            ->line('Error: '.$this->run->error)
            ->action('Buka di panel operator', central_route('admin.tenants.show', $tenant));
    }
}
