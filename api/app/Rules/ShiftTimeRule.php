<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a shift's end time. A shift may not be zero-length; whether it
 * crosses midnight is chosen explicitly via the crosses_midnight flag, so we
 * only require that start != end and both are valid times.
 */
class ShiftTimeRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = request('start_time');

        if (is_string($start) && $start === $value) {
            $fail('A shift must have a start time different from its end time.');
        }
    }
}
