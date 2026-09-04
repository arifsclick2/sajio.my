<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'email',
    'purpose',
    'code_hash',
    'expires_at',
    'attempts',
    'used_at',
])]
class EmailOtp extends Model
{
    use HasFactory;

    public const MAX_ATTEMPTS = 5;

    public const TTL_MINUTES = 10;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'used_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Creation / verification                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Create a fresh OTP record for an email and return the plaintext code.
     */
    public static function issue(string $email, string $purpose = 'verify_email'): string
    {
        $code = (string) random_int(100000, 999999);

        self::query()->create([
            'email' => Str::lower($email),
            'purpose' => $purpose,
            'code_hash' => bcrypt($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $code;
    }

    /**
     * Check a submitted code against the latest unused OTP for the email.
     */
    public static function verify(string $email, string $code, string $purpose = 'verify_email'): bool
    {
        $otp = self::query()
            ->where('email', Str::lower($email))
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        if ($otp->expires_at->isPast()) {
            return false;
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $otp->increment('attempts');

        if (! password_verify($code, $otp->code_hash)) {
            return false;
        }

        $otp->forceFill(['used_at' => now()])->save();

        // Invalidate any other outstanding codes for this email.
        self::query()
            ->where('email', Str::lower($email))
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('id', '!=', $otp->id)
            ->update(['used_at' => now()]);

        return true;
    }

    public function isExpired(?Carbon $now = null): bool
    {
        return $this->expires_at->isPast($now ?? now());
    }
}
