<?php

namespace App\Models;

use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'type',
        'phone',
        'address',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
        ];
    }

    // ===== RELATIONS =====

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Все сотрудники организации (через organization_id в users)
     */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'organization_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    // ===== SCOPES =====

    public function scopeOfType(Builder $query, OrganizationType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeAgencies(Builder $query): Builder
    {
        return $query->ofType(OrganizationType::AGENCY);
    }

    public function scopeBoardingHouses(Builder $query): Builder
    {
        return $query->ofType(OrganizationType::BOARDING_HOUSE);
    }

    // ===== TYPE CHECKS =====

    public function isAgency(): bool
    {
        return $this->type === OrganizationType::AGENCY;
    }

    public function isBoardingHouse(): bool
    {
        return $this->type === OrganizationType::BOARDING_HOUSE;
    }

    /**
     * Требуется ли явное назначение доступа к дневникам
     */
    public function requiresExplicitDiaryAccess(): bool
    {
        return $this->isAgency();
    }

    // ===== EMPLOYEE MANAGEMENT =====

    /**
     * Получить сотрудников с определённой ролью
     */
    public function employeesWithRole(string $role)
    {
        return $this->employees()->role($role);
    }

    /**
     * Получить администраторов (owner + admin)
     */
    public function admins()
    {
        return $this->employees()->role(['owner', 'admin']);
    }

    /**
     * Получить врачей
     */
    public function doctors()
    {
        return $this->employees()->role('doctor');
    }

    /**
     * Получить сиделок
     */
    public function caregivers()
    {
        return $this->employees()->role('caregiver');
    }

    /**
     * Добавить сотрудника в организацию
     */
    public function addEmployee(User $user, string $role): void
    {
        $user->organization_id = $this->id;
        $user->save();
        
        // Убираем старые организационные роли и назначаем новую
        $user->syncRoles([$role]);
    }

    /**
     * Удалить сотрудника из организации
     */
    public function removeEmployee(User $user): void
    {
        if ($user->organization_id !== $this->id) {
            return;
        }

        $user->organization_id = null;
        $user->save();
        
        // Убираем организационные роли
        $user->syncRoles([]);
    }

    /**
     * Изменить роль сотрудника
     */
    public function changeEmployeeRole(User $user, string $newRole): void
    {
        if ($user->organization_id !== $this->id) {
            return;
        }

        $user->syncRoles([$newRole]);
    }

    /**
     * Проверить, является ли пользователь сотрудником
     */
    public function hasEmployee(User $user): bool
    {
        return $user->organization_id === $this->id;
    }

    // ===== STATISTICS =====

    public function getEmployeeCountAttribute(): int
    {
        return $this->employees()->count();
    }

    public function getPatientCountAttribute(): int
    {
        return $this->patients()->count();
    }
}
