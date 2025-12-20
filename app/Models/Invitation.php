<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'inviter_id',
        'invitee_id',
        'token',
        'type',
        'role',
        'patient_id',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    // ===== CONSTANTS =====
    
    public const TYPE_EMPLOYEE = 'employee';
    public const TYPE_CLIENT = 'client';
    
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    public const ALLOWED_ROLES = ['admin', 'doctor', 'caregiver'];

    // ===== RELATIONS =====

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    // ===== SCOPES =====

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('expires_at', '>', now());
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    // ===== HELPERS =====

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_PENDING 
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markAsAccepted(User $user): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'invitee_id' => $user->id,
            'accepted_at' => now(),
        ]);
    }

    public function markAsRevoked(): void
    {
        $this->update(['status' => self::STATUS_REVOKED]);
    }

    public function getInviteUrl(): string
    {
        return config('app.frontend_url', config('app.url')) . '/invite/' . $this->token;
    }

    public function isEmployeeInvite(): bool
    {
        return $this->type === self::TYPE_EMPLOYEE;
    }

    public function isClientInvite(): bool
    {
        return $this->type === self::TYPE_CLIENT;
    }
}
