<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'restaurant_id', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                          */
    /* ------------------------------------------------------------------ */

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /**
     * Is this user a restaurant employee who must clock in to be on duty?
     * Staff and managers yes; owners are exempt.
     */
    public function requiresAttendance(): bool
    {
        return $this->role === UserRole::Staff || $this->role === UserRole::Manager;
    }

    /* ------------------------------------------------------------------ */
    /*  Role helpers                                                       */
    /* ------------------------------------------------------------------ */

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function belongsToRestaurant(?Restaurant $restaurant): bool
    {
        return $restaurant !== null && $this->restaurant_id === $restaurant->id;
    }
}
