<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\ApplyTemplateToCopies;
use App\Actions\Platform\UpdateRoleTemplate;
use App\Actions\Role\ResetRoleToTemplate;
use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ConfirmPlatformActionRequest;
use App\Http\Requests\Platform\UpdateRoleTemplateRequest;
use App\Models\Business;
use App\Models\Role;
use App\Support\Platform\PlatformAudit;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

/**
 * The shared role templates in the platform console (Phase 5, B6): the only
 * place they change. An edit reaches new businesses and "Reset to default";
 * existing copies change only through "Apply to all copies". A business's
 * copy can be reset on its behalf from its page. Every action audited.
 *
 * `{role}` binds only shared roles here (an operator is in no business, so the
 * role scope shows only the templates): a business's role is a 404.
 */
class RoleTemplateController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function index(): View
    {
        $templates = Role::query()->whereNull('business_id')->withCount('permissions')->orderBy('name')->get();

        PlatformAudit::record('role_templates.listed');

        return view('platform.roles.index', ['templates' => $templates, 'copies' => $this->copyCounts()]);
    }

    public function edit(Role $role): View
    {
        abort_unless($role->isShared(), 404);

        PlatformAudit::record('role_template.viewed', null, ['template' => $role->name]);

        return view('platform.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'copies' => $this->copyCounts()[$role->id] ?? 0,
        ]);
    }

    public function update(UpdateRoleTemplateRequest $request, Role $role, UpdateRoleTemplate $update): RedirectResponse
    {
        abort_unless($role->isShared(), 404);

        $update->execute($role, $request->validated('permissions') ?? []);

        return redirect()->route('platform.roles.edit', $role)->with('success', $role->isOwnerRole()
            ? __('The owner role is updated, for every business\'s owners.')
            : __('The template is updated. New businesses get it, and Reset to default applies it; existing copies are unchanged.'));
    }

    public function applyToCopies(ConfirmPlatformActionRequest $request, Role $role, ApplyTemplateToCopies $apply): RedirectResponse
    {
        abort_unless($role->isShared(), 404);

        try {
            $count = $apply->execute($role);
        } catch (DomainException $e) {
            return redirect()->route('platform.roles.edit', $role)->with('error', $e->getMessage());
        }

        return redirect()->route('platform.roles.edit', $role)
            ->with('success', trans_choice('{0} No business has a copy of this template.|{1} Applied to 1 business\'s copy.|[2,*] Applied to :count businesses\' copies.', $count, ['count' => $count]));
    }

    /**
     * Resets one business's copy of a template to default, on its behalf.
     *
     * @param  string  $copy  the copy's uuid (looked up inside the business)
     */
    public function resetCopy(ConfirmPlatformActionRequest $request, Business $business, string $copy, ResetRoleToTemplate $reset): RedirectResponse
    {
        $role = $this->tenant->run($business, fn (): ?Role => Role::query()->where('business_id', $business->id)->where('uuid', $copy)->first());
        abort_if($role === null, 404);

        if (! $role->isTemplateCopy()) {
            return redirect()->route('platform.businesses.show', $business)->with('error', __('Only a built-in role can be reset to default.'));
        }

        $this->tenant->run($business, fn () => $reset->execute($role));

        PlatformAudit::record('role.reset', $business, ['role' => $role->uuid, 'name' => $role->name], AuditEvent::UPDATED);

        return redirect()->route('platform.businesses.show', $business)
            ->with('success', __(':role is reset to default.', ['role' => $role->displayName()]));
    }

    /**
     * How many businesses hold a copy of each template, platform-wide.
     *
     * @return Collection<int, int> template id => copies
     */
    private function copyCounts(): Collection
    {
        return $this->tenant->bypass(fn (): Collection => Role::query()->whereNotNull('template_id')
            ->selectRaw('template_id, count(*) as copies')->groupBy('template_id')->pluck('copies', 'template_id'));
    }
}
