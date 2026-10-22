<?php

namespace App\Http\Requests;

use App\Models\BusinessMembership;
use App\Models\User;
use App\Rules\GrantableRole;
use App\Rules\RealEmail;
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $this->realEmailUnlessKnown()],
            'name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['required', 'integer', new GrantableRole($this->user())],
            'shop_ids' => ['nullable', 'array'],
            'shop_ids.*' => ['integer', 'exists:shops,id'],
        ];
    }

    /**
     * The real-address check is for new addresses. An email that already has a
     * login has been through it (or predates it), and being invited to another
     * business doesn't change that.
     */
    private function realEmailUnlessKnown(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && User::query()->where('email', mb_strtolower($value))->exists()) {
                return;
            }

            (new RealEmail)->validate($attribute, $value, $fail);
        };
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
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
