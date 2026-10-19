<?php

namespace App\Http\Requests;

use App\Actions\Business\CopyRoleTemplates;
use App\Models\BusinessMembership;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * An invite to the current business (stage 3). The role must be one of the
 * business's roles, and an invite can't grant more than its sender holds:
 * only an owner invites an owner, and anyone else only a role whose
 * permissions they all hold themselves.
 */
class StoreBusinessInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'role_id' => ['required', 'integer'],
            'shop_ids' => ['nullable', 'array'],
            'shop_ids.*' => ['integer', 'exists:shops,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var User $inviter */
                $inviter = $this->user();
                $role = Role::query()->find($this->integer('role_id'));

                if ($role === null) {
                    $validator->errors()->add('role_id', __('Choose one of this business\'s roles.'));

                    return;
                }

                if (! $inviter->isBusinessOwner()) {
                    $held = $inviter->getAllPermissions()->pluck('name');
                    $grants = $role->permissions->pluck('name');

                    if ($role->name === CopyRoleTemplates::OWNER_ROLE || $grants->diff($held)->isNotEmpty()) {
                        $validator->errors()->add('role_id', __('You can only invite people to a role whose permissions you hold.'));

                        return;
                    }
                }

                $alreadyMember = BusinessMembership::query()
                    ->where('business_id', app(TenantContext::class)->id())
                    ->where('status', BusinessMembership::STATUS_ACTIVE)
                    ->whereHas('user', fn ($query) => $query->where('email', $this->input('email')))
                    ->exists();

                if ($alreadyMember) {
                    $validator->errors()->add('email', __('This person is already a member of the business.'));
                }
            },
        ];
    }
}
