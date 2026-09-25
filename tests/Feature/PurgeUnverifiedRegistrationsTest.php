<?php

namespace Tests\Feature;

use App\Central\Models\Domain;
use App\Central\Models\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\RegistersTenants;
use Tests\TestCase;

/** TDD §7: registrations not verified within 7 days are deleted automatically. */
class PurgeUnverifiedRegistrationsTest extends TestCase
{
    use DatabaseMigrations, RegistersTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->fakeExternalServices();
        Notification::fake();
        Queue::fake();
    }

    #[Test]
    public function unverified_registrations_older_than_seven_days_are_deleted(): void
    {
        $old = $this->register(['slug' => 'old', 'owner_email' => 'old@test.test']);
        $oldVerified = $this->register(['slug' => 'oldverified', 'owner_email' => 'ov@test.test']);
        $this->verify($oldVerified);

        $this->travel(6)->days();
        $recent = $this->register(['slug' => 'recent', 'owner_email' => 'recent@test.test']);

        $this->travel(1)->days();
        $this->travel(1)->minutes();
        $this->artisan('erp:registrations:purge')->assertSuccessful()->expectsOutputToContain('1 unverified');

        $this->assertNull(Tenant::find($old->id));
        $this->assertFalse(Domain::where('domain', 'old.erp.localhost')->exists());
        $this->assertNotNull(Tenant::find($oldVerified->id));
        $this->assertNotNull(Tenant::find($recent->id));

        // The slug and email are free again.
        $this->register(['slug' => 'old', 'owner_email' => 'old@test.test']);
    }

    #[Test]
    public function the_purge_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command, 'erp:registrations:purge'));

        $this->assertCount(1, $events);
        $this->assertSame('15 2 * * *', $events->first()->expression);
    }
}
