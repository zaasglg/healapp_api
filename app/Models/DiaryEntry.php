<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'diary_id',
        'author_id',
        'type',
        'key',
        'value',
        'notes',
        'recorded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Get the diary that owns this entry.
     */
    public function diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class, 'diary_id');
    }

    /**
     * Get the patient through the diary relationship.
     */
    public function patient(): BelongsTo
    {
        return $this->diary->patient();
    }

    /**
     * Get the author (user) who created this diary entry.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
