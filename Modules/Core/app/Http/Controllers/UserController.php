<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Core\Models\AuditLog;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\Store;
use Modules\Core\Models\User;
use Modules\Core\Models\Warehouse;
use Modules\Core\Notifications\InviteUser;
use Modules\Core\Services\Organization;
use Modules\Core\Services\PlanLimitExceeded;
use Spatie\Permission\Models\Role;

/**
 * Tenant users: invite by email, role and data scope (TDD §9 step 7,
 * PRD §8). New users choose their own password through the invite link.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('core::users.index', ['users' => User::with(['roles', 'scopes'])->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('core::users.form', $this->formData(new User(['status' => 'active'])));
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $organization->assertCanAddUser();
        } catch (PlanLimitExceeded $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                // Unusable until the invite link is used.
                'password' => Str::password(40),
                'status' => 'active',
                'default_company_id' => $data['default_company_id'] ?? null,
            ]);
            $this->syncAccess($user, $data);

            return $user;
        });

        $token = Password::broker('users')->createToken($user);
        $user->notify(new InviteUser(
            tenant()->url('password/reset/'.$token.'?'.http_build_query(['email' => $user->email])),
            Company::find($user->default_company_id)?->name ?? tenant()->name,
            $request->user()->name,
        ));

        return redirect()->route('core.users.index')->with('status', "Undangan dikirim ke {$user->email}.");
    }

    public function edit(User $user): View
    {
        return view('core::users.form', $this->formData($user->load(['roles', 'scopes'])));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['status'] !== 'active' || ! in_array($data['role'], $user->getRoleNames()->all(), true))) {
            return back()->withInput()->withErrors(['role' => 'Anda tidak bisa mengubah role atau menonaktifkan akun sendiri.']);
        }

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'name' => $data['name'],
                'status' => $data['status'],
                'default_company_id' => $data['default_company_id'] ?? null,
            ]);
            $this->syncAccess($user, $data);
        });

        return redirect()->route('core.users.index')->with('status', "User {$user->username} diperbarui.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'default_company_id' => ['nullable', Rule::exists('companies', 'id')],
            'scopes' => ['array'],
            'scopes.*' => ['string', 'regex:/^(own|(company|branch|store|warehouse):\d+)$/'],
        ];

        if ($user) {
            $rules['status'] = ['required', Rule::in(['active', 'inactive', 'locked'])];
        } else {
            $rules['username'] = ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')];
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
        }

        $data = $request->validate($rules);
        $data['status'] ??= 'active';

        // Everyone but SUPER ADMIN needs a data scope (deny by default).
        if ($data['role'] !== config('erp.provisioning.admin_role') && empty($data['scopes'])) {
            throw ValidationException::withMessages(['scopes' => 'Pilih minimal satu cakupan data.']);
        }

        return $data;
    }

    private function syncAccess(User $user, array $data): void
    {
        $oldRoles = $user->getRoleNames()->all();
        $oldScopes = $user->scopes()->get()->map(fn ($s) => $s->scope_type.($s->scope_id ? ":{$s->scope_id}" : ''))->sort()->values()->all();

        $user->syncRoles([$data['role']]);

        $user->scopes()->delete();
        foreach (array_unique($data['scopes'] ?? []) as $scope) {
            [$type, $id] = str_contains($scope, ':') ? explode(':', $scope) : [$scope, null];
            $user->scopes()->create(['scope_type' => $type, 'scope_id' => $id]);
        }

        $newScopes = collect($data['scopes'] ?? [])->unique()->sort()->values()->all();
        if ($oldRoles !== [$data['role']] || $oldScopes !== $newScopes) {
            AuditLog::record($user, 'access_changed', ['roles' => $oldRoles, 'scopes' => $oldScopes], ['roles' => [$data['role']], 'scopes' => $newScopes]);
        }
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->pluck('name'),
            'companies' => Company::orderBy('code')->get(),
            'branches' => Branch::orderBy('code')->get(),
            'stores' => Store::orderBy('code')->get(),
            'warehouses' => Warehouse::orderBy('code')->get(),
            'selectedScopes' => $user->exists
                ? $user->scopes->map(fn ($s) => $s->scope_type.($s->scope_id ? ":{$s->scope_id}" : ''))->all()
                : [],
        ];
    }
}
