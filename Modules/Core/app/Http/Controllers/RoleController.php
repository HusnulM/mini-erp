<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Core\Models\AuditLog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles with permissions MODULE.ENTITY.ACTION (TDD §5). Only permissions of
 * installed modules exist, so a role can never hold access to a module the
 * tenant does not have. SUPER ADMIN is managed by the system.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('core::roles.index', ['roles' => Role::where('guard_name', 'web')->withCount(['permissions', 'users'])->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('core::roles.form', ['role' => new Role, 'permissions' => $this->groupedPermissions(), 'selected' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions'] ?? []);
            AuditLog::record($role, 'created', [], ['name' => $role->name, 'permissions' => $data['permissions'] ?? []]);
        });

        return redirect()->route('core.roles.index')->with('status', "Role {$data['name']} dibuat.");
    }

    public function edit(Role $role): View|RedirectResponse
    {
        if ($this->isSystemRole($role)) {
            return redirect()->route('core.roles.index')->withErrors(['role' => 'Role SUPER ADMIN dikelola sistem dan selalu punya semua permission.']);
        }

        return view('core::roles.form', ['role' => $role, 'permissions' => $this->groupedPermissions(), 'selected' => $role->permissions->pluck('name')->all()]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($this->isSystemRole($role), 403);
        $data = $this->validated($request, $role);

        DB::transaction(function () use ($role, $data) {
            $old = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->sort()->values()->all()];
            $role->update(['name' => $data['name']]);
            $role->syncPermissions($data['permissions'] ?? []);
            AuditLog::record($role, 'updated', $old, ['name' => $role->name, 'permissions' => collect($data['permissions'] ?? [])->sort()->values()->all()]);
        });

        return redirect()->route('core.roles.index')->with('status', "Role {$data['name']} diperbarui.");
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::notIn([config('erp.provisioning.admin_role')]),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
    }

    private function isSystemRole(Role $role): bool
    {
        return $role->name === config('erp.provisioning.admin_role');
    }

    /** @return array<string, list<string>> module => permission names */
    private function groupedPermissions(): array
    {
        return Permission::where('guard_name', 'web')->orderBy('name')->pluck('name')
            ->groupBy(fn ($name) => strtok($name, '.'))
            ->map->values()->map->all()->all();
    }
}
