<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use LogicException;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesProvisionedTenant;
use Tests\TestCase;

/** Acceptance criterion: every change to master data and configuration is in audit_logs (PRD §49). */
class AuditLogTest extends TestCase
{
    use DatabaseMigrations, UsesProvisionedTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionedTenant();
        $this->loginAsOwner();
    }

    #[Test]
    public function creating_changing_and_deleting_master_data_is_recorded_with_user_and_ip(): void
    {
        $company = $this->tenant->run(fn () => Company::sole());
        $this->post($this->url('organization/branches'), ['company_id' => $company->id, 'code' => 'SBY', 'name' => 'Surabaya'])
            ->assertSessionHasNoErrors();

        $this->tenant->run(function () {
            $admin = User::sole();
            $branch = Branch::where('code', 'SBY')->sole();

            $created = AuditLog::where('auditable_type', $branch->getMorphClass())->where('auditable_id', $branch->id)->sole();
            $this->assertSame('created', $created->event);
            $this->assertSame($admin->id, $created->user_id);
            $this->assertSame('127.0.0.1', $created->ip);
            $this->assertSame('Surabaya', $created->new_values['name']);
            $this->assertArrayNotHasKey('created_by', $created->new_values);
            $this->assertSame($admin->id, $branch->created_by, 'user stamps');

            auth('web')->setUser($admin);
            $branch->update(['name' => 'Surabaya Timur']);
            $branch->delete();

            $events = AuditLog::where('auditable_id', $branch->id)->where('auditable_type', $branch->getMorphClass())->orderBy('id')->get();
            $this->assertSame(['created', 'updated', 'deleted'], $events->pluck('event')->all());
            $this->assertSame(['name' => 'Surabaya'], $events[1]->old_values);
            $this->assertSame(['name' => 'Surabaya Timur'], $events[1]->new_values);
        });
    }

    #[Test]
    public function secrets_are_never_logged_and_logins_are_not_changes(): void
    {
        $this->tenant->run(function () {
            $user = User::create(['name' => 'Budi', 'username' => 'budi', 'email' => 'budi@alpha.test', 'password' => 'budi-password-1', 'status' => 'active']);
            $user->update(['password' => 'another-password-1']);
            $before = AuditLog::count();
            $user->forceFill(['last_login_at' => now()])->save();

            $logs = AuditLog::where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->id)->get();
            $this->assertStringNotContainsString('password', $logs->toJson());
            $this->assertSame(['created'], $logs->pluck('event')->all(), 'password-only change leaves nothing to log');
            $this->assertSame($before, AuditLog::count());
        });
    }

    #[Test]
    public function audit_logs_are_append_only(): void
    {
        $this->tenant->run(function () {
            $log = AuditLog::firstOrFail();

            $this->expectException(LogicException::class);
            $log->update(['event' => 'tampered']);
        });
    }

    #[Test]
    public function the_audit_log_page_lists_changes(): void
    {
        $this->get($this->url('audit-logs'))->assertOk()->assertSee('Company')->assertSee('created');
        $this->get($this->url('audit-logs?event=updated&type=Store'))->assertOk();
    }
}
