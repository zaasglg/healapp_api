<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'phone',
        'unverified_phone',
        'password',
        'verification_code',
        'phone_verified_at',
        'organization_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Append account_type attribute to model's JSON representation.
     *
     * @var array
     */
    protected $appends = ['account_type'];

    /**
     * Get the account type based on user's role.
     *
     * @return string|null
     */
    public function getAccountTypeAttribute(): ?string
    {
        $roleToAccountType = [
            'client' => 'client',
            'specialist' => 'specialist',
            'manager' => $this->organization ? ($this->organization->name ? 'pansionat' : 'agency') : 'pansionat',
            'admin' => 'admin',
            'caregiver' => 'caregiver',
            'doctor' => 'doctor',
        ];

        $role = $this->roles->first();
        
        if (!$role) {
            return null;
        }

        // For manager role, check if user owns organization to determine type
        if ($role->name === 'manager' && $this->organization) {
            // You can add additional logic here to differentiate between pansionat and agency
            return 'pansionat'; // Default to pansionat, adjust logic as needed
        }

        return $roleToAccountType[$role->name] ?? null;
    }

    /**
     * Get the organization owned by the user (if user is a manager/owner).
     */
    public function organization(): HasOne
    {
        return $this->hasOne(Organization::class, 'owner_id');
    }

    /**
     * Get the organization the user belongs to (if user is an employee).
     */
    public function employeeOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get the patients created by the user.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'creator_id');
    }

    /**
     * Get the patients assigned to this user (caregiver/doctor).
     */
    public function assignedPatients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'patient_user', 'user_id', 'patient_id')
            ->withTimestamps();
    }
}
