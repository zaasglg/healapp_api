<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    /**
     * Task statuses.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_MISSED = 'missed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'template_id',
        'assigned_to',
        'title',
        'start_at',
        'end_at',
        'original_start_at',
        'original_end_at',
        'status',
        'priority',
        'completed_at',
        'completed_by',
        'comment',
        'photos',
        'reschedule_reason',
        'rescheduled_by',
        'rescheduled_at',
        'related_diary_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'original_start_at' => 'datetime',
            'original_end_at' => 'datetime',
            'completed_at' => 'datetime',
            'rescheduled_at' => 'datetime',
            'photos' => 'array',
            'priority' => 'integer',
        ];
    }

    /**
     * Get the patient that owns this task.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the template that generated this task.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'template_id');
    }

    /**
     * Get the user assigned to this task.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who completed this task.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the user who rescheduled this task.
     */
    public function rescheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }

    /**
     * Check if task was rescheduled.
     */
    public function isRescheduled(): bool
    {
        return $this->original_start_at !== null;
    }

    /**
     * Check if task is overdue (past end time and still pending).
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING 
            && $this->end_at->isPast();
    }

    /**
     * Scope for pending tasks.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for tasks assigned to a specific user.
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope for overdue tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('end_at', '<', now());
    }
}
