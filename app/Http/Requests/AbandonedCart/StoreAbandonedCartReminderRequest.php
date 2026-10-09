<?php

namespace App\Http\Requests\AbandonedCart;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbandonedCartReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
