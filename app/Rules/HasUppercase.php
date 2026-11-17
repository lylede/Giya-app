<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** The password must contain at least one capital letter (A-Z). */
class HasUppercase implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/\p{Lu}/u', $value)) {
            $fail('validation.password.uppercase')->translate();
        }
    }
}
