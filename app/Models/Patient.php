<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'creator_id',
        'organization_id',
        'first_name',
        'last_name',
        'middle_name',
        'birth_date',
        'gender',
        'weight',
        'height',
        'mobility',
        'diagnoses',
        'needed_services',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diagnoses' => 'array',
            'needed_services' => 'array',
            'birth_date' => 'date',
        ];
    }

    /**
     * Get the creator of the patient.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the organization that owns the patient.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get the diary for the patient.
     */
    public function diary(): HasOne
    {
        return $this->hasOne(Diary::class, 'patient_id');
    }

    /**
     * Get the task templates for the patient.
     */
    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class, 'patient_id');
    }

    /**
     * Get the tasks for the patient.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'patient_id');
    }

    /**
     * Get the users (caregivers/doctors) assigned to this patient.
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'patient_user', 'patient_id', 'user_id')
            ->withTimestamps();
    }
}
