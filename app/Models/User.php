<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const STATUS_ACTIVE = 'active';

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = self::normalizeUsername($value);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'recorded_by');
    }

    public function voidedSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'voided_by');
    }

    public function restocks(): HasMany
    {
        return $this->hasMany(Restock::class, 'recorded_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'performed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
