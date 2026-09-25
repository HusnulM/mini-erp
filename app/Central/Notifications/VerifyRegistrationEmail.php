<?php

namespace App\Central\Notifications;

use App\Central\Models\Tenant;
use App\Support\CentralRoutes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyRegistrationEmail extends Notification implements ShouldQueue
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

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) config('erp.registration.unverified_ttl_days');

        return (new MailMessage)
            ->subject('Verifikasi email pendaftaran '.config('app.name'))
            ->greeting("Halo {$this->tenant->owner_name},")
            ->line("Terima kasih telah mendaftarkan {$this->tenant->name}. Klik tombol di bawah untuk memverifikasi email Anda; sistem akan langsung disiapkan setelahnya.")
            ->action('Verifikasi email', self::url($this->tenant))
            ->line("Link berlaku {$days} hari. Pendaftaran yang tidak diverifikasi dalam {$days} hari akan dihapus otomatis.")
            ->line('Abaikan email ini jika Anda tidak merasa mendaftar.');
    }

    /** Signed link, always on the canonical central domain. */
    public static function url(Tenant $tenant): string
    {
        return URL::temporarySignedRoute(
            CentralRoutes::name('register.verify', CentralRoutes::primaryDomain()),
            now()->addDays((int) config('erp.registration.unverified_ttl_days')),
            ['tenant' => $tenant->id, 'hash' => sha1($tenant->owner_email)],
        );
    }
}
