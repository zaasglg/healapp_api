<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diary extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'pinned_parameters',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pinned_parameters' => 'array',
            'settings' => 'array',
        ];
    }

    // ===== RELATIONS =====

    /**
     * Get the patient that owns this diary.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the diary entries for this diary.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class, 'diary_id');
    }

    /**
     * Get the alarms for this diary.
     */
    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class, 'diary_id');
    }

    /**
     * Get the users who have access to this diary.
     */
    public function accessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'diary_access', 'diary_id', 'user_id')
            ->withPivot(['permission', 'status'])
            ->withTimestamps();
    }

    /**
     * Alias for accessUsers for DiaryPolicy compatibility.
     */
    public function access(): BelongsToMany
    {
        return $this->accessUsers();
    }

    // ===== ACCESS CONTROL =====

    /**
     * Check if a user has access to this diary.
     */
    public function hasAccess(User $user, string $requiredPermission = 'view'): bool
    {
        $permissions = ['view' => 1, 'edit' => 2, 'full' => 3];

        $access = $this->accessUsers()
            ->where('user_id', $user->id)
            ->wherePivot('status', 'active')
            ->first();

        if (!$access) {
            return false;
        }

        $userPermissionLevel = $permissions[$access->pivot->permission] ?? 0;
        $requiredLevel = $permissions[$requiredPermission] ?? 0;

        return $userPermissionLevel >= $requiredLevel;
    }

    /**
     * Grant access to a user.
     */
    public function grantAccess(User $user, string $permission = 'view'): void
    {
        $this->accessUsers()->syncWithoutDetaching([
            $user->id => [
                'permission' => $permission,
                'status' => 'active',
            ]
        ]);
    }

    /**
     * Revoke access from a user.
     */
    public function revokeAccess(User $user): void
    {
        // Soft revoke - just change status
        $this->accessUsers()->updateExistingPivot($user->id, [
            'status' => 'revoked',
        ]);
    }

    /**
     * Hard revoke access (completely remove).
     */
    public function removeAccess(User $user): void
    {
        $this->accessUsers()->detach($user->id);
    }

    /**
     * Get all users with active access.
     */
    public function activeAccessUsers(): BelongsToMany
    {
        return $this->accessUsers()->wherePivot('status', 'active');
    }

    /**
     * Check if user can fill entries in this diary.
     */
    public function canFill(User $user): bool
    {
        return $this->hasAccess($user, 'view');
    }

    /**
     * Check if user can edit diary settings.
     */
    public function canEdit(User $user): bool
    {
        return $this->hasAccess($user, 'edit');
    }

    /**
     * Check if user has full access.
     */
    public function hasFullAccess(User $user): bool
    {
        return $this->hasAccess($user, 'full');
    }
}
