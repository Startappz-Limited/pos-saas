<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SignInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            // The business to sign in to (its uuid); needed only when the user
            // has several and no usable default.
            'business' => ['sometimes', 'string', 'uuid'],
            // Signing in on a paired till (Phase 7): its register's uuid and
            // this installation's device id; the token then acts for that
            // register's shop only.
            'register' => ['sometimes', 'string', 'uuid'],
            'device_id' => ['required_with:register', 'string', 'max:191'],
        ];
    }
}
