<?php

namespace App\Rules;

use App\Models\Role;
use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * A role name for the current business (Phase 5), used by create and edit
 * alike: unique among the business's own roles, so two businesses may each
 * have a "supervisor", and never a built-in role's name (`admin`, `manager`,
 * ...), so a business can't make a second owner role or shadow a template.
 * A business's copy of a template keeps its name, and can't be renamed.
 */
class RoleName implements ValidationRule
{
    public function __construct(private ?Role $editing = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $businessId = app(TenantContext::class)->id() ?? throw MissingTenantContext::forModel('Role');
        $name = (string) $value;

        $keepsOwnName = $this->editing !== null && $this->editing->name === $name;

        if ($this->editing?->isTemplateCopy() && ! $keepsOwnName) {
            $fail(__('A built-in role keeps its name.'));

            return;
        }

        // Plain queries: the role scope hides a template behind the business's
        // copy, and these checks need both.
        $builtIn = DB::table('roles')->whereNull('business_id')->where('guard_name', 'web')->where('name', $name)->exists();

        if ($builtIn && ! $keepsOwnName) {
            $fail(__('That name belongs to a built-in role. Choose another name.'));

            return;
        }

        $taken = DB::table('roles')->where('business_id', $businessId)->where('guard_name', 'web')->where('name', $name)
            ->when($this->editing !== null, fn ($query) => $query->where('id', '!=', $this->editing->id))
            ->exists();

        if ($taken) {
            $fail(__('This business already has a role with that name.'));
        }
    }
}
