<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Branch;
use Modules\Core\Models\User;
use Modules\Core\Notifications\InviteUser;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesProvisionedTenant;
use Tests\TestCase;

/** TDD §9 step 7: invite users by email, with role and data scope. */
class UserManagementTest extends TestCase
{
    use DatabaseMigrations, UsesProvisionedTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionedTenant();
        $this->loginAsOwner();
        $this->post($this->url('roles'), ['name' => 'KASIR', 'permissions' => ['core.store.view', 'pos.transaction.view', 'pos.transaction.create']])
            ->assertSessionHasNoErrors();
    }

    private function invite(array $overrides = [])
    {
        $branchId = $this->tenant->run(fn () => Branch::sole()->id);

        return $this->post($this->url('users'), $overrides + [
            'name' => 'Budi Kasir', 'username' => 'budi', 'email' => 'budi@alpha.test',
            'role' => 'KASIR', 'scopes' => ["branch:{$branchId}"],
        ]);
    }

    #[Test]
    public function an_invited_user_sets_a_password_through_the_link_and_logs_in(): void
    {
        $this->invite()->assertRedirect(route('core.users.index'))->assertSessionHas('status', 'Undangan dikirim ke budi@alpha.test.');

        $budi = $this->tenant->run(fn () => User::where('username', 'budi')->sole());
        $url = null;
        Notification::assertSentTo($budi, InviteUser::class, function (InviteUser $n) use (&$url) {
            $url = $n->url;

            return str_starts_with($n->url, 'http://alpha.erp.localhost/password/reset/');
        });

        $this->post($this->url('logout'));
        $this->get($url)->assertOk()->assertSee('Buat password');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->post($this->url('password/reset'), [
            'token' => $token, 'email' => $query['email'], 'password' => 'budi-new-password', 'password_confirmation' => 'budi-new-password',
        ])->assertRedirect(route('core.login'));

        $this->post($this->url('login'), ['login' => 'budi', 'password' => 'budi-new-password'])->assertRedirect('/');
        $this->get($this->url('/'))->assertOk()->assertSee('data-module="pos"', false)->assertDontSee('data-module="inventory"', false);
        $this->get($this->url('users'))->assertForbidden();

        $this->tenant->run(function () {
            $budi = User::where('username', 'budi')->sole();
            $this->assertTrue($budi->hasRole('KASIR'));
            $this->assertSame([['branch', Branch::sole()->id]], $budi->scopes->map(fn ($s) => [$s->scope_type, $s->scope_id])->all());
            $this->assertTrue(AuditLog::where('event', 'access_changed')->where('auditable_id', $budi->id)->exists());
        });
    }

    #[Test]
    public function forgot_password_sends_a_link_on_the_tenant_domain(): void
    {
        $this->post($this->url('logout'));
        $this->post($this->url('password/forgot'), ['email' => 'ani@alpha.test'])->assertSessionHas('status');
        $this->post($this->url('password/forgot'), ['email' => 'nobody@alpha.test'])->assertSessionHas('status');

        $owner = $this->tenant->run(fn () => User::where('email', 'ani@alpha.test')->sole());
        Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $n) use ($owner) {
            return str_starts_with($n->toMail($owner)->actionUrl, 'http://alpha.erp.localhost/password/reset/');
        });
    }

    #[Test]
    public function a_user_other_than_super_admin_needs_a_scope(): void
    {
        $this->invite(['scopes' => []])->assertSessionHasErrors(['scopes' => 'Pilih minimal satu cakupan data.']);
        $this->invite(['scopes' => ['galaxy:1']])->assertSessionHasErrors('scopes.0');
        $this->invite(['email' => 'ani@alpha.test'])->assertSessionHasErrors('email');
    }

    #[Test]
    public function the_plan_user_limit_is_enforced(): void
    {
        foreach (range(1, 4) as $i) {
            $this->invite(['username' => "u{$i}", 'email' => "u{$i}@alpha.test"])->assertSessionHasNoErrors();
        }

        $this->invite(['username' => 'u5', 'email' => 'u5@alpha.test'])
            ->assertSessionHasErrors(['email' => 'Batas paket tercapai: maksimal 5 user. Upgrade paket untuk menambah.']);
    }

    #[Test]
    public function admins_cannot_lock_themselves_out_and_super_admin_is_managed_by_the_system(): void
    {
        $owner = $this->tenant->run(fn () => User::where('email', 'ani@alpha.test')->sole());

        $this->put($this->url("users/{$owner->id}"), ['name' => 'Ani', 'role' => 'KASIR', 'status' => 'active', 'scopes' => ['own']])
            ->assertSessionHasErrors('role');

        $superAdmin = $this->tenant->run(fn () => Role::findByName('SUPER ADMIN', 'web'));
        $this->get($this->url("roles/{$superAdmin->id}/edit"))->assertRedirect(route('core.roles.index'));
        $this->put($this->url("roles/{$superAdmin->id}"), ['name' => 'X'])->assertForbidden();
        $this->post($this->url('roles'), ['name' => 'SUPER ADMIN'])->assertSessionHasErrors('name');
    }

    #[Test]
    public function role_permission_changes_are_audited(): void
    {
        $kasir = $this->tenant->run(fn () => Role::findByName('KASIR', 'web'));

        $this->put($this->url("roles/{$kasir->id}"), ['name' => 'KASIR', 'permissions' => ['pos.transaction.view']])->assertSessionHasNoErrors();

        $this->tenant->run(function () use ($kasir) {
            $log = AuditLog::where('auditable_id', $kasir->id)->where('event', 'updated')->latest('id')->first();
            $this->assertSame(['pos.transaction.view'], $log->new_values['permissions']);
            $this->assertContains('pos.transaction.create', $log->old_values['permissions']);
        });
    }
}
