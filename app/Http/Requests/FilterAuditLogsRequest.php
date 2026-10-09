<?php

namespace App\Http\Requests;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', AuditLog::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', Rule::enum(AuditEvent::class)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Normalised filter set for AuditService, with day boundaries expanded so a
     * date-only `to` still includes that whole day.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'auditable_type' => $validated['auditable_type'] ?? null,
            'event' => $validated['event'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'from' => isset($validated['from']) ? $this->date('from')?->startOfDay() : null,
            'to' => isset($validated['to']) ? $this->date('to')?->endOfDay() : null,
        ];
    }
}
