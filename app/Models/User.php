<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'phone',
        'unverified_phone',
        'password',
        'verification_code',
        'phone_verified_at',
        'type',
        'organization_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
        ];
    }

    protected $appends = ['account_type'];

    // ===== RELATIONS =====

    /**
     * Организация, к которой принадлежит сотрудник
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Организации, которыми пользователь владеет
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /**
     * Пациенты, созданные пользователем
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'creator_id');
    }

    /**
     * Пациенты, владельцем которых является пользователь (клиент)
     */
    public function ownedPatients(): HasMany
    {
        return $this->hasMany(Patient::class, 'owner_id');
    }

    /**
     * Пациенты, назначенные пользователю (для сиделок/врачей)
     */
    public function assignedPatients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'patient_user', 'user_id', 'patient_id')
            ->withTimestamps();
    }

    /**
     * Приглашения, отправленные пользователем
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'inviter_id');
    }

    // ===== ACCESSORS =====

    public function getAccountTypeAttribute(): ?string
    {
        if ($this->isClient()) {
            return 'client';
        }

        if ($this->isPrivateCaregiver()) {
            return 'specialist';
        }

        // Для сотрудников организации
        if ($this->organization_id) {
            $org = $this->organization;
            if ($org) {
                if ($this->hasRole('owner') || $this->hasRole('admin')) {
                    return $org->type === OrganizationType::BOARDING_HOUSE ? 'pansionat' : 'agency';
                }
                return $this->roles->first()?->name;
            }
        }

        return null;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name} {$this->first_name} {$this->middle_name}");
    }

    // ===== TYPE CHECKS =====

    public function isClient(): bool
    {
        return $this->type === UserType::CLIENT;
    }

    public function isPrivateCaregiver(): bool
    {
        return $this->type === UserType::PRIVATE_CAREGIVER;
    }

    public function isOrganizationOwner(): bool
    {
        return $this->type === UserType::ORGANIZATION;
    }

    public function belongsToOrganization(): bool
    {
        return $this->organization_id !== null;
    }

    // ===== ROLE CHECKS (с контекстом организации) =====

    /**
     * Проверить, является ли пользователь владельцем своей организации
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Проверить, является ли пользователь администратором
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('owner');
    }

    /**
     * Проверить, может ли управлять сотрудниками
     */
    public function canManageEmployees(): bool
    {
        return $this->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Проверить, может ли управлять доступом к дневникам
     */
    public function canManageAccess(): bool
    {
        return $this->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Проверить, может ли создавать карточки подопечных
     */
    public function canCreatePatients(): bool
    {
        return $this->hasAnyRole(['owner', 'admin']) || $this->isPrivateCaregiver();
    }

    /**
     * Проверить, может ли создавать маршрутные листы
     */
    public function canCreateTasks(): bool
    {
        return $this->hasAnyRole(['owner', 'admin', 'doctor']);
    }

    /**
     * Проверить, может ли выполнять задачи
     */
    public function canCompleteTasks(): bool
    {
        return $this->hasAnyRole(['owner', 'admin', 'caregiver']) || $this->isPrivateCaregiver();
    }

    // ===== DIARY ACCESS =====

    /**
     * Проверить доступ к дневнику с учётом типа организации
     */
    public function canAccessDiary(Diary $diary): bool
    {
        $patient = $diary->patient;

        // Владелец карточки (клиент)
        if ($patient->owner_id === $this->id) {
            return true;
        }

        // Частная сиделка с прямым доступом
        if ($this->isPrivateCaregiver()) {
            return $diary->hasAccess($this);
        }

        if ($this->organization_id && $patient->organization_id === $this->organization_id) {
            // Владелец организации имеет полный доступ
            if ($this->isOwner()) {
                return true;
            }

            $org = $this->organization;
            
            // Пансионат: все сотрудники видят все дневники
            if ($org->isBoardingHouse()) {
                return true;
            }
            
            // Агентство: нужен явный доступ
            if ($org->isAgency()) {
                return $diary->hasAccess($this);
            }
        }

        return false;
    }

    /**
     * Получить дневники, доступные пользователю
     */
    public function accessibleDiaries()
    {
        // Клиент: только свои дневники
        if ($this->isClient()) {
            return Diary::whereHas('patient', function ($q) {
                $q->where('owner_id', $this->id);
            });
        }

        // Частная сиделка: только назначенные
        if ($this->isPrivateCaregiver()) {
            return Diary::whereHas('accessUsers', function ($q) {
                $q->where('user_id', $this->id)
                  ->where('diary_access.status', 'active');
            });
        }

        // Сотрудник организации
        if ($this->organization_id) {
            $org = $this->organization;
            
            // Пансионат: все дневники организации
            if ($org->isBoardingHouse()) {
                return Diary::whereHas('patient', function ($q) {
                    $q->where('organization_id', $this->organization_id);
                });
            }
            
            // Агентство: только назначенные
            if ($org->isAgency()) {
                return Diary::whereHas('accessUsers', function ($q) {
                    $q->where('user_id', $this->id)
                      ->where('diary_access.status', 'active');
                });
            }
        }

        return Diary::whereRaw('1 = 0'); // Пустой результат
    }
}
