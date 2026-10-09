<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Like `exists:table,id`, but looked up through the Eloquent model so its
 * global scopes apply: a user can only reference shops, products, suppliers,
 * customers... that they are allowed to see. A raw `exists:` rule queries the
 * table directly and would accept another business's ids.
 */
class ExistsForViewer implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(private readonly string $model) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! $this->model::query()->whereKey($value)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
