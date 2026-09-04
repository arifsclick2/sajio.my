<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a restaurant subdomain per the Sajio plan §7:
 * lowercase letters/numbers/hyphens, no leading/trailing hyphen,
 * minimum 3 characters, and not on the reserved list.
 */
class SubdomainRule implements ValidationRule
{
    /**
     * Reserved subdomains (plan §7) — these belong to the platform.
     *
     * @var list<string>
     */
    public const RESERVED = [
        'www', 'admin', 'api', 'app', 'mail', 'support',
        'billing', 'status', 'my', 'sajio', 'test',
    ];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $subdomain = strtolower((string) $value);

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,}[a-z0-9]$/', $subdomain)) {
            $fail('The :attribute must be 3–63 characters using lowercase letters, numbers and hyphens only, and must not start or end with a hyphen.');
        }

        if (in_array($subdomain, self::RESERVED, true)) {
            $fail('That subdomain is reserved and cannot be used.');
        }
    }
}
