<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\Shop;
use App\Rules\KeepsBusinessOwner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('shop_ids')) {
            $this->merge(['shop_ids' => []]);
        }

        // Convert role_id to roles array
        if ($this->has('role_id') && $this->role_id) {
            $role = Role::query()->find($this->role_id);
            if ($role) {
                $this->merge(['roles' => [$role->name]]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', Rule::enum(UserStatus::class), KeepsBusinessOwner::status($user, $this->user())],
            'role_id' => ['nullable', 'exists:roles,id'],
            'roles' => ['nullable', 'array', KeepsBusinessOwner::roles($user, $this->user())],
            'roles.*' => [Rule::in(Role::assignableBy($this->user())->pluck('name'))],
            'shop_ids' => ['nullable', 'array'],
            // Shop is scoped, so this only accepts shops the current user can access
            'shop_ids.*' => ['integer', Rule::in(Shop::query()->pluck('id'))],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'date_of_birth' => 'date of birth',
            'profile_photo' => 'profile photo',
            'roles.*' => 'role',
            'shop_ids.*' => 'shop',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
            'date_of_birth.before' => 'The date of birth must be a date before today.',
            'profile_photo.image' => 'The file must be an image.',
            'profile_photo.max' => 'The profile photo must not be larger than 2MB.',
        ];
    }
}
