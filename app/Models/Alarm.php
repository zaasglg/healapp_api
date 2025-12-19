<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alarm extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'diary_id',
        'creator_id',
        'name',
        'type',
        'days_of_week',
        'times',
        'dosage',
        'notes',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'times' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the diary that owns this alarm.
     */
    public function diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class);
    }

    /**
     * Get the creator of this alarm.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the patient through the diary.
     */
    public function patient()
    {
        return $this->hasOneThrough(Patient::class, Diary::class, 'id', 'id', 'diary_id', 'patient_id');
    }
}
