<?php

namespace App\Services\Admin;

use App\Enums\AdminCapability;
use App\Enums\AdminRole;
use App\Models\AdminRolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * What each role may do: its defaults from AdminRole, or an owner's edit of them.
 *
 * Read once per request (it is a scoped binding), because the gate asks for every link
 * in the admin navigation. Owner-only capabilities are filtered out on both read and
 * write, so neither an edit through the UI nor a row written straight to the table can
 * give a role settings, billing or user management.
 */
final class RolePermissions
{
    /** @var array<string, list<AdminCapability>>|null */
    private ?array $edited = null;

    /** @return list<AdminCapability> */
    public function for(AdminRole $role): array
    {
        $edited = $this->edited()[$role->value] ?? null;

        return $edited ?? $role->defaultCapabilities();
    }

    public function allows(AdminRole $role, AdminCapability $capability): bool
    {
        return in_array($capability, $this->for($role), true);
    }

    public function isEdited(AdminRole $role): bool
    {
        return isset($this->edited()[$role->value]);
    }

    public function record(AdminRole $role): ?AdminRolePermission
    {
        return AdminRolePermission::with('updatedBy')->where('role', $role->value)->first();
    }

    /**
     * Store a role's capabilities. Owner-only ones are dropped, and a set identical to the
     * defaults is stored as no row, so "edited" always means "differs from the defaults".
     *
     * @param  list<string>  $values
     * @return list<AdminCapability>
     */
    public function save(AdminRole $role, array $values, User $by): array
    {
        $capabilities = self::grantable(array_values(array_filter(array_map(fn ($v) => AdminCapability::tryFrom((string) $v), $values))));

        if (self::sameSet($capabilities, $role->defaultCapabilities())) {
            AdminRolePermission::where('role', $role->value)->delete();
        } else {
            AdminRolePermission::updateOrCreate(['role' => $role->value], [
                'capabilities' => array_map(fn (AdminCapability $c) => $c->value, $capabilities),
                'updated_by' => $by->getKey(),
            ]);
        }
        $this->edited = null;

        return $capabilities;
    }

    public function reset(AdminRole $role): void
    {
        AdminRolePermission::where('role', $role->value)->delete();
        $this->edited = null;
    }

    /**
     * @param  list<AdminCapability>  $capabilities
     * @return list<AdminCapability>
     */
    public static function grantable(array $capabilities): array
    {
        // In declaration order, so a stored set and the matrix read the same way.
        return array_values(array_filter(AdminCapability::cases(), fn (AdminCapability $c) => ! $c->ownerOnly() && in_array($c, $capabilities, true)));
    }

    /** @return array<string, list<AdminCapability>> */
    private function edited(): array
    {
        if ($this->edited !== null) {
            return $this->edited;
        }
        // Before the migration has run (a fresh deploy mid-migrate), the defaults apply.
        if (! Schema::hasTable('admin_role_permissions')) {
            return $this->edited = [];
        }
        $out = [];
        foreach (AdminRolePermission::all() as $row) {
            if (AdminRole::tryFrom($row->role) === null) {
                continue;
            }
            $out[$row->role] = self::grantable(array_values(array_filter(array_map(fn ($v) => AdminCapability::tryFrom((string) $v), (array) $row->capabilities))));
        }

        return $this->edited = $out;
    }

    /**
     * @param  list<AdminCapability>  $a
     * @param  list<AdminCapability>  $b
     */
    private static function sameSet(array $a, array $b): bool
    {
        $values = fn (array $list) => collect($list)->map(fn (AdminCapability $c) => $c->value)->sort()->values()->all();

        return $values($a) === $values($b);
    }
}
